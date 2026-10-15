<?php

namespace Tests\Feature\BlogAutomation;

use App\Models\BlogGeneration;
use App\Models\Post;
use App\Services\BlogAutomation\TopicSelector;

/** Deterministic topic selection: country balance, history, exclusions, cannibalisation. */
class TopicSelectionTest extends BlogAutomationTestCase
{
    private function history(string $country, int $count): void
    {
        static $i = 0;
        for ($k = 0; $k < $count; $k++) {
            $i++;
            BlogGeneration::create([
                'run_date' => '2030-01-01', 'slot_key' => 'h-' . $i, 'trigger' => 'schedule',
                'status' => BlogGeneration::STATUS_PUBLISHED, 'country' => $country, 'topic_key' => "hist-{$country}-{$i}",
                'topic_category' => 'x', 'topic_title' => "Sujet historique {$i}", 'primary_keyword' => "motcle{$i} unique{$i}",
            ]);
        }
    }

    public function test_morocco_comes_first_on_an_empty_history(): void
    {
        $topic = (new TopicSelector())->select($this->settings());

        $this->assertSame('ma', $topic['country']);
        $this->assertSame('catalogue', $topic['source']);
    }

    public function test_france_is_picked_when_below_its_30_percent_share(): void
    {
        $this->history('ma', 8);
        $this->history('fr', 2);

        $this->assertSame('fr', (new TopicSelector())->select($this->settings())['country']);
    }

    public function test_morocco_is_picked_when_france_is_above_its_share(): void
    {
        $this->history('ma', 6);
        $this->history('fr', 4);

        $this->assertSame('ma', (new TopicSelector())->select($this->settings())['country']);
    }

    public function test_only_the_selected_target_countries_are_used(): void
    {
        $topic = (new TopicSelector())->select($this->settings(['target_countries' => ['fr']]));

        $this->assertSame('fr', $topic['country']);
    }

    public function test_a_topic_already_in_the_history_is_never_picked_again(): void
    {
        $selector = new TopicSelector();
        $first = $selector->select($this->settings());

        $this->pendingGeneration(['topic_key' => $first['key'], 'status' => BlogGeneration::STATUS_DRAFT, 'country' => 'ma', 'primary_keyword' => $first['keyword'], 'topic_title' => $first['title']]);

        $this->assertNotSame($first['key'], $selector->select($this->settings())['key']);
    }

    public function test_failed_generations_do_not_burn_their_topic(): void
    {
        $selector = new TopicSelector();
        $first = $selector->select($this->settings());
        $this->pendingGeneration(['topic_key' => $first['key'], 'status' => BlogGeneration::STATUS_FAILED, 'country' => 'ma']);

        $this->assertSame($first['key'], $selector->select($this->settings())['key']);
    }

    public function test_admin_exclusions_are_respected(): void
    {
        $settings = $this->settings(['excluded_topics' => "logiciel\nmodèle\nfacture"]);
        $topic = (new TopicSelector())->select($settings);

        $this->assertNotNull($topic);
        foreach (['logiciel', 'modèle', 'facture'] as $word) {
            $this->assertStringNotContainsString($word, mb_strtolower($topic['title'] . ' ' . $topic['keyword']));
        }
    }

    public function test_topics_overlapping_an_existing_article_are_detected_as_duplicates(): void
    {
        Post::create(['title' => 'Délai de validité d\'un devis : le guide', 'slug' => 'test-validite-devis-' . uniqid(), 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()]);
        $selector = new TopicSelector();
        $existing = $selector->existingTopics();

        $this->assertNotNull($selector->duplicateOf('durée validité devis', $existing));
        $this->assertNotNull($selector->duplicateOf('validité des devis Maroc', $existing), 'Country names must not make a topic look new.');
        $this->assertNull($selector->duplicateOf('lettre de relance facture impayée', $existing));
    }

    public function test_the_catalogue_topic_overlapping_an_article_is_skipped(): void
    {
        Post::create(['title' => 'Logiciel de facturation pour PME au Maroc', 'slug' => 'test-pme-' . uniqid(), 'body' => '<p>x</p>', 'status' => 'draft']);

        $topic = (new TopicSelector())->select($this->settings());

        $this->assertNotSame('ma-logiciel-facturation-pme-criteres', $topic['key']);
    }

    public function test_seasonal_topics_get_a_boost_in_their_months(): void
    {
        config(['blog_automation.topics' => [
            ['key' => 'plain', 'country' => 'ma', 'category' => 'a', 'title' => 'Sujet A neutre', 'keyword' => 'alpha bravo', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
            ['key' => 'seasonal', 'country' => 'ma', 'category' => 'b', 'title' => 'Sujet B saisonnier', 'keyword' => 'charlie delta', 'intent' => 'informational', 'regulatory' => false, 'value' => 1, 'months' => [3]],
        ]]);

        $this->assertSame('seasonal', (new TopicSelector())->select($this->settings())['key']);
    }

    public function test_exhausted_catalogue_falls_back_to_an_ai_proposal_checked_for_duplicates(): void
    {
        config(['blog_automation.topics' => [], 'blog_automation.country_weights' => ['ma' => 100]]);
        Post::create(['title' => 'Comment classer ses factures fournisseurs', 'slug' => 'test-classer-' . uniqid(), 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()]);

        $duplicate = new TopicSelector(fn () => ['title' => 'Classer ses factures fournisseurs', 'primary_keyword' => 'classer factures fournisseurs', 'category' => 'organisation', 'search_intent' => 'informational', 'regulatory' => false]);
        $this->assertNull($duplicate->select($this->settings()), 'A duplicate AI proposal must be refused.');

        $fresh = new TopicSelector(fn () => ['title' => 'Préparer un devis pour un chantier de rénovation', 'primary_keyword' => 'devis chantier rénovation', 'category' => 'btp', 'search_intent' => 'transactional']);
        $topic = $fresh->select($this->settings());
        $this->assertSame('ai', $topic['source']);
        $this->assertSame('btp', $topic['category']);
        $this->assertTrue($topic['regulatory'], 'Unknown regulatory status defaults to true (safer).');

        $badCategory = new TopicSelector(fn () => ['title' => 'Un sujet', 'primary_keyword' => 'sujet inédit', 'category' => 'inconnue']);
        $this->assertNull($badCategory->select($this->settings()));
    }
}
