<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\PlanMarketingItem;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding plans…');

        $plans = [
            [
                'slug'          => 'starter',
                'name'          => ['fr' => 'Starter',  'en' => 'Starter',  'es' => 'Starter'],
                'short_description' => [
                    'fr' => 'Idéal pour démarrer et tester Fakturalista.',
                    'en' => 'Perfect to get started with Fakturalista.',
                    'es' => 'Ideal para empezar con Fakturalista.',
                ],
                'badge'         => ['fr' => '', 'en' => '', 'es' => ''],
                'button_text'   => ['fr' => 'Commencer gratuitement', 'en' => 'Start for free', 'es' => 'Empezar gratis'],
                'button_action' => 'free_trial',
                'color'         => '#6b7280',
                'icon'          => 'heroicon-o-rocket-launch',
                'sort_order'    => 0,
                'is_featured'   => false,
                'monthly_price' => 490,
                'yearly_price'  => 4490,
                'trial_days'    => 14,
                'currency'      => 'eur',
                'active'        => true,
                'limits'        => [
                    'invoices_per_month' => 25,
                    'customers'          => 25,
                    'users'              => 1,
                    'products'           => 50,
                    'quotes'             => 10,
                ],
                'features'      => ['pdf_export', 'email_send'],
                'marketing'     => [
                    ['fr' => '25 factures / mois',       'en' => '25 invoices / month',       'es' => '25 facturas / mes',       'icon' => '✓', 'hl' => false],
                    ['fr' => '25 clients',                'en' => '25 clients',                'es' => '25 clientes',             'icon' => '✓', 'hl' => false],
                    ['fr' => '1 utilisateur',             'en' => '1 user',                    'es' => '1 usuario',               'icon' => '✓', 'hl' => false],
                    ['fr' => 'Création de devis',         'en' => 'Quote creation',            'es' => 'Creación de presupuestos', 'icon' => '✓', 'hl' => false],
                    ['fr' => 'Export PDF professionnel',  'en' => 'Professional PDF export',   'es' => 'Exportación PDF',         'icon' => '✓', 'hl' => false],
                    ['fr' => 'Support par email',         'en' => 'Email support',             'es' => 'Soporte por email',       'icon' => '✓', 'hl' => false],
                ],
            ],
            [
                'slug'          => 'pro',
                'name'          => ['fr' => 'Pro',  'en' => 'Pro',  'es' => 'Pro'],
                'short_description' => [
                    'fr' => 'Pour les freelances et petites équipes qui grandissent.',
                    'en' => 'For freelancers and small teams on the move.',
                    'es' => 'Para freelancers y equipos pequeños en crecimiento.',
                ],
                'badge'         => ['fr' => 'Populaire', 'en' => 'Popular', 'es' => 'Popular'],
                'button_text'   => ['fr' => 'Choisir Pro', 'en' => 'Get Pro', 'es' => 'Elegir Pro'],
                'button_action' => 'checkout',
                'color'         => '#fa7070',
                'icon'          => 'heroicon-o-star',
                'sort_order'    => 1,
                'is_featured'   => true,
                'monthly_price' => 990,
                'yearly_price'  => 8990,
                'trial_days'    => 14,
                'currency'      => 'eur',
                'active'        => true,
                'limits'        => [
                    'invoices_per_month' => 250,
                    'customers'          => 250,
                    'users'              => 5,
                    'products'           => null,
                    'quotes'             => null,
                ],
                'features'      => ['pdf_export', 'email_send', 'recurring_invoices', 'payment_reminders', 'dashboard'],
                'marketing'     => [
                    ['fr' => '250 factures / mois',          'en' => '250 invoices / month',       'es' => '250 facturas / mes',          'icon' => '✓', 'hl' => true],
                    ['fr' => '250 clients',                   'en' => '250 clients',                'es' => '250 clientes',                'icon' => '✓', 'hl' => false],
                    ['fr' => '5 utilisateurs',                'en' => '5 users',                    'es' => '5 usuarios',                  'icon' => '✓', 'hl' => false],
                    ['fr' => 'Factures récurrentes',          'en' => 'Recurring invoices',         'es' => 'Facturas recurrentes',        'icon' => '✓', 'hl' => false],
                    ['fr' => 'Relances de paiement',          'en' => 'Payment reminders',          'es' => 'Recordatorios de pago',       'icon' => '✓', 'hl' => false],
                    ['fr' => 'Tableau de bord complet',       'en' => 'Full dashboard',             'es' => 'Panel completo',              'icon' => '✓', 'hl' => false],
                    ['fr' => 'Produits & devis illimités',    'en' => 'Unlimited products & quotes','es' => 'Productos y presupuestos ilimitados', 'icon' => '✓', 'hl' => false],
                    ['fr' => 'Support prioritaire',           'en' => 'Priority support',           'es' => 'Soporte prioritario',         'icon' => '✓', 'hl' => false],
                ],
            ],
            [
                'slug'          => 'business',
                'name'          => ['fr' => 'Business',  'en' => 'Business',  'es' => 'Business'],
                'short_description' => [
                    'fr' => 'Tout illimité pour les entreprises qui ont besoin de puissance.',
                    'en' => 'Unlimited everything for companies that mean business.',
                    'es' => 'Todo ilimitado para empresas con grandes necesidades.',
                ],
                'badge'         => ['fr' => '', 'en' => '', 'es' => ''],
                'button_text'   => ['fr' => 'Choisir Business', 'en' => 'Get Business', 'es' => 'Elegir Business'],
                'button_action' => 'checkout',
                'color'         => '#1e293b',
                'icon'          => 'heroicon-o-building-office',
                'sort_order'    => 2,
                'is_featured'   => false,
                'monthly_price' => 1990,
                'yearly_price'  => 17900,
                'trial_days'    => 14,
                'currency'      => 'eur',
                'active'        => true,
                'limits'        => [
                    'invoices_per_month' => null,
                    'customers'          => null,
                    'users'              => null,
                    'products'           => null,
                    'quotes'             => null,
                ],
                'features'      => ['pdf_export', 'email_send', 'recurring_invoices', 'payment_reminders', 'dashboard', 'stripe', 'api_access', 'excel_export'],
                'marketing'     => [
                    ['fr' => 'Factures illimitées',           'en' => 'Unlimited invoices',         'es' => 'Facturas ilimitadas',         'icon' => '✓', 'hl' => true],
                    ['fr' => 'Clients illimités',             'en' => 'Unlimited clients',          'es' => 'Clientes ilimitados',         'icon' => '✓', 'hl' => false],
                    ['fr' => 'Utilisateurs illimités',        'en' => 'Unlimited users',            'es' => 'Usuarios ilimitados',         'icon' => '✓', 'hl' => false],
                    ['fr' => 'Paiements en ligne (Stripe)',   'en' => 'Online payments (Stripe)',   'es' => 'Pagos en línea (Stripe)',     'icon' => '⚡', 'hl' => true],
                    ['fr' => 'Accès API',                     'en' => 'API access',                 'es' => 'Acceso a la API',             'icon' => '⚡', 'hl' => false],
                    ['fr' => 'Export Excel et PDF',           'en' => 'Excel & PDF export',         'es' => 'Exportación Excel y PDF',     'icon' => '✓', 'hl' => false],
                    ['fr' => 'Support prioritaire dédié',     'en' => 'Dedicated priority support', 'es' => 'Soporte prioritario dedicado','icon' => '✓', 'hl' => false],
                ],
            ],
        ];

        // Deactivate legacy plans
        Plan::on('mysql')->whereNotIn('slug', array_column($plans, 'slug'))->update(['active' => false]);

        foreach ($plans as $idx => $planData) {
            $limitData    = $planData['limits'];
            $featureSlugs = $planData['features'];
            $marketingData = $planData['marketing'];

            unset($planData['limits'], $planData['features'], $planData['marketing']);

            // Pack JSON columns
            foreach (['name', 'short_description', 'badge', 'button_text'] as $field) {
                if (is_array($planData[$field])) {
                    $planData[$field] = json_encode($planData[$field], JSON_UNESCAPED_UNICODE);
                }
            }

            // Legacy columns kept for backward compat
            $planData['amount']   = $planData['monthly_price'];
            $planData['interval'] = 'month';

            $plan = Plan::on('mysql')->updateOrCreate(['slug' => $planData['slug']], $planData);

            // Sync limits
            foreach ($limitData as $resource => $value) {
                PlanLimit::on('mysql')->updateOrCreate(
                    ['plan_id' => $plan->id, 'resource' => $resource],
                    ['value'   => $value]
                );
            }

            // Sync features
            $featureIds = Feature::on('mysql')
                ->whereIn('slug', $featureSlugs)
                ->pluck('id');
            $plan->features()->sync($featureIds);

            // Sync marketing items
            PlanMarketingItem::on('mysql')->where('plan_id', $plan->id)->delete();
            foreach ($marketingData as $order => $item) {
                PlanMarketingItem::on('mysql')->create([
                    'plan_id'        => $plan->id,
                    'text_fr'        => $item['fr'],
                    'text_en'        => $item['en'],
                    'text_es'        => $item['es'],
                    'icon'           => $item['icon'],
                    'sort_order'     => $order,
                    'is_highlighted' => $item['hl'],
                ]);
            }

            $name = json_decode($plan->getRawOriginal('name'), true)['fr'] ?? $plan->slug;
            $this->command->info("  ✓ Plan '{$name}'");
        }

        $this->command->info('✅ Plans seeded successfully.');
    }
}
