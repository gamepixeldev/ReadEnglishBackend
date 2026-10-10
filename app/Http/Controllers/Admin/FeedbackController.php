<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class FeedbackController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $status = $request->query('status', '');
        abort_if($status !== '' && ! in_array($status, ['pending', 'reviewing', 'resolved'], true), 404);

        $query = DB::table('content_feedback')
            ->leftJoin('users', 'users.id', '=', 'content_feedback.user_id')
            ->select('content_feedback.id', 'content_feedback.category', 'content_feedback.target_type', 'content_feedback.target_title', 'content_feedback.message', 'content_feedback.status', 'content_feedback.admin_note', 'content_feedback.admin_reply', 'content_feedback.responded_at', 'content_feedback.created_at', 'users.name as user_name')
            ->orderByDesc('content_feedback.created_at')->orderByDesc('content_feedback.id');
        if ($status !== '') $query->where('content_feedback.status', $status);

        $feedback = $query->paginate(20)->withQueryString();
        $replyMap = DB::table('content_feedback_replies')->whereIn('feedback_id', $feedback->getCollection()->pluck('id'))
            ->orderBy('id')->get(['id', 'feedback_id', 'kind', 'message', 'created_at'])->groupBy('feedback_id');
        $feedback->getCollection()->transform(function (object $item) use ($replyMap): object {
            $item->replies = $replyMap->get($item->id, collect())->values();
            if ($item->replies->isEmpty() && $item->admin_reply) {
                $item->replies = collect([(object) [
                    'id' => 0,
                    'kind' => $item->status === 'resolved' ? 'resolution' : 'update',
                    'message' => $item->admin_reply,
                    'created_at' => $item->responded_at,
                ]]);
            }
            return $item;
        });
        return Inertia::render('Admin/Feedback/Index', [
            'feedback' => $feedback,
            'filters' => ['status' => $status],
            'counts' => DB::table('content_feedback')->selectRaw("SUM(status = 'pending') as pending, SUM(status = 'reviewing') as reviewing, SUM(status = 'resolved') as resolved")->first(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,reviewing,resolved',
            'admin_note' => 'nullable|string|max:3000',
            'admin_reply' => 'nullable|string|max:3000',
            'reply_kind' => 'sometimes|in:update,resolution',
        ]);
        $feedback = DB::table('content_feedback')->where('id', $id)->first();
        abort_unless($feedback, 404);
        $reply = isset($data['admin_reply']) ? trim($data['admin_reply']) ?: null : null;
        $status = $data['status'];
        $latestReply = DB::table('content_feedback_replies')->where('feedback_id', $id)->orderByDesc('id')->value('message') ?: $feedback->admin_reply;
        $replyChanged = $reply !== null && $reply !== $latestReply;
        if ($replyChanged) {
            DB::table('content_feedback_replies')->insert([
                'feedback_id' => $id,
                'kind' => $data['reply_kind'] ?? 'update',
                'message' => $reply,
                'created_at' => now(),
            ]);
            if (($data['reply_kind'] ?? 'update') === 'resolution') $status = 'resolved';
            elseif ($status === 'pending') $status = 'reviewing';
        }
        $resolvedNow = $status === 'resolved' && $feedback->status !== 'resolved';
        DB::table('content_feedback')->where('id', $id)->update([
            'status' => $status,
            'admin_note' => isset($data['admin_note']) ? trim($data['admin_note']) ?: null : null,
            'admin_reply' => $replyChanged ? $reply : $feedback->admin_reply,
            'responded_at' => $replyChanged ? now() : $feedback->responded_at,
            'seen_at' => ($replyChanged || $resolvedNow) ? null : $feedback->seen_at,
            'updated_at' => now(),
        ]);
        return back()->with('success', '反馈处理状态已保存');
    }
}
