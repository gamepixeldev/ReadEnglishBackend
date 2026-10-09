<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DailyWordPlanController extends Controller
{
    public function index(): InertiaResponse
    {
        $plans = DB::table('daily_word_plans as p')
            ->leftJoin('daily_word_plan_items as i', 'i.plan_id', '=', 'p.id')
            ->select('p.id', 'p.plan_date', 'p.status', 'p.updated_at')
            ->selectRaw('count(i.word_id) as word_count')
            ->groupBy('p.id', 'p.plan_date', 'p.status', 'p.updated_at')
            ->orderByDesc('p.plan_date')->paginate(20);

        return Inertia::render('Admin/DailyWords/Index', ['plans' => $plans]);
    }

    public function form(?int $id = null): InertiaResponse
    {
        $plan = $id ? DB::table('daily_word_plans')->where('id', $id)->first() : null;
        if ($id) abort_unless($plan, 404);
        $selectedWords = $plan ? DB::table('daily_word_plan_items as i')
            ->join('words as w', 'w.id', '=', 'i.word_id')
            ->where('i.plan_id', $id)->orderBy('i.sort_order')
            ->get(['w.id', 'w.headword', 'w.meaning', 'w.status']) : collect();

        return Inertia::render('Admin/DailyWords/Form', [
            'plan' => $plan,
            'selectedWords' => $selectedWords,
        ]);
    }

    public function wordOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $words = DB::table('words')->where('status', 'published')
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search): void {
                $match->where('headword', 'like', '%'.$search.'%')->orWhere('meaning', 'like', '%'.$search.'%');
            }))
            ->orderBy('headword')->limit(30)->get(['id', 'headword', 'meaning']);

        return response()->json(['items' => $words]);
    }

    public function save(Request $request, ?int $id = null): RedirectResponse
    {
        $data = $request->validate([
            'planDate' => ['required', 'date_format:Y-m-d', Rule::unique('daily_word_plans', 'plan_date')->ignore($id)],
            'wordIds' => ['required', 'array', 'min:1', 'max:30'],
            'wordIds.*' => ['required', 'integer', 'distinct', Rule::exists('words', 'id')->where('status', 'published')],
        ]);
        if ($id) {
            $existing = DB::table('daily_word_plans')->where('id', $id)->first();
            abort_unless($existing, 404);
            if ($existing->plan_date !== $data['planDate']) {
                return back()->withErrors(['planDate' => '计划日期创建后不可修改，请另建一天的计划'])->withInput();
            }
            if (DB::table('daily_word_completions')->where('plan_id', $id)->exists()) {
                $currentIds = DB::table('daily_word_plan_items')->where('plan_id', $id)->orderBy('sort_order')->pluck('word_id')->map(fn ($value) => (int) $value)->all();
                if ($currentIds !== array_map('intval', $data['wordIds'])) {
                    return back()->withErrors(['wordIds' => '已有用户完成此计划，不能再调整单词或顺序'])->withInput();
                }
            }
        }

        DB::transaction(function () use ($data, &$id): void {
            if ($id) {
                DB::table('daily_word_plans')->where('id', $id)->update([
                    'plan_date' => $data['planDate'], 'updated_at' => now(),
                ]);
                DB::table('daily_word_plan_items')->where('plan_id', $id)->delete();
            } else {
                $id = DB::table('daily_word_plans')->insertGetId([
                    'plan_date' => $data['planDate'], 'status' => 'draft',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($data['wordIds'] as $index => $wordId) {
                DB::table('daily_word_plan_items')->insert([
                    'plan_id' => $id, 'word_id' => $wordId, 'sort_order' => $index + 1,
                ]);
            }
        });

        return redirect()->route('admin.daily-words.edit', $id)->with('success', '每日单词计划已保存');
    }

    public function toggle(int $id): RedirectResponse
    {
        $plan = DB::table('daily_word_plans')->where('id', $id)->first();
        abort_unless($plan, 404);
        if ($plan->status !== 'published') {
            $wordIds = DB::table('daily_word_plan_items')->where('plan_id', $id)->pluck('word_id');
            if ($wordIds->isEmpty() || DB::table('words')->whereIn('id', $wordIds)->where('status', 'published')->count() !== $wordIds->count()) {
                return back()->withErrors(['publish' => '发布前请至少选择一个已发布的单词']);
            }
        }
        $published = $plan->status !== 'published';
        DB::table('daily_word_plans')->where('id', $id)->update([
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? now() : null,
            'updated_at' => now(),
        ]);

        return back()->with('success', $published ? '每日单词计划已发布' : '每日单词计划已下架');
    }
}
