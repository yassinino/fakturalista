<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Services\TenantProvisioningService;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class CreateTenant extends CreateRecord
{
    use HasWizard;

    protected static string $resource = TenantResource::class;

    protected function getSteps(): array
    {
        return [

            // ── Step 1: Company information ───────────────────────────────
            Step::make('company')
                ->label('Empresa')
                ->icon('heroicon-o-building-office')
                ->schema([
                    Forms\Components\TextInput::make('company_name')
                        ->label('Nombre de la empresa')
                        ->required()
                        ->maxLength(255)
                        ->live(debounce: 500)
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            // Pre-fill subdomain from company name
                            $slug = Str::slug($state);
                            if ($slug) {
                                $set('subdomain', $slug);
                            }
                        }),

                    Forms\Components\TextInput::make('company_email')
                        ->label('Email corporativo')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('company_phone')
                        ->label('Teléfono (opcional)')
                        ->tel()
                        ->maxLength(50),

                    Forms\Components\Select::make('country')
                        ->label('País')
                        ->options(TenantResource::countryOptions())
                        ->searchable()
                        ->required()
                        ->default('ES'),

                    Forms\Components\Select::make('timezone')
                        ->label('Zona horaria')
                        ->options(TenantResource::timezoneOptions())
                        ->searchable()
                        ->required()
                        ->default('Europe/Madrid'),

                    Forms\Components\Select::make('currency')
                        ->label('Moneda')
                        ->options(TenantResource::currencyOptions())
                        ->required()
                        ->default('EUR'),

                    Forms\Components\Select::make('language')
                        ->label('Idioma')
                        ->options(['es' => 'Español', 'fr' => 'Français', 'en' => 'English'])
                        ->required()
                        ->default('es'),
                ])->columns(2),

            // ── Step 2: Subdomain ─────────────────────────────────────────
            Step::make('subdomain')
                ->label('Dominio')
                ->icon('heroicon-o-globe-alt')
                ->schema([
                    Forms\Components\TextInput::make('subdomain')
                        ->label('Subdominio')
                        ->required()
                        ->maxLength(63)
                        ->prefix('https://')
                        ->suffix('.fakturalista.com')
                        ->helperText('Solo letras minúsculas, números y guiones. Mínimo 3 caracteres.')
                        ->rules([
                            'regex:/^[a-z0-9][a-z0-9\-]{1,61}[a-z0-9]$/',
                            new class implements \Illuminate\Contracts\Validation\ValidationRule {
                                public function validate(string $attribute, mixed $value, \Closure $fail): void
                                {
                                    if (Domain::where('domain', $value . '.fakturalista.com')->exists()) {
                                        $fail('Este subdominio ya está en uso.');
                                    }
                                }
                            },
                        ])
                        ->live(debounce: 600)
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $set('subdomain', Str::slug($state));
                        }),

                    Forms\Components\Placeholder::make('dns_notice')
                        ->label('Configuración DNS')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div class="text-sm text-gray-500 space-y-1">'
                            . '<p>Para que el dominio funcione, asegúrate de que el registro DNS esté configurado:</p>'
                            . '<code class="block bg-gray-100 dark:bg-gray-800 rounded px-3 py-2 mt-2 font-mono text-xs">'
                            . '*.fakturalista.com → IP del servidor'
                            . '</code>'
                            . '<p class="mt-2">Con el wildcard DNS configurado, cualquier nuevo subdominio funcionará automáticamente.</p>'
                            . '</div>'
                        ))
                        ->columnSpanFull(),
                ])->columns(1),

            // ── Step 3: Administrator ─────────────────────────────────────
            Step::make('administrator')
                ->label('Administrador')
                ->icon('heroicon-o-user-circle')
                ->schema([
                    Forms\Components\TextInput::make('owner_name')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('owner_email')
                        ->label('Email del administrador')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('admin_password')
                        ->label('Contraseña')
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->rules(['confirmed'])
                        ->suffixActions([
                            Forms\Components\Actions\Action::make('generate_password')
                                ->icon('heroicon-m-arrow-path')
                                ->label('Generar')
                                ->action(function (Forms\Set $set) {
                                    $password = Str::random(12) . rand(10, 99);
                                    $set('admin_password', $password);
                                    $set('admin_password_confirmation', $password);
                                }),
                        ]),

                    Forms\Components\TextInput::make('admin_password_confirmation')
                        ->label('Confirmar contraseña')
                        ->password()
                        ->required(),

                    Forms\Components\Placeholder::make('credentials_notice')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div class="text-sm text-amber-600 dark:text-amber-400 flex items-start gap-2">'
                            . '<svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">'
                            . '<path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />'
                            . '</svg>'
                            . '<span>Guarda estas credenciales. El administrador deberá usarlas para acceder al tenant por primera vez.</span>'
                            . '</div>'
                        ))
                        ->columnSpanFull(),
                ])->columns(2),

            // ── Step 4: Review ────────────────────────────────────────────
            Step::make('review')
                ->label('Revisión')
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    Forms\Components\Section::make('Datos de la empresa')
                        ->schema([
                            Forms\Components\Placeholder::make('r_company_name')
                                ->label('Empresa')
                                ->content(fn (Forms\Get $get) => $get('company_name') ?: '-'),
                            Forms\Components\Placeholder::make('r_company_email')
                                ->label('Email corporativo')
                                ->content(fn (Forms\Get $get) => $get('company_email') ?: '-'),
                            Forms\Components\Placeholder::make('r_country')
                                ->label('País')
                                ->content(fn (Forms\Get $get) => TenantResource::countryOptions()[$get('country')] ?? $get('country') ?: '-'),
                            Forms\Components\Placeholder::make('r_currency')
                                ->label('Moneda')
                                ->content(fn (Forms\Get $get) => $get('currency') ?: '-'),
                            Forms\Components\Placeholder::make('r_language')
                                ->label('Idioma')
                                ->content(fn (Forms\Get $get) => match ($get('language')) {
                                    'es' => 'Español', 'fr' => 'Français', 'en' => 'English', default => '-',
                                }),
                            Forms\Components\Placeholder::make('r_timezone')
                                ->label('Zona horaria')
                                ->content(fn (Forms\Get $get) => $get('timezone') ?: '-'),
                        ])->columns(3),

                    Forms\Components\Section::make('Dominio y acceso')
                        ->schema([
                            Forms\Components\Placeholder::make('r_subdomain')
                                ->label('URL del tenant')
                                ->content(fn (Forms\Get $get) => $get('subdomain')
                                    ? 'https://' . $get('subdomain') . '.fakturalista.com'
                                    : '-'),
                            Forms\Components\Placeholder::make('r_owner')
                                ->label('Administrador')
                                ->content(fn (Forms\Get $get) => trim(($get('owner_name') ?? '') . ' <' . ($get('owner_email') ?? '') . '>') ?: '-'),
                        ])->columns(2),

                    Forms\Components\Placeholder::make('provisioning_note')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<div class="text-sm text-blue-600 dark:text-blue-400 flex items-start gap-2">'
                            . '<svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">'
                            . '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />'
                            . '</svg>'
                            . '<span>Al confirmar, se creará la base de datos del tenant, se ejecutarán las migraciones y se creará el usuario administrador. Este proceso puede tardar unos segundos.</span>'
                            . '</div>'
                        ))
                        ->columnSpanFull(),
                ]),

        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(TenantProvisioningService::class)->provision($data);
    }

    protected function getCreatedNotification(): ?Notification
    {
        $record = $this->record;
        $domain = $record?->domains->first()?->domain ?? '';

        return Notification::make()
            ->title('Tenant creado correctamente')
            ->body($domain ? "Accesible en https://{$domain}" : null)
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
