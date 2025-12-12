<?php

namespace App\Models\Passport;

use Laravel\Passport\Client as BaseClient;

class Client extends BaseClient
{
    // On force ce modèle à utiliser la connexion centrale
    protected $connection = 'mysql';
}