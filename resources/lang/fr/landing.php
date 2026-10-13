<?php

/*
 * Market landing pages (MarketLandingController, App\Support\MarketLandingPages).
 *
 * Every product claim here was checked against the code base (October 2026):
 * MA tax presets are TVA 20 %, 10 % and exempt; recording a payment marks
 * the whole invoice as paid (no partial payments); due/overdue alerts are
 * emailed to the account owner, not to clients; there is no DGI connection.
 * Keep it that way - do not add claims the product cannot back up.
 *
 * ":days" is replaced with the trial length of the active plans.
 */
return [

    'common' => [
        'example_note'  => 'Exemple fictif, à titre d\'illustration.',
        'sources_title' => 'Sources',
        'menu_label'    => 'Sur cette page',
        'related_blog'  => 'Article',
    ],

    // ── /ma/logiciel-facturation ─────────────────────────────────────────
    'ma_invoicing' => [
        'nav_label'        => 'Logiciel de facturation au Maroc',
        'meta_title'       => 'Logiciel de facturation au Maroc : factures en DH, TVA et ICE',
        'meta_description' => 'Créez des factures en dirhams avec TVA à 20 % ou 10 %, ICE et numérotation automatique, puis suivez vos paiements et vos retards. Essai gratuit de :days jours.',
        'eyebrow'          => 'Logiciel de facturation Maroc',
        'h1'               => 'Le logiciel de facturation pensé pour les entreprises au Maroc',
        'intro'            => 'Fakturalista vous permet de créer des factures et des devis professionnels en dirhams, avec la TVA, l\'ICE et les informations de votre entreprise, puis de suivre ce qui a été payé et ce qui reste à encaisser. Tout se fait en ligne, depuis votre navigateur, en français ou en arabe.',
        'highlights' => [
            ['Factures en dirhams', 'TVA à 20 % ou 10 %, ou opérations exonérées : les totaux HT, TVA et TTC sont calculés automatiquement.'],
            ['ICE, IF et RC', 'Les identifiants de votre entreprise et de vos clients professionnels apparaissent sur vos documents.'],
            ['Numérotation automatique', 'Chaque facture émise reçoit un numéro séquentiel, sans trou ni doublon.'],
            ['Paiements suivis', 'Repérez d\'un coup d\'œil les factures payées, en attente et en retard.'],
        ],
        'sections' => [
            [
                'h2'         => 'Pourquoi passer d\'Excel ou Word à un logiciel de facturation ?',
                'paragraphs' => [
                    'Beaucoup de TPE et d\'indépendants au Maroc démarrent avec un modèle Word ou un tableur. Cela fonctionne pour quelques factures par mois, puis les difficultés arrivent : numéros en double, TVA mal recalculée après une modification, ICE du client oublié, et aucune vue d\'ensemble sur les factures impayées sans rouvrir chaque fichier.',
                    'Un logiciel de facturation centralise vos clients, votre catalogue et vos documents. Chaque facture reprend automatiquement les bonnes informations, et le suivi des paiements se met à jour au fil de l\'eau.',
                ],
                'link' => ['Comment passer d\'Excel à un logiciel de facturation', '/blog/passer-excel-logiciel-facturation'],
            ],
            [
                'h2'    => 'Ce que vous pouvez faire avec Fakturalista',
                'items' => [
                    ['Créer une facture en quelques secondes', 'Choisissez le client, ajoutez vos produits ou services depuis votre catalogue ou en ligne libre, appliquez une remise et le taux de TVA, puis téléchargez le PDF ou envoyez-le par e-mail.'],
                    ['Transformer un devis en facture', 'Un devis accepté devient une facture en un clic, sans rien ressaisir.'],
                    ['Ouvrir un portail à vos clients', 'Votre client consulte et télécharge ses factures et devis en ligne, et accepte ou refuse un devis depuis un lien sécurisé.'],
                    ['Être alerté avant et après l\'échéance', 'Vous recevez un e-mail quelques jours avant l\'échéance d\'une facture et lorsqu\'elle passe en retard, pour relancer votre client au bon moment.'],
                    ['Préparer une facture avec l\'IA', 'Décrivez la facture en une phrase, par exemple « Facture à Youssef 3 heures de développement à 500 DH HT ». Fakturalista prépare le brouillon, vous vérifiez avant de valider.'],
                    ['Exporter vos données', 'Factures en PDF et au format XML UBL 2.1, listes de factures, devis, paiements et clients exportables vers Excel pour votre comptable.'],
                ],
            ],
            [
                'h2'         => 'Les informations à faire figurer sur une facture au Maroc',
                'paragraphs' => [
                    'L\'article 145 du Code général des impôts liste les mentions qu\'une facture doit comporter. On y trouve notamment l\'identité du vendeur et ses identifiants (identifiant fiscal, ICE, taxe professionnelle, registre du commerce selon la forme de l\'entreprise), la date et le numéro de la facture, l\'identité du client et son ICE lorsqu\'il s\'agit d\'une entreprise, la désignation, les quantités et prix des biens ou services, le montant hors taxe, le taux et le montant de la TVA, et le total TTC.',
                    'Dans Fakturalista, vous renseignez une seule fois l\'ICE, l\'IF et le RC de votre entreprise ainsi que ceux de vos clients professionnels ; ils sont ensuite repris sur chaque document. Faites valider vos modèles par votre comptable, en particulier si votre activité est exonérée de TVA ou relève d\'un régime particulier.',
                ],
                'link' => ['Comment créer une facture professionnelle au Maroc', '/blog/creer-facture-professionnelle-maroc'],
            ],
            [
                'h2'    => 'Pour qui ?',
                'items' => [
                    ['Auto-entrepreneurs et indépendants', 'Des factures claires, avec ou sans TVA selon votre situation, et une trace de chaque paiement reçu.'],
                    ['TPE et PME', 'Plusieurs utilisateurs selon votre formule, un catalogue commun, le suivi des retards de paiement et des rapports d\'activité.'],
                    ['Prestataires de services et agences', 'Devis détaillés, acceptation en ligne et conversion en facture sans ressaisie.'],
                    ['BTP et artisans', 'Des devis par poste et un suivi clair des chantiers facturés.'],
                ],
                'link' => ['Facturation BTP et artisans', '/ma/facturation-btp'],
            ],
            [
                'h2'         => 'Combien coûte Fakturalista ?',
                'paragraphs' => [
                    'Les formules sont mensuelles, affichées en dirhams et sans engagement. Vous pouvez tester toutes les fonctionnalités gratuitement pendant :days jours, sans carte bancaire, puis choisir la formule adaptée à votre volume.',
                ],
                'link' => ['Voir les tarifs', '/pricing'],
            ],
        ],
        'example' => [
            'title' => 'Exemple : une facture de prestation à Casablanca',
            'head'  => ['Désignation', 'Qté', 'Prix unitaire HT', 'Total HT'],
            'rows'  => [
                ['Création d\'un site vitrine', '1', '8 000,00 DH', '8 000,00 DH'],
                ['Maintenance mensuelle', '3', '500,00 DH', '1 500,00 DH'],
            ],
            'totals' => [
                ['Total HT', '9 500,00 DH'],
                ['TVA 20 %', '1 900,00 DH'],
                ['Total TTC', '11 400,00 DH'],
            ],
            'caption' => 'Client : Atlas Conseil SARL, ICE renseigné. Fakturalista calcule la TVA et les totaux, et attribue le numéro de facture à l\'émission.',
        ],
        'faq' => [
            ['Fakturalista convient-il aux auto-entrepreneurs ?', 'Oui. Vous pouvez émettre des factures sans TVA en choisissant le traitement « exonéré » sur vos lignes, ou en le définissant par défaut dans vos paramètres. Vérifiez avec votre comptable la mention exacte qui s\'applique à votre situation.'],
            ['Existe-t-il une version gratuite ?', 'Fakturalista n\'a pas de formule gratuite permanente, mais vous pouvez l\'essayer gratuitement pendant :days jours, sans carte bancaire et avec toutes les fonctionnalités.'],
            ['Mes factures seront-elles conformes à la réglementation marocaine ?', 'Fakturalista reprend les éléments demandés par l\'article 145 du CGI que vous renseignez : ICE, IF et RC, numérotation continue, TVA détaillée par taux, totaux HT et TTC. Le numéro de taxe professionnelle n\'a pas encore de champ dédié. La conformité dépend aussi des informations saisies et de votre situation : faites valider vos documents par votre comptable. Fakturalista n\'est pas connecté à une plateforme de la DGI.'],
            ['Puis-je utiliser Fakturalista en arabe ?', 'Oui. L\'interface est disponible en arabe, en français et en anglais.'],
            ['Mes données sont-elles en sécurité ?', 'Chaque entreprise dispose de sa propre base de données, et seuls vous et les utilisateurs que vous invitez accèdent à votre compte.'],
            ['Puis-je résilier à tout moment ?', 'Oui. L\'abonnement est sans engagement et se résilie depuis votre espace.'],
        ],
        'related' => [
            ['Logiciel de devis et factures', '/ma/logiciel-devis-facture'],
            ['Facturation électronique au Maroc', '/ma/facturation-electronique'],
            ['Comment choisir un logiciel de facturation au Maroc', '/blog/meilleur-logiciel-facturation-maroc-comment-choisir'],
            ['Suivre les factures impayées et relancer ses clients', '/blog/suivre-factures-impayees-relancer-clients'],
        ],
        'cta_title' => 'Créez votre première facture aujourd\'hui',
        'cta_text'  => 'Essayez Fakturalista gratuitement pendant :days jours. Aucune carte bancaire n\'est demandée.',
    ],

    // ── /ma/logiciel-devis-facture ───────────────────────────────────────
    'ma_quotes' => [
        'nav_label'        => 'Logiciel de devis et factures',
        'meta_title'       => 'Logiciel devis et factures au Maroc : du devis au paiement',
        'meta_description' => 'Créez des devis professionnels en dirhams, faites-les accepter en ligne par vos clients et transformez-les en factures en un clic. Essai gratuit de :days jours.',
        'eyebrow'          => 'Devis et factures',
        'h1'               => 'Devis et factures au même endroit, du premier contact au paiement',
        'intro'            => 'Préparez un devis clair, partagez-le avec votre client, laissez-le l\'accepter en ligne, puis transformez-le en facture sans ressaisie. Fakturalista garde l\'historique de chaque client et vous alerte quand une facture arrive à échéance.',
        'highlights' => [
            ['Devis en quelques minutes', 'Catalogue de produits et services, lignes libres, remises et TVA calculée automatiquement.'],
            ['Acceptation en ligne', 'Votre client accepte ou refuse le devis depuis son portail ; vous voyez le statut immédiatement.'],
            ['Conversion en un clic', 'Le devis accepté devient une facture avec les mêmes lignes et les mêmes prix.'],
            ['Historique par client', 'Devis, factures et paiements de chaque client regroupés sur sa fiche.'],
        ],
        'sections' => [
            [
                'h2'    => 'Du devis à la facture, étape par étape',
                'steps' => [
                    ['Préparez le devis', 'Ajoutez vos lignes depuis le catalogue ou en texte libre, indiquez les quantités, les remises, le taux de TVA, la date de validité et vos conditions de paiement.'],
                    ['Envoyez-le', 'Téléchargez le PDF ou envoyez-le par e-mail depuis Fakturalista. Vous pouvez aussi ouvrir un portail client sécurisé.'],
                    ['Recevez la réponse', 'Votre client accepte ou refuse le devis depuis son portail, sans créer de compte. La date de sa décision est enregistrée.'],
                    ['Facturez', 'Convertissez le devis accepté en facture. Le numéro définitif est attribué au moment où vous émettez la facture.'],
                    ['Suivez le paiement', 'Vous êtes alerté par e-mail avant l\'échéance et en cas de retard, puis vous enregistrez le paiement reçu.'],
                ],
            ],
            [
                'h2'         => 'Pourquoi relier devis et factures dans un seul outil ?',
                'paragraphs' => [
                    'Quand les devis vivent dans un fichier et les factures dans un autre, chaque conversion est une occasion d\'erreur : un prix modifié, une ligne oubliée, une remise appliquée deux fois. En reliant les deux, la facture reprend exactement ce que le client a accepté.',
                    'Vous savez aussi à tout moment quels devis attendent une réponse, lesquels ont été acceptés et lesquels restent à facturer.',
                ],
            ],
            [
                'h2'         => 'Ce qu\'un bon devis doit contenir',
                'paragraphs' => ['Un devis précis évite les malentendus et accélère la décision du client. Vérifiez au minimum :'],
                'list' => [
                    'l\'identité et les identifiants de votre entreprise (ICE, IF, RC) ;',
                    'le nom du client et, s\'il s\'agit d\'une entreprise, son ICE ;',
                    'une description précise de chaque prestation ou produit, avec quantités et prix unitaires HT ;',
                    'les remises éventuelles, le taux et le montant de la TVA, le total TTC ;',
                    'la durée de validité du devis et vos conditions de paiement.',
                ],
                'link' => ['Devis et factures : les erreurs à éviter', '/blog/devis-factures-erreurs-tpe-maroc'],
            ],
            [
                'h2'         => 'Des documents à votre image',
                'paragraphs' => [
                    'Ajoutez votre logo et personnalisez le modèle de vos devis et factures. Vos clients reçoivent des documents cohérents, que ce soit en PDF par e-mail ou depuis leur portail.',
                ],
            ],
        ],
        'example' => [
            'title' => 'Exemple : un devis d\'agence à Rabat',
            'head'  => ['Désignation', 'Qté', 'Prix unitaire HT', 'Total HT'],
            'rows'  => [
                ['Création d\'une identité visuelle', '1', '6 500,00 DH', '6 500,00 DH'],
                ['Déclinaison des supports (cartes, en-têtes)', '1', '2 000,00 DH', '2 000,00 DH'],
                ['Remise commerciale 10 %', '', '', '- 850,00 DH'],
            ],
            'totals' => [
                ['Total HT', '7 650,00 DH'],
                ['TVA 20 %', '1 530,00 DH'],
                ['Total TTC', '9 180,00 DH'],
            ],
            'caption' => 'Devis valable 30 jours, paiement à 30 jours fin de prestation. Une fois accepté en ligne, il devient une facture en un clic.',
        ],
        'faq' => [
            ['Mon client doit-il créer un compte pour accepter un devis ?', 'Non. Vous lui partagez un lien sécurisé vers son portail, depuis lequel il consulte ses documents et accepte ou refuse le devis.'],
            ['Puis-je réutiliser un devis pour un autre client ?', 'Oui. Vous pouvez dupliquer un devis ou une facture existante, puis changer le client et les lignes.'],
            ['Que se passe-t-il quand je convertis un devis en facture ?', 'La facture reprend le client, les lignes, les remises et la TVA du devis. Le devis reste rattaché à la facture dans votre historique.'],
            ['Puis-je indiquer une date de validité et des conditions de paiement ?', 'Oui. Chaque devis comporte une date d\'expiration et un champ pour vos conditions de paiement.'],
            ['Puis-je tester gratuitement ?', 'Oui, pendant :days jours, sans carte bancaire.'],
        ],
        'related' => [
            ['Logiciel de facturation au Maroc', '/ma/logiciel-facturation'],
            ['Facturation BTP et artisans', '/ma/facturation-btp'],
            ['Créer et gérer ses devis et factures en ligne', '/blog/creer-gerer-devis-factures-en-ligne'],
            ['Les erreurs à éviter sur vos devis et factures', '/blog/devis-factures-erreurs-tpe-maroc'],
        ],
        'cta_title' => 'Envoyez votre prochain devis avec Fakturalista',
        'cta_text'  => ':days jours d\'essai gratuit, sans carte bancaire. Vos devis, factures et clients au même endroit.',
    ],

    // ── /ma/facturation-electronique ─────────────────────────────────────
    'ma_einvoicing' => [
        'nav_label'        => 'Facturation électronique au Maroc',
        'meta_title'       => 'Facturation électronique au Maroc : où en est-on ?',
        'meta_description' => 'Ce que prévoit le CGI sur la facturation électronique au Maroc, ce qui reste à préciser par la DGI, et comment structurer vos factures dès aujourd\'hui.',
        'eyebrow'          => 'Facturation électronique',
        'h1'               => 'Facturation électronique au Maroc : comprendre et se préparer',
        'intro'            => 'La facturation électronique fait l\'objet d\'annonces et d\'articles de plus en plus nombreux au Maroc. Cette page fait le point sur ce qui est établi, sur ce que les entreprises doivent encore surveiller, et sur ce que Fakturalista permet, et ne permet pas encore, aujourd\'hui.',
        'highlights' => [
            ['Une base légale existante', 'Le CGI prévoit depuis la loi de finances 2018 le recours à un système de facturation répondant aux critères de l\'administration.'],
            ['Des modalités à confirmer', 'Calendrier, seuils et spécifications techniques : seules les publications officielles de la DGI font foi.'],
            ['Des données à structurer dès maintenant', 'Identifiants, numérotation continue et TVA détaillée sont la base de toute facture électronique.'],
        ],
        'sections' => [
            [
                'h2'         => 'Ce que prévoit le cadre légal',
                'paragraphs' => [
                    'La loi de finances 2018 a introduit au paragraphe IX de l\'article 145 du Code général des impôts une disposition qui permet à l\'administration fiscale d\'imposer aux contribuables l\'utilisation d\'un système de facturation répondant à des critères techniques qu\'elle fixe.',
                    'En janvier 2019, la Direction générale des impôts avait précisé que cette mesure n\'entrerait en vigueur qu\'après la publication d\'un texte réglementaire, et qu\'elle serait mise en œuvre de manière progressive, en concertation avec les professionnels de chaque secteur.',
                ],
            ],
            [
                'h2'         => 'Ce qui est annoncé, et ce qui reste à confirmer',
                'paragraphs' => [
                    'Depuis 2025, de nombreuses publications spécialisées font état d\'un déploiement progressif à partir de 2026, en commençant par les plus grandes entreprises, avec des factures qui transiteraient par une plateforme de l\'administration. Les dates, les seuils et les détails techniques diffèrent d\'une source à l\'autre.',
                    'Avant de prendre une décision, vérifiez le calendrier qui s\'applique à votre entreprise sur le portail de la DGI (tax.gov.ma) ou auprès de votre expert-comptable. Méfiez-vous des offres qui se disent déjà « certifiées DGI » sans référence officielle.',
                ],
            ],
            [
                'h2'         => 'Ce que vous pouvez faire dès maintenant',
                'list' => [
                    'Renseigner l\'ICE, l\'IF et le RC de votre entreprise, et l\'ICE de vos clients professionnels.',
                    'Utiliser une numérotation continue et ne jamais modifier une facture émise : corrigez-la par un document rectificatif.',
                    'Détailler la TVA par taux (20 % et 10 % depuis la réforme engagée par la loi de finances 2024) et isoler les opérations exonérées.',
                    'Centraliser vos factures dans un outil plutôt que dans des fichiers Word ou Excel dispersés.',
                    'Conserver vos factures pendant la durée prévue par le CGI (dix ans).',
                ],
            ],
            [
                'h2'    => 'Ce que fait Fakturalista aujourd\'hui',
                'items' => [
                    ['Export UBL 2.1', 'Chaque facture peut être exportée en XML au format UBL 2.1, un standard international de facture structurée. Le fichier est validé contre le schéma officiel OASIS avant téléchargement.'],
                    ['Données structurées', 'ICE, IF et RC, numérotation séquentielle à l\'émission, TVA ventilée par taux, factures émises verrouillées et corrections par document rectificatif.'],
                    ['Partage avec vos clients', 'Depuis le portail client, vos clients téléchargent leurs factures en PDF et au format UBL.'],
                ],
            ],
            [
                'h2'         => 'Ce que Fakturalista ne fait pas encore',
                'paragraphs' => [
                    'Fakturalista n\'est pas connecté à une plateforme de la DGI et ne transmet pas vos factures à l\'administration. Le format exact qui sera exigé au Maroc (profil UBL, signature électronique, éventuel QR code, mode de transmission) n\'est pas encore pris en charge. Nous ferons évoluer l\'outil lorsque des spécifications officielles applicables seront publiées, et nous l\'indiquerons clairement sur cette page.',
                ],
                'link' => ['Lire notre guide sur la facturation électronique', '/blog/facturation-electronique-maroc-ce-qu-il-faut-savoir'],
            ],
        ],
        'faq' => [
            ['Fakturalista est-il certifié par la DGI ?', 'Non. Fakturalista propose un export au format standard UBL 2.1, mais n\'est ni certifié ni connecté à une plateforme de l\'administration fiscale.'],
            ['Qu\'est-ce que le format UBL 2.1 ?', 'UBL (Universal Business Language) est un standard international, publié par l\'organisation OASIS, qui décrit une facture sous forme de données structurées (XML) lisibles par un logiciel, et pas seulement par une personne.'],
            ['Un export UBL suffit-il pour respecter la future obligation ?', 'Pas nécessairement. Le format, la signature et le mode de transmission exigés au Maroc doivent être précisés par la DGI. Un export UBL est une bonne base, pas une garantie de conformité.'],
            ['Quand la facturation électronique sera-t-elle obligatoire pour mon entreprise ?', 'Cela dépend du calendrier officiel et de la taille de votre entreprise. Consultez les publications de la DGI sur tax.gov.ma ou votre expert-comptable.'],
        ],
        'sources' => [
            ['Direction générale des impôts, portail officiel', 'https://www.tax.gov.ma'],
            ['Challenge, « Facturation électronique : les précisions de la DGI » (9 janvier 2019)', 'https://archive.challenge.ma/facturation-electroniques-les-precisions-de-la-dgi-103232/'],
            ['Upsilon Consulting, « Réforme TVA Maroc 2024-2026 : calendrier et nouveaux taux »', 'https://upsilon-consulting.com/reforme-tva-maroc-2024-2026/'],
        ],
        'related' => [
            ['Logiciel de facturation au Maroc', '/ma/logiciel-facturation'],
            ['Facturation électronique au Maroc : ce que les entreprises doivent savoir', '/blog/facturation-electronique-maroc-ce-qu-il-faut-savoir'],
            ['Comment créer une facture professionnelle au Maroc', '/blog/creer-facture-professionnelle-maroc'],
        ],
        'cta_title' => 'Structurez votre facturation dès aujourd\'hui',
        'cta_text'  => 'Numérotation continue, TVA par taux, ICE et export UBL 2.1. Essai gratuit de :days jours, sans carte bancaire.',
    ],

    // ── /ma/facturation-btp ──────────────────────────────────────────────
    'ma_btp' => [
        'nav_label'        => 'Facturation BTP et artisans',
        'meta_title'       => 'Logiciel de facturation BTP au Maroc : devis et factures',
        'meta_description' => 'Devis par poste, TVA, acceptation en ligne, conversion en facture et suivi des retards : la facturation des entreprises du BTP et des artisans au Maroc.',
        'eyebrow'          => 'BTP et artisans',
        'h1'               => 'Facturation BTP et artisans : des devis clairs, des factures sans ressaisie',
        'intro'            => 'Entreprises du bâtiment, plombiers, électriciens, menuisiers ou peintres : vos devis comptent souvent de nombreux postes, et vos chantiers évoluent. Fakturalista vous aide à chiffrer proprement, à faire valider vos devis et à suivre les paiements de chaque chantier.',
        'highlights' => [
            ['Devis par poste', 'Fournitures, main-d\'œuvre et prestations forfaitaires sur des lignes séparées, avec remises et TVA.'],
            ['Catalogue de prix', 'Enregistrez vos prestations et fournitures courantes pour les réutiliser d\'un devis à l\'autre.'],
            ['Validation en ligne', 'Votre client accepte le devis depuis son portail, la date de sa décision est enregistrée.'],
            ['Retards repérés', 'Alertes avant échéance et en cas de retard, vue claire des factures impayées.'],
        ],
        'sections' => [
            [
                'h2'         => 'Les difficultés de facturation propres au BTP',
                'paragraphs' => [
                    'Un devis de rénovation ou de second œuvre mélange souvent fournitures, main-d\'œuvre, location de matériel et forfaits. Les quantités changent en cours de chantier, le client demande des variantes, et le paiement arrive parfois bien après la fin des travaux.',
                    'Sans outil, il faut recopier le devis dans la facture, recalculer la TVA et suivre les règlements dans un carnet ou un tableur. C\'est là que naissent les oublis et les écarts.',
                ],
            ],
            [
                'h2'    => 'Comment Fakturalista vous aide',
                'items' => [
                    ['Chiffrer rapidement', 'Combinez lignes de catalogue et lignes libres, précisez l\'unité dans la désignation (m², ml, forfait) et appliquez une remise par ligne ou globale.'],
                    ['Réutiliser vos devis', 'Dupliquez un devis de chantier similaire et ajustez seulement les quantités.'],
                    ['Faire valider sans papier', 'Partagez le devis par e-mail ou via le portail client ; votre client l\'accepte en ligne.'],
                    ['Facturer sans ressaisie', 'Le devis accepté devient une facture en un clic, avec les mêmes lignes et la même TVA.'],
                    ['Suivre les encaissements', 'Recevez une alerte avant l\'échéance et en cas de retard, puis enregistrez le paiement reçu.'],
                ],
            ],
            [
                'h2'         => 'Acomptes, situations de travaux : comment faire aujourd\'hui',
                'paragraphs' => [
                    'Fakturalista ne propose pas encore de module dédié aux situations de travaux, à la retenue de garantie ou aux paiements partiels sur une même facture. Pour un acompte, émettez une facture distincte (par exemple « Acompte de 30 % sur le devis n° … »), puis une facture de solde en fin de chantier, en déduisant l\'acompte déjà facturé. Faites valider ce schéma par votre comptable.',
                ],
            ],
            [
                'h2'         => 'Délais de paiement : ce que prévoit la loi',
                'paragraphs' => [
                    'La loi n° 69-21, qui a modifié le Code de commerce, encadre les délais de paiement entre professionnels : à défaut d\'accord, le délai est de 60 jours à compter de la date de facture, et un délai convenu ne peut pas dépasser 120 jours. Les entreprises dont le chiffre d\'affaires dépasse 2 millions de dirhams doivent en outre déclarer les factures payées hors délai auprès de la DGI.',
                    'Émettre vos factures dès la fin des travaux, avec une date d\'échéance claire, et relancer au bon moment reste le meilleur moyen de protéger votre trésorerie.',
                ],
                'link' => ['Suivre ses factures impayées et relancer ses clients', '/blog/suivre-factures-impayees-relancer-clients'],
            ],
        ],
        'example' => [
            'title' => 'Exemple : un devis de rénovation de salle de bain à Marrakech',
            'head'  => ['Désignation', 'Qté', 'Prix unitaire HT', 'Total HT'],
            'rows'  => [
                ['Dépose et évacuation de l\'existant (forfait)', '1', '2 500,00 DH', '2 500,00 DH'],
                ['Fourniture carrelage (m²)', '12', '180,00 DH', '2 160,00 DH'],
                ['Pose carrelage (m²)', '12', '150,00 DH', '1 800,00 DH'],
                ['Plomberie et raccordements (forfait)', '1', '3 200,00 DH', '3 200,00 DH'],
            ],
            'totals' => [
                ['Total HT', '9 660,00 DH'],
                ['TVA 20 %', '1 932,00 DH'],
                ['Total TTC', '11 592,00 DH'],
            ],
            'caption' => 'Le taux de TVA applicable dépend de la nature des travaux et de votre situation : vérifiez-le avec votre comptable.',
        ],
        'faq' => [
            ['Puis-je facturer un acompte ?', 'Oui, en émettant une facture d\'acompte distincte, puis une facture de solde en fin de chantier. Il n\'existe pas encore de module de situations de travaux.'],
            ['Mon client peut-il accepter le devis en ligne ?', 'Oui. Depuis son portail, il consulte le devis, le télécharge en PDF et l\'accepte ou le refuse.'],
            ['Puis-je reprendre un ancien devis ?', 'Oui. Dupliquez un devis existant et modifiez le client, les lignes ou les quantités.'],
            ['Fakturalista gère-t-il les fiches de chantier ou le planning ?', 'Non. Fakturalista est un outil de devis, de facturation et de suivi des paiements ; il ne gère pas le planning ni les stocks.'],
            ['Combien de temps puis-je essayer gratuitement ?', 'Pendant :days jours, sans carte bancaire.'],
        ],
        'sources' => [
            ['Hespress FR, « Maroc : fin des mauvais payeurs ? La loi 69.21 impose son tempo »', 'https://fr.hespress.com/402650-402650.html'],
            ['Maroc Hebdo, « Délais de paiement : la DGI rappelle l\'obligation de dépôt de la déclaration » (mars 2026)', 'https://www.maroc-hebdo.com/article/delais-de-paiement-la-dgi-rappelle-lobligation-de-depot-de-la-declaration-avant-le-1er-avril-2026'],
        ],
        'related' => [
            ['Logiciel de devis et factures', '/ma/logiciel-devis-facture'],
            ['Logiciel de facturation au Maroc', '/ma/logiciel-facturation'],
            ['Devis et factures : les erreurs à éviter', '/blog/devis-factures-erreurs-tpe-maroc'],
        ],
        'cta_title' => 'Chiffrez votre prochain chantier avec Fakturalista',
        'cta_text'  => 'Essai gratuit de :days jours, sans carte bancaire. Devis, factures et suivi des paiements au même endroit.',
    ],
];
