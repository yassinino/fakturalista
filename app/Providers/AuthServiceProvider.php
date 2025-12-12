<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;
use App\Models\Passport\Client as PassportClient;
use App\Models\Passport\PersonalAccessClient as PassportPersonalAccessClient;
use App\Models\Passport\Token as PassportToken;
use App\Models\Passport\AuthCode as PassportAuthCode;
use App\Models\Passport\RefreshToken as PassportRefreshToken;
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
            // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();


        // ⚠️ IMPORTANT : on force l’utilisation de nos modèles "centrales"
        Passport::useClientModel(PassportClient::class);
        Passport::usePersonalAccessClientModel(PassportPersonalAccessClient::class);
        Passport::useTokenModel(PassportToken::class);
        Passport::useAuthCodeModel(PassportAuthCode::class);
        Passport::useRefreshTokenModel(PassportRefreshToken::class);

    }
}
