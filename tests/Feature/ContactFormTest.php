<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Public /contact page + form (routes/web.php GET|POST /contact ->
 * HomeController::contact()/sendContact()) - covers the new field set
 * (first_name/last_name/phone/company/subject/message), the honeypot,
 * the centralized destination email (config/fakturalista.php
 * 'contact_email'), and the dedicated 'contact' rate limiter.
 */
class ContactFormTest extends TestCase
{
    /**
     * Performs a GET first to seed a real math-captcha challenge into the
     * (array-driver) test session, mirroring SelfServiceRegistrationTest's
     * own pattern for MathCaptchaService::verify().
     */
    private function payloadWithFreshCaptcha(array $overrides = []): array
    {
        $this->get('http://fakturalista.test/contact');
        $captchaAnswer = session('math_captcha_answer');

        return array_merge([
            'first_name'     => 'Marie',
            'last_name'      => 'Dupont',
            'email'          => 'marie+' . uniqid() . '@example.com',
            'phone'          => '+212 612345678',
            'company'        => 'Acme SARL',
            'subject'        => 'pricing',
            'message'        => 'Bonjour, j\'ai une question sur vos tarifs.',
            'captcha_answer' => $captchaAnswer,
        ], $overrides);
    }

    /** @test */
    public function the_contact_page_loads_in_french_by_default(): void
    {
        $response = $this->get('http://fakturalista.test/contact');

        $response->assertOk();
        $response->assertSee('Parlez-nous de votre besoin', false);
    }

    /** @test */
    public function a_valid_submission_sends_the_notification_to_the_centrally_configured_contact_email(): void
    {
        Mail::fake();

        $payload = $this->payloadWithFreshCaptcha();
        $response = $this->post('http://fakturalista.test/contact', $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) use ($payload) {
            return $mail->hasTo(config('fakturalista.contact_email'))
                && $mail->name === 'Marie Dupont'
                && $mail->email === $payload['email']
                && $mail->phone === $payload['phone']
                && $mail->company === $payload['company'];
        });
    }

    /** @test */
    public function missing_required_fields_are_rejected_and_no_mail_is_sent(): void
    {
        Mail::fake();

        $payload = $this->payloadWithFreshCaptcha([
            'first_name' => '',
            'subject'    => '',
            'message'    => '',
        ]);
        $response = $this->post('http://fakturalista.test/contact', $payload);

        $response->assertSessionHasErrors(['first_name', 'subject', 'message']);
        Mail::assertNothingSent();
    }

    /** @test */
    public function an_invalid_subject_value_outside_the_fixed_list_is_rejected(): void
    {
        Mail::fake();

        $payload = $this->payloadWithFreshCaptcha(['subject' => 'not-a-real-option']);
        $response = $this->post('http://fakturalista.test/contact', $payload);

        $response->assertSessionHasErrors(['subject']);
        Mail::assertNothingSent();
    }

    /** @test */
    public function filling_the_honeypot_field_pretends_success_but_sends_no_mail(): void
    {
        Mail::fake();

        $payload = $this->payloadWithFreshCaptcha(['company_website' => 'http://spam.example']);
        $response = $this->post('http://fakturalista.test/contact', $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        Mail::assertNothingSent();
    }

    /** @test */
    public function the_contact_endpoint_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $payload = $this->payloadWithFreshCaptcha(['email' => 'marie+' . uniqid() . '@example.com']);
            $this->post('http://fakturalista.test/contact', $payload);
        }

        $payload = $this->payloadWithFreshCaptcha(['email' => 'marie+' . uniqid() . '@example.com']);
        $response = $this->post('http://fakturalista.test/contact', $payload);

        $response->assertStatus(429);
    }
}
