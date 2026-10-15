<?php

namespace Tests\Feature\BlogAutomation;

use App\Models\Post;
use App\Services\BlogAutomation\ArticleHtml;
use App\Services\BlogAutomation\ArticleValidator;
use App\Services\BlogAutomation\SourcePolicy;

/** The deterministic quality gate and the HTML sanitizer. */
class ArticleValidationTest extends BlogAutomationTestCase
{
    private const TOPIC = ['country' => 'ma', 'regulatory' => false];

    private function validate(array $overrides = [], array $topic = self::TOPIC, array $grounding = [])
    {
        $raw = $this->article($overrides);
        $sanitized = ArticleHtml::sanitize($raw['body_html'], new SourcePolicy(false));
        $article = $raw;
        $article['body'] = $sanitized['html'];

        return (new ArticleValidator())->validate($article, $topic, $sanitized['links'], $grounding);
    }

    private function withSentence(string $sentence): array
    {
        return ['body_html' => $this->article()['body_html'] . '<p>' . $sentence . '</p>'];
    }

    public function test_a_good_article_passes(): void
    {
        $report = $this->validate();

        $this->assertSame([], $report->errors, implode("\n", $report->errors));
        $this->assertGreaterThan(900, $report->wordCount);
        $this->assertFalse($report->regulatory);
    }

    public function test_short_content_fails(): void
    {
        $report = $this->validate(['body_html' => '<h2>A</h2><p>Un texte court sur la facture au Maroc.</p><h2>B</h2><p>Fakturalista.</p><h2>C</h2><p>Fin.</p>']);

        $this->assertStringContainsString('trop court', implode(' ', $report->errors));
    }

    public function test_heading_structure_is_checked(): void
    {
        $body = str_replace(['<h2>', '</h2>'], ['<h3>', '</h3>'], $this->article()['body_html']);
        $errors = implode(' ', $this->validate(['body_html' => $body])->errors);

        $this->assertStringContainsString('intertitre(s) H2', $errors);
        $this->assertStringContainsString('H3 précède', $errors);
    }

    public function test_seo_metadata_lengths_are_checked(): void
    {
        $errors = implode(' ', $this->validate(['meta_title' => 'Court', 'meta_description' => 'Trop court aussi.', 'slug' => 'Pas Un Slug'])->errors);

        $this->assertStringContainsString('Titre SEO', $errors);
        $this->assertStringContainsString('Méta description', $errors);
        $this->assertStringContainsString('Slug invalide', $errors);
    }

    public function test_duplicate_metadata_and_near_duplicate_content_fail(): void
    {
        $article = $this->article();
        Post::create([
            'title' => 'Autre titre', 'slug' => 'existing-' . uniqid(), 'body' => $article['body_html'], 'status' => 'published', 'published_at' => now(),
            'meta_title' => $article['meta_title'], 'meta_description' => $article['meta_description'],
        ]);

        $errors = implode(' ', $this->validate()->errors);

        $this->assertStringContainsString('Titre identique', $errors);
        $this->assertStringContainsString('Méta description identique', $errors);
        $this->assertStringContainsString('Contenu trop proche', $errors);
    }

    public function test_placeholders_and_markdown_leftovers_fail(): void
    {
        foreach (['Contactez [Nom du client] pour valider.', 'Lorem ipsum dolor sit amet.', 'Un point **important** à retenir.', 'TODO compléter cette partie.'] as $sentence) {
            $this->assertStringContainsString('Texte provisoire', implode(' ', $this->validate($this->withSentence($sentence))->errors), $sentence);
        }
    }

    public function test_unsupported_product_claims_fail_but_honest_negations_pass(): void
    {
        $claims = [
            'Fakturalista est certifié par la DGI pour la facture électronique.',
            'Fakturalista est une plateforme agréée PDP pour la réforme française.',
            'Fakturalista gère les paiements partiels et les acomptes.',
            'Fakturalista transmet vos factures directement à la DGI.',
        ];
        foreach ($claims as $claim) {
            $this->assertFalse($this->validate($this->withSentence($claim))->passes(), $claim);
        }

        $honest = $this->validate($this->withSentence('Fakturalista n\'est pas certifié par la DGI et ne transmet pas vos factures à l\'administration.'));
        $this->assertTrue($honest->passes(), implode("\n", $honest->errors));
    }

    public function test_invented_statistics_and_testimonials_fail(): void
    {
        $this->assertStringContainsString('Statistique', implode(' ', $this->validate($this->withSentence('Aujourd\'hui, 63 % des PME marocaines paient en retard.'))->errors));
        $this->assertStringContainsString('Témoignage', implode(' ', $this->validate($this->withSentence('Voici le témoignage de Karim, gérant à Fès.'))->errors));
    }

    public function test_keyword_stuffing_and_over_promotion_fail(): void
    {
        $stuffed = str_repeat('<p>suivi encaissements clients et suivi encaissements clients.</p>', 25);
        $this->assertStringContainsString('Bourrage', implode(' ', $this->validate(['body_html' => $this->article()['body_html'] . $stuffed])->errors));

        $promo = str_repeat('<p>Fakturalista ' . uniqid() . ' pour votre activité.</p>', 8);
        $this->assertStringContainsString('promotionnel', implode(' ', $this->validate(['body_html' => $this->article()['body_html'] . $promo])->errors));
    }

    public function test_non_french_text_fails(): void
    {
        $english = str_repeat('<h2>Section</h2><p>This is an English paragraph about invoices and payments for small companies in Morocco, which should be rejected by the language check because it is not French at all.</p>', 30);

        $this->assertStringContainsString('français', implode(' ', $this->validate(['body_html' => $english])->errors));
    }

    public function test_regulatory_wording_needs_a_cited_and_consulted_official_source(): void
    {
        $legal = $this->withSentence('Selon l\'article 145 du CGI, certaines mentions sont obligatoires sur la facture.');

        $unverified = $this->validate($legal);
        $this->assertTrue($unverified->regulatory);
        $this->assertFalse($unverified->sourcesVerified);

        $cited = ['body_html' => $legal['body_html'] . '<p>Source : <a href="https://www.tax.gov.ma/wps/portal/DGI">portail de la DGI</a>.</p>'];
        $this->assertFalse($this->validate($cited)->sourcesVerified, 'A citation without grounding is not verification.');

        $verified = $this->validate($cited, self::TOPIC, [['uri' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/abc', 'title' => 'tax.gov.ma']]);
        $this->assertTrue($verified->sourcesVerified);
        $this->assertSame(['https://www.tax.gov.ma/wps/portal/DGI'], $verified->officialCitations);
    }

    public function test_a_regulatory_topic_is_regulatory_even_without_legal_wording(): void
    {
        $this->assertTrue($this->validate([], ['country' => 'ma', 'regulatory' => true])->regulatory);
    }

    public function test_sanitizer_strips_unsafe_html_and_untrusted_links(): void
    {
        $html = '<h1>Titre</h1><p onclick="x()" style="color:red">Texte <script>alert(1)</script><a href="javascript:alert(1)">js</a> '
            . '<a href="https://spam.example.com/x">spam</a> <a href="/inexistant">interne cassé</a> <a href="/pricing">tarifs</a> '
            . '<a href="https://fakturalista.com/register?x=1">essai</a> <a href="https://www.impots.gouv.fr/professionnel">impots</a></p>'
            . '<iframe src="https://evil"></iframe><img src=x onerror=alert(1)><h4>Sous</h4>';

        $result = ArticleHtml::sanitize($html, new SourcePolicy(false));
        $out = $result['html'];

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('style=', $out);
        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('spam.example.com', $out);
        $this->assertStringNotContainsString('href="/inexistant"', $out);
        $this->assertStringContainsString('<h2>Titre</h2>', $out);
        $this->assertStringContainsString('<h3>Sous</h3>', $out);
        $this->assertStringContainsString('<a href="/pricing">tarifs</a>', $out);
        $this->assertStringContainsString('<a href="/register">essai</a>', $out);
        $this->assertStringContainsString('href="https://www.impots.gouv.fr/professionnel" rel="noopener" target="_blank"', $out);
        $this->assertStringContainsString('spam', $out, 'Link text is kept when the link is removed.');
        $this->assertCount(2, array_filter($result['links'], fn ($l) => ! $l['kept'] && $l['href'] !== 'javascript:alert(1)'));
    }

    public function test_internal_links_to_published_posts_only(): void
    {
        $published = Post::create(['title' => 'P', 'slug' => 'pub-' . uniqid(), 'body' => 'x', 'status' => 'published', 'published_at' => now()]);
        $draft = Post::create(['title' => 'D', 'slug' => 'draft-' . uniqid(), 'body' => 'x', 'status' => 'draft']);
        $policy = new SourcePolicy(false);

        $this->assertSame(['href' => '/blog/' . $published->slug], $policy('/blog/' . $published->slug));
        $this->assertNull($policy('/blog/' . $draft->slug));
    }
}
