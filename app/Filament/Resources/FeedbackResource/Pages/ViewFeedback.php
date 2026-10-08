<?php

namespace App\Filament\Resources\FeedbackResource\Pages;

use App\Filament\Resources\FeedbackResource;
use App\Models\Feedback;
use App\Services\Feedback\FeedbackService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewFeedback extends ViewRecord
{
    protected static string $resource = FeedbackResource::class;

    public function getTitle(): string
    {
        return sprintf('Retour #%d · %s', $this->record->id, $this->record->typeLabel());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label('Répondre')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->modalHeading(fn () => 'Répondre à ' . $this->record->email)
                ->modalDescription("La réponse est envoyée par e-mail à l'expéditeur, avec son message d'origine. Les notes internes ne sont jamais incluses.")
                ->modalSubmitActionLabel('Envoyer la réponse')
                ->form([
                    Forms\Components\Textarea::make('message')
                        ->label('Votre réponse')
                        ->required()
                        ->minLength(2)
                        ->maxLength(10000)
                        ->rows(8)
                        ->autosize(),
                ])
                ->action(function (array $data, Actions\Action $action) {
                    try {
                        app(FeedbackService::class)->reply($this->record, $data['message'], auth('admin')->user());
                    } catch (Throwable $e) {
                        report($e);

                        Notification::make()
                            ->title("L'e-mail n'a pas pu être envoyé")
                            ->body('Aucune réponse enregistrée. Vérifiez la configuration e-mail puis réessayez.')
                            ->danger()
                            ->send();

                        $action->halt();
                    }

                    $this->record->refresh()->load('replies.admin');

                    Notification::make()->title('Réponse envoyée à ' . $this->record->email)->success()->send();
                }),

            Actions\Action::make('status')
                ->label('Statut')
                ->icon('heroicon-o-flag')
                ->color('gray')
                ->fillForm(fn () => ['status' => $this->record->status])
                ->form([
                    Forms\Components\Radio::make('status')
                        ->label('Statut')
                        ->options(Feedback::STATUS_LABELS)
                        ->in(Feedback::STATUSES)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update(['status' => $data['status']]);

                    Notification::make()->title('Statut mis à jour')->success()->send();
                }),

            Actions\Action::make('notes')
                ->label('Notes internes')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->modalDescription("Privées : visibles uniquement dans le backoffice, jamais envoyées à l'expéditeur.")
                ->fillForm(fn () => ['admin_notes' => $this->record->admin_notes])
                ->form([
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Notes')
                        ->maxLength(10000)
                        ->rows(8)
                        ->autosize(),
                ])
                ->action(function (array $data) {
                    $this->record->update(['admin_notes' => $data['admin_notes'] ?: null]);

                    Notification::make()->title('Notes enregistrées')->success()->send();
                }),

            Actions\DeleteAction::make()->label('Supprimer'),
        ];
    }
}
