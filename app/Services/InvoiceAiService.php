<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class InvoiceAiService
{
    private const API_BASE   = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const MAX_TOKENS = 800;
    private const TIMEOUT    = 30;

    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
        $this->model  = config('services.gemini.model', 'gemini-2.0-flash');
    }

    /**
     * Parse a free-text invoice description (fr/ar/darija/es/en) into
     * structured fields, ready to populate the invoice creation form.
     * Never invents data - unmentioned fields come back null/empty.
     *
     * @return array{
     *     client_name: string,
     *     items: array<int, array{description:string, quantity:float, unit_price:float}>,
     *     tax_rate: ?float,
     *     payment_amount: ?float,
     *     payment_method: ?string,
     *     currency: string,
     * }
     * @throws \RuntimeException
     */
    public function parseInvoice(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $url = self::API_BASE . $this->model . ':generateContent?key=' . $this->apiKey;

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['content-type' => 'application/json'])
                ->post($url, $this->buildPayload($text));
        } catch (ConnectionException $e) {
            Log::error('[InvoiceAiService] Connection error', ['message' => $e->getMessage()]);
            throw new \RuntimeException('Cannot reach the Gemini API. Check your internet connection.');
        }

        if ($response->status() === 429) {
            throw new \RuntimeException('Gemini API rate limit reached. Wait a moment and try again.');
        }

        if ($response->failed()) {
            $status = $response->status();
            $reason = $response->json('error.details.0.reason');
            Log::error('[InvoiceAiService] API failure', ['status' => $status, 'reason' => $reason, 'body' => $response->body()]);

            $message = match (true) {
                $reason === 'API_KEY_SERVICE_BLOCKED' =>
                    'GEMINI_API_KEY is valid but the Generative Language API is blocked for it - '
                    . 'in Google Cloud Console > APIs & Services > Credentials, edit the key\'s '
                    . 'API restrictions to allow it (or generate a fresh key at aistudio.google.com/apikey).',
                $reason === 'API_KEY_INVALID'  => 'GEMINI_API_KEY is invalid - check your .env.',
                $status === 400                => 'Invalid request sent to Gemini.',
                in_array($status, [401, 403])  => 'Invalid or unauthorized GEMINI_API_KEY - check your .env.',
                $status >= 500                 => 'Gemini service error. Try again later.',
                default                        => "Gemini API returned HTTP {$status}.",
            };

            throw new \RuntimeException($message);
        }

        $body    = $response->json();
        $rawText = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($rawText === null) {
            // A prompt blocked by Gemini's safety filters has no candidates
            // at all - surface a clear message instead of a generic one.
            $blockReason = $body['promptFeedback']['blockReason'] ?? null;
            Log::error('[InvoiceAiService] Unexpected response shape', ['body' => $body]);
            throw new \RuntimeException(
                $blockReason
                    ? 'Gemini could not process this text (reason: ' . $blockReason . ').'
                    : 'Gemini returned an unexpected response structure.'
            );
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
You are a precise invoice data extractor for a billing application used in Morocco.

The user's text may be written in French, Arabic, Moroccan Darija (Arabic script or Latin transliteration), Spanish, or English - possibly mixed. Understand it in whichever language(s) it is written, and always return the JSON below with the same structure regardless of input language.

Extract invoice information and return ONLY a valid JSON object with exactly these fields:
{
  "client_name": string,            // who is being invoiced (not the sender). "" if not mentioned.
  "items": [                        // one entry per distinct product/service/line mentioned
    { "description": string, "quantity": number, "unit_price": number }
  ],
  "tax_rate": number|null,          // VAT/tax rate as a plain percentage number (e.g. 20 for "VAT 20%" or "TVA 20%"). null if not mentioned.
  "payment_amount": number|null,    // amount already paid, if the text mentions a payment. null if not mentioned.
  "payment_method": string|null,    // e.g. "cash", "card", "bank transfer", "check". null if not mentioned.
  "currency": string                // ISO-like currency the amounts are in (e.g. "MAD", "EUR", "USD"). Default "MAD" if not mentioned or if DH/dirham is used.
}

Critical rules:
- Return ONLY the JSON object. No explanation, no markdown, no code fences, no extra text.
- NEVER invent information. If something is not mentioned in the text, use "" for strings, null for optional numbers, and an empty items array if no line item is identifiable.
- "items" must contain one object per distinct chargeable item/service mentioned (e.g. "travel 200, repair 650" -> two items). If only one item is mentioned, return an array with exactly one object.
- "quantity" must be a number, default 1 if not mentioned for that item.
- "unit_price" must be a plain number with no currency symbols, commas, or words (e.g. 250 not "250 DH" not "deux cent cinquante").
- Convert amounts written as words to numbers, in any of the supported languages (e.g. "five hundred" -> 500, "deux cents" -> 200, "خمسمية" -> 500).
- "tax_rate" and "payment_amount" are plain numbers with no "%" or currency symbol.
- Do not compute or invent totals, subtotals, or tax amounts - only extract what is explicitly stated.
PROMPT;

        return [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userText]]],
            ],
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'generationConfig' => [
                'temperature'      => 0,
                'maxOutputTokens'  => self::MAX_TOKENS,
                'responseMimeType' => 'application/json',
            ],
        ];
    }

    /**
     * Parse the model's text as JSON, defensively stripping accidental
     * markdown code fences before decoding (responseMimeType normally
     * prevents these, but we stay defensive across model versions).
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
        $items = [];
        foreach ((array) ($parsed['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $quantity = (float) ($item['quantity'] ?? 1);
            $items[] = [
                'description' => $description,
                'quantity'    => $quantity > 0 ? $quantity : 1,
                'unit_price'  => (float) ($item['unit_price'] ?? 0),
            ];
        }

        $taxRate = $parsed['tax_rate'] ?? null;
        $paymentAmount = $parsed['payment_amount'] ?? null;
        $paymentMethod = trim((string) ($parsed['payment_method'] ?? ''));

        return [
            'client_name'    => trim((string) ($parsed['client_name'] ?? '')),
            'items'          => $items,
            'tax_rate'       => is_numeric($taxRate) ? (float) $taxRate : null,
            'payment_amount' => is_numeric($paymentAmount) ? (float) $paymentAmount : null,
            'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
            'currency'       => strtoupper(trim((string) ($parsed['currency'] ?? ''))) ?: 'MAD',
        ];
    }
}
