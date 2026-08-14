<?php

namespace App\Http\Controllers\V1\Forum;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Forum\ForumPostUpdateRequest;
use App\Http\Requests\V1\Forum\ForumTopicStoreRequest;
use App\Http\Requests\V1\Forum\ForumTopicUpdateRequest;
use App\Http\Resources\V1\Forum\ForumCommentResource;
use App\Http\Resources\V1\Forum\ForumPostResource;
use App\Http\Resources\V1\Forum\ForumReportResource;
use App\Http\Resources\V1\Forum\ForumTopicResource;
use App\Models\ForumComment;
use App\Models\ForumPost;
use App\Models\ForumReport;
use App\Services\Forum\ForumCommentService;
use App\Services\Forum\ForumPostService;
use App\Services\Forum\ForumReportService;
use App\Services\Forum\ForumTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminForumController extends Controller
{
    public function __construct(
        protected ForumTopicService $topicService,
        protected ForumPostService $postService,
        protected ForumCommentService $commentService,
        protected ForumReportService $reportService,
    ) {}

    // ─── Topics ──────────────────────────────────────────────────

    public function topicIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'is_active', 'per_page']);
        $topics = $this->topicService->getAllTopics($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => ForumTopicResource::collection($topics->items()),
                'current_page' => $topics->currentPage(),
                'last_page' => $topics->lastPage(),
                'per_page' => $topics->perPage(),
                'total' => $topics->total(),
            ],
        ]);
    }

    public function topicShow(string $uuid): JsonResponse
    {
        $topic = $this->topicService->findByUuid($uuid);

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ForumTopicResource($topic),
        ]);
    }

    public function topicStore(ForumTopicStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $topic = $this->topicService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Topic created.',
            'data' => new ForumTopicResource($topic),
        ], 201);
    }

    public function topicUpdate(ForumTopicUpdateRequest $request, string $uuid): JsonResponse
    {
        $topic = $this->topicService->findByUuid($uuid);

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found.',
            ], 404);
        }

        $topic = $this->topicService->update($topic, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Topic updated.',
            'data' => new ForumTopicResource($topic),
        ]);
    }

    public function topicDestroy(string $uuid): JsonResponse
    {
        $topic = $this->topicService->findByUuid($uuid);

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found.',
            ], 404);
        }

        $this->topicService->delete($topic);

        return response()->json([
            'success' => true,
            'message' => 'Topic deleted.',
        ]);
    }

    public function topicReorder(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:forum_topics,id'],
        ]);

        $this->topicService->reorder($request->input('ids'));

        return response()->json([
            'success' => true,
            'message' => 'Topics reordered.',
        ]);
    }

    // ─── Posts ───────────────────────────────────────────────────

    public function postIndex(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status', 'topic_id', 'user_id', 'search',
            'is_pinned', 'is_locked', 'is_featured', 'per_page',
        ]);

        $posts = $this->postService->getAllPostsAdmin($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => ForumPostResource::collection($posts->items()),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function postShow(string $uuid): JsonResponse
    {
        $post = $this->postService->findByUuid($uuid);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ForumPostResource($post),
        ]);
    }

    public function postUpdate(ForumPostUpdateRequest $request, string $uuid): JsonResponse
    {
        $post = ForumPost::where('uuid', $uuid)->firstOrFail();

        $post = $this->postService->update($post, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Post updated.',
            'data' => new ForumPostResource($post),
        ]);
    }

    public function postDestroy(string $uuid): JsonResponse
    {
        $post = ForumPost::where('uuid', $uuid)->firstOrFail();

        $this->postService->delete($post);

        return response()->json([
            'success' => true,
            'message' => 'Post deleted.',
        ]);
    }

    // ─── Comments ────────────────────────────────────────────────

    public function commentIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'post_id', 'user_id', 'search', 'per_page']);

        $comments = $this->commentService->getAllCommentsAdmin($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => ForumCommentResource::collection($comments->items()),
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    public function commentHide(int $id): JsonResponse
    {
        $comment = ForumComment::findOrFail($id);
        $this->commentService->hideComment($comment);

        return response()->json([
            'success' => true,
            'message' => 'Comment hidden.',
        ]);
    }

    public function commentUnhide(int $id): JsonResponse
    {
        $comment = ForumComment::findOrFail($id);
        $this->commentService->unhideComment($comment);

        return response()->json([
            'success' => true,
            'message' => 'Comment restored.',
        ]);
    }

    public function commentDestroy(int $id): JsonResponse
    {
        $comment = ForumComment::findOrFail($id);
        $this->commentService->delete($comment);

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted.',
        ]);
    }

    // ─── Reports ─────────────────────────────────────────────────

    public function reportIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'reason', 'reportable_type', 'per_page']);

        $reports = $this->reportService->getReports($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => ForumReportResource::collection($reports->items()),
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    public function reportReview(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:reviewed,dismissed'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = ForumReport::findOrFail($id);

        $report = $this->reportService->reviewReport(
            $report,
            $request->user(),
            $request->only(['status', 'admin_notes']),
        );

        return response()->json([
            'success' => true,
            'message' => 'Report reviewed.',
            'data' => new ForumReportResource($report),
        ]);
    }

    // ─── Stats ───────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $postStats = $this->postService->getStats();
        $reportStats = $this->reportService->getStats();

        $topicCount = \App\Models\ForumTopic::count();
        $activeTopicCount = \App\Models\ForumTopic::active()->count();
        $totalComments = ForumComment::count();
        $visibleComments = ForumComment::where('status', 'visible')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'topics' => [
                    'total' => $topicCount,
                    'active' => $activeTopicCount,
                ],
                'posts' => $postStats,
                'comments' => [
                    'total' => $totalComments,
                    'visible' => $visibleComments,
                    'hidden' => $totalComments - $visibleComments,
                ],
                'reports' => $reportStats,
            ],
        ]);
    }
}
