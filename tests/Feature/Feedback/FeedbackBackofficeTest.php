<?php

namespace Tests\Feature\Feedback;

use App\Filament\Resources\FeedbackResource;
use App\Filament\Resources\FeedbackResource\Pages\ListFeedback;
use App\Filament\Resources\FeedbackResource\Pages\ViewFeedback;
use App\Mail\FeedbackReplyMail;
use App\Models\AdminUser;
use App\Models\Feedback;
use App\Services\Feedback\FeedbackService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Filament backoffice (/backoffice, 'admin' guard): FeedbackResource list,
 * detail page actions (reply / status / notes / delete) and the private
 * screenshot route.
 */
class FeedbackBackofficeTest extends TestCase
{
    private const BASE = 'http://fakturalista.test/backoffice';

    private int $maxId;
    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake(Feedback::DISK);
        $this->maxId = (int) Feedback::max('id');
        $this->admin = AdminUser::create([
            'name'     => 'Admin Test',
            'email'    => 'admin-fb-' . uniqid() . '@example.com',
            'password' => 'secret-password',
        ]);
        Filament::setCurrentPanel(Filament::getPanel('backoffice'));
    }

    protected function tearDown(): void
    {
        Feedback::where('id', '>', $this->maxId)->get()->each->delete();
        $this->admin->delete();
        parent::tearDown();
    }

    private function feedback(array $attributes = []): Feedback
    {
        return Feedback::create(array_merge([
            'type'         => 'bug',
            'source'       => 'public',
            'message'      => 'Le menu mobile ne se ferme pas. ' . uniqid(),
            'email'        => 'sender@example.com',
            'locale'       => 'fr',
            'admin_notes'  => 'NOTE-INTERNE-CONFIDENTIELLE',
        ], $attributes));
    }

    private function withScreenshot(Feedback $feedback): Feedback
    {
        $path = UploadedFile::fake()->image('s.png', 10, 10)->storeAs('2026/10', 'shot-' . $feedback->id . '.png', Feedback::DISK);
        $feedback->update(['attachment_path' => $path, 'attachment_name' => 's.png', 'attachment_mime' => 'image/png', 'attachment_size' => 100]);

        return $feedback;
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guests_cannot_reach_the_feedback_pages_or_screenshots(): void
    {
        $feedback = $this->withScreenshot($this->feedback());

        $this->get(self::BASE . '/feedback')->assertRedirect();
        $this->get(self::BASE . '/feedback/' . $feedback->id)->assertRedirect();
        $this->get(self::BASE . '/feedback-attachments/' . $feedback->id)->assertRedirect();
        $this->get(self::BASE . '/feedback-attachments/' . $feedback->id)->assertRedirectContains('/backoffice/login');
    }

    public function test_an_admin_sees_the_list_detail_and_screenshot(): void
    {
        $feedback = $this->withScreenshot($this->feedback(['source' => 'tenant', 'company_name' => 'Atlas SARL', 'user_name' => 'Nadia']));

        $this->actingAs($this->admin, 'admin');

        $this->get(self::BASE . '/feedback')
            ->assertOk()
            ->assertSee('Retours utilisateurs')
            ->assertSee('Atlas SARL');

        $this->get(self::BASE . '/feedback/' . $feedback->id)
            ->assertOk()
            ->assertSee($feedback->message)
            ->assertSee('NOTE-INTERNE-CONFIDENTIELLE')
            ->assertSee('feedback-attachments/' . $feedback->id, false);

        $this->get(self::BASE . '/feedback-attachments/' . $feedback->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_navigation_badge_counts_new_feedback(): void
    {
        $before = Feedback::new()->count();
        $this->feedback();
        $this->feedback(['status' => 'resolved']);

        $this->assertSame((string) ($before + 1), FeedbackResource::getNavigationBadge());
    }

    public function test_the_list_filters_and_searches(): void
    {
        $this->actingAs($this->admin, 'admin');
        $bug = $this->feedback(['type' => 'bug', 'company_name' => 'Zeta-' . uniqid()]);
        $idea = $this->feedback(['type' => 'suggestion', 'source' => 'tenant', 'status' => 'in_progress']);

        Livewire::test(ListFeedback::class)
            ->filterTable('type', 'suggestion')
            ->assertCanSeeTableRecords([$idea])
            ->assertCanNotSeeTableRecords([$bug]);

        Livewire::test(ListFeedback::class)
            ->searchTable($bug->company_name)
            ->assertCanSeeTableRecords([$bug])
            ->assertCanNotSeeTableRecords([$idea]);

        Livewire::test(ListFeedback::class)
            ->filterTable('status', 'in_progress')
            ->filterTable('source', 'tenant')
            ->assertCanSeeTableRecords([$idea])
            ->assertCanNotSeeTableRecords([$bug]);
    }

    // ── Actions ─────────────────────────────────────────────────────────

    public function test_an_admin_reply_is_emailed_saved_and_shown_without_internal_notes(): void
    {
        $this->actingAs($this->admin, 'admin');
        $feedback = $this->feedback(['locale' => 'en', 'user_name' => 'Sam']);

        Livewire::test(ViewFeedback::class, ['record' => $feedback->getRouteKey()])
            ->callAction('reply', data: ['message' => 'Thanks, this is fixed in today\'s release.'])
            ->assertHasNoActionErrors();

        $reply = $feedback->replies()->first();
        $this->assertNotNull($reply);
        $this->assertSame($this->admin->id, $reply->admin_user_id);
        $this->assertNotNull($reply->sent_at);
        $this->assertSame('in_progress', $feedback->fresh()->status);

        Mail::assertSent(FeedbackReplyMail::class, function (FeedbackReplyMail $mail) use ($feedback) {
            $html = $mail->render();

            return $mail->hasTo('sender@example.com')
                && $mail->locale === 'en'
                && str_contains($html, 'this is fixed in today')
                && str_contains($html, 'Hello Sam')
                && ! str_contains($html, 'NOTE-INTERNE-CONFIDENTIELLE');
        });

        // Reply history on the detail page.
        $this->get(self::BASE . '/feedback/' . $feedback->id)
            ->assertSee('Historique des réponses')
            ->assertSee('this is fixed in today', false);
    }

    public function test_a_reply_that_cannot_be_sent_is_not_recorded(): void
    {
        $feedback = $this->feedback();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

        try {
            app(FeedbackService::class)->reply($feedback, 'Bonjour', $this->admin);
            $this->fail('Expected the mail failure to propagate.');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $feedback->replies()->count());
        $this->assertSame('new', $feedback->fresh()->status);
    }

    public function test_an_admin_can_change_status_and_internal_notes(): void
    {
        $this->actingAs($this->admin, 'admin');
        $feedback = $this->feedback();

        Livewire::test(ViewFeedback::class, ['record' => $feedback->getRouteKey()])
            ->callAction('status', data: ['status' => 'resolved'])
            ->assertHasNoActionErrors()
            ->callAction('notes', data: ['admin_notes' => 'Corrigé en v2.3'])
            ->assertHasNoActionErrors();

        $feedback->refresh();
        $this->assertSame('resolved', $feedback->status);
        $this->assertSame('Corrigé en v2.3', $feedback->admin_notes);

        Livewire::test(ViewFeedback::class, ['record' => $feedback->getRouteKey()])
            ->callAction('status', data: ['status' => 'archived'])
            ->assertHasActionErrors(['status']);
    }

    public function test_deleting_feedback_removes_its_screenshot(): void
    {
        $this->actingAs($this->admin, 'admin');
        $feedback = $this->withScreenshot($this->feedback());
        $path = $feedback->attachment_path;

        Livewire::test(ViewFeedback::class, ['record' => $feedback->getRouteKey()])
            ->callAction('delete');

        $this->assertNull(Feedback::find($feedback->id));
        Storage::disk(Feedback::DISK)->assertMissing($path);
    }
}
