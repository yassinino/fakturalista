<?php

namespace App\Filament\Resources\PlanResource\Pages;

use App\Filament\Resources\PlanResource;
use App\Models\PlanLimit;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /** Expand JSON columns into locale-suffixed virtual fields for the form. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['name', 'short_description', 'full_description', 'badge', 'button_text'] as $field) {
            $raw          = $this->record->getRawOriginal($field) ?? '{}';
            $translations = json_decode($raw, true) ?? [];

            $data["{$field}_fr"] = $translations['fr'] ?? '';
            $data["{$field}_en"] = $translations['en'] ?? '';
            $data["{$field}_es"] = $translations['es'] ?? '';
            unset($data[$field]);
        }

        // Pre-populate virtual limit fields from plan_limits table
        $limits = PlanLimit::on('mysql')
            ->where('plan_id', $this->record->id)
            ->get()
            ->keyBy('resource');

        foreach (array_keys(PlanLimit::RESOURCES) as $resource) {
            $row = $limits->get($resource);
            $data["limit_{$resource}"] = $row ? $row->value : null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->packTranslatableFields($data);
    }

    protected function afterSave(): void
    {
        $this->syncLimits($this->record->id, $this->data);
    }

    // ── Shared helpers (duplicated from CreatePlan - kept explicit) ───

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
