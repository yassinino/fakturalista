<?php

namespace App\Http\Controllers;

use App\Services\InvoiceAiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InvoiceAiController extends Controller
{
    public function __construct(private readonly InvoiceAiService $aiService) {}

    public function parseInvoice(Request $request): JsonResponse
    {
        $request->validate(['text' => 'required|string|max:500']);

        try {
            $data = $this->aiService->parseInvoice(trim($request->input('text')));

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\RuntimeException $e) {
            Log::warning('[InvoiceAiController] Parse failed', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('[InvoiceAiController] Unexpected error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.',
            ], 500);
        }
    }
}
