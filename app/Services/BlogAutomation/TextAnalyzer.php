<?php

namespace App\Services\BlogAutomation;

use Illuminate\Support\Str;

/**
 * Small, deterministic text measures used by the topic selector and the
 * validator (no AI involved): word counts, French-language ratio, keyword
 * token overlap and 5-word shingle similarity between articles.
 */
class TextAnalyzer
{
    /** French function words: their share tells French prose apart. */
    private const FRENCH_WORDS = [
        'le', 'la', 'les', 'de', 'des', 'du', 'un', 'une', 'et', 'en', 'est', 'que', 'qui', 'pour', 'dans',
        'sur', 'par', 'au', 'aux', 'avec', 'ce', 'cette', 'ces', 'vous', 'votre', 'vos', 'pas', 'ne', 'plus',
        'son', 'sa', 'ses', 'il', 'elle', 'ou', 'mais', 'si', 'leur', 'leurs', 'sont', 'être', 'avoir', 'a',
    ];

    /** Words ignored when comparing topics/keywords. */
    private const STOPWORDS = [
        'le', 'la', 'les', 'de', 'des', 'du', 'un', 'une', 'et', 'en', 'au', 'aux', 'a', 'pour', 'dans', 'sur',
        'par', 'avec', 'ou', 'comment', 'quoi', 'quel', 'quelle', 'quels', 'quelles', 'faire', 'son', 'sa', 'ses',
        'votre', 'vos', 'ce', 'cette', 'ces', 'qui', 'que', 'est', 'il', 'elle', 'guide', 'conseils', 'savoir',
        'tout', 'tous', 'bien', 'ne', 'pas', 'd', 'l', 's', 'qu', 'n', 'c', 'j', 'm', 't',
    ];

    public static function plainText(string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/h[1-6]|\/li|\/tr|\/blockquote)[^>]*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $text));
    }

    /** @return string[] lowercase word tokens (letters/digits, apostrophes split). */
    public static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $m);

        return $m[0];
    }

    public static function wordCount(string $html): int
    {
        return count(self::words(self::plainText($html)));
    }

    public static function frenchRatio(string $text): float
    {
        $words = self::words($text);
        if ($words === []) {
            return 0.0;
        }
        $french = array_flip(self::FRENCH_WORDS);
        $hits = 0;
        foreach ($words as $w) {
            $hits += isset($french[$w]) ? 1 : 0;
        }

        return $hits / count($words);
    }

    /** @return string[] normalised, de-duplicated content tokens (accents stripped, plural folded). */
    public static function keywordTokens(string $text): array
    {
        $stop = array_flip(self::STOPWORDS);
        $out = [];
        foreach (self::words(Str::ascii($text)) as $w) {
            if (isset($stop[$w]) || mb_strlen($w) < 2) {
                continue;
            }
            if (mb_strlen($w) > 3) {
                $w = preg_replace('/(s|x)$/', '', $w);
            }
            $out[$w] = true;
        }

        return array_keys($out);
    }

    /** Share of the smaller token set found in the other (0..1). */
    public static function overlap(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        return count(array_intersect($a, $b)) / min(count($a), count($b));
    }

    public static function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $union = count(array_unique(array_merge($a, $b)));

        return $union ? count(array_intersect($a, $b)) / $union : 0.0;
    }

    /** @return string[] hashed 5-word shingles (bounded memory for long texts). */
    public static function shingles(string $text, int $size = 5): array
    {
        $words = self::words(Str::ascii($text));
        $out = [];
        for ($i = 0, $n = count($words) - $size; $i <= $n; $i++) {
            $out[crc32(implode(' ', array_slice($words, $i, $size)))] = true;
        }

        return array_keys($out);
    }

    public static function similarity(string $textA, string $textB): float
    {
        return self::jaccard(self::shingles($textA), self::shingles($textB));
    }
}
