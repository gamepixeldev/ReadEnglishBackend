<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RewardedHintSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RewardedAdController extends Controller
{
    public function settings(): InertiaResponse
    {
        return Inertia::render('Admin/RewardedAds/Settings', ['settings' => RewardedHintSettings::all()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $needsAdUnit = $request->boolean('enabled') || User::query()->where('rewarded_ads_mode', 'enabled')->exists();
        $values = $request->validate([
            'enabled' => ['required', 'boolean'],
            'adUnitId' => [$needsAdUnit ? 'required' : 'nullable', 'string', 'max:100'],
            'answerSeconds' => ['required', 'integer', 'min:1', 'max:30'],
        ], ['adUnitId.required' => '全局或用户单独开启广告时，必须填写微信激励视频广告位 ID。']);
        RewardedHintSettings::save($values);

        return back()->with('success', '激励广告设置已保存。');
    }

    public function users(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('q', ''));
        $query = User::query()->where('is_admin', false);
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                if (ctype_digit($search)) $builder->where('id', (int) $search);
                $builder->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        return Inertia::render('Admin/RewardedAds/Users', [
            'users' => $query->orderByDesc('id')->paginate(20)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function updateUser(Request $request, int $id): RedirectResponse
    {
        $values = $request->validate(['mode' => ['required', 'in:inherit,enabled,disabled']]);
        if ($values['mode'] === 'enabled' && RewardedHintSettings::all()['adUnitId'] === '') {
            return back()->withErrors(['mode' => '请先在激励广告设置中填写微信广告位 ID。']);
        }
        $user = User::query()->where('is_admin', false)->findOrFail($id);
        $user->rewarded_ads_mode = $values['mode'] === 'inherit' ? null : $values['mode'];
        $user->save();

        return back()->with('success', '用户广告策略已更新。');
    }
}
