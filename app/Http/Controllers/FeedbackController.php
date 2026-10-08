<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Services\Feedback\FeedbackService;
use Illuminate\Http\JsonResponse;

/**
 * Feedback widget endpoints (see App\Services\Feedback\FeedbackService):
 *  - storePublic: central domain POST /feedback (visitors, no account)
 *  - storeTenant: tenant domain POST /api/feedback (auth:api)
 */
class FeedbackController extends Controller
{
    public function __construct(private readonly FeedbackService $feedback) {}

    public function storePublic(StoreFeedbackRequest $request): JsonResponse
    {
        // Honeypot: invisible to people, filled by form-spamming bots.
        // Answer exactly like a success so the bot learns nothing.
        if (filled($request->input('website'))) {
            return $this->accepted();
        }

        $this->feedback->submitFromPublicSite($request->validated(), $request->file('screenshot'), $request);

        return $this->accepted();
    }

    public function storeTenant(StoreFeedbackRequest $request): JsonResponse
    {
        $this->feedback->submitFromTenant(
            $request->validated(),
            $request->file('screenshot'),
            $request,
            tenant(),
            $request->user(),
        );

        return $this->accepted();
    }

    private function accepted(): JsonResponse
    {
        return response()->json(['message' => __('site.feedback.success')], 201);
    }
}
