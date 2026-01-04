<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    public function run(): void
    {


        $plans = [
            [
                'name'  => 'Básico',
                'slug'  => Str::slug('Básico'),
                'amount' => 9.00,
                'currency' => 'eur',
                'interval' => 'month',
                'stripe_price_id' => 'price_basic_xxx',
                'paypal_plan_id'  => 'paypal_basic_xxx',
                'features' => json_encode([
                    'Facturas ilimitadas',
                    '1 usuario',
                    'Hasta 15 clientes',
                    'Soporte por email',
                ]),
                'active' => true,
            ],
            [
                'name'  => 'Profesional',
                'slug'  => Str::slug('Profesional'),
                'amount' => 19.00,
                'currency' => 'eur',
                'interval' => 'month',
                'stripe_price_id' => 'price_profesional_xxx',
                'paypal_plan_id'  => 'paypal_profesional_xxx',
                'features' => json_encode([
                    'Facturas ilimitadas',
                    '3 usuarios del equipo',
                    'Hasta 100 clientes',
                    'Soporte completo',
                ]),
                'active' => true,
            ],
            [
                'name'  => 'Empresa',
                'slug'  => Str::slug('Empresa'),
                'amount' => 29.00,
                'currency' => 'eur',
                'interval' => 'month',
                'stripe_price_id' => 'price_empresa_xxx',
                'paypal_plan_id'  => 'paypal_empresa_xxx',
                'features' => json_encode([
                    'Facturas ilimitadas',
                    '10 usuarios del equipo',
                    'Hasta 1000 clientes',
                    'Soporte prioritario',
                ]),
                'active' => true,
            ],
            [
                'name'  => 'Corporativo',
                'slug'  => Str::slug('Corporativo'),
                'amount' => 99.00,
                'currency' => 'eur',
                'interval' => 'month',
                'stripe_price_id' => 'price_corporativo_xxx',
                'paypal_plan_id'  => 'paypal_corporativo_xxx',
                'features' => json_encode([
                    'Facturas ilimitadas',
                    'Usuarios ilimitados',
                    'Clientes ilimitados',
                    'Soporte dedicado',
                    'Personalización de marca',
                ]),
                'active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }

        $this->command->info('✅ 4 plans créés avec succès (Stripe + PayPal IDs).');
    }
}