<?php

namespace App\Services\BlogAutomation;

use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Deterministic quality gate for a generated article - the model never grades
 * its own work here. Errors block automatic publication (the article is
 * saved as a draft with the reasons); warnings are shown to the reviewer.
 *
 * Also decides whether the article makes regulatory claims and whether those
 * are backed by an official source (grounding result + cited official link).
 */
class ArticleValidator
{
    /**
     * @param array{title:string, meta_title:string, meta_description:string, slug:string, excerpt:string, primary_keyword:string, secondary_keywords:array, body:string} $article
     *        body = sanitized HTML
     * @param array{country:string, regulatory:bool} $topic
     * @param array<int, array{href:string, text:string, kept:bool}> $links  links found by ArticleHtml::sanitize
     * @param array<int, array{uri:string, title:string}> $groundingSources
     */
    public function validate(array $article, array $topic, array $links, array $groundingSources = [], ?int $ignorePostId = null): ValidationReport
    {
        $q = config('blog_automation.quality');
        $report = new ValidationReport();
        $body = (string) ($article['body'] ?? '');
        $text = TextAnalyzer::plainText($body);
        $country = $topic['country'];

        // ── Required fields ─────────────────────────────────────────────
        foreach (['title' => 'Titre', 'meta_title' => 'Titre SEO', 'meta_description' => 'Méta description', 'excerpt' => 'Extrait', 'primary_keyword' => 'Mot-clé principal', 'body' => 'Contenu'] as $field => $label) {
            if (trim((string) ($article[$field] ?? '')) === '') {
                $report->error("{$label} manquant.");
            }
        }
        if ($body === '') {
            return $report;
        }

        // ── Length & structure ──────────────────────────────────────────
        $words = TextAnalyzer::wordCount($body);
        $report->wordCount = $words;
        if ($words < $q['min_words']) {
            $report->error("Contenu trop court ({$words} mots, minimum {$q['min_words']}).");
        } elseif ($words < $q['target_min_words']) {
            $report->warning("Contenu un peu court ({$words} mots).");
        } elseif ($words > $q['max_words']) {
            $report->error("Contenu trop long ({$words} mots, maximum {$q['max_words']}).");
        }

        $headings = ArticleHtml::headings($body);
        $h2 = count(array_filter($headings, fn ($h) => $h['level'] === 2));
        if ($h2 < $q['min_h2']) {
            $report->error("Structure insuffisante : {$h2} intertitre(s) H2, minimum {$q['min_h2']}.");
        }
        if ($headings !== [] && $headings[0]['level'] !== 2) {
            $report->error('Hiérarchie des titres incorrecte : un H3 précède le premier H2.');
        }
        foreach ($headings as $h) {
            if ($h['text'] === '') {
                $report->error('Intertitre vide.');
                break;
            }
        }
        $headingTexts = array_map(fn ($h) => mb_strtolower($h['text']), $headings);
        if (count($headingTexts) !== count(array_unique($headingTexts))) {
            $report->warning('Intertitres en double.');
        }

        // ── SEO metadata ────────────────────────────────────────────────
        $this->length($report, 'Titre SEO', (string) $article['meta_title'], $q['meta_title_hard'], $q['meta_title_soft']);
        $this->length($report, 'Méta description', (string) $article['meta_description'], $q['meta_description_hard'], $q['meta_description_soft']);
        if (mb_strlen((string) $article['title']) > 110) {
            $report->error('Titre trop long (plus de 110 caractères).');
        }
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string) $article['slug']) || strlen((string) $article['slug']) > 90) {
            $report->error('Slug invalide.');
        }
        if (mb_strlen((string) ($article['excerpt'] ?? '')) > 500) {
            $report->error('Extrait trop long (plus de 500 caractères).');
        }
        $keyword = mb_strtolower(trim((string) $article['primary_keyword']));
        if ($keyword !== '' && ! str_contains(Str::ascii(mb_strtolower($article['title'] . ' ' . $article['meta_title'])), Str::ascii(explode(' ', $keyword)[0]))) {
            $report->warning('Le mot-clé principal n\'apparaît pas dans le titre.');
        }

        // ── Uniqueness vs existing articles ─────────────────────────────
        $maxSimilarity = (float) $q['max_similarity'];
        Post::query()
            ->when($ignorePostId, fn ($query) => $query->whereKeyNot($ignorePostId))
            ->select(['id', 'title', 'slug', 'meta_title', 'meta_description', 'body'])
            ->orderBy('id')
            ->chunk(50, function ($posts) use ($article, $text, $maxSimilarity, $report) {
                foreach ($posts as $post) {
                    if ($post->slug === $article['slug']) {
                        $report->error("Slug déjà utilisé par « {$post->title} ».");
                    }
                    if ($this->same($post->title, $article['title']) || $this->same($post->getRawOriginal('meta_title'), $article['meta_title'])) {
                        $report->error("Titre identique à « {$post->title} ».");
                    }
                    if ($this->same($post->getRawOriginal('meta_description'), $article['meta_description'])) {
                        $report->error("Méta description identique à « {$post->title} ».");
                    }
                    $similarity = TextAnalyzer::similarity($text, TextAnalyzer::plainText((string) $post->body));
                    if ($similarity >= $maxSimilarity) {
                        $report->error(sprintf('Contenu trop proche de « %s » (similarité %d %%).', $post->title, round($similarity * 100)));
                    }
                }
            });

        // ── Language ────────────────────────────────────────────────────
        $ratio = TextAnalyzer::frenchRatio($text);
        if ($ratio < $q['min_french_ratio']) {
            $report->error(sprintf('Le texte ne semble pas rédigé en français (indice %.2f).', $ratio));
        }
        if (preg_match_all('/[\x{0600}-\x{06FF}]/u', $text) > 20) {
            $report->error('Le texte contient des passages en arabe.');
        }

        // ── Repetition, stuffing, promotion ─────────────────────────────
        $paragraphs = array_map(fn ($p) => mb_strtolower(preg_replace('/\s+/u', ' ', $p)), ArticleHtml::paragraphs($body));
        $long = array_filter($paragraphs, fn ($p) => mb_strlen($p) > 40);
        if (count($long) !== count(array_unique($long))) {
            $report->error('Paragraphes répétés.');
        }

        if ($keyword !== '' && $words > 0) {
            $occurrences = substr_count(Str::ascii(mb_strtolower($text)), Str::ascii($keyword));
            $density = $occurrences * count(TextAnalyzer::words($keyword)) / $words;
            if ($density > $q['max_keyword_density']) {
                $report->error(sprintf('Bourrage de mots-clés : « %s » représente %.1f %% du texte.', $keyword, $density * 100));
            }
        }

        $brand = substr_count(mb_strtolower($text), 'fakturalista');
        if ($brand > $q['max_brand_mentions']) {
            $report->error("Ton trop promotionnel : Fakturalista cité {$brand} fois.");
        } elseif ($brand === 0) {
            $report->warning('Aucune mention de Fakturalista (pas d\'appel à l\'action).');
        }

        // ── Placeholders, formatting, forbidden content ─────────────────
        foreach (config('blog_automation.placeholder_patterns') as $pattern) {
            if (preg_match($pattern, $text) || preg_match($pattern, (string) $article['title'] . ' ' . $article['meta_description'])) {
                $report->error('Texte provisoire ou mise en forme cassée détectée (' . trim($pattern, '/imu') . ').');
                break;
            }
        }
        if (preg_match('/&lt;\/?[a-z]+|<\/?[a-z]+/i', $text)) {
            $report->error('Balises HTML cassées dans le texte.');
        }

        $sentences = preg_split('/(?<=[.!?])\s+|\n+/u', $text);
        foreach (config('blog_automation.forbidden_claims') as $pattern => $label) {
            foreach ($sentences as $sentence) {
                if (preg_match($pattern, $sentence) && ! $this->negated($sentence)) {
                    $report->error("{$label} : « " . Str::limit(trim($sentence), 140) . ' »');
                    break;
                }
            }
        }
        foreach (config('blog_automation.forbidden_patterns') as $pattern => $label) {
            if (preg_match($pattern, $text, $m)) {
                $report->error("{$label} : « {$m[0]} »");
            }
        }

        // ── Market relevance ────────────────────────────────────────────
        $marketPattern = $country === 'ma' ? '/\b(maroc|marocain\w*|dirhams?|DH|MAD)\b/iu' : '/\b(France|français\w*|euros?)\b|€/iu';
        if (! preg_match($marketPattern, $text)) {
            $report->error('Le texte ne mentionne pas son marché cible (' . config("blog_automation.country_labels.{$country}") . ').');
        }

        // ── Links ───────────────────────────────────────────────────────
        $internal = 0;
        $external = [];
        foreach ($links as $link) {
            $isInternal = str_starts_with($link['href'], '/') || str_contains($link['href'], parse_url((string) config('seo.canonical_url'), PHP_URL_HOST));
            if (! $link['kept']) {
                $report->warning(($isInternal ? 'Lien interne invalide retiré : ' : 'Lien externe non fiable ou injoignable retiré : ') . Str::limit($link['href'], 120));
                continue;
            }
            $isInternal ? $internal++ : $external[] = $link['href'];
        }
        if ($internal === 0) {
            $report->warning('Aucun lien interne vers Fakturalista.');
        }
        if (count($external) > $q['max_external_links']) {
            $report->warning('Beaucoup de liens externes (' . count($external) . ').');
        }

        // ── Regulatory claims & sources ─────────────────────────────────
        $report->regulatory = (bool) $topic['regulatory'];
        foreach (config('blog_automation.regulatory_patterns') as $pattern) {
            if (preg_match($pattern, $text)) {
                $report->regulatory = true;
                break;
            }
        }

        $official = config("blog_automation.sources.official.{$country}", []);
        $report->officialCitations = array_values(array_filter($external, fn ($url) => SourcePolicy::hostMatches(parse_url($url, PHP_URL_HOST), $official)));
        $groundedOfficial = array_filter($groundingSources, fn ($s) => SourcePolicy::hostMatches(parse_url($s['uri'], PHP_URL_HOST), $official)
            || SourcePolicy::hostMatches(strtolower(trim($s['title'])), $official));
        $report->sourcesVerified = $report->officialCitations !== [] && $groundedOfficial !== [];

        return $report;
    }

    private function length(ValidationReport $report, string $label, string $value, array $hard, array $soft): void
    {
        $len = mb_strlen($value);
        if ($len === 0) {
            return;
        }
        if ($len < $hard[0] || $len > $hard[1]) {
            $report->error("{$label} : {$len} caractères (attendu {$hard[0]}-{$hard[1]}).");
        } elseif ($len < $soft[0] || $len > $soft[1]) {
            $report->warning("{$label} : {$len} caractères (idéal {$soft[0]}-{$soft[1]}).");
        }
    }

    private function same(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && trim($a) !== ''
            && mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    /** "Fakturalista n'est pas certifié", "ni agréé", "ne transmet pas"... */
    private function negated(string $sentence): bool
    {
        return (bool) preg_match('/\b(n[\'’]est pas|n[\'’]est ni|ne \w+ (pas|ni|aucun)|ni\s|pas encore|aucun\w*|non\s)\b/iu', $sentence);
    }
}
