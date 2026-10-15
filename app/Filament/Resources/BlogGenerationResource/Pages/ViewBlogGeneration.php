<?php

namespace App\Filament\Resources\BlogGenerationResource\Pages;

use App\Filament\Resources\BlogGenerationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewBlogGeneration extends ViewRecord
{
    protected static string $resource = BlogGenerationResource::class;

    public function getTitle(): string
    {
        return sprintf('Génération #%d · %s', $this->record->id, $this->record->statusLabel());
    }

    protected function getHeaderActions(): array
    {
        return [
            BlogGenerationResource::previewAction(Action::make('preview')),
            BlogGenerationResource::editPostAction(Action::make('editPost')),
            BlogGenerationResource::approveAction(Action::make('approve')),
            BlogGenerationResource::rejectAction(Action::make('reject')),
            BlogGenerationResource::retryAction(Action::make('retry')),
        ];
    }
}
