<?php

namespace Tests\Feature\BlogAutomation;

use App\Models\BlogAutomationSetting;
use App\Models\BlogGeneration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Shared setup for the AI blog automation tests. Everything runs inside a
 * transaction on the central connection (rolled back after each test), on a
 * fixed future day so real generation slots are never touched. Gemini is
 * always faked; link reachability checks are off.
 */
abstract class BlogAutomationTestCase extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['mysql'];

    protected const HOST = 'http://fakturalista.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(\Illuminate\Support\Carbon::parse('2031-03-10 09:30:00', 'Africa/Casablanca'));
        config([
            'services.gemini.key'                    => 'test-gemini-key',
            'services.gemini.model'                  => 'gemini-test-model',
            'blog_automation.check_links'            => false,
            'blog_automation.gemini.retry_delay_ms'  => 0,
            'blog_automation.gemini.price_input_per_million'  => null,
            'blog_automation.gemini.price_output_per_million' => null,
            'fakturalista.admin_email'               => 'admin@example.com',
        ]);
        Mail::fake();
        Sleep::fake();
        Http::preventStrayRequests();

        // Start from an empty history (rolled back afterwards).
        BlogGeneration::query()->delete();
        BlogAutomationSetting::query()->delete();
    }

    protected function settings(array $attributes = []): BlogAutomationSetting
    {
        $settings = BlogAutomationSetting::current();
        $settings->update($attributes);

        return $settings->fresh();
    }

    /** A pending generation with a fixed (non-regulatory) topic. */
    protected function pendingGeneration(array $attributes = []): BlogGeneration
    {
        return BlogGeneration::create($attributes + [
            'run_date'        => now('Africa/Casablanca')->toDateString(),
            'slot_key'        => now('Africa/Casablanca')->toDateString() . '#1',
            'trigger'         => 'manual',
            'status'          => BlogGeneration::STATUS_PENDING,
            'country'         => 'ma',
            'topic_key'       => 'test-topic-' . uniqid(),
            'topic_category'  => 'paiements',
            'topic_title'     => 'Suivre ses encaissements clients : méthode simple',
            'primary_keyword' => 'suivi encaissements clients',
            'search_intent'   => 'informational',
            'regulatory'      => false,
        ]);
    }

    /** A valid ~1 000-word French article about client payments in Morocco. */
    protected function article(array $overrides = []): array
    {
        $sentences = [
            'Pour une petite entreprise au Maroc, chaque facture envoyée est aussi une promesse de trésorerie qu\'il faut suivre avec méthode.',
            'Le plus simple est de noter la date d\'échéance dès l\'envoi, puis de vérifier chaque semaine ce qui a été encaissé.',
            'Un client qui paie en retard n\'est pas forcément de mauvaise foi : il a souvent oublié, ou il attend une pièce manquante.',
            'Un tableau de bord clair permet de voir en un coup d\'œil les montants en attente et les retards à traiter en priorité.',
            'Il est utile de garder une trace écrite des échanges avec le client, car elle facilite la discussion si le retard se prolonge.',
            'La régularité compte davantage que la fréquence : un point hebdomadaire bien tenu vaut mieux que de longues séances ponctuelles.',
            'Les montants en dirhams doivent correspondre exactement à ceux du devis accepté, sinon le client risque de bloquer le paiement.',
            'Une relance courtoise, envoyée quelques jours après l\'échéance, suffit souvent à débloquer la situation sans tension.',
            'Pensez aussi à préciser vos coordonnées bancaires sur chaque document pour éviter les allers-retours inutiles avec le client.',
            'Enfin, centraliser les informations dans un seul outil évite de rechercher les fichiers dispersés sur plusieurs ordinateurs.',
        ];
        $sections = ['Pourquoi suivre ses encaissements', 'Mettre en place un suivi hebdomadaire', 'Repérer les retards à temps', 'Organiser les relances', 'Un exemple concret', 'Les erreurs fréquentes à éviter'];

        $body = '<p>Suivre ses encaissements clients est une tâche simple en apparence, mais elle conditionne la santé de toute entreprise. Voici une méthode concrète, pensée pour les TPE au Maroc.</p>';
        $n = 0;
        foreach ($sections as $s => $heading) {
            $body .= "<h2>{$heading}</h2>";
            if ($s === 3) {
                $body .= '<h3>Le bon moment pour relancer</h3>';
            }
            for ($p = 0; $p < 3; $p++) {
                $text = [];
                for ($k = 0; $k < 3; $k++) {
                    $text[] = $sentences[($n + $k * 3) % count($sentences)];
                }
                $n++;
                $body .= '<p>Point ' . $n . ' de la méthode, dans la partie « ' . mb_strtolower($heading) . ' » : ' . implode(' ', $text) . '</p>';
            }
        }
        $body .= '<p>Avec Fakturalista, vous voyez les factures payées, en attente et en retard au même endroit. <a href="/register">Essayez-le gratuitement</a> ou consultez <a href="/ma/logiciel-facturation">notre page dédiée</a>.</p>';

        return $overrides + [
            'title'              => 'Suivre ses encaissements clients : la méthode simple pour une TPE au Maroc',
            'meta_title'         => 'Suivi des encaissements clients : méthode pour une TPE',
            'meta_description'   => 'Échéances, tableau de bord, relances courtoises : une méthode simple et concrète pour suivre les encaissements de vos clients au Maroc chaque semaine.',
            'slug'               => 'suivre-encaissements-clients-methode-tpe',
            'excerpt'            => 'Une méthode hebdomadaire pour savoir ce qui a été payé, ce qui reste à encaisser et quand relancer.',
            'primary_keyword'    => 'suivi encaissements clients',
            'secondary_keywords' => ['suivi des paiements clients', 'relance client', 'trésorerie TPE'],
            'body_html'          => $body,
            'sources'            => [],
        ];
    }

    /** Gemini generateContent response wrapping a JSON payload as text. */
    protected function geminiBody(array|string $payload, array $grounding = []): array
    {
        $candidate = [
            'content'      => ['role' => 'model', 'parts' => [['text' => is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE)]]],
            'finishReason' => 'STOP',
        ];
        if ($grounding) {
            $candidate['groundingMetadata'] = [
                'webSearchQueries' => ['test query'],
                'groundingChunks'  => array_map(fn ($g) => ['web' => $g], $grounding),
            ];
        }

        return [
            'candidates'    => [$candidate],
            'usageMetadata' => ['promptTokenCount' => 1200, 'candidatesTokenCount' => 3000, 'totalTokenCount' => 4200],
            'modelVersion'  => 'gemini-test-model',
        ];
    }

    /** Fakes Gemini: article for writer calls, $review for reviewer calls. */
    protected function fakeGemini(?array $article = null, array $review = ['verdict' => 'pass', 'issues' => []], array $grounding = []): void
    {
        $article ??= $this->article();

        Http::fake([
            'generativelanguage.googleapis.com/*' => function (Request $request) use ($article, $review, $grounding) {
                $system = $request->data()['systemInstruction']['parts'][0]['text'] ?? '';

                return Http::response(str_contains($system, 'relecteur')
                    ? $this->geminiBody($review)
                    : $this->geminiBody($article, $grounding));
            },
        ]);
    }
}
