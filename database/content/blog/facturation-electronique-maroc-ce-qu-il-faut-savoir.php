<?php

return [
    'slug'             => 'facturation-electronique-maroc-ce-qu-il-faut-savoir',
    'title'            => 'Facturation électronique au Maroc : ce que les entreprises doivent savoir',
    'meta_title'       => 'Facturation électronique au Maroc : ce qu\'il faut savoir',
    'meta_description' => 'Base légale (article 145-IX du CGI), précisions de la DGI, annonces à vérifier et préparation concrète : l\'essentiel sur la facture électronique au Maroc.',
    'excerpt'          => 'Entre textes officiels, annonces et promesses commerciales, il est difficile de s\'y retrouver. Ce guide distingue ce qui est établi de ce qui reste à confirmer, et ce que vous pouvez préparer dès maintenant.',
    'body'             => <<<'HTML'
<p>La facturation électronique est l'un des sujets les plus commentés par les éditeurs de logiciels au Maroc. Le problème : les articles se contredisent sur les dates et les obligations, et certains outils se présentent déjà comme « conformes » sans référence officielle. Ce guide fait le tri.</p>
<blockquote>Mise à jour : octobre 2026. Les modalités évoluent ; seules les publications officielles de la Direction générale des impôts (DGI) font foi.</blockquote>

<h2>Facture PDF et facture électronique : ce n'est pas la même chose</h2>
<p>Une facture envoyée en PDF par e-mail est une facture « dématérialisée » : elle est numérique, mais c'est une image d'un document papier, lue par une personne. Une facture électronique, au sens des réformes menées dans de nombreux pays, est un fichier de <strong>données structurées</strong> (par exemple au format XML), qu'un logiciel peut lire automatiquement, et qui transite souvent par une plateforme contrôlée par l'administration.</p>

<h2>Ce que prévoit le cadre légal</h2>
<p>La loi de finances 2018 a introduit au paragraphe IX de l'article 145 du Code général des impôts une disposition permettant à l'administration fiscale d'imposer aux contribuables l'utilisation d'un système de facturation répondant à des critères techniques qu'elle définit.</p>
<p>En janvier 2019, face aux inquiétudes des commerçants, la DGI avait précisé que la mesure n'était pas encore entrée en vigueur, qu'elle nécessitait la publication d'un texte réglementaire, et qu'elle serait déployée de manière progressive, secteur par secteur, en concertation avec les associations professionnelles.</p>

<h2>Ce qui est annoncé aujourd'hui</h2>
<p>Depuis 2025, de nombreuses publications, souvent issues d'éditeurs ou de cabinets, décrivent un déploiement progressif à partir de 2026 : d'abord les plus grandes entreprises, ensuite les PME, puis les plus petites structures, avec un modèle dans lequel la facture serait validée par une plateforme de l'administration. Les dates, les seuils de chiffre d'affaires et le nom des plateformes varient d'une source à l'autre.</p>
<p>Notre conseil est simple : avant toute décision ou tout achat, vérifiez le calendrier et le format qui s'appliquent à votre entreprise directement sur <a href="https://www.tax.gov.ma" target="_blank" rel="noopener">tax.gov.ma</a> ou auprès de votre expert-comptable.</p>

<h2>Les deux grands modèles, pour comprendre les annonces</h2>
<ul>
<li><strong>Modèle de validation préalable (« clearance »)</strong> : la facture est transmise à la plateforme de l'administration, qui la valide avant qu'elle soit remise au client.</li>
<li><strong>Modèle de déclaration (« reporting »)</strong> : la facture est échangée entre entreprises, et les données sont transmises à l'administration en parallèle ou après coup.</li>
</ul>
<p>Le choix du modèle a des conséquences pratiques : délai d'émission, gestion des rejets, identifiants obligatoires. C'est précisément ce que les spécifications officielles doivent préciser.</p>

<h2>Comment se préparer dès maintenant</h2>
<p>Quel que soit le calendrier, une facture électronique repose sur des données propres. Vous pouvez dès aujourd'hui :</p>
<ol>
<li><strong>fiabiliser vos identifiants</strong> : ICE, IF et RC de votre entreprise, ICE de chaque client professionnel ;</li>
<li><strong>sortir des fichiers Word et Excel</strong> : un outil qui stocke vos factures sous forme de données (et pas seulement de PDF) facilitera toute transition (voir <a href="/blog/passer-excel-logiciel-facturation">comment passer d'Excel à un logiciel</a>) ;</li>
<li><strong>respecter une numérotation continue</strong> et corriger par document rectificatif plutôt que par modification ;</li>
<li><strong>détailler la TVA par taux</strong> (20 % et 10 % depuis la réforme engagée par la loi de finances 2024) et identifier les opérations exonérées ;</li>
<li><strong>conserver vos factures</strong> pendant la durée prévue par le CGI, soit dix ans ;</li>
<li><strong>interroger votre éditeur</strong> : quel format produit-il ? Prévoit-il une connexion à la plateforme officielle ? Sur quelle base affirme-t-il être conforme ?</li>
</ol>

<h2>Qu'est-ce que le format UBL 2.1 ?</h2>
<p>UBL (Universal Business Language) est un standard international, publié par l'organisation OASIS, qui décrit une facture sous forme de données XML structurées : vendeur, client, lignes, taxes, totaux. De nombreux dispositifs de facturation électronique dans le monde s'appuient sur UBL ou sur des formats proches. Produire un fichier UBL valide est une bonne base technique, mais ce n'est pas, à lui seul, une garantie de conformité à un dispositif national : celui-ci peut ajouter des champs, des règles, une signature ou un mode de transmission propres.</p>

<h2>Ce que fait Fakturalista, et ce qu'il ne fait pas</h2>
<p>Fakturalista exporte chaque facture au format UBL 2.1, validé contre le schéma officiel OASIS avant le téléchargement, et structure déjà vos données : identifiants marocains, numérotation séquentielle à l'émission, TVA ventilée par taux, factures verrouillées après émission. En revanche, Fakturalista <strong>n'est pas connecté</strong> à une plateforme de la DGI, ne transmet pas vos factures à l'administration et ne se présente pas comme « certifié ». Nous adapterons l'outil lorsque des spécifications officielles applicables seront publiées.</p>
<p>Pour le détail, consultez notre page <a href="/ma/facturation-electronique">facturation électronique au Maroc</a>, ou <a href="/register">testez Fakturalista gratuitement</a>.</p>

<h2>Questions fréquentes</h2>
<h3>Mes factures PDF actuelles sont-elles encore valables ?</h3>
<p>Tant qu'aucune obligation ne s'applique à votre entreprise, une facture comportant les mentions de l'article 145 du CGI reste la référence. Vérifiez votre situation auprès de la DGI ou de votre comptable.</p>
<h3>Dois-je changer de logiciel tout de suite ?</h3>
<p>Pas forcément. Commencez par fiabiliser vos données et demandez à votre éditeur actuel sa feuille de route, en exigeant des références officielles.</p>

<h2>Sources</h2>
<ul>
<li>Direction générale des impôts, portail officiel : <a href="https://www.tax.gov.ma" target="_blank" rel="noopener">tax.gov.ma</a></li>
<li>Challenge, « Facturation électronique : les précisions de la DGI » (9 janvier 2019) : <a href="https://archive.challenge.ma/facturation-electroniques-les-precisions-de-la-dgi-103232/" target="_blank" rel="noopener">archive.challenge.ma</a></li>
<li>OASIS, Universal Business Language (UBL) 2.1 : <a href="https://docs.oasis-open.org/ubl/os-UBL-2.1/UBL-2.1.html" target="_blank" rel="noopener">docs.oasis-open.org</a></li>
<li>Upsilon Consulting, « Réforme TVA Maroc 2024-2026 » : <a href="https://upsilon-consulting.com/reforme-tva-maroc-2024-2026/" target="_blank" rel="noopener">upsilon-consulting.com</a></li>
</ul>
HTML,
];
