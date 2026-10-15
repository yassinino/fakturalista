<?php

namespace App\Services\BlogAutomation;

use App\Models\BlogAutomationSetting;
use App\Models\BlogGeneration;
use App\Models\Post;
use Illuminate\Support\Carbon;

/**
 * Picks the next article topic - deterministically, from the curated
 * catalogue in config/blog_automation.php:
 *
 *  1. Country: the one furthest below its long-run share (70 % Maroc,
 *     30 % France) over the recent generation history.
 *  2. Candidates of that country, minus topics already used (topic history),
 *     topics excluded in the backoffice, and topics whose keyword overlaps an
 *     existing article (keyword cannibalisation).
 *  3. Best score: editorial business value, seasonal relevance, and category
 *     diversity (categories not covered recently come first).
 *
 * When a country's catalogue is exhausted, an optional "ideator" (Gemini)
 * may propose a new topic, which goes through the same duplicate checks.
 * No search volume or ranking data is used or claimed.
 */
class TopicSelector
{
    private const HISTORY_WINDOW = 30;

    /** @var (callable(string $country, array $context): ?array)|null */
    private $ideator;

    public function __construct(?callable $ideator = null)
    {
        $this->ideator = $ideator;
    }

    /**
     * @return array{key:string, country:string, category:string, title:string, keyword:string, intent:string, regulatory:bool, source:string}|null
     */
    public function select(BlogAutomationSetting $settings, ?Carbon $today = null): ?array
    {
        $today ??= now(config('blog_automation.timezone'));

        $history = BlogGeneration::usedTopics()
            ->orderByDesc('id')
            ->get(['topic_key', 'country', 'topic_category', 'primary_keyword', 'topic_title']);
        $existing = $this->existingTopics($history);
        $exclusions = $settings->exclusions();

        foreach ($this->countryOrder($settings->countries(), $history) as $country) {
            $topic = $this->bestCatalogueTopic($country, $history, $existing, $exclusions, (int) $today->month);

            if (! $topic && $this->ideator) {
                $proposal = ($this->ideator)($country, [
                    'existing_titles' => array_slice(array_column($existing, 'label'), 0, 80),
                    'categories'      => config("blog_automation.categories.{$country}", []),
                    'exclusions'      => $exclusions,
                ]);
                $topic = $proposal ? $this->acceptProposal($country, $proposal, $existing, $exclusions) : null;
            }

            if ($topic) {
                return $topic;
            }
        }

        return null;
    }

    /** Countries sorted by how far each is below its target share. */
    public function countryOrder(array $countries, $history): array
    {
        $weights = array_intersect_key(config('blog_automation.country_weights'), array_flip($countries));
        $sum = array_sum($weights) ?: 1;
        $recent = $history->take(self::HISTORY_WINDOW);
        $total = $recent->count();

        $deficits = [];
        foreach ($weights as $country => $weight) {
            $actual = $total ? $recent->where('country', $country)->count() / $total : 0;
            $deficits[$country] = [$weight / $sum - $actual, $weight];
        }

        uksort($deficits, function ($a, $b) use ($deficits) {
            return [$deficits[$b][0], $deficits[$b][1]] <=> [$deficits[$a][0], $deficits[$a][1]];
        });

        return array_keys($deficits);
    }

    private function bestCatalogueTopic(string $country, $history, array $existing, array $exclusions, int $month): ?array
    {
        $used = $history->pluck('topic_key')->filter()->flip();
        $countryHistory = $history->where('country', $country);
        $recentCategories = $countryHistory->take(5)->pluck('topic_category')->all();
        $categoryUses = $countryHistory->countBy('topic_category')->all();

        $best = null;
        $bestScore = PHP_INT_MIN;

        foreach (config('blog_automation.topics', []) as $topic) {
            if ($topic['country'] !== $country || isset($used[$topic['key']])) {
                continue;
            }
            if ($this->isExcluded($topic, $exclusions) || $this->duplicateOf($topic['keyword'], $existing)) {
                continue;
            }

            $score = (int) ($topic['value'] ?? 1) * 10
                + (in_array($month, $topic['months'] ?? [], true) ? 15 : 0)
                + (in_array($topic['category'], $recentCategories, true) ? 0 : 8)
                - (int) ($categoryUses[$topic['category']] ?? 0);

            if ($score > $bestScore) {
                $best = $topic;
                $bestScore = $score;
            }
        }

        return $best ? $this->normalize($best, 'catalogue') : null;
    }

    private function acceptProposal(string $country, array $proposal, array $existing, array $exclusions): ?array
    {
        $categories = config("blog_automation.categories.{$country}", []);
        $title = trim((string) ($proposal['title'] ?? ''));
        $keyword = trim((string) ($proposal['primary_keyword'] ?? $proposal['keyword'] ?? ''));
        $category = (string) ($proposal['category'] ?? '');

        if ($title === '' || $keyword === '' || mb_strlen($keyword) > 80 || ! isset($categories[$category])) {
            return null;
        }

        $topic = [
            'key'        => 'ai-' . $country . '-' . \Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($keyword, 60, '')),
            'country'    => $country,
            'category'   => $category,
            'title'      => mb_substr($title, 0, 190),
            'keyword'    => $keyword,
            'intent'     => in_array($proposal['search_intent'] ?? null, ['informational', 'commercial', 'transactional'], true) ? $proposal['search_intent'] : 'informational',
            'regulatory' => (bool) ($proposal['regulatory'] ?? true),
        ];

        if (BlogGeneration::usedTopics()->where('topic_key', $topic['key'])->exists()
            || $this->isExcluded($topic, $exclusions)
            || $this->duplicateOf($topic['keyword'], $existing)
            || $this->duplicateOf($topic['title'], $existing)) {
            return null;
        }

        return $this->normalize($topic, 'ai');
    }

    private function normalize(array $topic, string $source): array
    {
        return [
            'key'        => $topic['key'],
            'country'    => $topic['country'],
            'category'   => $topic['category'],
            'title'      => $topic['title'],
            'keyword'    => $topic['keyword'],
            'intent'     => $topic['intent'] ?? 'informational',
            'regulatory' => (bool) ($topic['regulatory'] ?? false),
            'source'     => $source,
        ];
    }

    private function isExcluded(array $topic, array $exclusions): bool
    {
        $haystack = mb_strtolower($topic['title'] . ' ' . $topic['keyword'] . ' ' . $topic['category']
            . ' ' . config("blog_automation.categories.{$topic['country']}.{$topic['category']}", ''));

        foreach ($exclusions as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Same topic as an existing article or generation: the keyword's content
     * words are all in its label, or the two overlap strongly (Jaccard).
     * Country names are ignored - every article mentions its market.
     */
    public function duplicateOf(string $keyword, array $existing): ?string
    {
        $tokens = $this->topicTokens($keyword);
        if ($tokens === []) {
            return null;
        }
        $threshold = (float) config('blog_automation.quality.topic_similarity');

        foreach ($existing as $item) {
            foreach ($item['tokens'] as $other) {
                if ($other === []) {
                    continue;
                }
                if (array_diff($tokens, $other) === [] || TextAnalyzer::jaccard($tokens, $other) >= $threshold) {
                    return $item['label'];
                }
            }
        }

        return null;
    }

    /** @return array<int, array{label:string, tokens: array<int, string[]>}> */
    public function existingTopics($history = null): array
    {
        $history ??= BlogGeneration::usedTopics()->get(['primary_keyword', 'topic_title']);
        $out = [];

        Post::query()
            ->orderByDesc('id')
            ->get(['title', 'meta_title', 'primary_keyword'])
            ->each(function (Post $post) use (&$out) {
                $out[] = ['label' => $post->title, 'tokens' => array_map(
                    fn ($text) => $this->topicTokens((string) $text),
                    array_filter([$post->title, $post->getRawOriginal('meta_title'), $post->primary_keyword])
                )];
            });

        foreach ($history as $row) {
            $out[] = ['label' => (string) ($row->topic_title ?: $row->primary_keyword), 'tokens' => array_map(
                fn ($text) => $this->topicTokens((string) $text),
                array_filter([$row->primary_keyword, $row->topic_title])
            )];
        }

        return $out;
    }

    private function topicTokens(string $text): array
    {
        return array_values(array_diff(TextAnalyzer::keywordTokens($text), ['maroc', 'marocain', 'marocaine', 'marocaine', 'france', 'francai', 'francaise']));
    }
}
