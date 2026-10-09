<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReadingLevelController extends Controller
{
    public function index(): InertiaResponse
    {
        $levels = DB::table('reading_levels')->orderBy('sort_order')->orderBy('id')->get();
        $usage = DB::table('reading_items')->select('level')->selectRaw('count(*) as article_count')->whereNotNull('level')->groupBy('level')->pluck('article_count', 'level');
        $seriesUsage = DB::table('series')->select('level')->selectRaw('count(*) as series_count')->whereNotNull('level')->groupBy('level')->pluck('series_count', 'level');

        $levels->transform(function (object $level) use ($usage, $seriesUsage): object {
            $level->is_active = (bool) $level->is_active;
            $level->content_count = (int) ($usage[$level->name] ?? 0) + (int) ($seriesUsage[$level->name] ?? 0);
            return $level;
        });

        return Inertia::render('Admin/ReadingLevels/Index', ['levels' => $levels]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:32', Rule::unique('reading_levels', 'name')]]);
        DB::table('reading_levels')->insert([
            'name' => trim($data['name']),
            'is_active' => true,
            'sort_order' => (int) DB::table('reading_levels')->max('sort_order') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', '阅读级别已添加');
    }

    public function toggle(int $id): RedirectResponse
    {
        $level = DB::table('reading_levels')->where('id', $id)->first();
        abort_unless($level, 404);
        DB::table('reading_levels')->where('id', $id)->update(['is_active' => ! $level->is_active, 'updated_at' => now()]);

        return back()->with('success', $level->is_active ? '阅读级别已停用' : '阅读级别已启用');
    }

    public function move(int $id, string $direction): RedirectResponse
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        $level = DB::table('reading_levels')->where('id', $id)->first();
        abort_unless($level, 404);

        DB::transaction(function () use ($level, $direction): void {
            $ordered = DB::table('reading_levels')->orderBy('sort_order')->orderBy('id')->get(['id', 'sort_order']);
            $position = $ordered->search(fn (object $item): bool => (int) $item->id === (int) $level->id);
            if ($position === false) return;
            $target = $ordered[$position + ($direction === 'up' ? -1 : 1)] ?? null;
            if (! $target) return;

            $temporary = (int) $ordered->max('sort_order') + 1;
            DB::table('reading_levels')->where('id', $level->id)->update(['sort_order' => $temporary]);
            DB::table('reading_levels')->where('id', $target->id)->update(['sort_order' => $level->sort_order, 'updated_at' => now()]);
            DB::table('reading_levels')->where('id', $level->id)->update(['sort_order' => $target->sort_order, 'updated_at' => now()]);
        });

        return back()->with('success', '阅读级别顺序已更新');
    }
}
