<?php

return [
    'slug'             => 'passer-excel-logiciel-facturation',
    'title'            => 'Comment passer d\'Excel à un logiciel de facturation ?',
    'meta_title'       => 'Passer d\'Excel à un logiciel de facturation en 7 étapes',
    'meta_description' => 'Date de bascule, clients, catalogue, numérotation, archivage : la méthode pour quitter Excel ou Word sans perdre le fil de votre facturation.',
    'excerpt'          => 'Quitter Excel ne demande pas un grand projet : une date de bascule, des données propres et une règle claire pour la numérotation suffisent. Voici la méthode, étape par étape.',
    'body'             => <<<'HTML'
<p>Excel et Word rendent service au démarrage. Mais à partir de quelques dizaines de factures, les limites apparaissent : numéros en double, formules cassées, aucune vue claire des impayés, fichiers éparpillés entre plusieurs ordinateurs. Passer à un logiciel de facturation se fait en quelques heures si l'on suit un ordre précis.</p>

<h2>Étape 1 : choisir une date de bascule</h2>
<p>Choisissez un début de mois, ou mieux, le début d'un trimestre. Toutes les factures émises avant cette date restent dans vos anciens fichiers ; toutes celles émises après sont créées dans le nouvel outil. Évitez de faire cohabiter les deux systèmes pour de nouvelles factures : c'est la meilleure façon de créer des doublons de numéros.</p>

<h2>Étape 2 : décider de la règle de numérotation</h2>
<p>La numérotation des factures doit rester continue et chronologique. Deux approches existent :</p>
<ul>
<li><strong>poursuivre la séquence existante</strong>, si le logiciel permet de fixer le prochain numéro ;</li>
<li><strong>ouvrir une nouvelle série clairement distincte</strong>, avec un préfixe différent (par exemple « FA-2026- »), en conservant l'ancienne série close à son dernier numéro.</li>
</ul>
<p>Dans les deux cas, notez la règle retenue et le dernier numéro de l'ancienne série, et validez le choix avec votre comptable avant la bascule.</p>

<h2>Étape 3 : nettoyer votre liste de clients</h2>
<p>Ne transférez que les clients actifs. Pour chacun, vérifiez la raison sociale exacte, l'adresse et, pour les entreprises, l'<strong>ICE</strong>. C'est le moment de compléter les identifiants manquants : un ICE absent sur une facture B2B est une source fréquente de refus de facture.</p>

<h2>Étape 4 : construire votre catalogue</h2>
<p>Listez les produits et services que vous facturez le plus souvent, avec une désignation claire, un prix unitaire hors taxe et le taux de TVA applicable (20 %, 10 % ou exonéré). Un catalogue de 10 à 30 lignes couvre souvent l'essentiel de l'activité d'une TPE.</p>

<h2>Étape 5 : paramétrer vos informations d'entreprise et votre modèle</h2>
<p>Renseignez vos identifiants (ICE, IF, RC…), votre logo, vos coordonnées bancaires et vos conditions de paiement par défaut. Créez ensuite une facture de test, téléchargez le PDF et vérifiez chaque mention avec <a href="/blog/creer-facture-professionnelle-maroc">notre liste des mentions obligatoires</a>.</p>

<h2>Étape 6 : reprendre les factures encore ouvertes</h2>
<p>Les factures impayées émises avant la bascule restent dans votre ancien système : ne les recréez pas dans le nouvel outil avec un nouveau numéro. Tenez une courte liste de ces factures ouvertes et suivez-les jusqu'à leur paiement. Au bout de quelques semaines, tout votre suivi se fera dans le logiciel.</p>

<h2>Étape 7 : archiver l'historique</h2>
<p>Exportez vos anciens fichiers en PDF, rangez-les par année dans un espace sauvegardé et gardez-les accessibles : le CGI impose de conserver les documents comptables pendant dix ans.</p>

<h2>Combien de temps prévoir ?</h2>
<table>
<thead><tr><th>Tâche</th><th>Temps indicatif pour une TPE</th></tr></thead>
<tbody>
<tr><td>Paramétrage de l'entreprise et du modèle</td><td>30 minutes</td></tr>
<tr><td>Saisie de 20 à 50 clients actifs</td><td>1 à 2 heures</td></tr>
<tr><td>Catalogue de 10 à 30 produits ou services</td><td>30 minutes à 1 heure</td></tr>
<tr><td>Facture de test et vérification</td><td>15 minutes</td></tr>
</tbody>
</table>
<p><em>Estimations indicatives, à adapter à votre volume.</em></p>

<h2>Ce que vous gagnez ensuite</h2>
<ul>
<li>des totaux et une TVA toujours justes, sans formule à maintenir ;</li>
<li>une numérotation attribuée automatiquement, sans doublon ;</li>
<li>la conversion des devis en factures sans ressaisie ;</li>
<li>une vue immédiate des factures payées, en attente et en retard ;</li>
<li>des exports propres pour votre comptable.</li>
</ul>

<h2>Faire la bascule avec Fakturalista</h2>
<p>Dans Fakturalista, la numérotation des factures repose sur une série dont vous choisissez le préfixe : la méthode la plus simple est d'ouvrir une nouvelle série distincte de vos anciennes factures Excel. Fakturalista ne propose pas d'import automatique de fichiers : vos clients et votre catalogue se saisissent depuis l'interface, ce qui est aussi l'occasion de les nettoyer. Vous pouvez ensuite exporter vos listes vers Excel à tout moment.</p>
<p><a href="/ma/logiciel-facturation">Découvrir le logiciel de facturation</a> ou <a href="/register">commencer l'essai gratuit</a>.</p>

<h2>Sources</h2>
<ul>
<li>Direction générale des impôts, Code général des impôts (articles 145 et 211) : <a href="https://www.tax.gov.ma" target="_blank" rel="noopener">tax.gov.ma</a></li>
</ul>
HTML,
];
