<?php

namespace App\Services\Ai;

/** Text + usage + grounding of one Gemini generateContent call. */
class GeminiResponse
{
    /**
     * @param array<int, array{uri:string, title:string}> $groundingSources
     * @param string[]                                    $searchQueries
     */
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly int $promptTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $totalTokens = 0,
        public readonly array $groundingSources = [],
        public readonly array $searchQueries = [],
        public readonly ?string $finishReason = null,
    ) {}

    /** Decodes the text as a JSON object, tolerating code fences and surrounding prose. */
    public function json(): ?array
    {
        $raw = trim($this->text);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Grounded answers can't use JSON mode: keep the outermost object.
        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
