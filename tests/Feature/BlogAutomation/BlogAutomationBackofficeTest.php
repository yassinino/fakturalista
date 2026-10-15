<?php

namespace Tests\Feature\BlogAutomation;

use App\Filament\Pages\BlogAutomation;
use App\Filament\Resources\BlogGenerationResource\Pages\ListBlogGenerations;
use App\Filament\Resources\BlogGenerationResource\Pages\ViewBlogGeneration;
use App\Jobs\GenerateBlogArticleJob;
use App\Models\AdminUser;
use App\Models\BlogGeneration;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/** Filament "Blog IA" section: access control, settings, actions, preview. */
class BlogAutomationBackofficeTest extends BlogAutomationTestCase
{
    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = AdminUser::create(['name' => 'Admin Blog', 'email' => 'admin-blog-' . uniqid() . '@example.com', 'password' => 'secret-password']);
        Filament::setCurrentPanel(Filament::getPanel('backoffice'));
    }

    public function test_guests_and_tenant_users_cannot_reach_the_controls(): void
    {
        $generation = $this->pendingGeneration();

        foreach (['/backoffice/blog-automation', '/backoffice/blog-generations', '/backoffice/blog-generations/' . $generation->id] as $path) {
            $this->get(self::HOST . $path)->assertRedirect();
        }
        // The default (web) guard is not the backoffice guard.
        $this->get(self::HOST . '/backoffice/blog-preview/1')->assertRedirect();
    }

    public function test_admins_see_the_dashboard_with_safe_defaults(): void
    {
        $this->actingAs($this->admin, 'admin');

        $this->get(self::HOST . '/backoffice/blog-automation')
            ->assertOk()
            ->assertSee('Automatisation du blog IA')
            ->assertSee('Désactivée')
            ->assertSee('Brouillons uniquement')
            ->assertSee('gemini-test-model')
            ->assertDontSee('test-gemini-key')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get(self::HOST . '/backoffice/blog-generations')->assertOk();
    }

    public function test_settings_can_be_saved_and_the_daily_cap_is_enforced(): void
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(BlogAutomation::class)
            ->fillForm([
                'enabled' => true, 'auto_publish' => true, 'require_manual_approval' => false,
                'max_per_day' => 1, 'target_countries' => ['ma'], 'excluded_topics' => "BTP\nTVA", 'language' => 'fr',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = $this->settings();
        $this->assertTrue($settings->enabled);
        $this->assertTrue($settings->publishesAutomatically());
        $this->assertSame(['ma'], $settings->countries());
        $this->assertSame(['btp', 'tva'], $settings->exclusions());

        Livewire::test(BlogAutomation::class)
            ->fillForm(['max_per_day' => 5])
            ->call('save')
            ->assertHasFormErrors(['max_per_day']);
    }

    public function test_generate_now_queues_one_job_and_respects_the_daily_limit(): void
    {
        Queue::fake();
        $this->actingAs($this->admin, 'admin');

        Livewire::test(BlogAutomation::class)->callAction('generateNow');
        Livewire::test(BlogAutomation::class)->callAction('generateNow');

        Queue::assertPushed(GenerateBlogArticleJob::class, 1);
        $this->assertSame('manual', BlogGeneration::sole()->trigger);
    }

    public function test_review_actions_preview_approve_and_reject(): void
    {
        $this->fakeGemini();
        $this->actingAs($this->admin, 'admin');
        $generation = $this->pendingGeneration();
        app(\App\Services\BlogAutomation\BlogAutomationService::class)->run($generation->id);
        $generation->refresh();

        $this->get(self::HOST . '/backoffice/blog-preview/' . $generation->post_id)
            ->assertOk()
            ->assertSee($generation->article_title)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        Livewire::test(ListBlogGenerations::class)->assertCanSeeTableRecords([$generation]);

        Livewire::test(ViewBlogGeneration::class, ['record' => $generation->getRouteKey()])
            ->assertActionVisible('approve')
            ->assertActionHidden('retry')
            ->callAction('approve');

        $generation->refresh();
        $this->assertSame(BlogGeneration::STATUS_PUBLISHED, $generation->status);
        $this->assertSame($this->admin->id, $generation->reviewed_by);
        $this->assertSame('published', $generation->post->status);
    }

    public function test_failed_generations_can_be_retried_from_the_table(): void
    {
        Queue::fake();
        $this->actingAs($this->admin, 'admin');
        $failed = $this->pendingGeneration(['status' => BlogGeneration::STATUS_FAILED, 'error_message' => 'Service Gemini indisponible (503).']);

        Livewire::test(ListBlogGenerations::class)->callTableAction('retry', $failed);

        $this->assertSame(BlogGeneration::STATUS_PENDING, $failed->fresh()->status);
        Queue::assertPushed(GenerateBlogArticleJob::class);
    }
}
