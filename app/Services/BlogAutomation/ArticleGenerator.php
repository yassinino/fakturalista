<?php

namespace App\Services\BlogAutomation;

use App\Models\Post;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiException;
use App\Services\Ai\GeminiResponse;

/**
 * Everything that asks Gemini something for the blog automation: proposing a
 * topic when the catalogue is exhausted, writing the article (with Google
 * Search grounding so current regulations come from real sources), and the
 * optional second-opinion review. Prompts carry the product facts and limits
 * from config so the model cannot be "helpful" about features that don't
 * exist; the deterministic ArticleValidator still checks everything.
 */
class ArticleGenerator
{
    /** Token usage of every call made through this instance. */
    public int $promptTokens = 0;
    public int $outputTokens = 0;
    public int $totalTokens = 0;
    public ?string $model = null;

    public function __construct(private readonly GeminiClient $gemini) {}

    /**
     * Writes one article for a topic.
     *
     * @return array{article: array, sources: array<int, array{uri:string,title:string}>, search_queries: string[]}
     * @throws GeminiException
     */
    public function write(array $topic): array
    {
        $grounding = (bool) config('blog_automation.gemini.grounding');

        $response = $this->call(
            $this->writerSystemPrompt($topic, $grounding),
            $this->writerUserPrompt($topic),
            ['temperature' => 0.6, 'json' => true, 'grounding' => $grounding],
        );

        $data = $response->json();
        if (! is_array($data)) {
            throw new GeminiException('Gemini a renvoyé un article dans un format illisible (JSON invalide).', true);
        }

        $article = [
            'title'              => $this->str($data['title'] ?? ''),
            'meta_title'         => $this->str($data['meta_title'] ?? ''),
            'meta_description'   => $this->str($data['meta_description'] ?? ''),
            'slug'               => $this->str($data['slug'] ?? ''),
            'excerpt'            => $this->str($data['excerpt'] ?? ''),
            'primary_keyword'    => $this->str($data['primary_keyword'] ?? $topic['keyword']),
            'secondary_keywords' => array_slice(array_values(array_filter(array_map(fn ($k) => $this->str($k), (array) ($data['secondary_keywords'] ?? [])))), 0, 8),
            'body'               => is_string($data['body_html'] ?? null) ? trim($data['body_html']) : '',
            'sources'            => array_values(array_filter((array) ($data['sources'] ?? []), 'is_array')),
        ];

        if ($article['body'] === '' || $article['title'] === '') {
            throw new GeminiException('Gemini a renvoyé un article incomplet (titre ou contenu manquant).', true);
        }

        return [
            'article'        => $article,
            'sources'        => $response->groundingSources,
            'search_queries' => $response->searchQueries,
        ];
    }

    /**
     * Second opinion on factual/legal risks. Can only block publication,
     * never approve something the deterministic validator rejected.
     *
     * @return array{verdict: string, issues: string[]}
     */
    public function review(array $article, array $topic): array
    {
        $country = config("blog_automation.country_labels.{$topic['country']}");
        $system = <<<PROMPT
Tu es relecteur éditorial et juridique pour le blog d'un logiciel de facturation. Tu vérifies un article destiné à des entrepreneurs ({$country}).

Signale uniquement des problèmes concrets :
- affirmation juridique, fiscale ou chiffrée (taux, date, seuil, article de loi) douteuse, invérifiable ou présentée sans nuance ;
- conseil juridique ou financier trompeur ou trop catégorique ;
- fonctionnalité ou statut attribué à Fakturalista qui n'est pas dans la liste des faits produit ;
- statistique, témoignage ou citation qui semble inventé ;
- contenu hors sujet, répétitif ou de faible utilité ;
- français incorrect.

Faits produit autorisés :
{$this->bullets(config('blog_automation.product_facts'))}
Limites du produit :
{$this->bullets(config('blog_automation.product_limits'))}

Réponds UNIQUEMENT avec ce JSON :
{"verdict": "pass" ou "needs_review", "issues": ["problème précis en une phrase", ...]}
"pass" seulement si aucun problème réel. En cas de doute sur une règle juridique, "needs_review".
PROMPT;

        $user = "Titre : {$article['title']}\nMéta description : {$article['meta_description']}\n\nArticle (HTML) :\n" . mb_substr($article['body'], 0, 30000);

        $response = $this->call($system, $user, ['temperature' => 0, 'json' => true, 'max_output_tokens' => 4096, 'timeout' => 90]);
        $data = $response->json();

        if (! is_array($data) || ! in_array($data['verdict'] ?? null, ['pass', 'needs_review'], true)) {
            throw new GeminiException('Relecture IA illisible.', true);
        }

        return [
            'verdict' => $data['verdict'],
            'issues'  => array_slice(array_values(array_filter(array_map(fn ($i) => $this->str($i), (array) ($data['issues'] ?? [])))), 0, 15),
        ];
    }

    /**
     * Proposes a new topic for a country whose catalogue is exhausted.
     * Returns null on any failure: no topic is better than a bad one.
     */
    public function ideate(string $country, array $context): ?array
    {
        $label = config("blog_automation.country_labels.{$country}");
        $categories = collect($context['categories'])->map(fn ($name, $key) => "- {$key} : {$name}")->implode("\n");
        $existing = collect($context['existing_titles'])->map(fn ($t) => "- {$t}")->implode("\n");
        $exclusions = $context['exclusions'] ? implode(', ', $context['exclusions']) : 'aucune';

        $system = <<<PROMPT
Tu es responsable éditorial SEO du blog de Fakturalista, un logiciel de facturation en ligne. Propose UN sujet d'article en français, utile pour des entrepreneurs ({$label}), dans l'une des catégories ci-dessous.

Le sujet doit :
- répondre à une intention de recherche précise et réaliste (question qu'un dirigeant de TPE, un freelance ou un comptable se pose) ;
- être différent de tous les articles existants listés (pas de reformulation d'un sujet déjà traité) ;
- rester lié à la facturation, aux devis, aux paiements clients ou à l'organisation administrative ;
- ne pas porter sur une actualité que tu ne peux pas vérifier.

N'invente aucun volume de recherche ni aucune donnée de concurrence.

Réponds UNIQUEMENT avec ce JSON :
{"title": "titre de travail", "primary_keyword": "requête principale (2 à 6 mots)", "category": "clé de catégorie", "search_intent": "informational|commercial|transactional", "regulatory": true si le sujet implique des règles légales ou fiscales, sinon false}
PROMPT;

        $user = "Catégories :\n{$categories}\n\nArticles existants (à ne pas dupliquer) :\n{$existing}\n\nSujets exclus : {$exclusions}";

        try {
            return $this->call($system, $user, ['temperature' => 0.9, 'json' => true, 'max_output_tokens' => 2048, 'timeout' => 60])->json();
        } catch (GeminiException) {
            return null;
        }
    }

    private function call(string $system, string $user, array $options): GeminiResponse
    {
        $response = $this->gemini->generate($system, $user, $options);

        $this->promptTokens += $response->promptTokens;
        $this->outputTokens += $response->outputTokens;
        $this->totalTokens += $response->totalTokens;
        $this->model = $response->model;

        return $response;
    }

    private function writerSystemPrompt(array $topic, bool $grounding): string
    {
        $country = $topic['country'];
        $label = config("blog_automation.country_labels.{$country}");
        $audience = $country === 'ma'
            ? 'dirigeants de TPE et PME, auto-entrepreneurs, freelances, artisans et entreprises du BTP au Maroc. Montants en dirhams (DH). Contexte fiscal marocain (DGI, CGI, ICE, IF, RC, TVA).'
            : 'freelances, micro-entrepreneurs, TPE et agences en France. Montants en euros. Contexte français (DGFiP, Code de commerce, Code général des impôts).';
        $official = implode(', ', config("blog_automation.sources.official.{$country}"));
        $reliable = implode(', ', config('blog_automation.sources.reliable'));
        $links = $this->internalLinks($country);
        $facts = $this->bullets(config('blog_automation.product_facts'));
        $limits = $this->bullets(config('blog_automation.product_limits'));
        $frenchProduct = $country === 'fr'
            ? "\nPour la France, Fakturalista n'a ni taux de TVA français préconfigurés ni statut de plateforme agréée : présente-le seulement comme un outil en ligne pour créer et suivre devis et factures, sans promettre la conformité à la réforme française."
            : '';
        $sourcesRule = $grounding
            ? "Utilise la recherche Google pour vérifier toute règle juridique, fiscale, date, taux ou seuil AVANT de l'écrire, en privilégiant les sites officiels ({$official})."
            : "Tu n'as pas accès à une recherche web : n'affirme aucune date, taux, seuil ou obligation récente que tu ne peux pas rattacher à un texte officiel stable, et renvoie le lecteur vers les sites officiels ({$official}).";

        return <<<PROMPT
Tu es un rédacteur web expert en facturation et gestion de petites entreprises. Tu écris pour le blog de Fakturalista (https://fakturalista.com), un logiciel de facturation en ligne.

Public : {$audience}

OBJECTIF
Un article utile, original et concret, qui répond pleinement à l'intention de recherche, en français naturel et fluide. L'utilité prime sur la longueur : vise environ 900 à 1 500 mots si le sujet le justifie.

RÈGLES DE FOND (impératives)
- {$sourcesRule}
- N'invente jamais de règle juridique, de date, de taux, de seuil, de montant d'amende ni de numéro d'article de loi. Si une information n'est pas confirmée par une source officielle, ne l'écris pas, ou indique clairement qu'elle reste à vérifier auprès de l'administration ou d'un expert-comptable.
- Cite chaque règle juridique ou fiscale avec un lien vers la source officielle consultée (domaines autorisés : {$official} ; autres sources fiables autorisées : {$reliable}). N'utilise que des URL que tu as réellement consultées ; à défaut, lie la page d'accueil du site officiel.
- Aucune statistique, aucun témoignage, aucune citation, aucun avis client, aucun nom de client réel. Les exemples chiffrés sont présentés comme fictifs (« exemple fictif »).
- Pas de conseil juridique ou financier catégorique : pour les cas particuliers, recommande de consulter un expert-comptable.
- Pas de bourrage de mots-clés, pas de paragraphes génériques ou répétitifs, pas de formules creuses (« dans le monde d'aujourd'hui », « en conclusion, il est important de... »).

FAKTURALISTA
Faits produit (les seuls que tu peux affirmer) :
{$facts}
Limites (ne jamais affirmer le contraire) :
{$limits}{$frenchProduct}
Mentionne Fakturalista avec mesure : au maximum 3 fois, dont un appel à l'action naturel vers l'essai gratuit (/register) ou une page pertinente, de préférence dans la dernière section. Pas de ton publicitaire.

LIENS INTERNES (utilise-en 2 à 4, uniquement parmi ceux-ci, avec un chemin relatif) :
{$links}

FORMAT DU CONTENU (body_html)
- HTML simple uniquement : <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <a href>, <blockquote>, <table>, <thead>, <tbody>, <tr>, <th>, <td>.
- Pas de <h1> (le titre est affiché à part), pas de style, pas de classe, pas d'image, pas de Markdown.
- Introduction directe (1 à 2 paragraphes), puis 4 à 7 sections <h2>, avec des <h3> si utile, au moins un exemple concret, une liste à puces si pertinent, et une courte conclusion actionnable.
- Pas de section FAQ.

MÉTADONNÉES
- title : titre d'article clair et spécifique (60 à 90 caractères maximum).
- meta_title : 50 à 60 caractères, contient le mot-clé principal, différent du title si possible.
- meta_description : 140 à 160 caractères, incitative et fidèle au contenu.
- slug : minuscules, sans accents, mots séparés par des tirets, 3 à 8 mots.
- excerpt : 1 à 2 phrases (moins de 300 caractères).
- secondary_keywords : 3 à 6 expressions proches, sans volumes inventés.

Réponds UNIQUEMENT avec un objet JSON valide, sans texte autour :
{"title": "...", "meta_title": "...", "meta_description": "...", "slug": "...", "excerpt": "...", "primary_keyword": "...", "secondary_keywords": ["..."], "body_html": "...", "sources": [{"title": "...", "url": "..."}]}
PROMPT;
    }

    private function writerUserPrompt(array $topic): string
    {
        $label = config("blog_automation.country_labels.{$topic['country']}");
        $category = config("blog_automation.categories.{$topic['country']}.{$topic['category']}", $topic['category']);
        $intent = ['informational' => 'informationnelle (comprendre, apprendre)', 'commercial' => 'commerciale (comparer, choisir un outil)', 'transactional' => 'pratique (faire, obtenir un modèle)'][$topic['intent']] ?? $topic['intent'];
        $regulatory = $topic['regulatory']
            ? "\nCe sujet touche à la réglementation : chaque règle doit être vérifiée et sourcée sur un site officiel, sinon omise."
            : '';
        $existing = Post::published()->latest('published_at')->limit(40)->pluck('title')->map(fn ($t) => "- {$t}")->implode("\n");
        $today = now(config('blog_automation.timezone'))->locale('fr')->isoFormat('LL');

        return <<<PROMPT
Date du jour : {$today}
Marché : {$label}
Catégorie : {$category}
Sujet de travail : {$topic['title']}
Mot-clé principal : {$topic['keyword']}
Intention de recherche : {$intent}{$regulatory}

Articles déjà publiés sur le blog (ne les répète pas, tu peux y faire un lien si pertinent) :
{$existing}

Rédige l'article.
PROMPT;
    }

    private function internalLinks(string $country): string
    {
        $lines = [];
        foreach (config('blog_automation.internal_pages') as $page) {
            if (in_array($country, $page['markets'], true)) {
                $lines[] = "- {$page['path']} : {$page['label']}";
            }
        }
        Post::published()->latest('published_at')->limit(30)->get(['slug', 'title', 'target_country'])
            ->filter(fn (Post $p) => $p->target_country === null ? $country === 'ma' : $p->target_country === $country)
            ->each(function (Post $p) use (&$lines) {
                $lines[] = "- /blog/{$p->slug} : {$p->title}";
            });

        return implode("\n", $lines);
    }

    private function bullets(array $items): string
    {
        return implode("\n", array_map(fn ($i) => "- {$i}", $items));
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value))) : '';
    }
}
