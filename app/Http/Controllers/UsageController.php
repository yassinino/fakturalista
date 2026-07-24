<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use Illuminate\Http\JsonResponse;

class UsageController extends Controller
{
    public function __construct(private PlanService $planService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->planService->getUsageSummary());
    }
}
