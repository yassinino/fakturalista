<?php

namespace App\Filament\Resources\BlogGenerationResource\Pages;

use App\Filament\Pages\BlogAutomation;
use App\Filament\Resources\BlogGenerationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBlogGenerations extends ListRecords
{
    protected static string $resource = BlogGenerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('settings')
                ->label('Réglages de l\'automatisation')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(BlogAutomation::getUrl()),
        ];
    }
}
