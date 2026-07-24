<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Models\Subscription;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /** Pre-populate the plan_id field from the active subscription. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $subscription = Subscription::on('mysql')
            ->where('tenant_id', $this->record->id)
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->first();

        $data['plan_id'] = $subscription?->plan_id;

        return $data;
    }

    /** Intercept plan_id before saving - update subscription, not the Tenant model. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $planId = $data['plan_id'] ?? null;
        unset($data['plan_id']); // plan_id is not a column on the tenants table

        if ($planId) {
            Subscription::on('mysql')->updateOrCreate(
                [
                    'tenant_id' => $this->record->id,
                ],
                [
                    'plan_id'  => $planId,
                    'provider' => 'manual',
                    'status'   => 'active',
                ]
            );

            // Keep subscription_status in sync when plan is manually assigned
            if (empty($data['subscription_status']) || $data['subscription_status'] === 'trialing') {
                $data['subscription_status'] = 'active';
            }
        }

        return $data;
    }
}
