<?php

namespace App\Filament\Resources\FeedbackResource\Pages;

use App\Filament\Resources\FeedbackResource;
use App\Models\Feedback;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListFeedback extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    public function getTabs(): array
    {
        return [
            'all'  => Tab::make('Tous'),
            'open' => Tab::make('À traiter')
                ->badge(Feedback::whereIn('status', ['new', 'in_progress'])->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['new', 'in_progress'])),
            'done' => Tab::make('Traités')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['resolved', 'closed'])),
        ];
    }
}
