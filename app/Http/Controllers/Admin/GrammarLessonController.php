<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HtmlContentSanitizer;
use App\Support\ReadingCover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class GrammarLessonController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['draft', 'published'], true) ? $request->query('status') : null;
        $lessons = DB::table('grammar_lessons as g')
            ->leftJoin('grammar_questions as q', 'q.grammar_lesson_id', '=', 'g.id')
            ->when($search !== '', fn ($query) => $query->where('g.title', 'like', '%'.$search.'%'))
            ->when($status, fn ($query) => $query->where('g.status', $status))
            ->select('g.id', 'g.title', 'g.level', 'g.status', 'g.sort_order', 'g.updated_at')
            ->selectRaw('count(q.id) as question_count')
            ->groupBy('g.id', 'g.title', 'g.level', 'g.status', 'g.sort_order', 'g.updated_at')
            ->orderBy('g.sort_order')->orderBy('g.id')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Grammar/Index', compact('lessons', 'search', 'status'));
    }

    public function form(?int $id = null): InertiaResponse
    {
        $lesson = $id ? DB::table('grammar_lessons')->where('id', $id)->first() : null;
        if ($id) abort_unless($lesson, 404);
        $content = $id ? DB::table('grammar_lesson_contents')->where('grammar_lesson_id', $id)->first() : null;
        $questions = $id ? DB::table('grammar_questions')->where('grammar_lesson_id', $id)
            ->orderBy('sort_order')->get()->map(fn ($row) => [
                'kind' => $row->kind, 'prompt' => $row->prompt,
                'options' => $row->options_json ? json_decode($row->options_json, true) : [],
                'answer' => $row->kind === 'choice' ? (int) $row->answer : $row->answer,
                'explanation' => $row->explanation,
            ]) : collect();

        if ($content?->type === 'richtext' && ($content->format ?? 'html') === 'html') {
            $content->html = app(HtmlContentSanitizer::class)->clean($content->html ?? '');
        }
        $levels = DB::table('grammar_levels')->orderBy('sort_order')->orderBy('id')->get(['name', 'is_active']);

        $coverUrl = ReadingCover::url($lesson->cover_asset ?? null);

        return Inertia::render('Admin/Grammar/Form', compact('lesson', 'content', 'questions', 'levels', 'coverUrl'));
    }

    public function save(Request $request, HtmlContentSanitizer $sanitizer, ?int $id = null): RedirectResponse
    {
        $currentLevel = $id ? DB::table('grammar_lessons')->where('id', $id)->value('level') : null;
        $levelRule = Rule::exists('grammar_levels', 'name');
        if ($request->input('level') !== $currentLevel) $levelRule->where('is_active', true);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string|max:10000',
            'coverImage' => 'nullable|image|mimes:jpeg,png,webp|max:4096',
            'level' => ['required', 'string', 'max:32', $levelRule],
            'sortOrder' => 'nullable|integer|min:0|max:4294967295',
            'contentType' => ['required', Rule::in(['richtext', 'officialAccount'])],
            'contentFormat' => ['sometimes', Rule::in(['html', 'markdown'])],
            'contentHtml' => 'required_if:contentType,richtext|nullable|string|max:1000000',
            'contentUrl' => 'required_if:contentType,officialAccount|nullable|url|max:2048',
            'contentTitle' => 'nullable|string|max:255',
            'contentSummary' => 'nullable|string|max:10000',
            'questions' => 'present|array|max:100',
            'questions.*.kind' => ['required', Rule::in(['choice', 'fill'])],
            'questions.*.prompt' => 'required|string|max:10000',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*' => 'nullable|string|max:1000',
            'questions.*.answer' => 'required',
            'questions.*.explanation' => 'nullable|string|max:10000',
        ]);
        $source = $data['contentHtml'] ?? '';
        $format = $data['contentType'] === 'richtext' ? ($data['contentFormat'] ?? 'html') : 'html';
        $rendered = $data['contentType'] === 'richtext' ? $sanitizer->clean($format === 'markdown' ? Str::markdown($source) : $source) : null;
        if ($data['contentType'] === 'richtext' && trim(strip_tags((string) $rendered)) === '') {
            return back()->withErrors(['contentHtml' => '请填写可显示的语法正文'])->withInput();
        }
        $html = $format === 'markdown' ? $source : $rendered;
        foreach ($data['questions'] as $index => $question) {
            if ($question['kind'] === 'choice') {
                $options = array_values(array_map('trim', $question['options'] ?? []));
                if (count($options) < 2 || count($options) > 8 || in_array('', $options, true)
                    || filter_var($question['answer'], FILTER_VALIDATE_INT) === false
                    || (int) $question['answer'] < 0 || (int) $question['answer'] >= count($options)) {
                    return back()->withErrors(['questions' => '第 '.($index + 1).' 题的选项或正确答案无效'])->withInput();
                }
            } elseif (! is_string($question['answer']) || trim($question['answer']) === '') {
                return back()->withErrors(['questions' => '第 '.($index + 1).' 题缺少填空参考答案'])->withInput();
            }
        }
        if ($id) abort_unless(DB::table('grammar_lessons')->where('id', $id)->exists(), 404);

        $coverAsset = $id ? DB::table('grammar_lessons')->where('id', $id)->value('cover_asset') : null;
        if ($request->hasFile('coverImage')) {
            $coverAsset = $request->file('coverImage')->store('grammar-covers', 'public');
            if (! $coverAsset) abort(500, '封面保存失败');
        }

        DB::transaction(function () use ($data, $html, $format, $coverAsset, &$id): void {
            $now = now();
            $row = [
                'title' => trim($data['title']), 'summary' => $data['summary'] ?? null,
                'level' => $data['level'], 'cover_asset' => $coverAsset, 'sort_order' => $data['sortOrder'] ?? 0,
                'updated_at' => $now,
            ];
            if ($id) DB::table('grammar_lessons')->where('id', $id)->update($row);
            else $id = DB::table('grammar_lessons')->insertGetId($row + ['status' => 'draft', 'created_at' => $now]);

            $currentStatus = DB::table('grammar_lessons')->where('id', $id)->value('status');
            $hasContent = DB::table('grammar_lesson_contents')->where('grammar_lesson_id', $id)->exists();
            DB::table('grammar_lesson_contents')->updateOrInsert(['grammar_lesson_id' => $id], [
                'type' => $data['contentType'], 'format' => $format, 'html' => $html,
                'url' => $data['contentType'] === 'officialAccount' ? $data['contentUrl'] : null,
                'title' => $data['contentTitle'] ?? null, 'summary' => $data['contentSummary'] ?? null,
                'status' => $currentStatus, 'updated_at' => $now,
            ] + ($hasContent ? [] : ['created_at' => $now]));
            foreach ($data['questions'] as $index => $question) {
                $existingQuestion = DB::table('grammar_questions')->where('grammar_lesson_id', $id)->where('sort_order', $index + 1)->first();
                $questionRow = [
                    'kind' => $question['kind'], 'prompt' => trim($question['prompt']),
                    'options_json' => $question['kind'] === 'choice' ? json_encode(array_values(array_map('trim', $question['options'])), JSON_UNESCAPED_UNICODE) : null,
                    'answer' => (string) $question['answer'],
                    'explanation' => $question['explanation'] ?? null,
                    'updated_at' => $now,
                ];
                if ($existingQuestion) DB::table('grammar_questions')->where('id', $existingQuestion->id)->update($questionRow);
                else DB::table('grammar_questions')->insert($questionRow + [
                    'grammar_lesson_id' => $id, 'sort_order' => $index + 1, 'created_at' => $now,
                ]);
            }
            DB::table('grammar_questions')->where('grammar_lesson_id', $id)->where('sort_order', '>', count($data['questions']))->delete();
        });

        return redirect()->route('admin.grammar.edit', $id)->with('success', '语法课程及题目已保存');
    }

    public function toggle(int $id): RedirectResponse
    {
        $lesson = DB::table('grammar_lessons')->where('id', $id)->first();
        abort_unless($lesson, 404);
        if ($lesson->status !== 'published' && ! DB::table('grammar_lesson_contents')->where('grammar_lesson_id', $id)->exists()) {
            return back()->withErrors(['publish' => '发布前请完成语法正文']);
        }
        $status = $lesson->status === 'published' ? 'draft' : 'published';
        DB::transaction(function () use ($id, $status): void {
            DB::table('grammar_lessons')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
            DB::table('grammar_lesson_contents')->where('grammar_lesson_id', $id)->update(['status' => $status, 'updated_at' => now()]);
        });

        return back()->with('success', $status === 'published' ? '语法课程已发布' : '语法课程已下架');
    }
}
