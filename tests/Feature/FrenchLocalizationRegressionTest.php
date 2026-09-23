<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression coverage for the "accidental Spanish under French" audit:
 * a handful of user-facing strings were found hardcoded in Spanish
 * (InvoiceController action messages, RegisterTrialRequest validation
 * messages, homepage demo/mockup content) regardless of the app's
 * active locale. Each was moved to the existing __() translation
 * mechanism - these tests pin the French (and, where relevant, Spanish)
 * output so a future edit can't silently reintroduce a Spanish literal
 * into the French experience without a test failing.
 */
class FrenchLocalizationRegressionTest extends TestCase
{
    // ── InvoiceController action messages (resources/lang/*/invoice.php) ──

    /** @test */
    public function invoice_action_messages_are_french_under_fr_locale_and_never_spanish(): void
    {
        $keys = [
            'actions.created',
            'actions.updated',
            'actions.issued',
            'actions.marked_paid',
            'actions.cancelled',
            'actions.duplicated',
            'actions.printed',
            'actions.deleted',
        ];

        foreach ($keys as $key) {
            $fr = __('invoice.' . $key, [], 'fr');
            $es = __('invoice.' . $key, [], 'es');

            $this->assertNotSame($es, $fr, "invoice.{$key} must not be identical between fr and es (Spanish leaking into French).");
            $this->assertStringNotContainsString('¡', $fr, "invoice.{$key} contains a Spanish inverted exclamation mark under fr locale.");
        }

        $this->assertSame('Facture ajoutée !', __('invoice.actions.created', [], 'fr'));
        $this->assertSame('Facture supprimée !', __('invoice.actions.deleted', [], 'fr'));
        $this->assertSame('¡Factura añadida!', __('invoice.actions.created', [], 'es'));
        $this->assertSame('¡Factura eliminada!', __('invoice.actions.deleted', [], 'es'));
    }

    /** @test */
    public function invoice_mark_paid_and_issue_messages_interpolate_status_correctly_per_locale(): void
    {
        $fr = __('invoice.actions.mark_paid_requires_issued', ['status' => 'draft'], 'fr');
        $es = __('invoice.actions.mark_paid_requires_issued', ['status' => 'draft'], 'es');

        $this->assertStringContainsString('draft', $fr);
        $this->assertStringContainsString('Seules les factures émises', $fr);
        $this->assertStringContainsString('Solo se pueden marcar', $es);
    }

    // ── RegisterTrialRequest validation messages (resources/lang/*/site.php) ──

    /** @test */
    public function register_validation_messages_are_french_under_fr_locale(): void
    {
        $this->assertSame('Choisissez un mot de passe.', __('site.register.validation.password_required', [], 'fr'));
        $this->assertSame('Le mot de passe doit contenir au moins 8 caractères.', __('site.register.validation.password_min', [], 'fr'));
        $this->assertSame('Saisissez une adresse e-mail valide.', __('site.register.validation.email_invalid', [], 'fr'));

        $this->assertSame('Elige una contraseña.', __('site.register.validation.password_required', [], 'es'));
    }

    /** @test */
    public function the_register_form_shows_french_validation_errors_by_default(): void
    {
        $this->get('http://fakturalista.test/register');
        $captchaAnswer = session('math_captcha_answer');

        $response = $this->post('http://fakturalista.test/register', [
            'name'           => '',
            'email'          => 'not-an-email',
            'password'       => 'short',
            'captcha_answer' => $captchaAnswer,
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $errors = session('errors');
        $this->assertSame('Indiquez votre nom complet.', $errors->first('name'));
        $this->assertSame('Saisissez une adresse e-mail valide.', $errors->first('email'));
        $this->assertSame('Le mot de passe doit contenir au moins 8 caractères.', $errors->first('password'));
    }

    // ── Homepage demo/mockup content (resources/views/index.blade.php) ──

    /** @test */
    public function the_homepage_shows_french_demo_documents_under_fr_locale_never_spanish(): void
    {
        app()->setLocale('fr');
        $response = $this->get('http://fakturalista.test/');
        $response->assertOk();

        $response->assertSee('Devis #0032', false);
        $response->assertSee('Facture #0045', false);
        $response->assertSee('Studio Créatif SARL', false);
        $response->assertSee('Marie Dupont', false);
        $response->assertSee('Atelier Créatif SARL', false);
        $response->assertDontSee('Presupuesto', false);
        $response->assertDontSee('Estudio Creativo', false);
        $response->assertDontSee('María García', false);
        $response->assertDontSee('Taller Creativo', false);
    }

    /** @test */
    public function the_homepage_still_shows_spanish_demo_documents_under_es_locale(): void
    {
        app()->setLocale('es');
        $response = $this->get('http://fakturalista.test/');
        $response->assertOk();

        $response->assertSee('Presupuesto #0032', false);
        $response->assertSee('Factura #0045', false);
        $response->assertSee('Estudio Creativo S.L.', false);
        $response->assertSee('María García', false);
        $response->assertSee('Taller Creativo S.L.', false);
    }

    // ── /verifactu flow diagram + date badges (Spain-specific page) ──

    /** @test */
    public function the_verifactu_page_shows_french_labels_under_fr_locale_never_spanish(): void
    {
        app()->setLocale('fr');
        $response = $this->get('http://fakturalista.test/verifactu');
        $response->assertOk();

        $response->assertSee('Registre de facturation', false);
        $response->assertSee('Agence fiscale (AEAT)', false);
        $response->assertSee('1 JAN', false);
        $response->assertDontSee('Registro de facturación', false);
        $response->assertDontSee('Agencia Tributaria', false);
        $response->assertDontSee('1 ENE', false);
    }
}
