<?php

namespace App\Services\Analytics;

/**
 * Bot exclusion and device type from the User-Agent header. Deliberately
 * simple and conservative: the tracker also only fires from a real browser
 * running JavaScript (most crawlers don't), so this is a second filter.
 */
class UserAgentClassifier
{
    private const BOT_PATTERN = '~bot|crawl|spider|slurp|scrap|fetch|preview|monitor|uptime|pingdom|statuscake|'
        . 'headless|phantom|puppeteer|playwright|selenium|lighthouse|pagespeed|gtmetrix|chrome-lighthouse|'
        . 'python|curl|wget|httpclient|okhttp|go-http|java/|axios|node-fetch|libwww|facebookexternalhit|'
        . 'whatsapp|telegram|discord|slack|embedly|bingpreview|ahrefs|semrush|mj12|dotbot|petalbot|yandex|'
        . 'baiduspider|applebot|duckduckbot|gptbot|claudebot|perplexitybot|ccbot|bytespider~i';

    public function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '' || strlen($userAgent) < 20) {
            return true;
        }

        return (bool) preg_match(self::BOT_PATTERN, $userAgent);
    }

    /** desktop | mobile | tablet */
    public function device(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        if (preg_match('~ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))~i', $ua)) {
            return 'tablet';
        }
        if (preg_match('~mobi|iphone|ipod|android|blackberry|opera mini|iemobile|windows phone~i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
