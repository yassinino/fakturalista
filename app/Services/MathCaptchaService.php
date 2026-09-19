<?php

namespace App\Services;

/**
 * Self-hosted math CAPTCHA ("a + b = ?") for public forms.
 * The expected answer is kept server-side in the session only -
 * the frontend only ever sees the two operands.
 */
class MathCaptchaService
{
    private const SESSION_KEY = 'math_captcha_answer';

    /**
     * Generate a new challenge, store the expected answer in the session,
     * and return the two operands to render on the form.
     *
     * @return array{a:int, b:int}
     */
    public static function generate(): array
    {
        $a = random_int(1, 10);
        $b = random_int(1, 10);

        session([self::SESSION_KEY => $a + $b]);

        return ['a' => $a, 'b' => $b];
    }

    /**
     * Verify a submitted answer against the session's expected value.
     * The stored answer is consumed either way, so it can never be replayed.
     */
    public static function verify(?string $answer): bool
    {
        $expected = session()->pull(self::SESSION_KEY);

        return $expected !== null
            && $answer !== null
            && is_numeric($answer)
            && (int) $answer === (int) $expected;
    }
}
