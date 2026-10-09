<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PuzzleSetController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['draft', 'published'], true) ? $request->query('status') : null;
        $sets = DB::table('puzzle_sets as p')
            ->leftJoin('puzzle_sentences as s', 's.puzzle_set_id', '=', 'p.id')
            ->when($search !== '', fn ($query) => $query->where('p.title', 'like', '%'.$search.'%'))
            ->when($status, fn ($query) => $query->where('p.status', $status))
            ->select('p.id', 'p.title', 'p.subtitle', 'p.status', 'p.sort_order', 'p.source_reading_item_id', 'p.updated_at')
            ->selectRaw('count(s.id) as sentence_count')
            ->groupBy('p.id', 'p.title', 'p.subtitle', 'p.status', 'p.sort_order', 'p.source_reading_item_id', 'p.updated_at')
            ->orderBy('p.sort_order')->orderBy('p.id')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Puzzles/Index', compact('sets', 'search', 'status'));
    }

    public function form(?int $id = null): InertiaResponse
    {
        $set = $id ? DB::table('puzzle_sets')->where('id', $id)->first() : null;
        if ($id) abort_unless($set, 404);
        $sentences = $id ? DB::table('puzzle_sentences')->where('puzzle_set_id', $id)
            ->orderBy('sort_order')->get(['id', 'prompt_cn', 'answer_text']) : collect();
        $readings = DB::table('reading_items')->where('status', 'published')
            ->orderByDesc('id')->limit(30)->get(['id', 'title', 'translation']);
        if ($set?->source_reading_item_id && ! $readings->contains('id', $set->source_reading_item_id)) {
            $source = DB::table('reading_items')->where('id', $set->source_reading_item_id)->first(['id', 'title', 'translation']);
            if ($source) $readings->prepend($source);
        }

        return Inertia::render('Admin/Puzzles/Form', compact('set', 'sentences', 'readings'));
    }

    public function readingOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $items = DB::table('reading_items')->where('status', 'published')
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search): void {
                $match->where('title', 'like', '%'.$search.'%')->orWhere('translation', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('id')->limit(30)->get(['id', 'title', 'translation']);

        return response()->json(['items' => $items]);
    }

    public function save(Request $request, ?int $id = null): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'iconAsset' => 'nullable|string|max:255',
            'sourceReadingItemId' => ['nullable', 'integer', Rule::exists('reading_items', 'id')->where('status', 'published')],
            'sortOrder' => 'nullable|integer|min:0|max:4294967295',
            'sentences' => 'required|array|min:1|max:200',
            'sentences.*.promptCn' => 'required|string|max:10000',
            'sentences.*.answerText' => 'required|string|max:10000',
        ]);
        if ($id) {
            abort_unless(DB::table('puzzle_sets')->where('id', $id)->exists(), 404);
            $oldCount = DB::table('puzzle_sentences')->where('puzzle_set_id', $id)->count();
            if ($oldCount !== count($data['sentences']) && DB::table('puzzle_progress')->where('puzzle_set_id', $id)->exists()) {
                return back()->withErrors(['sentences' => '已有用户开始这组拼句，不能增加或减少句子'])->withInput();
            }
        }
        foreach ($data['sentences'] as $index => $sentence) {
            $tokens = preg_split('/\s+/u', trim($sentence['answerText']));
            if (count($tokens) < 2 || count($tokens) > 40) {
                return back()->withErrors(['sentences' => '第 '.($index + 1).' 句需要 2–40 个空格分隔的词块'])->withInput();
            }
        }

        DB::transaction(function () use ($data, &$id): void {
            $now = now();
            $row = [
                'title' => trim($data['title']), 'subtitle' => $data['subtitle'] ?? null,
                'icon_asset' => $data['iconAsset'] ?? null,
                'source_reading_item_id' => $data['sourceReadingItemId'] ?? null,
                'sort_order' => $data['sortOrder'] ?? 0, 'updated_at' => $now,
            ];
            if ($id) DB::table('puzzle_sets')->where('id', $id)->update($row);
            else $id = DB::table('puzzle_sets')->insertGetId($row + ['status' => 'draft', 'created_at' => $now]);

            foreach ($data['sentences'] as $index => $sentence) {
                $tokens = preg_split('/\s+/u', trim($sentence['answerText']));
                $bank = array_map(fn ($text, $position) => ['id' => $position + 1, 'text' => $text], $tokens, array_keys($tokens));
                $existing = DB::table('puzzle_sentences')->where('puzzle_set_id', $id)->where('sort_order', $index + 1)->first();
                $sentenceRow = [
                    'prompt_cn' => trim($sentence['promptCn']),
                    'answer_text' => trim($sentence['answerText']),
                    'tokens_json' => json_encode($bank, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'answer_token_ids_json' => json_encode(range(1, count($bank))),
                    'updated_at' => $now,
                ];
                if ($existing) DB::table('puzzle_sentences')->where('id', $existing->id)->update($sentenceRow);
                else DB::table('puzzle_sentences')->insert($sentenceRow + [
                    'puzzle_set_id' => $id, 'sort_order' => $index + 1, 'created_at' => $now,
                ]);
            }
            DB::table('puzzle_sentences')->where('puzzle_set_id', $id)->where('sort_order', '>', count($data['sentences']))->delete();
        });

        return redirect()->route('admin.puzzles.edit', $id)->with('success', '拼句内容已保存');
    }

    public function toggle(int $id): RedirectResponse
    {
        $set = DB::table('puzzle_sets')->where('id', $id)->first();
        abort_unless($set, 404);
        if ($set->status !== 'published' && ! DB::table('puzzle_sentences')->where('puzzle_set_id', $id)->exists()) {
            return back()->withErrors(['publish' => '发布前请添加拼句句子']);
        }
        $status = $set->status === 'published' ? 'draft' : 'published';
        DB::table('puzzle_sets')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);

        return back()->with('success', $status === 'published' ? '拼句内容已发布' : '拼句内容已下架');
    }
}
