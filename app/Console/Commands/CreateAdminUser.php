<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'blog:admin {--name= : Display name} {--email= : Email address} {--password= : Password}';

    protected $description = 'Create a blog admin user for the Filament panel';

    public function handle(): int
    {
        $name     = $this->option('name')     ?? $this->ask('Name');
        $email    = $this->option('email')    ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password');

        if (AdminUser::where('email', $email)->exists()) {
            $this->error("An admin with the email [{$email}] already exists.");
            return self::FAILURE;
        }

        AdminUser::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Admin user [{$email}] created successfully.");
        $this->line('Log in at: ' . url('/admin'));

        return self::SUCCESS;
    }
}
