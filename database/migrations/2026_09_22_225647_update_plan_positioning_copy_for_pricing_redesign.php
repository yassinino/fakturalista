<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aligns each plan's short_description/badge (both Filament-editable JSON
 * columns) with the positioning tone requested for the pricing redesign -
 * content only, still fully editable in Filament afterwards.
 */
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        $updates = [
            'starter' => [
                'short_description' => [
                    'fr' => 'Tout le nécessaire pour gérer votre facturation simplement.',
                    'en' => 'Everything you need to manage your invoicing simply.',
                    'es' => 'Todo lo necesario para gestionar tu facturación de forma sencilla.',
                ],
            ],
            'pro' => [
                'short_description' => [
                    'fr' => 'Pour les professionnels qui facturent régulièrement et veulent aller plus loin.',
                    'en' => 'For professionals who invoice regularly and want to go further.',
                    'es' => 'Para profesionales que facturan regularmente y quieren ir más lejos.',
                ],
                'badge' => [
                    'fr' => 'Le plus populaire',
                    'en' => 'Most popular',
                    'es' => 'Lo más popular',
                ],
            ],
            'business' => [
                'short_description' => [
                    'fr' => 'Pour les entreprises qui veulent travailler sans limites.',
                    'en' => 'For businesses that want to work without limits.',
                    'es' => 'Para empresas que quieren trabajar sin límites.',
                ],
            ],
        ];

        foreach ($updates as $slug => $fields) {
            foreach ($fields as $column => $translations) {
                DB::connection('mysql')->table('plans')
                    ->where('slug', $slug)
                    ->update([$column => json_encode($translations, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void
    {
        // Content-only copy update - no prior "correct" state to restore.
    }
};
