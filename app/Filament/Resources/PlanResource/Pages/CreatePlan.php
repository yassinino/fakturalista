<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use App\Models\PlanLimit;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->packTranslatableFields($data);
    }

    protected function afterCreate(): void
    {
        $this->syncLimits($this->record->id, $this->data);
    }

    // ── Shared helpers ────────────────────────────────────────────────

    /**
     * Merge locale-suffixed virtual fields back into JSON columns.
     * e.g. name_fr + name_en + name_es → name (JSON)
     */
    protected function packTranslatableFields(array $data): array
    {
        foreach (['name', 'short_description', 'full_description', 'badge', 'button_text'] as $field) {
            $data[$field] = json_encode([
                'fr' => $data["{$field}_fr"] ?? '',
                'en' => $data["{$field}_en"] ?? '',
                'es' => $data["{$field}_es"] ?? '',
            ], JSON_UNESCAPED_UNICODE);

            unset($data["{$field}_fr"], $data["{$field}_en"], $data["{$field}_es"]);
        }

        // Remove virtual limit fields - persisted via syncLimits()
        foreach (array_keys(PlanLimit::RESOURCES) as $resource) {
            unset($data["limit_{$resource}"]);
        }

        return $data;
    }

    protected function syncLimits(int $planId, array $data): void
    {
        foreach (array_keys(PlanLimit::RESOURCES) as $resource) {
            $key = "limit_{$resource}";
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key] !== '' && $data[$key] !== null
                ? (int) $data[$key]
                : null;

            PlanLimit::on('mysql')->updateOrCreate(
                ['plan_id' => $planId, 'resource' => $resource],
                ['value' => $value]
            );
        }
    }
}
