<?php

/*
 * AI blog automation (php artisan blog:generate-daily, Filament
 * "Automatisation blog IA"). Runtime switches (enabled, auto-publish, ...)
 * live in the central `blog_automation_settings` row and are edited in the
 * backoffice; this file holds the static parts: the topic catalogue, the
 * trusted source domains, the quality thresholds and the Gemini limits.
 *
 * The Gemini key and model are NOT configured here: the generator reuses
 * config('services.gemini') like the invoice assistant.
 */
return [

    // Days, the daily limit and the 08:00 schedule are counted in this zone.
    'timezone' => env('BLOG_AUTOMATION_TIMEZONE', 'Africa/Casablanca'),

    'schedule_time' => '08:00',

    // Hard ceiling of new articles per day, whatever the backoffice says.
    'max_per_day_cap' => 1,

    // Long-run share of articles per country (percent). The selector picks
    // the country furthest below its share over the recent history.
    'country_weights' => ['ma' => 70, 'fr' => 30],

    'country_labels' => ['ma' => 'Maroc', 'fr' => 'France'],

    // Queue used for the generation job (the scheduler only dispatches it).
    'queue' => env('BLOG_AUTOMATION_QUEUE', 'default'),

    'gemini' => [
        // Hard bounds on every request (small production server).
        'timeout'           => (int) env('BLOG_AI_TIMEOUT', 120),
        'connect_timeout'   => 10,
        'max_output_tokens' => (int) env('BLOG_AI_MAX_OUTPUT_TOKENS', 16384),
        // Retries for 429 / 5xx / network errors inside one attempt.
        'max_retries'       => 2,
        'retry_delay_ms'    => 5000,
        'max_retry_after_s' => 60,
        // Google Search grounding: lets Gemini consult and cite current
        // sources. Required to auto-publish regulatory articles.
        'grounding'         => (bool) env('BLOG_AI_GROUNDING', true),
        // Optional cost estimate, in USD per million tokens. Left empty on
        // purpose: fill in from Google's current price list for your model.
        'price_input_per_million'  => env('BLOG_AI_PRICE_INPUT_PER_M'),
        'price_output_per_million' => env('BLOG_AI_PRICE_OUTPUT_PER_M'),
    ],

    // Check that cited external links answer before keeping them.
    'check_links' => (bool) env('BLOG_AI_CHECK_LINKS', true),

    // A running generation older than this is considered interrupted.
    'stale_after_minutes' => 30,

    // Admin alert once this many generations in a row have failed.
    'failure_alert_threshold' => 2,

    // Deterministic quality gate (App\Services\BlogAutomation\ArticleValidator).
    'quality' => [
        'min_words'                => 700,
        'target_min_words'         => 900,
        'max_words'                => 2600,
        'min_h2'                   => 3,
        'meta_title_hard'          => [25, 70],
        'meta_title_soft'          => [50, 60],
        'meta_description_hard'    => [100, 170],
        'meta_description_soft'    => [140, 160],
        'max_keyword_density'      => 0.03,
        'max_brand_mentions'       => 6,
        'max_similarity'           => 0.30, // 5-word shingle Jaccard vs any existing post
        'topic_similarity'         => 0.5,  // keyword token overlap = same topic
        'min_french_ratio'         => 0.12, // share of French function words
        'max_external_links'       => 6,
    ],

    // Links to these hosts (and their subdomains) are kept in articles.
    // `official` ones also count as verified sources for regulatory topics.
    'sources' => [
        'official' => [
            'ma' => ['tax.gov.ma', 'finances.gov.ma', 'sgg.gov.ma', 'cnss.ma', 'ompic.ma', 'ae.gov.ma', 'bkam.ma', 'maroc.ma', 'add.gov.ma'],
            'fr' => ['impots.gouv.fr', 'economie.gouv.fr', 'service-public.fr', 'legifrance.gouv.fr', 'urssaf.fr', 'bpifrance-creation.fr', 'banque-france.fr', 'gouvernement.fr'],
        ],
        'reliable' => ['europa.eu', 'oasis-open.org', 'cci.fr'],
    ],

    // Fakturalista pages articles may link to (besides published blog posts).
    // `markets`: countries for which the page is relevant.
    'internal_pages' => [
        ['path' => '/register', 'label' => 'Essai gratuit de Fakturalista', 'markets' => ['ma', 'fr']],
        ['path' => '/pricing', 'label' => 'Tarifs de Fakturalista', 'markets' => ['ma', 'fr']],
        ['path' => '/ma/logiciel-facturation', 'label' => 'Logiciel de facturation au Maroc', 'markets' => ['ma']],
        ['path' => '/ma/logiciel-devis-facture', 'label' => 'Logiciel de devis et factures', 'markets' => ['ma']],
        ['path' => '/ma/facturation-electronique', 'label' => 'Facturation électronique au Maroc', 'markets' => ['ma']],
        ['path' => '/ma/facturation-btp', 'label' => 'Facturation BTP et artisans', 'markets' => ['ma']],
        ['path' => '/security', 'label' => 'Sécurité des données', 'markets' => ['ma', 'fr']],
    ],

    /*
     * What Fakturalista really does (checked against the code base, see
     * resources/lang/fr/landing.php). Given to the model as the only product
     * facts it may state; the forbidden patterns below are enforced by the
     * validator whatever the model writes.
     */
    'product_facts' => [
        'Logiciel de facturation en ligne (navigateur), interface en français, arabe et anglais.',
        'Création de devis et de factures en PDF, envoi par e-mail ; un devis accepté devient une facture en un clic.',
        'Numérotation séquentielle automatique à l\'émission ; une facture émise est verrouillée et se corrige par un document rectificatif.',
        'Maroc : factures en dirhams, TVA à 20 %, 10 % ou exonérée, ICE / IF / RC de l\'entreprise et des clients professionnels.',
        'Suivi des factures payées, en attente et en retard ; enregistrer un paiement marque toute la facture comme payée (pas de paiement partiel ni d\'acompte).',
        'E-mail à l\'utilisateur (pas au client) avant l\'échéance et quand une facture passe en retard.',
        'Portail client : le client consulte et télécharge ses factures et devis, et accepte ou refuse un devis en ligne.',
        'Assistant IA qui prépare un brouillon de facture à partir d\'une phrase ; l\'utilisateur vérifie avant de valider.',
        'Export des factures en XML UBL 2.1 et des listes (factures, devis, paiements, clients) vers Excel.',
        'Chaque entreprise dispose de sa propre base de données.',
        'Essai gratuit sans carte bancaire, formules mensuelles sans engagement.',
    ],

    'product_limits' => [
        'Fakturalista n\'est ni certifié ni agréé par la DGI et n\'est connecté à aucune plateforme de l\'administration marocaine.',
        'Fakturalista n\'est pas une plateforme agréée (PA / ex-PDP) de facturation électronique en France et ne transmet pas de factures à l\'administration française.',
        'Fakturalista n\'a pas de taux de TVA français préconfigurés et n\'est pas un logiciel de caisse ni de comptabilité.',
        'Pas de paiement partiel, pas d\'acompte, pas d\'import de données, pas de synchronisation bancaire, pas de relance automatique envoyée aux clients.',
    ],

    // Product claims the validator rejects (case-insensitive regex, matched
    // per sentence). They describe statuses or features Fakturalista does
    // not have; a sentence that negates them ("n'est pas certifié") is fine.
    'forbidden_claims' => [
        '/Fakturalista[^.]{0,80}\b(certifi|agré|homologu|labellis)\w*/iu' => 'Statut officiel (certification/agrément) non établi',
        '/Fakturalista[^.]{0,80}\b(plateforme agréée|PDP|PA\b|plateforme de dématérialisation partenaire|opérateur de dématérialisation)/iu' => 'Fakturalista présenté comme plateforme agréée',
        '/Fakturalista[^.]{0,80}\b(conforme|en conformité|garantit la conformité)\b/iu' => 'Conformité réglementaire revendiquée',
        '/Fakturalista[^.]{0,80}\b(connecté|transmet|télétransmet|télédéclar)\w*\b[^.]{0,60}\b(DGI|administration|impôts|Chorus|PPF)\b/iu' => 'Connexion à l\'administration revendiquée',
        '/Fakturalista[^.]{0,100}\b(paiements? partiels?|acomptes?|synchronisation bancaire|rapprochement bancaire|relances? automatiques?|import)\b/iu' => 'Fonctionnalité non disponible revendiquée',
        '/Fakturalista[^.]{0,100}\b(TVA française|taux de TVA français|logiciel de caisse|logiciel de comptabilité)\b/iu' => 'Fonctionnalité non disponible revendiquée',
    ],

    // Content the validator rejects whatever the context.
    'forbidden_patterns' => [
        '/\b(témoignage|nous a confié|selon notre client|avis client|nos clients nous)\b/iu' => 'Témoignage potentiellement inventé',
        '/\b\d{1,3}(,\d+)?\s?%\s+des\s+(entreprises|PME|TPE|freelances?|indépendants|auto-entrepreneurs|clients|factures|dirigeants)\b/iu' => 'Statistique non sourcée',
        '/\b(meilleur logiciel du marché|n°\s?1 de la facturation|leader (du|de la) (marché|facturation))\b/iu' => 'Superlatif promotionnel invérifiable',
    ],

    // Placeholder / formatting leftovers that must never be published.
    'placeholder_patterns' => [
        '/lorem ipsum/i', '/\bTODO\b/', '/\bXXX\b/', '/\[(nom|insérer|lien|url|votre|source|date)[^\]]*\]/iu',
        '/\{\{.*?\}\}/', '/```/', '/(^|\s)#{2,3}\s/m', '/\*\*[^*]+\*\*/', '/\bINSÉRER\b/u',
    ],

    // Words that flag a regulatory/legal claim in the text (draft unless the
    // article cites an official source and verification succeeded).
    'regulatory_patterns' => [
        '/\barticle\s+\d+/iu', '/\bloi\s+(n°|no|numéro)?\s*\d/iu', '/\bdécret\b/iu', '/\bCode général des impôts\b/iu',
        '/\bCGI\b/u', '/\bloi de finances\b/iu', '/\bamende\b/iu', '/\bpénalité/iu', '/\bobligatoire/iu',
        '/\bTVA\s+(à|de)\s+\d/iu', '/\b\d{1,2}\s?%\s+de\s+TVA\b/iu', '/\bà partir du\s+\d{1,2}(er)?\s+\w+\s+20\d\d\b/iu',
    ],

    /*
     * Topic catalogue. Each topic: country, category, working title, primary
     * keyword, search intent, regulatory (needs verified sources), value
     * (1-3 business value) and optional months (seasonal boost). Keywords are
     * chosen not to overlap the hand-written articles. No search volumes are
     * claimed anywhere - priorities are editorial.
     */
    'categories' => [
        'ma' => [
            'logiciel'      => 'Logiciel de facturation pour PME marocaines',
            'facture'       => 'Créer des factures professionnelles au Maroc',
            'mentions'      => 'Obligations de facturation au Maroc',
            'modeles'       => 'Modèles de factures et de devis',
            'paiements'     => 'Encaissements et paiements clients',
            'impayes'       => 'Relance des factures impayées',
            'digital'       => 'Transformation numérique des PME',
            'btp'           => 'Facturation BTP et construction',
            'freelance'     => 'Facturation des freelances',
            'devis'         => 'Devis et factures',
            'efacture'      => 'Facturation électronique au Maroc',
            'identifiants'  => 'ICE, IF, RC et TVA',
            'organisation'  => 'Organisation comptable des petites entreprises',
        ],
        'fr' => [
            'logiciel-freelance' => 'Logiciel de facturation pour freelances',
            'micro'              => 'Devis et factures en micro-entreprise',
            'impayes'            => 'Gestion des factures impayées',
            'efacture'           => 'Facturation électronique en France',
            'agences'            => 'Gestion des factures en agence',
            'numerotation'       => 'Numérotation et organisation des factures',
            'tresorerie'         => 'Amélioration de la trésorerie',
            'choisir'            => 'Choisir un logiciel de facturation',
        ],
    ],

    'topics' => [
        // ── Maroc ──────────────────────────────────────────────────────────
        ['key' => 'ma-logiciel-facturation-pme-criteres', 'country' => 'ma', 'category' => 'logiciel', 'title' => 'Logiciel de facturation pour PME au Maroc : les fonctionnalités vraiment utiles', 'keyword' => 'logiciel facturation PME Maroc', 'intent' => 'commercial', 'regulatory' => false, 'value' => 3],
        ['key' => 'ma-logiciel-gratuit-ou-payant', 'country' => 'ma', 'category' => 'logiciel', 'title' => 'Logiciel de facturation gratuit ou payant au Maroc : comment trancher', 'keyword' => 'logiciel facturation gratuit Maroc', 'intent' => 'commercial', 'regulatory' => false, 'value' => 3],
        ['key' => 'ma-facture-en-ligne-etapes', 'country' => 'ma', 'category' => 'facture', 'title' => 'Faire une facture en ligne au Maroc : méthode pas à pas', 'keyword' => 'faire une facture en ligne Maroc', 'intent' => 'transactional', 'regulatory' => false, 'value' => 3],
        ['key' => 'ma-facture-avoir', 'country' => 'ma', 'category' => 'facture', 'title' => 'Facture d\'avoir au Maroc : quand et comment corriger une facture', 'keyword' => 'facture avoir Maroc', 'intent' => 'informational', 'regulatory' => true, 'value' => 2],
        ['key' => 'ma-facture-proforma', 'country' => 'ma', 'category' => 'devis', 'title' => 'Facture proforma, devis et facture définitive : les différences', 'keyword' => 'facture proforma Maroc', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'ma-mentions-obligatoires-facture', 'country' => 'ma', 'category' => 'mentions', 'title' => 'Mentions obligatoires d\'une facture au Maroc : la checklist', 'keyword' => 'mentions obligatoires facture Maroc', 'intent' => 'informational', 'regulatory' => true, 'value' => 3],
        ['key' => 'ma-conservation-factures', 'country' => 'ma', 'category' => 'mentions', 'title' => 'Combien de temps conserver ses factures au Maroc ?', 'keyword' => 'durée conservation factures Maroc', 'intent' => 'informational', 'regulatory' => true, 'value' => 2],
        ['key' => 'ma-modele-facture-word-limites', 'country' => 'ma', 'category' => 'modeles', 'title' => 'Modèle de facture Word ou Excel au Maroc : ce qu\'il doit contenir et ses limites', 'keyword' => 'modèle facture Maroc', 'intent' => 'transactional', 'regulatory' => false, 'value' => 3],
        ['key' => 'ma-modele-devis', 'country' => 'ma', 'category' => 'modeles', 'title' => 'Modèle de devis au Maroc : structure et exemple commenté', 'keyword' => 'modèle devis Maroc', 'intent' => 'transactional', 'regulatory' => false, 'value' => 3],
        ['key' => 'ma-moyens-paiement-clients', 'country' => 'ma', 'category' => 'paiements', 'title' => 'Virement, chèque, espèces : organiser les paiements de ses clients au Maroc', 'keyword' => 'moyens de paiement clients Maroc', 'intent' => 'informational', 'regulatory' => true, 'value' => 2],
        ['key' => 'ma-suivi-encaissements', 'country' => 'ma', 'category' => 'paiements', 'title' => 'Suivre ses encaissements clients : méthode simple pour une TPE', 'keyword' => 'suivi encaissements clients', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'ma-delais-paiement-loi-69-21', 'country' => 'ma', 'category' => 'impayes', 'title' => 'Délais de paiement au Maroc : ce que change la loi 69-21 pour les PME', 'keyword' => 'délais de paiement Maroc loi 69-21', 'intent' => 'informational', 'regulatory' => true, 'value' => 3],
        ['key' => 'ma-modele-lettre-relance', 'country' => 'ma', 'category' => 'impayes', 'title' => 'Lettre de relance de facture impayée : modèles et bonnes pratiques', 'keyword' => 'lettre de relance facture impayée', 'intent' => 'transactional', 'regulatory' => false, 'value' => 3],
        ['key' => 'ma-prevenir-retards-paiement', 'country' => 'ma', 'category' => 'impayes', 'title' => 'Prévenir les retards de paiement avant même d\'envoyer la facture', 'keyword' => 'éviter retards de paiement clients', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'ma-digitaliser-pme', 'country' => 'ma', 'category' => 'digital', 'title' => 'Digitaliser sa PME au Maroc : par où commencer ?', 'keyword' => 'digitalisation PME Maroc', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'ma-zero-papier-facturation', 'country' => 'ma', 'category' => 'digital', 'title' => 'Passer au zéro papier pour ses devis et factures', 'keyword' => 'facturation zéro papier', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'ma-devis-btp-postes', 'country' => 'ma', 'category' => 'btp', 'title' => 'Rédiger un devis BTP détaillé par postes au Maroc', 'keyword' => 'devis BTP Maroc', 'intent' => 'transactional', 'regulatory' => false, 'value' => 3],
        ['key' => 'ma-facturation-artisan', 'country' => 'ma', 'category' => 'btp', 'title' => 'Facturation pour artisans au Maroc : plombiers, électriciens, menuisiers', 'keyword' => 'facture artisan Maroc', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'ma-freelance-facturer-client', 'country' => 'ma', 'category' => 'freelance', 'title' => 'Freelance au Maroc : comment facturer ses clients correctement', 'keyword' => 'facturer en freelance Maroc', 'intent' => 'informational', 'regulatory' => true, 'value' => 3],
        ['key' => 'ma-auto-entrepreneur-facture', 'country' => 'ma', 'category' => 'freelance', 'title' => 'Auto-entrepreneur au Maroc : faire une facture sans TVA', 'keyword' => 'facture auto-entrepreneur Maroc', 'intent' => 'informational', 'regulatory' => true, 'value' => 3],
        ['key' => 'ma-fixer-tarifs-freelance', 'country' => 'ma', 'category' => 'freelance', 'title' => 'Fixer ses tarifs de freelance et les présenter dans un devis', 'keyword' => 'tarif freelance devis', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'ma-devis-accepte-facture', 'country' => 'ma', 'category' => 'devis', 'title' => 'Du devis accepté à la facture : éviter les écarts et les ressaisies', 'keyword' => 'transformer devis en facture', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'ma-validite-devis', 'country' => 'ma', 'category' => 'devis', 'title' => 'Durée de validité d\'un devis : comment la fixer et l\'indiquer', 'keyword' => 'durée validité devis', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'ma-efacture-preparer-donnees', 'country' => 'ma', 'category' => 'efacture', 'title' => 'Facture électronique au Maroc : préparer ses données de facturation', 'keyword' => 'préparer facture électronique Maroc', 'intent' => 'informational', 'regulatory' => true, 'value' => 3],
        ['key' => 'ma-ice-explique', 'country' => 'ma', 'category' => 'identifiants', 'title' => 'ICE au Maroc : à quoi sert-il et où l\'indiquer sur une facture ?', 'keyword' => 'ICE Maroc facture', 'intent' => 'informational', 'regulatory' => true, 'value' => 3],
        ['key' => 'ma-if-rc-differences', 'country' => 'ma', 'category' => 'identifiants', 'title' => 'IF, RC, ICE : comprendre les identifiants d\'une entreprise marocaine', 'keyword' => 'différence IF RC ICE', 'intent' => 'informational', 'regulatory' => true, 'value' => 2],
        ['key' => 'ma-tva-facture-calcul', 'country' => 'ma', 'category' => 'identifiants', 'title' => 'Calculer la TVA sur une facture au Maroc : HT, TVA et TTC', 'keyword' => 'calcul TVA facture Maroc', 'intent' => 'informational', 'regulatory' => true, 'value' => 3],
        ['key' => 'ma-organiser-pieces-comptables', 'country' => 'ma', 'category' => 'organisation', 'title' => 'Organiser ses pièces comptables pour son comptable : la routine mensuelle', 'keyword' => 'organiser pièces comptables', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'ma-cloture-annee-factures', 'country' => 'ma', 'category' => 'organisation', 'title' => 'Fin d\'année : vérifier et classer ses factures avant la clôture', 'keyword' => 'clôture annuelle factures', 'intent' => 'informational', 'regulatory' => false, 'value' => 2, 'months' => [11, 12, 1]],
        ['key' => 'ma-rentree-relances', 'country' => 'ma', 'category' => 'impayes', 'title' => 'Rentrée : faire le point sur ses factures en attente', 'keyword' => 'point factures en attente rentrée', 'intent' => 'informational', 'regulatory' => false, 'value' => 1, 'months' => [9, 10]],

        // ── France ─────────────────────────────────────────────────────────
        ['key' => 'fr-logiciel-facturation-freelance', 'country' => 'fr', 'category' => 'logiciel-freelance', 'title' => 'Logiciel de facturation pour freelance : les critères qui comptent', 'keyword' => 'logiciel facturation freelance', 'intent' => 'commercial', 'regulatory' => false, 'value' => 3],
        ['key' => 'fr-micro-entreprise-facture', 'country' => 'fr', 'category' => 'micro', 'title' => 'Facture de micro-entrepreneur : les mentions à ne pas oublier', 'keyword' => 'facture micro-entreprise mentions', 'intent' => 'informational', 'regulatory' => true, 'value' => 3],
        ['key' => 'fr-devis-micro-entreprise', 'country' => 'fr', 'category' => 'micro', 'title' => 'Faire un devis en micro-entreprise : structure et conseils', 'keyword' => 'devis micro-entreprise', 'intent' => 'transactional', 'regulatory' => false, 'value' => 3],
        ['key' => 'fr-relance-impayes-etapes', 'country' => 'fr', 'category' => 'impayes', 'title' => 'Facture impayée : les étapes d\'une relance efficace', 'keyword' => 'relance facture impayée', 'intent' => 'informational', 'regulatory' => false, 'value' => 3],
        ['key' => 'fr-penalites-retard', 'country' => 'fr', 'category' => 'impayes', 'title' => 'Pénalités de retard et indemnité forfaitaire : ce qu\'il faut savoir', 'keyword' => 'pénalités de retard facture', 'intent' => 'informational', 'regulatory' => true, 'value' => 2],
        ['key' => 'fr-efacture-calendrier', 'country' => 'fr', 'category' => 'efacture', 'title' => 'Facturation électronique en France : le calendrier pour les TPE', 'keyword' => 'facturation électronique calendrier TPE', 'intent' => 'informational', 'regulatory' => true, 'value' => 3],
        ['key' => 'fr-efacture-preparer', 'country' => 'fr', 'category' => 'efacture', 'title' => 'Se préparer à la facture électronique quand on est indépendant', 'keyword' => 'préparer facture électronique indépendant', 'intent' => 'informational', 'regulatory' => true, 'value' => 2],
        ['key' => 'fr-agence-facturation-projets', 'country' => 'fr', 'category' => 'agences', 'title' => 'Agence : organiser la facturation de ses projets clients', 'keyword' => 'facturation agence projets', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'fr-numerotation-factures', 'country' => 'fr', 'category' => 'numerotation', 'title' => 'Numérotation des factures : règles et exemples de formats', 'keyword' => 'numérotation factures règles', 'intent' => 'informational', 'regulatory' => true, 'value' => 3],
        ['key' => 'fr-classer-factures', 'country' => 'fr', 'category' => 'numerotation', 'title' => 'Classer et archiver ses factures simplement', 'keyword' => 'classer ses factures', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'fr-tresorerie-freelance', 'country' => 'fr', 'category' => 'tresorerie', 'title' => 'Améliorer sa trésorerie de freelance grâce à sa facturation', 'keyword' => 'améliorer trésorerie freelance', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'fr-conditions-paiement', 'country' => 'fr', 'category' => 'tresorerie', 'title' => 'Conditions de paiement sur un devis : bien les rédiger', 'keyword' => 'conditions de paiement devis', 'intent' => 'informational', 'regulatory' => false, 'value' => 2],
        ['key' => 'fr-choisir-logiciel-facturation', 'country' => 'fr', 'category' => 'choisir', 'title' => 'Choisir un logiciel de facturation : la grille de comparaison', 'keyword' => 'choisir logiciel de facturation', 'intent' => 'commercial', 'regulatory' => false, 'value' => 3],
        ['key' => 'fr-excel-ou-logiciel', 'country' => 'fr', 'category' => 'choisir', 'title' => 'Tableur ou logiciel de facturation : quand changer ?', 'keyword' => 'facture Excel ou logiciel', 'intent' => 'commercial', 'regulatory' => false, 'value' => 2],
    ],
];
