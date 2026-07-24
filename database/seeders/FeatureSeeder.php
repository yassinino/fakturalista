<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['slug' => 'pdf_export',          'name_fr' => 'Export PDF',                    'name_en' => 'PDF export',                    'name_es' => 'Exportación PDF',              'sort_order' => 0],
            ['slug' => 'email_send',           'name_fr' => 'Envoi par email',               'name_en' => 'Email delivery',                'name_es' => 'Envío por email',              'sort_order' => 1],
            ['slug' => 'recurring_invoices',   'name_fr' => 'Factures récurrentes',          'name_en' => 'Recurring invoices',            'name_es' => 'Facturas recurrentes',         'sort_order' => 2],
            ['slug' => 'payment_reminders',    'name_fr' => 'Relances automatiques',         'name_en' => 'Automatic reminders',           'name_es' => 'Recordatorios automáticos',    'sort_order' => 3],
            ['slug' => 'dashboard',            'name_fr' => 'Tableau de bord avancé',        'name_en' => 'Advanced dashboard',            'name_es' => 'Panel avanzado',               'sort_order' => 4],
            ['slug' => 'stripe',               'name_fr' => 'Paiements en ligne (Stripe)',   'name_en' => 'Online payments (Stripe)',       'name_es' => 'Pagos en línea (Stripe)',      'sort_order' => 5],
            ['slug' => 'api_access',           'name_fr' => 'Accès API',                     'name_en' => 'API access',                    'name_es' => 'Acceso a la API',              'sort_order' => 6],
            ['slug' => 'excel_export',         'name_fr' => 'Export Excel',                  'name_en' => 'Excel export',                  'name_es' => 'Exportación Excel',            'sort_order' => 7],
            ['slug' => 'multi_currency',       'name_fr' => 'Multi-devises',                 'name_en' => 'Multi-currency',                'name_es' => 'Multimoneda',                  'sort_order' => 8],
            ['slug' => 'custom_templates',     'name_fr' => 'Templates personnalisés',       'name_en' => 'Custom templates',              'name_es' => 'Plantillas personalizadas',    'sort_order' => 9],
            ['slug' => 'whatsapp_share',       'name_fr' => 'Partage WhatsApp',              'name_en' => 'WhatsApp sharing',              'name_es' => 'Compartir WhatsApp',           'sort_order' => 10],
        ];

        foreach ($features as $featureData) {
            Feature::on('mysql')->updateOrCreate(['slug' => $featureData['slug']], array_merge($featureData, ['is_active' => true]));
        }

        $this->command->info('✅ Features seeded: ' . count($features) . ' features.');
    }
}
