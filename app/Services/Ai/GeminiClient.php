<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Generic Gemini generateContent client, reusing the application's Gemini
 * configuration (config/services.php 'gemini': GEMINI_API_KEY / GEMINI_MODEL)
 * like App\Services\InvoiceAiService.
 *
 * - The key travels in the x-goog-api-key header, never in the URL, so it
 *   cannot leak into logs or exception messages.
 * - Every call is bounded: timeout, max output tokens, a few retries on
 *   429 / 5xx / network errors (Retry-After honoured up to a cap).
 * - Logs carry status codes and reasons only, never prompts or bodies.
 */
class GeminiClient
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function model(): string
    {
        return (string) config('services.gemini.model', 'gemini-2.0-flash');
    }

    public function isConfigured(): bool
    {
        return trim((string) config('services.gemini.key')) !== '';
    }

    /**
     * @param array{
     *     temperature?: float,
     *     max_output_tokens?: int,
     *     json?: bool,
     *     grounding?: bool,
     *     timeout?: int,
     * } $options
     */
    public function generate(string $systemPrompt, string $userPrompt, array $options = []): GeminiResponse
    {
        if (! $this->isConfigured()) {
            throw new GeminiException('GEMINI_API_KEY n\'est pas configurée.');
        }

        $limits     = config('blog_automation.gemini');
        $grounding  = (bool) ($options['grounding'] ?? false);
        $generation = [
            'temperature'     => $options['temperature'] ?? 0.7,
            'maxOutputTokens' => min((int) ($options['max_output_tokens'] ?? $limits['max_output_tokens']), (int) $limits['max_output_tokens']),
        ];
        // JSON mode and the search tool cannot be combined on every model:
        // grounded calls ask for JSON in the prompt instead.
        if (($options['json'] ?? false) && ! $grounding) {
            $generation['responseMimeType'] = 'application/json';
        }

        $payload = [
            'contents'          => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'generationConfig'  => $generation,
        ];
        if ($grounding) {
            $payload['tools'] = [['google_search' => (object) []]];
        }

        $response = $this->send($payload, (int) ($options['timeout'] ?? $limits['timeout']));

        return $this->parse($response->json() ?? []);
    }

    private function send(array $payload, int $timeout): Response
    {
        $limits  = config('blog_automation.gemini');
        $retries = (int) $limits['max_retries'];
        $url     = self::API_BASE . $this->model() . ':generateContent';

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout((int) $limits['connect_timeout'])
                    ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                    ->acceptJson()
                    ->post($url, $payload);
            } catch (ConnectionException $e) {
                Log::warning('[GeminiClient] Connection error', ['attempt' => $attempt + 1]);
                if ($attempt < $retries) {
                    $this->pause($attempt, null);
                    continue;
                }
                throw new GeminiException('Impossible de joindre l\'API Gemini (réseau ou délai dépassé).', true);
            }

            if ($response->successful()) {
                return $response;
            }

            $status    = $response->status();
            $retryable = $status === 429 || $status >= 500;
            Log::warning('[GeminiClient] API failure', [
                'status'  => $status,
                'reason'  => $response->json('error.details.0.reason') ?? $response->json('error.status'),
                'attempt' => $attempt + 1,
            ]);

            if ($retryable && $attempt < $retries) {
                $this->pause($attempt, $response->header('Retry-After'));
                continue;
            }

            throw new GeminiException($this->describe($response), $retryable);
        }
    }

    private function pause(int $attempt, ?string $retryAfter): void
    {
        $limits = config('blog_automation.gemini');
        $ms     = is_numeric($retryAfter)
            ? min((int) $retryAfter, (int) $limits['max_retry_after_s']) * 1000
            : (int) $limits['retry_delay_ms'] * (2 ** $attempt);

        if ($ms > 0) {
            Sleep::for($ms)->milliseconds();
        }
    }

    private function describe(Response $response): string
    {
        $status = $response->status();
        $reason = $response->json('error.details.0.reason');

        return match (true) {
            $reason === 'API_KEY_SERVICE_BLOCKED' => 'La clé Gemini est bloquée pour l\'API Generative Language (Google Cloud Console).',
            $reason === 'API_KEY_INVALID'         => 'GEMINI_API_KEY est invalide.',
            $status === 429                       => 'Limite de requêtes Gemini atteinte (429).',
            $status === 400                       => 'Requête refusée par Gemini (400).',
            in_array($status, [401, 403], true)   => 'Clé Gemini non autorisée (' . $status . ').',
            $status === 404                       => 'Modèle Gemini introuvable (' . $this->model() . ').',
            $status >= 500                        => 'Service Gemini indisponible (' . $status . ').',
            default                               => 'Gemini a répondu HTTP ' . $status . '.',
        };
    }

    private function parse(array $body): GeminiResponse
    {
        $candidate = $body['candidates'][0] ?? null;

        if (! is_array($candidate)) {
            $block = $body['promptFeedback']['blockReason'] ?? null;
            throw new GeminiException($block
                ? 'Gemini a bloqué la requête (' . $block . ').'
                : 'Réponse Gemini sans contenu.');
        }

        // Thinking models may return "thought" parts: keep the answer only.
        $text = '';
        foreach ((array) ($candidate['content']['parts'] ?? []) as $part) {
            if (is_array($part) && empty($part['thought']) && isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        $finish = $candidate['finishReason'] ?? null;
        if ($finish === 'MAX_TOKENS') {
            throw new GeminiException('Réponse Gemini tronquée (limite de tokens atteinte).');
        }
        if (in_array($finish, ['SAFETY', 'RECITATION', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII'], true)) {
            throw new GeminiException('Réponse Gemini interrompue (' . $finish . ').');
        }
        if (trim($text) === '') {
            throw new GeminiException('Réponse Gemini vide.');
        }

        $sources = [];
        foreach ((array) ($candidate['groundingMetadata']['groundingChunks'] ?? []) as $chunk) {
            $web = $chunk['web'] ?? null;
            if (is_array($web) && ! empty($web['uri'])) {
                $sources[] = ['uri' => (string) $web['uri'], 'title' => (string) ($web['title'] ?? '')];
            }
        }

        $usage = $body['usageMetadata'] ?? [];
        $prompt = (int) ($usage['promptTokenCount'] ?? 0);
        // Thinking tokens are billed as output.
        $output = (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0);

        return new GeminiResponse(
            text: $text,
            model: (string) ($body['modelVersion'] ?? $this->model()),
            promptTokens: $prompt,
            outputTokens: $output,
            totalTokens: (int) ($usage['totalTokenCount'] ?? ($prompt + $output)),
            groundingSources: $sources,
            searchQueries: array_values(array_filter((array) ($candidate['groundingMetadata']['webSearchQueries'] ?? []), 'is_string')),
            finishReason: $finish,
        );
    }
}
