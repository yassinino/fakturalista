<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * A Gemini call that did not produce usable text. `retryable` tells the
 * caller whether trying again later can help (rate limit, outage, network)
 * or not (missing/invalid key, bad request, blocked prompt). Messages never
 * contain the API key or the response body.
 */
class GeminiException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
