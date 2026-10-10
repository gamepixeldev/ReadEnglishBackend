<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => 'required|in:content,suggestion,other',
            'targetType' => 'nullable|in:reading,word,grammar,puzzle,general',
            'targetId' => 'nullable|integer|min:1',
            'targetTitle' => 'nullable|string|max:255',
            'message' => 'required|string|min:5|max:3000',
        ]);

        $targetType = $data['targetType'] ?? 'general';
        $targetId = $targetType === 'general' ? null : ($data['targetId'] ?? null);
        abort_if($targetType !== 'general' && ! $targetId, 422, '请选择要反馈的内容');

        $targetTables = [
            'reading' => 'reading_items',
            'word' => 'words',
            'grammar' => 'grammar_lessons',
            'puzzle' => 'puzzle_sets',
        ];
        $targetTitle = null;
        if ($targetId !== null) {
            $table = $targetTables[$targetType];
            $target = DB::table($table)->where('id', $targetId)->first();
            abort_unless($target, 422, '反馈对象不存在');
            $targetTitle = $target->title ?? $target->headword ?? null;
            if ($targetType === 'reading' && ($target->kind ?? null) === 'chapter') {
                $targetTitle = $target->title;
            }
        }

        $userId = $this->optionalUserId($request);
        $accessToken = bin2hex(random_bytes(32));
        $id = DB::table('content_feedback')->insertGetId([
            'user_id' => $userId,
            'category' => $data['category'],
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_title' => $targetTitle ?: ($data['targetTitle'] ?? null),
            'message' => trim($data['message']),
            'access_token_hash' => hash('sha256', $accessToken),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id, 'accessToken' => $accessToken, 'message' => '反馈已收到，谢谢你的帮助'], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'accessTokens' => 'sometimes|array|max:500',
            'accessTokens.*' => ['string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/i'],
            'markSeen' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1',
        ]);
        $query = $this->ownedFeedbackQuery($request, $data['accessTokens'] ?? []);
        $replyCounts = DB::table('content_feedback_replies')->selectRaw('feedback_id, COUNT(*) as reply_count')->groupBy('feedback_id');
        $unreadCount = (clone $query)->whereNull('seen_at')->where(function ($query): void {
            $query->whereExists(function ($replies): void {
                $replies->selectRaw('1')->from('content_feedback_replies')->whereColumn('feedback_id', 'content_feedback.id');
            })->orWhereNotNull('admin_reply')->orWhere('status', 'resolved');
        })->count();
        $page = $query->leftJoinSub($replyCounts, 'reply_counts', 'reply_counts.feedback_id', '=', 'content_feedback.id')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(20, ['content_feedback.id', 'category', 'target_type', 'target_title', 'message', 'status', 'admin_reply', 'responded_at', 'created_at', 'seen_at', 'reply_counts.reply_count'], 'page', $data['page'] ?? 1);
        $page->getCollection()->transform(function (object $item): object {
            $item->is_unread = $item->seen_at === null && ((int) ($item->reply_count ?? 0) > 0 || $item->admin_reply !== null || $item->status === 'resolved');
            return $item;
        });

        return response()->json([
            'items' => $page->items(),
            'page' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'total' => $page->total(),
            'unreadCount' => $unreadCount,
        ]);
    }

    public function showMine(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'accessTokens' => 'sometimes|array|max:500',
            'accessTokens.*' => ['string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/i'],
        ]);
        $feedback = $this->ownedFeedbackQuery($request, $data['accessTokens'] ?? [])->where('id', $id)->first();
        abort_unless($feedback, 404);

        $replies = DB::table('content_feedback_replies')->where('feedback_id', $id)->orderBy('id')->get(['id', 'kind', 'message', 'created_at']);
        if ($replies->isEmpty() && $feedback->admin_reply) {
            $replies = collect([(object) ['id' => 0, 'kind' => $feedback->status === 'resolved' ? 'resolution' : 'update', 'message' => $feedback->admin_reply, 'created_at' => $feedback->responded_at]]);
        }
        $wasUnread = $feedback->seen_at === null && ($replies->isNotEmpty() || $feedback->status === 'resolved');
        if ($wasUnread) DB::table('content_feedback')->where('id', $id)->update(['seen_at' => now()]);
        $unreadCount = $this->ownedFeedbackQuery($request, $data['accessTokens'] ?? [])->whereNull('seen_at')->where(function ($query): void {
            $query->whereExists(function ($replies): void {
                $replies->selectRaw('1')->from('content_feedback_replies')->whereColumn('feedback_id', 'content_feedback.id');
            })->orWhereNotNull('admin_reply')->orWhere('status', 'resolved');
        })->count();

        return response()->json(['item' => $feedback, 'replies' => $replies, 'unreadCount' => $unreadCount]);
    }

    private function ownedFeedbackQuery(Request $request, array $accessTokens)
    {
        $userId = $this->optionalUserId($request);
        $hashes = array_map(fn (string $token): string => hash('sha256', $token), $accessTokens);
        return DB::table('content_feedback')->where(function ($query) use ($userId, $hashes): void {
            if ($userId !== null) $query->where('user_id', $userId);
            if ($hashes !== [] && $userId !== null) $query->orWhereIn('access_token_hash', $hashes);
            elseif ($hashes !== []) $query->whereIn('access_token_hash', $hashes);
            elseif ($userId === null) $query->whereRaw('1 = 0');
        });
    }

    private function optionalUserId(Request $request): ?int
    {
        $plainToken = $request->bearerToken();
        if (! is_string($plainToken) || ! preg_match('/\A[a-f0-9]{64}\z/i', $plainToken)) {
            return null;
        }

        $token = DB::table('mobile_access_tokens')
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())
            ->first();
        if (! $token) return null;

        $user = User::query()->find($token->user_id);
        return $user && ! $user->is_admin ? (int) $user->id : null;
    }
}
