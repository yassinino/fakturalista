<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the Morocco SEO articles (content in database/content/blog/).
 *
 * Insert-only and idempotent: an article whose slug already exists is left
 * untouched, so edits made later in the Filament backoffice are never
 * overwritten by a re-run. down() removes only the articles this migration
 * created and that were never edited since.
 */
return new class extends Migration
{
    private const ARTICLES = [
        'meilleur-logiciel-facturation-maroc-comment-choisir',
        'creer-facture-professionnelle-maroc',
        'devis-factures-erreurs-tpe-maroc',
        'facturation-electronique-maroc-ce-qu-il-faut-savoir',
        'suivre-factures-impayees-relancer-clients',
        'passer-excel-logiciel-facturation',
        'creer-gerer-devis-factures-en-ligne',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ARTICLES as $i => $slug) {
            if (DB::connection('mysql')->table('posts')->where('slug', $slug)->exists()) {
                continue;
            }

            $article = require database_path("content/blog/{$slug}.php");
            // Stagger by a minute so the blog lists them in this order.
            $publishedAt = $now->copy()->subMinutes($i);

            DB::connection('mysql')->table('posts')->insert([
                'title'            => $article['title'],
                'slug'             => $article['slug'],
                'excerpt'          => $article['excerpt'],
                'body'             => $article['body'],
                'status'           => 'published',
                'published_at'     => $publishedAt,
                'meta_title'       => $article['meta_title'],
                'meta_description' => $article['meta_description'],
                'created_at'       => $publishedAt,
                'updated_at'       => $publishedAt,
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('mysql')->table('posts')
            ->whereIn('slug', self::ARTICLES)
            ->whereColumn('updated_at', 'created_at')
            ->delete();
    }
};
