<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class InvoiceAiService
{
    private const API_BASE      = 'https://generativelanguage.googleapis.com/v1beta/models';
    private const TIMEOUT       = 30;
    private const MAX_RETRIES   = 2;      // per model: 0 s → 1 s → 2 s
    private const FALLBACK_MODEL = 'gemini-1.5-flash';

    private string $apiKey;
    private string $model;
    private int    $defaultVat;

    public function __construct()
    {
        $this->apiKey     = config('services.gemini.key', '');
        $this->model      = config('services.gemini.model', 'gemini-2.0-flash');
        $this->defaultVat = (int) config('services.gemini.default_vat', 0);
    }

    /**
     * Parse a natural language invoice description into structured fields.
     *
     * @return array{client:string, description:string, amount:float, vat:float, date:string}
     * @throws \RuntimeException
     */
    public function parseInvoice(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $today   = now()->toDateString();
        $payload = $this->buildPayload($text, $today);

        // Try primary model, then fallback - each with up to MAX_RETRIES on 429
        $models = array_values(array_unique([$this->model, self::FALLBACK_MODEL]));

        foreach ($models as $model) {
            for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
                if ($attempt > 0) {
                    sleep($attempt); // 1 s after first failure, 2 s after second
                }

                $result = $this->callModel($model, $payload);

                if ($result['rate_limited']) {
                    Log::info("[InvoiceAiService] 429 on {$model} (attempt {$attempt})");
                    continue; // retry same model after sleep
                }

                if (isset($result['error'])) {
                    // Non-rate-limit errors are terminal - don't retry
                    throw new \RuntimeException($result['error']);
                }

                return $this->normalize($result['data'], $today);
            }

            // All retries exhausted on this model - try next
            Log::warning("[InvoiceAiService] Exhausted retries on {$model}, switching to fallback.");
        }

        throw new \RuntimeException(
            'Gemini rate limit reached on all models. Wait a moment and try again.'
        );
    }

    /**
     * Single HTTP call to one Gemini model.
     *
     * Returns one of:
     *   ['rate_limited' => true]
     *   ['error'        => string]
     *   ['data'         => array]
     */
    private function callModel(string $model, array $payload): array
    {
        $url = self::API_BASE . "/{$model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::error('[InvoiceAiService] Connection error', ['message' => $e->getMessage()]);
            return ['error' => 'Cannot reach Gemini API. Check your internet connection.'];
        }

        if ($response->status() === 429) {
            return ['rate_limited' => true];
        }

        if ($response->failed()) {
            $status  = $response->status();
            $body    = $response->body();
            Log::error('[InvoiceAiService] API failure', ['status' => $status, 'body' => $body]);

            $message = match (true) {
                $status === 400             => 'Invalid request sent to Gemini.',
                in_array($status, [401, 403]) => 'Invalid GEMINI_API_KEY - check your .env.',
                $status >= 500              => 'Gemini service error. Try again later.',
                default                     => "Gemini API returned HTTP {$status}.",
            };

            return ['error' => $message];
        }

        $body    = $response->json();
        $rawText = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($rawText === null) {
            Log::error('[InvoiceAiService] Unexpected response shape', ['body' => $body]);
            return ['error' => 'Gemini returned an unexpected response structure.'];
        }

        $parsed = json_decode($rawText, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            Log::error('[InvoiceAiService] Malformed JSON', [
                'raw'   => $rawText,
                'error' => json_last_error_msg(),
            ]);
            return ['error' => 'AI returned malformed data. Try rephrasing your input.'];
        }

        return ['data' => $parsed];
    }

    private function buildPayload(string $userText, string $today): array
    {
        $systemPrompt = <<<PROMPT
You are a precise invoice data extractor for a billing application.

Extract invoice information from the user's text and return ONLY a valid JSON object with exactly these five fields:
- "client": the customer or company name to invoice (string, empty if not mentioned)
- "description": the service, product, or work performed (string, empty if not mentioned)
- "amount": the invoice amount as a plain number with no currency symbols or commas (number, 0 if not mentioned)
- "vat": the VAT/tax percentage as a plain number (number, default {$this->defaultVat} if not mentioned - common values: 0, 4, 10, 20, 21)
- "date": the invoice date in YYYY-MM-DD format (string, use today {$today} if not mentioned)

Critical rules:
- Return ONLY the JSON object. No explanation. No markdown. No code blocks. No extra text.
- "amount" must be a number (e.g. 250 not "€250" not "250 euros")
- "vat" must be a number (e.g. 20 not "20%")
- "date" must be YYYY-MM-DD or empty string if truly ambiguous
- "client" is who is being invoiced, not the sender
- Translate amounts written as words to numbers: "five hundred" → 500, "deux cents" → 200
PROMPT;

        return [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => $userText]],
                ],
            ],
            'generationConfig' => [
                'temperature'      => 0.1,
                'responseMimeType' => 'application/json',
                'responseSchema'   => [
                    'type'       => 'object',
                    'properties' => [
                        'client'      => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'amount'      => ['type' => 'number'],
                        'vat'         => ['type' => 'number'],
                        'date'        => ['type' => 'string'],
                    ],
                    'required' => ['client', 'description', 'amount', 'vat', 'date'],
                ],
            ],
        ];
    }

    private function normalize(array $parsed, string $today): array
    {
        return [
            'client'      => trim((string) ($parsed['client']      ?? '')),
            'description' => trim((string) ($parsed['description'] ?? '')),
            'amount'      => (float)       ($parsed['amount']      ?? 0),
            'vat'         => (float)       ($parsed['vat']         ?? $this->defaultVat),
            'date'        => trim((string) ($parsed['date']        ?? $today)) ?: $today,
        ];
    }
}
