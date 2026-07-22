<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon        = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup       = 'Gestión';
    protected static ?string $navigationLabel       = 'Tenants';
    protected static ?string $modelLabel            = 'Tenant';
    protected static ?string $pluralModelLabel      = 'Tenants';
    protected static ?int    $navigationSort        = 1;

    // ── Shared form schema (used by EditTenant) ───────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Empresa')
                ->schema([
                    Forms\Components\TextInput::make('company_name')
                        ->label('Nombre de la empresa')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('company_email')
                        ->label('Email corporativo')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('company_phone')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(50),

                    Forms\Components\Select::make('country')
                        ->label('País')
                        ->options(static::countryOptions())
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('timezone')
                        ->label('Zona horaria')
                        ->options(static::timezoneOptions())
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('currency')
                        ->label('Moneda')
                        ->options(static::currencyOptions())
                        ->required(),

                    Forms\Components\Select::make('language')
                        ->label('Idioma')
                        ->options(['es' => 'Español', 'fr' => 'Français', 'en' => 'English'])
                        ->required(),
                ])->columns(2),

            Forms\Components\Section::make('Propietario')
                ->schema([
                    Forms\Components\TextInput::make('owner_name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('owner_email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make('Estado')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Estado operacional')
                        ->options(['active' => 'Activo', 'suspended' => 'Suspendido'])
                        ->required(),

                    Forms\Components\Select::make('subscription_status')
                        ->label('Estado de suscripción')
                        ->options([
                            'trialing' => 'Trial',
                            'active'   => 'Activo',
                            'expired'  => 'Expirado',
                            'canceled' => 'Cancelado',
                        ]),

                    Forms\Components\DateTimePicker::make('trial_ends_at')
                        ->label('Trial hasta')
                        ->seconds(false),
                ])->columns(3),

        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('domains'))
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('domain')
                    ->label('Dominio')
                    ->getStateUsing(fn (Tenant $record) => $record->domains->first()?->domain ?? '—')
                    ->searchable(query: fn ($query, string $search) =>
                        $query->whereHas('domains', fn ($q) => $q->where('domain', 'like', "%{$search}%"))
                    )
                    ->copyable()
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('owner_email')
                    ->label('Propietario')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('País')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->colors([
                        'success' => 'active',
                        'danger'  => 'suspended',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active'    => 'Activo',
                        'suspended' => 'Suspendido',
                        default     => $state,
                    }),

                Tables\Columns\BadgeColumn::make('subscription_status')
                    ->label('Suscripción')
                    ->getStateUsing(fn (Tenant $record) => $record->subscription_status)
                    ->colors([
                        'primary' => 'trialing',
                        'success' => 'active',
                        'warning' => 'expired',
                        'danger'  => 'canceled',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'trialing' => 'Trial',
                        'active'   => 'Activo',
                        'expired'  => 'Expirado',
                        'canceled' => 'Cancelado',
                        default    => $state ?? '—',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado operacional')
                    ->options(['active' => 'Activo', 'suspended' => 'Suspendido']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('suspend')
                    ->label('Suspender')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->visible(fn (Tenant $record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('¿Suspender este tenant?')
                    ->modalDescription('El tenant no podrá acceder a la aplicación hasta que lo reactives.')
                    ->modalSubmitActionLabel('Sí, suspender')
                    ->action(fn (Tenant $record) => $record->update(['status' => 'suspended'])),

                Tables\Actions\Action::make('activate')
                    ->label('Activar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Tenant $record) => $record->status === 'suspended')
                    ->requiresConfirmation()
                    ->modalHeading('¿Reactivar este tenant?')
                    ->modalSubmitActionLabel('Sí, activar')
                    ->action(fn (Tenant $record) => $record->update(['status' => 'active'])),

                Tables\Actions\Action::make('delete')
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar tenant permanentemente')
                    ->modalDescription(fn (Tenant $record) =>
                        'ATENCIÓN: Se eliminará la empresa "' . ($record->company_name ?? $record->getTenantKey()) . '", '
                        . 'su dominio, su base de datos y TODOS sus datos. Esta acción no se puede deshacer.'
                    )
                    ->modalSubmitActionLabel('Sí, eliminar todo')
                    ->action(function (Tenant $record) {
                        // TenancyServiceProvider fires Jobs\DeleteDatabase automatically
                        // on the TenantDeleted event — no need to call deleteDatabase() here.
                        $record->delete();

                        Notification::make()
                            ->title('Tenant eliminado')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    // ── Infolist (View page) ──────────────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Infolists\Components\Section::make('Empresa')
                ->schema([
                    Infolists\Components\TextEntry::make('company_name')->label('Nombre'),
                    Infolists\Components\TextEntry::make('company_email')->label('Email corporativo'),
                    Infolists\Components\TextEntry::make('company_phone')->label('Teléfono')->placeholder('—'),
                    Infolists\Components\TextEntry::make('country')->label('País'),
                    Infolists\Components\TextEntry::make('timezone')->label('Zona horaria'),
                    Infolists\Components\TextEntry::make('currency')->label('Moneda'),
                    Infolists\Components\TextEntry::make('language')->label('Idioma'),
                ])->columns(3),

            Infolists\Components\Section::make('Propietario')
                ->schema([
                    Infolists\Components\TextEntry::make('owner_name')->label('Nombre'),
                    Infolists\Components\TextEntry::make('owner_email')->label('Email'),
                ])->columns(2),

            Infolists\Components\Section::make('Dominio')
                ->schema([
                    Infolists\Components\TextEntry::make('domain')
                        ->label('URL del tenant')
                        ->getStateUsing(fn (Tenant $record) => $record->domains->first()?->domain ?? '—')
                        ->fontFamily('mono')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('id')
                        ->label('UUID')
                        ->fontFamily('mono')
                        ->copyable(),
                ])->columns(2),

            Infolists\Components\Section::make('Estado')
                ->schema([
                    Infolists\Components\TextEntry::make('status')
                        ->label('Estado operacional')
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            'active'    => 'success',
                            'suspended' => 'danger',
                            default     => 'gray',
                        })
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'active'    => 'Activo',
                            'suspended' => 'Suspendido',
                            default     => $state,
                        }),

                    Infolists\Components\TextEntry::make('subscription_status')
                        ->label('Suscripción')
                        ->getStateUsing(fn (Tenant $record) => $record->subscription_status)
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            'trialing' => 'primary',
                            'active'   => 'success',
                            'expired'  => 'warning',
                            'canceled' => 'danger',
                            default    => 'gray',
                        })
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'trialing' => 'Trial',
                            'active'   => 'Activo',
                            'expired'  => 'Expirado',
                            'canceled' => 'Cancelado',
                            default    => $state ?? '—',
                        }),

                    Infolists\Components\TextEntry::make('trial_ends_at')
                        ->label('Trial hasta')
                        ->getStateUsing(fn (Tenant $record) => $record->trial_ends_at)
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),
                ])->columns(3),

            Infolists\Components\Section::make('Registro')
                ->schema([
                    Infolists\Components\TextEntry::make('created_at')->label('Creado')->dateTime('d/m/Y H:i'),
                    Infolists\Components\TextEntry::make('updated_at')->label('Actualizado')->dateTime('d/m/Y H:i'),
                ])->columns(2),

        ]);
    }

    // ── Relations & pages ─────────────────────────────────────────────────

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view'   => Pages\ViewTenant::route('/{record}'),
            'edit'   => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    // ── Shared option helpers ─────────────────────────────────────────────

    public static function countryOptions(): array
    {
        return [
            'ES' => 'España',
            'FR' => 'Francia',
            'DE' => 'Alemania',
            'IT' => 'Italia',
            'PT' => 'Portugal',
            'NL' => 'Países Bajos',
            'BE' => 'Bélgica',
            'CH' => 'Suiza',
            'AT' => 'Austria',
            'GB' => 'Reino Unido',
            'US' => 'Estados Unidos',
            'CA' => 'Canadá',
            'MX' => 'México',
            'AR' => 'Argentina',
            'CO' => 'Colombia',
            'CL' => 'Chile',
            'PE' => 'Perú',
            'VE' => 'Venezuela',
            'EC' => 'Ecuador',
            'BO' => 'Bolivia',
            'UY' => 'Uruguay',
            'PY' => 'Paraguay',
            'CR' => 'Costa Rica',
            'DO' => 'Rep. Dominicana',
            'MA' => 'Marruecos',
            'DZ' => 'Argelia',
            'TN' => 'Túnez',
            'SN' => 'Senegal',
            'CI' => 'Costa de Marfil',
        ];
    }

    public static function timezoneOptions(): array
    {
        return collect(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL))
            ->mapWithKeys(fn (string $tz) => [$tz => $tz])
            ->toArray();
    }

    public static function currencyOptions(): array
    {
        return [
            'EUR' => 'EUR — Euro',
            'USD' => 'USD — Dólar americano',
            'GBP' => 'GBP — Libra esterlina',
            'CHF' => 'CHF — Franco suizo',
            'MXN' => 'MXN — Peso mexicano',
            'ARS' => 'ARS — Peso argentino',
            'COP' => 'COP — Peso colombiano',
            'CLP' => 'CLP — Peso chileno',
            'PEN' => 'PEN — Sol peruano',
            'MAD' => 'MAD — Dírham marroquí',
            'DZD' => 'DZD — Dinar argelino',
        ];
    }
}
