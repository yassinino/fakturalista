<?php

namespace Tests\Feature;

use App\Models\{CompanyProfile, Tenant, User};
use App\Services\NotificationPreferencesService;
use Tests\TestCase;

/**
 * Settings > Notifications - PUT /api/settings accepts
 * notification_preferences as either an array/object or a JSON string,
 * both normalized through NotificationPreferencesService. Setup mirrors
 * CountryTaxConfigurationTest.
 */
class NotificationPreferencesSettingsTest extends TestCase
{
    private Tenant $tenant;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['id' => 'test-notif-prefs-' . uniqid()]);
        $this->domain = $this->tenant->id . '.fakturalista.test';
        $this->tenant->domains()->create(['domain' => $this->domain]);
        $user = $this->tenant->run(function () {
            CompanyProfile::create([
                'legal_name' => 'Notification prefs test', 'country_code' => 'MA',
                'currency' => 'MAD', 'locale' => 'fr', 'invoice_prefix' => 'INV',
                'onboarding_completed_at' => now(),
            ]);
            return User::factory()->create();
        });
        $this->actingAs($user, 'api');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    private function url(): string
    {
        return 'http://' . $this->domain . '/api/settings';
    }

    private function settings(): array
    {
        return $this->getJson($this->url())->assertOk()->json('settings');
    }

    private function storedPreferences(): ?array
    {
        return $this->tenant->run(fn () => CompanyProfile::first()->notification_preferences);
    }

    /** Changed values plus things resolve() must sanitize away. */
    private function changedPreferences(): array
    {
        return [
            'invoice_paid'         => false,
            'quote_decision'       => false,
            'invoice_overdue_days' => [1, 99],   // 99 is not an allowed choice
            'not_a_real_key'       => true,      // unknown keys are dropped
        ];
    }

    private function assertChangedPreferencesStored(): void
    {
        $stored = $this->storedPreferences();

        $this->assertFalse($stored['invoice_paid']);
        $this->assertFalse($stored['quote_decision']);
        $this->assertSame([1], $stored['invoice_overdue_days']);
        $this->assertArrayNotHasKey('not_a_real_key', $stored);
        // Untouched keys keep their defaults.
        $this->assertTrue($stored['invoice_due_soon']);
        $this->assertSame(NotificationPreferencesService::DEFAULTS['invoice_due_soon_days'], $stored['invoice_due_soon_days']);
    }

    public function test_preferences_are_accepted_as_an_array(): void
    {
        $this->putJson($this->url(), array_merge($this->settings(), [
            'notification_preferences' => $this->changedPreferences(),
        ]))->assertOk();

        $this->assertChangedPreferencesStored();
    }

    public function test_preferences_are_accepted_as_a_json_string(): void
    {
        $this->putJson($this->url(), array_merge($this->settings(), [
            'notification_preferences' => json_encode($this->changedPreferences()),
        ]))->assertOk();

        $this->assertChangedPreferencesStored();
    }

    public function test_the_get_payload_can_be_put_back_unchanged(): void
    {
        $this->putJson($this->url(), array_merge($this->settings(), [
            'notification_preferences' => $this->changedPreferences(),
        ]))->assertOk();

        $before = $this->settings();
        $this->putJson($this->url(), $before)->assertOk();

        $this->assertSame($before['notification_preferences'], $this->settings()['notification_preferences']);
        $this->assertChangedPreferencesStored();
    }

    public function test_malformed_or_non_object_json_fails_safely(): void
    {
        $this->putJson($this->url(), array_merge($this->settings(), [
            'notification_preferences' => $this->changedPreferences(),
        ]))->assertOk();

        foreach (['{not json', '5', '"text"', 'true', 42] as $bad) {
            $this->putJson($this->url(), array_merge($this->settings(), [
                'notification_preferences' => $bad,
            ]))->assertStatus(422)->assertJsonValidationErrors('notification_preferences');
        }

        // Nothing was overwritten by the rejected requests.
        $this->assertChangedPreferencesStored();
    }

    public function test_existing_preference_behavior_is_intact(): void
    {
        // A tenant that never saved preferences gets the full default shape.
        $this->assertSame(
            app(NotificationPreferencesService::class)->resolve(null),
            $this->settings()['notification_preferences']
        );

        // The master switch still turns every optional notification off.
        $this->putJson($this->url(), array_merge($this->settings(), [
            'notification_preferences' => json_encode(['email_notifications_enabled' => false]),
        ]))->assertOk();

        $service = app(NotificationPreferencesService::class);
        $stored  = $this->storedPreferences();
        $this->assertFalse($service->isEnabled($stored, 'invoice_paid'));
        $this->assertFalse($service->isEnabled($stored, 'quote_decision'));
    }
}
