<?php

namespace Tests\Feature\Feedback;

use App\Mail\NewFeedbackAdminMail;
use App\Models\Feedback;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Public-site feedback widget: POST /feedback on the central domain
 * (FeedbackController::storePublic) - visitors without an account.
 */
class PublicFeedbackTest extends TestCase
{
    private const URL = 'http://fakturalista.test/feedback';

    private int $maxId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake(Feedback::DISK);
        $this->maxId = (int) Feedback::max('id');
    }

    protected function tearDown(): void
    {
        Feedback::where('id', '>', $this->maxId)->get()->each->delete();
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type'     => 'suggestion',
            'message'  => 'Ce serait génial de pouvoir dupliquer une facture.',
            'email'    => 'Visitor+' . uniqid() . '@Example.com',
            'page_url' => 'http://fakturalista.test/pricing?reset_token=secret#signup=abc',
        ], $overrides);
    }

    private function created(): ?Feedback
    {
        return Feedback::where('id', '>', $this->maxId)->latest('id')->first();
    }

    public function test_a_visitor_can_submit_feedback_without_an_account(): void
    {
        $payload = $this->payload();

        $this->postJson(self::URL, $payload)
            ->assertCreated()
            ->assertJsonPath('message', __('site.feedback.success'));

        $feedback = $this->created();
        $this->assertNotNull($feedback);
        $this->assertSame('public', $feedback->source);
        $this->assertSame('new', $feedback->status);
        $this->assertSame('suggestion', $feedback->type);
        $this->assertSame(strtolower($payload['email']), $feedback->email);
        $this->assertNull($feedback->tenant_id);
        $this->assertNull($feedback->user_id);
        $this->assertNotNull($feedback->created_at);
    }

    public function test_the_page_url_keeps_only_the_path_on_the_serving_host(): void
    {
        $this->postJson(self::URL, $this->payload([
            'page_url' => 'https://evil.example/pricing?token=secret#signup=abc',
        ]))->assertCreated();

        // Query/fragment (tokens) dropped; host is the one that served the request.
        $this->assertSame('http://fakturalista.test/pricing', $this->created()->page_url);
    }

    public function test_client_supplied_attribution_and_status_are_ignored(): void
    {
        $this->postJson(self::URL, $this->payload([
            'tenant_id'   => 'some-tenant',
            'user_id'     => 42,
            'source'      => 'tenant',
            'status'      => 'resolved',
            'admin_notes' => 'injected',
        ]))->assertCreated();

        $feedback = $this->created();
        $this->assertNull($feedback->tenant_id);
        $this->assertNull($feedback->user_id);
        $this->assertSame('public', $feedback->source);
        $this->assertSame('new', $feedback->status);
        $this->assertNull($feedback->admin_notes);
    }

    public function test_the_administrator_is_notified_with_a_link_to_the_backoffice(): void
    {
        $this->postJson(self::URL, $this->payload(['type' => 'bug', 'message' => 'Le bouton PDF ne répond pas.']))
            ->assertCreated();

        $feedback = $this->created();

        Mail::assertSent(NewFeedbackAdminMail::class, function (NewFeedbackAdminMail $mail) use ($feedback) {
            $html = $mail->render();

            return $mail->hasTo(config('fakturalista.admin_email'))
                && $mail->hasReplyTo($feedback->email)
                && str_contains($html, 'Le bouton PDF ne répond pas.')
                && str_contains($html, $feedback->email)
                && str_contains($html, '/backoffice/feedback/' . $feedback->id);
        });
    }

    public function test_required_fields_and_allowed_values_are_validated(): void
    {
        $this->postJson(self::URL, ['type' => 'praise', 'message' => '   ', 'email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'message', 'email']);

        $this->postJson(self::URL, $this->payload(['email' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->postJson(self::URL, $this->payload(['message' => str_repeat('a', 5001)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->assertNull($this->created());
        Mail::assertNothingSent();
    }

    public function test_control_characters_are_stripped_from_the_message(): void
    {
        $this->postJson(self::URL, $this->payload(['message' => "Ligne 1\r\nLigne\x00 2\x07 <b>ok</b>"]))
            ->assertCreated();

        // Stored as plain text (escaped on output), minus control characters.
        $this->assertSame("Ligne 1\nLigne 2 <b>ok</b>", $this->created()->message);
    }

    public function test_the_honeypot_pretends_success_but_stores_nothing(): void
    {
        $this->postJson(self::URL, $this->payload(['website' => 'http://spam.example']))
            ->assertCreated();

        $this->assertNull($this->created());
        Mail::assertNothingSent();
    }

    public function test_the_public_endpoint_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::URL, $this->payload())->assertCreated();
        }

        $this->postJson(self::URL, $this->payload())->assertStatus(429);
        $this->assertSame(5, Feedback::where('id', '>', $this->maxId)->count());
    }

    public function test_a_screenshot_is_stored_privately_under_a_random_name(): void
    {
        $this->post(self::URL, $this->payload([
            'screenshot' => UploadedFile::fake()->image('../../mon écran.png', 800, 600),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $feedback = $this->created();
        $this->assertTrue($feedback->hasAttachment());
        $this->assertSame('image/png', $feedback->attachment_mime);
        $this->assertMatchesRegularExpression('~^\d{4}/\d{2}/[0-9a-f-]{36}\.png$~', $feedback->attachment_path);
        $this->assertStringNotContainsString('/', $feedback->attachment_name);
        Storage::disk(Feedback::DISK)->assertExists($feedback->attachment_path);

        // Not reachable through any public URL: the disk is outside public/.
        $this->assertStringNotContainsString(public_path(), config('filesystems.disks.feedback.root'));
    }

    public function test_unsafe_or_oversized_attachments_are_rejected(): void
    {
        $cases = [
            'svg'            => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'php as png'     => UploadedFile::fake()->createWithContent('shell.png', '<?php system($_GET["c"]); ?>'),
            'pdf'            => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'),
            'gif'            => UploadedFile::fake()->image('anim.gif'),
            'too large'      => UploadedFile::fake()->image('huge.png')->size(6000),
        ];

        foreach ($cases as $label => $file) {
            $this->post(self::URL, $this->payload(['screenshot' => $file]), ['Accept' => 'application/json'])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['screenshot'], 'errors', "Expected '$label' to be rejected");
        }

        $this->assertNull($this->created());
        $this->assertSame([], Storage::disk(Feedback::DISK)->allFiles());
    }
}
