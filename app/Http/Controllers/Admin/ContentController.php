<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HtmlContentSanitizer;
use App\Support\ReadingCover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ContentController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $tab = $request->query('tab') === 'words' ? 'words' : 'articles';
        $id = $request->integer('id');
        $create = $request->query('id') === 'new';

        if ($tab === 'words') {
            return redirect()->route($create ? 'admin.words.create' : ($id ? 'admin.words.edit' : 'admin.words.index'), $id ? ['id' => $id] : []);
        }

        return redirect()->route($create ? 'admin.articles.create' : ($id ? 'admin.articles.edit' : 'admin.articles.index'), $id ? ['id' => $id] : []);
    }

    public function dashboard(): InertiaResponse
    {
        return Inertia::render('Admin/Dashboard', [
            'articleCount' => DB::table('reading_items')->where('kind', 'article')->count(),
            'publishedArticles' => DB::table('reading_items')->where('kind', 'article')->where('status', 'published')->count(),
            'wordCount' => DB::table('words')->count(),
            'publishedWords' => DB::table('words')->where('status', 'published')->count(),
            'userCount' => DB::table('users')->where('is_admin', false)->count(),
            'latestArticles' => DB::table('reading_items')->where('kind', 'article')->orderByDesc('updated_at')->orderByDesc('id')->limit(5)->get(),
            'latestWords' => DB::table('words')->orderByDesc('updated_at')->orderByDesc('id')->limit(5)->get(),
        ]);
    }

    public function articles(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['draft', 'published'], true) ? $request->query('status') : null;
        $query = DB::table('reading_items')->where('kind', 'article');
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('translation', 'like', '%'.$search.'%');
            });
        }
        if ($status) $query->where('status', $status);

        $articles = $query->orderBy('sort_order')->orderBy('id')->paginate(15)->withQueryString();
        $articles->getCollection()->transform(function (object $article): object {
            $article->publishChecks = $this->articlePublishChecks($article);
            return $article;
        });

        return Inertia::render('Admin/Articles/Index', [
            'articles' => $articles,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function series(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['draft', 'published'], true) ? $request->query('status') : null;
        $query = DB::table('series')->select('series.*')->selectSub(
            DB::table('reading_items')->selectRaw('count(*)')->whereColumn('series_id', 'series.id')->where('kind', 'chapter'),
            'chapter_count'
        );
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')->orWhere('translation', 'like', '%'.$search.'%');
            });
        }
        if ($status) $query->where('status', $status);

        return Inertia::render('Admin/Series/Index', [
            'seriesItems' => $query->orderBy('sort_order')->orderBy('id')->paginate(15)->withQueryString(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function seriesForm(?int $id = null): InertiaResponse
    {
        $series = $id ? DB::table('series')->where('id', $id)->first() : null;
        if ($id) abort_unless($series, 404);
        if ($series) {
            $series->chapters = DB::table('reading_items')->where('series_id', $id)->where('kind', 'chapter')
                ->orderBy('chapter_no')->get();
        }

        return Inertia::render('Admin/Series/Form', [
            'series' => $series,
            'coverUrl' => ReadingCover::url($series->cover_asset ?? null),
            'readingLevels' => $this->readingLevelOptions(),
            'nextSortOrder' => (int) DB::table('series')->max('sort_order') + 1,
        ]);
    }

    public function saveSeries(Request $request, ?int $id = null): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255', 'translation' => 'nullable|string|max:255',
            'author' => 'nullable|string|max:255', 'level' => ['nullable', 'string', 'max:32', Rule::exists('reading_levels', 'name')],
            'description' => 'nullable|string|max:10000',
            'coverImage' => 'nullable|image|mimes:jpeg,png,webp|max:4096',
            'rightsType' => ['required', 'string', Rule::in(['original', 'authorized', 'public_domain', 'excerpt', 'other'])],
            'rightsNote' => 'nullable|string|max:10000',
            'sortOrder' => 'sometimes|integer|min:0|max:4294967295',
        ]);
        $existing = $id ? DB::table('series')->where('id', $id)->first() : null;
        if ($id) abort_unless($existing, 404);
        $coverAsset = $existing->cover_asset ?? null;
        if ($request->hasFile('coverImage')) {
            $coverAsset = $request->file('coverImage')->store('series-covers', 'public');
            if (! $coverAsset) abort(500, '封面保存失败');
        }
        $row = [
            'title' => trim($data['title']), 'translation' => $data['translation'] ?? null,
            'author' => $data['author'] ?? null, 'level' => $data['level'] ?? null,
            'description' => $data['description'] ?? null, 'cover_asset' => $coverAsset,
            'rights_type' => $data['rightsType'] ?? null, 'rights_note' => $data['rightsNote'] ?? null,
            'sort_order' => (int) ($data['sortOrder'] ?? ($existing->sort_order ?? ((int) DB::table('series')->max('sort_order') + 1))),
            'updated_at' => now(),
        ];
        if ($id) {
            DB::table('series')->where('id', $id)->update($row);
        } else {
            $row['status'] = 'draft';
            $row['sort_order'] = (int) ($data['sortOrder'] ?? ((int) DB::table('series')->max('sort_order') + 1));
            $row['created_at'] = now();
            $id = DB::table('series')->insertGetId($row);
        }

        return redirect()->route('admin.series.edit', $id)->with('success', '外刊小说信息已保存');
    }

    public function publishSeries(int $id): RedirectResponse
    {
        $series = DB::table('series')->where('id', $id)->first();
        abort_unless($series, 404);
        $hasPublishedChapter = DB::table('reading_items')->where('series_id', $id)->where('kind', 'chapter')->where('status', 'published')->exists();
        if (! $series->rights_type || ! $hasPublishedChapter) {
            return back()->withErrors(['publish' => '发布外刊小说前，请选择版权来源类型，并至少发布一个章节。']);
        }
        DB::table('series')->where('id', $id)->update(['status' => 'published', 'updated_at' => now()]);
        return redirect()->route('admin.series.index')->with('success', '外刊小说已发布');
    }

    public function unpublishSeries(int $id): RedirectResponse
    {
        abort_unless(DB::table('series')->where('id', $id)->exists(), 404);
        DB::table('series')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
        return redirect()->route('admin.series.index')->with('success', '外刊小说已下架');
    }

    public function moveChapter(int $seriesId, int $id, string $direction): RedirectResponse
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        $chapter = DB::table('reading_items')->where('id', $id)->where('series_id', $seriesId)->where('kind', 'chapter')->first();
        abort_unless($chapter, 404);
        DB::transaction(function () use ($seriesId, $chapter, $direction): void {
            $ordered = DB::table('reading_items')->where('series_id', $seriesId)->where('kind', 'chapter')->orderBy('chapter_no')->get(['id', 'chapter_no']);
            $position = $ordered->search(fn ($item) => (int) $item->id === (int) $chapter->id);
            $targetPosition = $direction === 'up' ? $position - 1 : $position + 1;
            if ($position === false || ! isset($ordered[$targetPosition])) return;
            $target = $ordered[$targetPosition];
            $temporaryNo = (int) $ordered->max('chapter_no') + 1;
            DB::table('reading_items')->where('id', $chapter->id)->update(['chapter_no' => $temporaryNo]);
            DB::table('reading_items')->where('id', $target->id)->update(['chapter_no' => $chapter->chapter_no, 'sort_order' => $chapter->chapter_no, 'updated_at' => now()]);
            DB::table('reading_items')->where('id', $chapter->id)->update(['chapter_no' => $target->chapter_no, 'sort_order' => $target->chapter_no, 'updated_at' => now()]);
        });
        return redirect()->route('admin.series.edit', $seriesId)->with('success', '章节顺序已更新');
    }

    public function publishChapter(int $seriesId, int $id): RedirectResponse
    {
        $chapter = DB::table('reading_items')->where('id', $id)->where('series_id', $seriesId)->where('kind', 'chapter')->first();
        abort_unless($chapter, 404);
        $series = DB::table('series')->where('id', $seriesId)->first();
        $body = json_decode($chapter->body_json, true) ?: [];
        $hasSentences = collect($body)->contains(fn ($paragraph) => collect($paragraph['sentences'] ?? [])->contains(fn ($sentence) => trim((string) $sentence) !== ''));
        if (! trim($chapter->title) || ! $hasSentences || ! ($chapter->rights_type || $series->rights_type)) {
            return back()->withErrors(['publish' => '发布章节前，请补齐章节标题、英文正文，以及章节或小说级的版权来源类型。']);
        }
        DB::transaction(function () use ($id): void {
            DB::table('reading_items')->where('id', $id)->update(['status' => 'published', 'updated_at' => now()]);
            DB::table('reading_analyses')->where('reading_item_id', $id)->update(['status' => 'published', 'updated_at' => now()]);
        });
        return redirect()->route('admin.series.edit', $seriesId)->with('success', '章节已发布');
    }

    public function unpublishChapter(int $seriesId, int $id): RedirectResponse
    {
        abort_unless(DB::table('reading_items')->where('id', $id)->where('series_id', $seriesId)->where('kind', 'chapter')->exists(), 404);
        DB::transaction(function () use ($id): void {
            DB::table('reading_items')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
            DB::table('reading_analyses')->where('reading_item_id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
        });
        return redirect()->route('admin.series.edit', $seriesId)->with('success', '章节已下架');
    }

    public function articleForm(HtmlContentSanitizer $sanitizer, ?int $id = null): InertiaResponse
    {
        $article = $id ? DB::table('reading_items')->where('id', $id)->where('kind', 'article')->first() : null;
        if ($id) abort_unless($article, 404);

        if ($article) {
            $article->body = json_decode($article->body_json, true) ?: [];
            $article->analysis = DB::table('reading_analyses')->where('reading_item_id', $id)->first();
            if ($article->analysis?->type === 'richtext' && ($article->analysis->format ?? 'html') === 'html') {
                $article->analysis->html = $sanitizer->clean($article->analysis->html ?? '');
            }
            $article->wordIds = DB::table('reading_words')->where('reading_item_id', $id)->orderBy('sort_order')->pluck('word_id')->map(fn ($id): int => (int) $id)->all();
            $article->questions = DB::table('reading_questions')->where('reading_item_id', $id)->orderBy('sort_order')->get()->map(function ($question) {
                $question->options = $question->options_json ? (json_decode($question->options_json, true) ?: []) : [];
                $question->answerIndex = $question->kind === 'choice' ? (int) $question->answer : 0;
                return $question;
            });
        }

        return Inertia::render('Admin/Articles/Form', [
            'article' => $article,
            'kind' => 'article',
            'series' => null,
            'readingLevels' => $this->readingLevelOptions(),
            'coverUrl' => ReadingCover::url($article->cover_asset ?? null),
            'detailImageUrl' => ReadingCover::url($article->detail_image_asset ?? null),
            'nextSortOrder' => (int) DB::table('reading_items')->where('kind', 'article')->max('sort_order') + 1,
            'selectedWords' => $article
                ? DB::table('words')->whereIn('id', $article->wordIds)->get()
                    ->sortBy(fn ($word) => array_search((int) $word->id, $article->wordIds, true))->values()
                : collect(),
        ]);
    }

    public function chapterForm(HtmlContentSanitizer $sanitizer, int $seriesId, ?int $id = null): InertiaResponse
    {
        $series = DB::table('series')->where('id', $seriesId)->first();
        abort_unless($series, 404);
        $article = $id ? DB::table('reading_items')->where('id', $id)->where('kind', 'chapter')->where('series_id', $seriesId)->first() : null;
        if ($id) abort_unless($article, 404);
        if ($article) {
            $article->body = json_decode($article->body_json, true) ?: [];
            $article->analysis = DB::table('reading_analyses')->where('reading_item_id', $id)->first();
            if ($article->analysis?->type === 'richtext' && ($article->analysis->format ?? 'html') === 'html') {
                $article->analysis->html = $sanitizer->clean($article->analysis->html ?? '');
            }
            $article->wordIds = DB::table('reading_words')->where('reading_item_id', $id)->orderBy('sort_order')->pluck('word_id')->map(fn ($wordId): int => (int) $wordId)->all();
            $article->questions = DB::table('reading_questions')->where('reading_item_id', $id)->orderBy('sort_order')->get()->map(function ($question) {
                $question->options = $question->options_json ? (json_decode($question->options_json, true) ?: []) : [];
                return $question;
            });
        }

        return Inertia::render('Admin/Articles/Form', [
            'article' => $article,
            'kind' => 'chapter',
            'series' => $series,
            'readingLevels' => $this->readingLevelOptions(),
            'nextChapterNo' => (int) DB::table('reading_items')->where('series_id', $seriesId)->where('kind', 'chapter')->max('chapter_no') + 1,
            'coverUrl' => ReadingCover::url($article->cover_asset ?? null),
            'detailImageUrl' => ReadingCover::url($article->detail_image_asset ?? null),
            'selectedWords' => $article
                ? DB::table('words')->whereIn('id', $article->wordIds)->get()
                    ->sortBy(fn ($word) => array_search((int) $word->id, $article->wordIds, true))->values()
                : collect(),
        ]);
    }

    public function words(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['draft', 'published'], true) ? $request->query('status') : null;
        $query = DB::table('words');
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('headword', 'like', '%'.$search.'%')
                    ->orWhere('meaning', 'like', '%'.$search.'%');
            });
        }
        if ($status) $query->where('status', $status);

        return Inertia::render('Admin/Words/Index', [
            'words' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function wordForm(HtmlContentSanitizer $sanitizer, ?int $id = null): InertiaResponse
    {
        $word = $id ? DB::table('words')->where('id', $id)->first() : null;
        if ($id) abort_unless($word, 404);
        if ($word) {
            $word->detail = DB::table('word_details')->where('word_id', $id)->first();
            if ($word->detail?->type === 'richtext') $word->detail->html = $sanitizer->clean($word->detail->html ?? '');
        }

        return Inertia::render('Admin/Words/Form', compact('word'));
    }

    public function passwordForm(): InertiaResponse
    {
        return Inertia::render('Admin/Password');
    }

    public function saveArticle(Request $request, ?int $id = null): RedirectResponse
    {
        return $this->saveReadingItem($request, 'article', null, $id);
    }

    public function saveChapter(Request $request, int $seriesId, ?int $id = null): RedirectResponse
    {
        abort_unless(DB::table('series')->where('id', $seriesId)->exists(), 404);
        return $this->saveReadingItem($request, 'chapter', $seriesId, $id);
    }

    private function saveReadingItem(Request $request, string $kind, ?int $seriesId, ?int $id): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('reading_items', 'slug')->ignore($id)],
            'title' => 'required|string|max:255', 'translation' => 'nullable|string|max:255',
            'level' => ['nullable', 'string', 'max:32', Rule::exists('reading_levels', 'name')], 'minutes' => 'nullable|integer|min:1|max:65535',
            'chapterNo' => $kind === 'chapter' ? ['required', 'integer', 'min:1', 'max:65535', Rule::unique('reading_items', 'chapter_no')->where(fn ($query) => $query->where('series_id', $seriesId)->where('kind', 'chapter'))->ignore($id)] : 'prohibited',
            'coverImage' => 'nullable|image|mimes:jpeg,png,webp|max:4096',
            'detailImage' => 'nullable|image|mimes:jpeg,png,webp|max:4096',
            'paragraphs' => 'required|array|min:1',
            'paragraphs.*.sentences' => 'required|string|max:20000',
            'paragraphs.*.translation' => 'required|string|max:5000',
            'paragraphs.*.sentenceTranslations' => 'sometimes|array',
            'paragraphs.*.sentenceTranslations.*' => 'nullable|string|max:5000',
            'rightsType' => $kind === 'article'
                ? ['required', 'string', Rule::in(['original', 'authorized', 'public_domain', 'excerpt', 'other'])]
                : ['nullable', 'string', Rule::in(['original', 'authorized', 'public_domain', 'excerpt', 'other'])],
            'rightsNote' => 'nullable|string|max:10000',
            'sortOrder' => $kind === 'article' ? 'sometimes|integer|min:0|max:4294967295' : 'prohibited',
        ]);
        $body = [];
        foreach ($data['paragraphs'] as $paragraph) {
            $sentences = array_values(array_filter(array_map('trim', preg_split('/\R/u', $paragraph['sentences']) ?: []), fn (string $line): bool => $line !== ''));
            if ($sentences === []) return back()->withErrors(['paragraphs' => '每段至少需要一句英文'])->withInput();
            $sentenceTranslations = array_map(
                fn (int $index): string => trim((string) ($paragraph['sentenceTranslations'][$index] ?? '')),
                array_keys($sentences)
            );
            $body[] = ['sentences' => $sentences, 'translation' => trim($paragraph['translation']), 'sentenceTranslations' => $sentenceTranslations];
        }

        $existingQuery = DB::table('reading_items')->where('kind', $kind);
        if ($kind === 'chapter') $existingQuery->where('series_id', $seriesId);
        $existing = $id ? $existingQuery->where('id', $id)->first() : null;
        if ($id) abort_unless($existing, 404);
        $coverAsset = $existing->cover_asset ?? null;
        if ($request->hasFile('coverImage')) {
            $coverAsset = $request->file('coverImage')->store('article-covers', 'public');
            if (! $coverAsset) abort(500, '封面保存失败');
        }
        $detailImageAsset = $existing->detail_image_asset ?? null;
        if ($request->hasFile('detailImage')) {
            $detailImageAsset = $request->file('detailImage')->store('article-detail-images', 'public');
            if (! $detailImageAsset) abort(500, '详情图片保存失败');
        }

        $row = [
            'slug' => $data['slug'] ?? null, 'kind' => $kind, 'series_id' => $seriesId,
            'chapter_no' => $kind === 'chapter' ? (int) $data['chapterNo'] : null, 'title' => $data['title'],
            'translation' => $data['translation'] ?? null, 'level' => $data['level'] ?? null,
            'minutes' => $data['minutes'] ?? null, 'cover_asset' => $coverAsset,
            'detail_image_asset' => $detailImageAsset,
            'body_json' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'rights_type' => $data['rightsType'] ?? null, 'rights_note' => $data['rightsNote'] ?? null,
            'updated_at' => now(),
        ];
        if ($kind === 'chapter') $row['sort_order'] = (int) $data['chapterNo'];
        else $row['sort_order'] = array_key_exists('sortOrder', $data)
            ? (int) $data['sortOrder']
            : (int) ($existing->sort_order ?? ((int) DB::table('reading_items')->where('kind', 'article')->max('sort_order') + 1));

        if ($id) {
            DB::table('reading_items')->where('id', $id)->update($row);
        } else {
            $row['status'] = 'draft';
            $row['sort_order'] = $kind === 'chapter' ? (int) $data['chapterNo'] : (int) ($data['sortOrder'] ?? 1);
            $row['created_at'] = now();
            $id = DB::table('reading_items')->insertGetId($row);
        }

        return $kind === 'chapter'
            ? redirect()->route('admin.series.chapters.edit', [$seriesId, $id])->with('success', '章节已保存')
            : redirect()->route('admin.articles.edit', $id)->with('success', '文章已保存');
    }

    public function saveAnalysis(Request $request, int $id, HtmlContentSanitizer $sanitizer): RedirectResponse
    {
        $article = $this->editableReadingItem($id);
        abort_unless($article, 404);
        $data = $request->validate([
            'type' => 'required|in:richtext,officialAccount',
            'format' => 'required|in:html,markdown',
            'html' => 'required_if:type,richtext|nullable|string|max:1000000',
            'url' => 'required_if:type,officialAccount|nullable|url|max:2048',
            'title' => 'nullable|string|max:255', 'summary' => 'nullable|string|max:10000',
        ]);
        $source = $data['html'] ?? '';
        $format = $data['type'] === 'richtext' ? $data['format'] : 'html';
        $html = $data['type'] === 'richtext'
            ? ($format === 'markdown' ? $sanitizer->clean(Str::markdown($source)) : $sanitizer->clean($source))
            : null;
        if ($data['type'] === 'richtext' && trim(strip_tags((string) $html)) === '') {
            return back()->withErrors(['html' => '清理后没有可显示的正文内容'])->withInput();
        }

        $now = now();
        $exists = DB::table('reading_analyses')->where('reading_item_id', $id)->exists();
        DB::table('reading_analyses')->updateOrInsert(['reading_item_id' => $id], [
            'type' => $data['type'], 'format' => $format,
            'html' => $data['type'] === 'richtext' && $format === 'markdown' ? $source : $html,
            'url' => $data['url'] ?? null,
            'title' => $data['title'] ?? null, 'summary' => $data['summary'] ?? null,
            'status' => $article->status === 'published' ? 'published' : 'draft', 'updated_at' => $now,
        ] + ($exists ? [] : ['created_at' => $now]));

        return $this->redirectToReadingEditor($article)->with('success', '文章解析已保存');
    }

    public function saveReadingWords(Request $request, int $id): RedirectResponse
    {
        $article = $this->editableReadingItem($id);
        abort_unless($article, 404);
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

        return $this->redirectToReadingEditor($article)->with('success', '重点词汇顺序已保存');
    }

    public function saveReadingQuestions(Request $request, int $id): RedirectResponse
    {
        $article = $this->editableReadingItem($id);
        abort_unless($article, 404);
        $data = $request->validate([
            'questions' => 'nullable|array|max:100',
            'questions.*.id' => 'nullable|integer',
            'questions.*.questionType' => 'required|string|max:32',
            'questions.*.kind' => 'required|in:choice,fill',
            'questions.*.prompt' => 'required|string|max:5000',
            'questions.*.promptCn' => 'nullable|string|max:5000',
            'questions.*.options' => 'nullable|string|max:10000',
            'questions.*.answer' => 'required|string|max:5000',
            'questions.*.explanation' => 'nullable|string|max:10000',
        ]);

        $rows = [];
        foreach ($data['questions'] ?? [] as $index => $item) {
            $options = array_values(array_filter(array_map('trim', preg_split('/\\R/u', $item['options'] ?? '') ?: []), fn (string $option): bool => $option !== ''));
            if ($item['kind'] === 'choice') {
                if (count($options) < 2 || count($options) > 8) {
                    return back()->withErrors(["questions.$index.options" => '选择题需要填写 2 至 8 个选项，每行一个'])->withInput();
                }
                $answerLetter = strtoupper(trim($item['answer']));
                if (! preg_match('/^[A-H]$/', $answerLetter)) {
                    return back()->withErrors(["questions.$index.answer" => '请选择正确选项字母'])->withInput();
                }
                $answerIndex = ord($answerLetter) - ord('A');
                if ($answerIndex < 0 || $answerIndex >= count($options)) {
                    return back()->withErrors(["questions.$index.answer" => '正确选项不在已填写的选项范围内'])->withInput();
                }
                $answer = (string) $answerIndex;
                $optionsJson = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } else {
                $answer = trim($item['answer']);
                $optionsJson = null;
            }
            $rows[] = [
                'id' => isset($item['id']) && $item['id'] !== '' ? (int) $item['id'] : null,
                'question_type' => $item['questionType'], 'kind' => $item['kind'],
                'prompt' => $item['prompt'], 'prompt_cn' => $item['promptCn'] ?? null,
                'options_json' => $optionsJson, 'answer' => $answer,
                'explanation' => $item['explanation'] ?? null, 'sort_order' => $index + 1,
            ];
        }

        DB::transaction(function () use ($id, $rows): void {
            $existingIds = DB::table('reading_questions')->where('reading_item_id', $id)->pluck('id')->map(fn ($value): int => (int) $value)->all();
            $submittedIds = array_values(array_filter(array_column($rows, 'id')));
            abort_if(array_diff($submittedIds, $existingIds) !== [], 422, '题目不属于当前文章');
            DB::table('reading_questions')->where('reading_item_id', $id)->whereNotIn('id', $submittedIds ?: [0])->delete();
            DB::table('reading_questions')->where('reading_item_id', $id)->increment('sort_order', 100000);
            foreach ($rows as $row) {
                $questionId = $row['id'];
                unset($row['id']);
                $row['reading_item_id'] = $id;
                $row['updated_at'] = now();
                if ($questionId) {
                    DB::table('reading_questions')->where('id', $questionId)->where('reading_item_id', $id)->update($row);
                } else {
                    $row['created_at'] = now();
                    DB::table('reading_questions')->insert($row);
                }
            }
        });

        return $this->redirectToReadingEditor($article)->with('success', '阅读理解题已保存');
    }

    public function publishArticle(int $id): RedirectResponse
    {
        $article = DB::table('reading_items')->where('id', $id)->where('kind', 'article')->first();
        abort_unless($article, 404);
        $failed = collect($this->articlePublishChecks($article))
            ->filter(fn (array $check): bool => $check['required'] && ! $check['passed'])
            ->pluck('label')->all();
        if ($failed !== []) {
            return back()->withErrors(['publish' => '发布前请完成：'.implode('、', $failed)]);
        }
        DB::transaction(function () use ($id): void {
            DB::table('reading_items')->where('id', $id)->update(['status' => 'published', 'updated_at' => now()]);
            DB::table('reading_analyses')->where('reading_item_id', $id)->update(['status' => 'published', 'updated_at' => now()]);
        });
        return redirect()->route('admin.articles.index')->with('success', '文章已发布');
    }

    public function unpublishArticle(int $id): RedirectResponse
    {
        abort_unless(DB::table('reading_items')->where('id', $id)->where('kind', 'article')->exists(), 404);
        DB::transaction(function () use ($id): void {
            DB::table('reading_items')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
            DB::table('reading_analyses')->where('reading_item_id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
        });
        return redirect()->route('admin.articles.index')->with('success', '文章已下架');
    }

    public function searchArticleWords(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        abort_unless($this->editableReadingItem($id), 404);
        $data = $request->validate(['q' => 'required|string|min:1|max:100']);
        $query = trim($data['q']);
        $items = DB::table('words')->where(function ($words) use ($query): void {
            $words->where('headword', 'like', '%'.$query.'%')->orWhere('meaning', 'like', '%'.$query.'%');
        })->orderBy('headword')->limit(20)->get(['id', 'headword', 'meaning', 'status']);

        return response()->json(['items' => $items]);
    }

    public function createArticleWord(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        abort_unless($this->editableReadingItem($id), 404);
        $data = $request->validate([
            'headword' => 'required|string|max:255', 'meaning' => 'required|string|max:255',
            'ipa' => 'nullable|string|max:255', 'partOfSpeech' => 'nullable|string|max:64',
        ]);
        $now = now();
        $wordId = DB::transaction(function () use ($data, $id, $now): int {
            $wordId = DB::table('words')->insertGetId([
                'headword' => trim($data['headword']), 'meaning' => trim($data['meaning']),
                'ipa' => $data['ipa'] ?? null, 'part_of_speech' => $data['partOfSpeech'] ?? null,
                'status' => 'draft', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('word_details')->insert([
                'word_id' => $wordId, 'type' => 'richtext', 'status' => 'draft',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $sortOrder = (int) DB::table('reading_words')->where('reading_item_id', $id)->max('sort_order') + 1;
            DB::table('reading_words')->insert([
                'reading_item_id' => $id, 'word_id' => $wordId, 'sort_order' => $sortOrder,
            ]);

            return $wordId;
        });

        return response()->json([
            'id' => $wordId, 'headword' => trim($data['headword']),
            'meaning' => trim($data['meaning']), 'status' => 'draft',
        ], 201);
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

        return redirect()->route('admin.words.edit', $id)->with('success', '单词及详解已保存');
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
        return redirect()->route('admin.words.edit', $id)->with('success', '单词已发布');
    }

    public function unpublishWord(int $id): RedirectResponse
    {
        abort_unless(DB::table('words')->where('id', $id)->exists(), 404);
        DB::transaction(function () use ($id): void {
            DB::table('words')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
            DB::table('word_details')->where('word_id', $id)->update(['status' => 'draft', 'updated_at' => now()]);
        });
        return redirect()->route('admin.words.edit', $id)->with('success', '单词已下架');
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

    private function editableReadingItem(int $id): ?object
    {
        return DB::table('reading_items')->where('id', $id)->whereIn('kind', ['article', 'chapter'])->first();
    }

    private function redirectToReadingEditor(object $item): RedirectResponse
    {
        return $item->kind === 'chapter'
            ? redirect()->route('admin.series.chapters.edit', [$item->series_id, $item->id])
            : redirect()->route('admin.articles.edit', $item->id);
    }

    private function articlePublishChecks(object $article): array
    {
        $body = json_decode($article->body_json ?? '[]', true) ?: [];
        $sentences = collect($body)->flatMap(fn ($paragraph) => $paragraph['sentences'] ?? [])
            ->filter(fn ($sentence) => trim((string) $sentence) !== '');
        $hasParagraphTranslations = $body !== [] && collect($body)->every(fn ($paragraph) => trim((string) ($paragraph['translation'] ?? '')) !== '');
        $hasSentenceTranslations = $sentences->isNotEmpty() && collect($body)->every(function ($paragraph): bool {
            $items = $paragraph['sentences'] ?? [];
            $translations = $paragraph['sentenceTranslations'] ?? [];
            return count($items) > 0 && collect($items)->keys()->every(fn ($index) => trim((string) ($translations[$index] ?? '')) !== '');
        });
        $analysisExists = DB::table('reading_analyses')->where('reading_item_id', $article->id)->exists();
        $questionCount = DB::table('reading_questions')->where('reading_item_id', $article->id)->count();
        $wordCount = DB::table('reading_words')->where('reading_item_id', $article->id)->count();

        return [
            ['key' => 'title', 'label' => '英文标题', 'passed' => trim((string) $article->title) !== '', 'required' => true],
            ['key' => 'body', 'label' => '英文正文', 'passed' => $sentences->isNotEmpty(), 'required' => true],
            ['key' => 'translation', 'label' => '各段中文翻译', 'passed' => $hasParagraphTranslations, 'required' => true],
            ['key' => 'rights', 'label' => '版权来源类型', 'passed' => trim((string) $article->rights_type) !== '', 'required' => true],
            ['key' => 'cover', 'label' => '文章封面', 'passed' => trim((string) $article->cover_asset) !== '', 'required' => false],
            ['key' => 'sentenceTranslations', 'label' => '逐句翻译', 'passed' => $hasSentenceTranslations, 'required' => false],
            ['key' => 'words', 'label' => '重点词汇', 'passed' => $wordCount > 0, 'required' => false, 'detail' => $wordCount ? $wordCount.' 个' : null],
            ['key' => 'analysis', 'label' => '语法解析', 'passed' => $analysisExists, 'required' => false],
            ['key' => 'questions', 'label' => '阅读理解题', 'passed' => $questionCount > 0, 'required' => false, 'detail' => $questionCount ? $questionCount.' 道' : null],
        ];
    }

    private function readingLevelOptions(): array
    {
        return DB::table('reading_levels')->orderBy('sort_order')->orderBy('id')->get()->map(fn (object $level): array => [
            'value' => $level->name,
            'label' => $level->name.($level->is_active ? '' : '（已停用）'),
            'disabled' => ! (bool) $level->is_active,
        ])->all();
    }
}
