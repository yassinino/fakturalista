<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Models\Tenant;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('suspend')
                ->label('Suspender')
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'active')
                ->requiresConfirmation()
                ->modalHeading('¿Suspender este tenant?')
                ->modalDescription('El tenant no podrá acceder a la aplicación hasta que lo reactives.')
                ->modalSubmitActionLabel('Sí, suspender')
                ->action(function () {
                    $this->record->update(['status' => 'suspended']);
                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->title('Tenant suspendido')
                        ->warning()
                        ->send();
                }),

            Actions\Action::make('activate')
                ->label('Activar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'suspended')
                ->requiresConfirmation()
                ->modalHeading('¿Reactivar este tenant?')
                ->modalSubmitActionLabel('Sí, activar')
                ->action(function () {
                    $this->record->update(['status' => 'active']);
                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->title('Tenant reactivado')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('delete')
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Eliminar tenant permanentemente')
                ->modalDescription(fn () =>
                    'ATENCIÓN: Se eliminará la empresa "' . ($this->record->company_name ?? $this->record->getTenantKey()) . '", '
                    . 'su dominio, su base de datos y TODOS sus datos. Esta acción no se puede deshacer.'
                )
                ->modalSubmitActionLabel('Sí, eliminar todo')
                ->action(function () {
                    $record = $this->record;

                    // TenancyServiceProvider fires Jobs\DeleteDatabase automatically
                    // on the TenantDeleted event — no need to call deleteDatabase() here.
                    $record->delete();

                    Notification::make()
                        ->title('Tenant eliminado')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}
