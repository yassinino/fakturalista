<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class InvoiceAiService
{
    private const API_URL     = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MODEL       = 'claude-haiku-4-5-20251001';
    private const MAX_TOKENS  = 300;
    private const TIMEOUT     = 30;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key', '');
    }

    /**
     * Parse a natural language invoice description into structured fields.
     *
     * @return array{client:string, description:string, quantity:float, unit_price:float}
     * @throws \RuntimeException
     */
    public function parseInvoice(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'content-type'      => 'application/json',
                ])
                ->post(self::API_URL, $this->buildPayload($text));
        } catch (ConnectionException $e) {
            Log::error('[InvoiceAiService] Connection error', ['message' => $e->getMessage()]);
            throw new \RuntimeException('Cannot reach the Claude API. Check your internet connection.');
        }

        if ($response->status() === 429) {
            throw new \RuntimeException('Claude API rate limit reached. Wait a moment and try again.');
        }

        if ($response->failed()) {
            $status = $response->status();
            Log::error('[InvoiceAiService] API failure', ['status' => $status, 'body' => $response->body()]);

            $message = match (true) {
                $status === 400               => 'Invalid request sent to Claude.',
                in_array($status, [401, 403]) => 'Invalid ANTHROPIC_API_KEY - check your .env.',
                $status >= 500                => 'Claude service error. Try again later.',
                default                       => "Claude API returned HTTP {$status}.",
            };

            throw new \RuntimeException($message);
        }

        $body    = $response->json();
        $rawText = $body['content'][0]['text'] ?? null;

        if ($rawText === null) {
            Log::error('[InvoiceAiService] Unexpected response shape', ['body' => $body]);
            throw new \RuntimeException('Claude returned an unexpected response structure.');
        }

        $parsed = $this->extractJson($rawText);

        if ($parsed === null) {
            Log::error('[InvoiceAiService] Malformed JSON', ['raw' => $rawText]);
            throw new \RuntimeException('AI returned malformed data. Try rephrasing your input.');
        }

        return $this->normalize($parsed);
    }

    private function buildPayload(string $userText): array
    {
        $systemPrompt = <<<PROMPT
You are a precise invoice data extractor for a billing application.

Extract invoice information from the user's text and return ONLY a valid JSON object with exactly these four fields:
- "client": the customer or company name to invoice (string, empty string if not mentioned)
- "description": the service, product, or work performed (string, empty string if not mentioned)
- "quantity": the quantity of items/units (number, default 1 if not mentioned)
- "unit_price": the price per unit as a plain number with no currency symbols or commas (number, 0 if not mentioned)

Critical rules:
- Return ONLY the JSON object. No explanation. No markdown. No code fences. No extra text before or after.
- "quantity" must be a number (e.g. 1 not "one")
- "unit_price" must be a number (e.g. 250 not "€250" not "250 euros")
- "client" is who is being invoiced, not the sender
- Translate amounts written as words to numbers: "five hundred" -> 500, "deux cents" -> 200
PROMPT;

        return [
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userText],
            ],
        ];
    }

    /**
     * Parse the model's text as JSON, defensively stripping accidental
     * markdown code fences before decoding.
     */
    private function extractJson(string $raw): ?array
    {
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $parsed = json_decode($cleaned, true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) ? $parsed : null;
    }

    private function normalize(array $parsed): array
    {
        $quantity = (float) ($parsed['quantity'] ?? 1);

        return [
            'client'      => trim((string) ($parsed['client']      ?? '')),
            'description' => trim((string) ($parsed['description'] ?? '')),
            'quantity'    => $quantity > 0 ? $quantity : 1,
            'unit_price'  => (float) ($parsed['unit_price'] ?? 0),
        ];
    }
}
