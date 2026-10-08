<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HtmlContentSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(Request $request, HtmlContentSanitizer $sanitizer): View
    {
        $tab = $request->query('tab') === 'words' ? 'words' : 'articles';
        $articles = DB::table('reading_items')->where('kind', 'article')->orderByDesc('updated_at')->limit(100)->get();
        $words = DB::table('words')->orderBy('headword')->limit(300)->get();
        $article = $request->integer('id') ? DB::table('reading_items')->where('id', $request->integer('id'))->where('kind', 'article')->first() : null;
        $word = $request->integer('id') ? DB::table('words')->where('id', $request->integer('id'))->first() : null;

        if (! $article && $tab === 'articles') $article = $articles->first();
        if (! $word && $tab === 'words') $word = $words->first();

        if ($article) {
            $article->body = json_decode($article->body_json, true) ?: [];
            $article->analysis = DB::table('reading_analyses')->where('reading_item_id', $article->id)->first();
            if ($article->analysis?->type === 'richtext') $article->analysis->html = $sanitizer->clean($article->analysis->html ?? '');
            $article->wordIds = DB::table('reading_words')->where('reading_item_id', $article->id)->orderBy('sort_order')->pluck('word_id')->map(fn ($id): int => (int) $id)->all();
        }
        if ($word) {
            $word->detail = DB::table('word_details')->where('word_id', $word->id)->first();
            if ($word->detail?->type === 'richtext') $word->detail->html = $sanitizer->clean($word->detail->html ?? '');
        }

        return view('admin.content', compact('tab', 'articles', 'words', 'article', 'word'));
    }

    public function passwordForm(): View
    {
        return view('admin.password');
    }

    public function saveArticle(Request $request, ?int $id = null): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('reading_items', 'slug')->ignore($id)],
            'title' => 'required|string|max:255', 'translation' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:32', 'minutes' => 'nullable|integer|min:1|max:65535',
            'coverAsset' => 'nullable|string|max:255',
            'paragraphs' => 'required|array|min:1',
            'paragraphs.*.sentences' => 'required|string|max:20000',
            'paragraphs.*.translation' => 'required|string|max:5000',
            'rightsType' => 'nullable|string|max:32', 'rightsNote' => 'nullable|string|max:10000',
        ]);
        $body = [];
        foreach ($data['paragraphs'] as $paragraph) {
            $sentences = array_values(array_filter(array_map('trim', preg_split('/\R/u', $paragraph['sentences']) ?: []), fn (string $line): bool => $line !== ''));
            if ($sentences === []) return back()->withErrors(['paragraphs' => '每段至少需要一句英文'])->withInput();
            $body[] = ['sentences' => $sentences, 'translation' => trim($paragraph['translation'])];
        }

        $row = [
            'slug' => $data['slug'] ?? null, 'kind' => 'article', 'title' => $data['title'],
            'translation' => $data['translation'] ?? null, 'level' => $data['level'] ?? null,
            'minutes' => $data['minutes'] ?? null, 'cover_asset' => $data['coverAsset'] ?? null,
            'body_json' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'rights_type' => $data['rightsType'] ?? null, 'rights_note' => $data['rightsNote'] ?? null,
            'updated_at' => now(),
        ];

        if ($id) {
            $exists = DB::table('reading_items')->where('id', $id)->where('kind', 'article')->exists();
            abort_unless($exists, 404);
            DB::table('reading_items')->where('id', $id)->update($row);
        } else {
            $row['status'] = 'draft';
            $row['sort_order'] = 0;
            $row['created_at'] = now();
            $id = DB::table('reading_items')->insertGetId($row);
        }

        return redirect()->route('admin.content', ['tab' => 'articles', 'id' => $id])->with('success', '文章已保存为草稿');
    }

    public function saveAnalysis(Request $request, int $id, HtmlContentSanitizer $sanitizer): RedirectResponse
    {
        $article = DB::table('reading_items')->where('id', $id)->where('kind', 'article')->first();
        abort_unless($article, 404);
        $data = $request->validate([
            'type' => 'required|in:richtext,officialAccount',
            'html' => 'required_if:type,richtext|nullable|string|max:1000000',
            'url' => 'required_if:type,officialAccount|nullable|url|max:2048',
            'title' => 'nullable|string|max:255', 'summary' => 'nullable|string|max:10000',
        ]);
        $html = $data['type'] === 'richtext' ? $sanitizer->clean($data['html'] ?? '') : null;
        if ($data['type'] === 'richtext' && trim(strip_tags((string) $html)) === '') {
            return back()->withErrors(['html' => '清理后没有可显示的正文内容'])->withInput();
        }

        $now = now();
        $exists = DB::table('reading_analyses')->where('reading_item_id', $id)->exists();
        DB::table('reading_analyses')->updateOrInsert(['reading_item_id' => $id], [
            'type' => $data['type'], 'html' => $html, 'url' => $data['url'] ?? null,
            'title' => $data['title'] ?? null, 'summary' => $data['summary'] ?? null,
            'status' => $article->status === 'published' ? 'published' : 'draft', 'updated_at' => $now,
        ] + ($exists ? [] : ['created_at' => $now]));

        return redirect()->route('admin.content', ['tab' => 'articles', 'id' => $id])->with('success', '文章解析已保存');
    }

    public function saveReadingWords(Request $request, int $id): RedirectResponse
    {
        abort_unless(DB::table('reading_items')->where('id', $id)->where('kind', 'article')->exists(), 404);
        $data = $request->validate([
            'wordIds' => 'nullable|array|max:500',
            'wordIds.*' => 'required|integer|distinct|exists:words,id',
        ]);

        DB::transaction(function () use ($id, $data): void {
            DB::table('reading_words')->where('reading_item_id', $id)->delete();
            $rows = [];
            foreach ($data['wordIds'] ?? [] as $index => $wordId) {
                $rows[] = ['reading_item_id' => $id, 'word_id' => $wordId, 'sort_order' => $index + 1];
            }
            if ($rows !== []) DB::table('reading_words')->insert($rows);
        });

        return redirect()->route('admin.content', ['tab' => 'articles', 'id' => $id])->with('success', '重点词汇顺序已保存');
    }

    public function publishArticle(int $id): RedirectResponse
    {
        $article = DB::table('reading_items')->where('id', $id)->where('kind', 'article')->first();
        abort_unless($article, 404);
        if (! $article->rights_type || ! $article->rights_note || ! $article->body_json) {
            return back()->withErrors(['publish' => '请先补齐正文和版权来源说明']);
        }
        DB::transaction(function () use ($id): void {
            DB::table('reading_items')->where('id', $id)->update(['status' => 'published', 'updated_at' => now()]);
            DB::table('reading_analyses')->where('reading_item_id', $id)->update(['status' => 'published', 'updated_at' => now()]);
        });
        return redirect()->route('admin.content', ['tab' => 'articles', 'id' => $id])->with('success', '文章已发布');
    }

    public function unpublishArticle(int $id): RedirectResponse
    {
        abort_unless(DB::table('reading_items')->where('id', $id)->where('kind', 'article')->exists(), 404);
        DB::transaction(function () use ($id): void {
            DB::table('reading_items')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
            DB::table('reading_analyses')->where('reading_item_id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
        });
        return redirect()->route('admin.content', ['tab' => 'articles', 'id' => $id])->with('success', '文章已下架');
    }

    public function saveWord(Request $request, HtmlContentSanitizer $sanitizer, ?int $id = null): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('words', 'slug')->ignore($id)],
            'headword' => 'required|string|max:255', 'ipa' => 'nullable|string|max:255',
            'partOfSpeech' => 'nullable|string|max:64', 'meaning' => 'required|string|max:255',
            'frequencyLabel' => 'nullable|string|max:255', 'audioAsset' => 'nullable|string|max:255',
            'detailType' => 'required|in:richtext,officialAccount',
            'detailHtml' => 'required_if:detailType,richtext|nullable|string|max:1000000',
            'detailUrl' => 'required_if:detailType,officialAccount|nullable|url|max:2048',
            'detailTitle' => 'nullable|string|max:255', 'detailSummary' => 'nullable|string|max:10000',
        ]);
        $html = $data['detailType'] === 'richtext' ? $sanitizer->clean($data['detailHtml'] ?? '') : null;
        if ($data['detailType'] === 'richtext' && trim(strip_tags((string) $html)) === '') {
            return back()->withErrors(['detailHtml' => '清理后没有可显示的详解正文'])->withInput();
        }

        $wordRow = [
            'slug' => $data['slug'] ?? null, 'headword' => $data['headword'], 'ipa' => $data['ipa'] ?? null,
            'part_of_speech' => $data['partOfSpeech'] ?? null, 'meaning' => $data['meaning'],
            'frequency_label' => $data['frequencyLabel'] ?? null, 'audio_asset' => $data['audioAsset'] ?? null,
            'updated_at' => now(),
        ];
        if ($id) {
            $word = DB::table('words')->where('id', $id)->first();
            abort_unless($word, 404);
            DB::table('words')->where('id', $id)->update($wordRow);
        } else {
            $wordRow['status'] = 'draft';
            $wordRow['created_at'] = now();
            $id = DB::table('words')->insertGetId($wordRow);
        }

        $now = now();
        $hasDetail = DB::table('word_details')->where('word_id', $id)->exists();
        DB::table('word_details')->updateOrInsert(['word_id' => $id], [
            'type' => $data['detailType'], 'html' => $html, 'url' => $data['detailUrl'] ?? null,
            'title' => $data['detailTitle'] ?? null, 'summary' => $data['detailSummary'] ?? null,
            'status' => DB::table('words')->where('id', $id)->value('status') === 'published' ? 'published' : 'draft',
            'updated_at' => $now,
        ] + ($hasDetail ? [] : ['created_at' => $now]));

        return redirect()->route('admin.content', ['tab' => 'words', 'id' => $id])->with('success', '单词及详解已保存');
    }

    public function publishWord(int $id): RedirectResponse
    {
        $detail = DB::table('word_details')->where('word_id', $id)->first();
        abort_unless(DB::table('words')->where('id', $id)->exists(), 404);
        if (! $detail || ($detail->type === 'richtext' && trim(strip_tags((string) $detail->html)) === '') || ($detail->type === 'officialAccount' && ! $detail->url)) {
            return back()->withErrors(['publish' => '请先补齐单词详解']);
        }
        DB::transaction(function () use ($id): void {
            DB::table('words')->where('id', $id)->update(['status' => 'published', 'updated_at' => now()]);
            DB::table('word_details')->where('word_id', $id)->update(['status' => 'published', 'updated_at' => now()]);
        });
        return redirect()->route('admin.content', ['tab' => 'words', 'id' => $id])->with('success', '单词已发布');
    }

    public function unpublishWord(int $id): RedirectResponse
    {
        abort_unless(DB::table('words')->where('id', $id)->exists(), 404);
        DB::transaction(function () use ($id): void {
            DB::table('words')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
            DB::table('word_details')->where('word_id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
        });
        return redirect()->route('admin.content', ['tab' => 'words', 'id' => $id])->with('success', '单词已下架');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => 'required|current_password:web',
            'password' => 'required|string|min:16|confirmed',
        ]);
        DB::table('users')->where('id', $request->user()->id)->update([
            'password' => Hash::make($data['password']), 'updated_at' => now(),
        ]);
        return back()->with('success', '密码已更新');
    }
}
