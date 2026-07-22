<?php

namespace App\Services;

use App\Mail\WelcomeTenantMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TenantProvisioningService
{
    /**
     * Provision a new tenant end-to-end:
     *
     *   1. Create the Tenant record (central DB).
     *      Stancl's TenancyServiceProvider listens to TenantCreated and runs
     *      Jobs\CreateDatabase + Jobs\MigrateDatabase synchronously.
     *      Jobs\CreateDatabase issues "CREATE DATABASE …" — a MySQL DDL statement
     *      that causes an implicit commit of any open transaction. Wrapping this
     *      in DB::transaction() therefore always throws "There is no active
     *      transaction" when the transaction layer tries to commit afterwards.
     *      Solution: no transaction wrapper here; cleanup is handled imperatively.
     *
     *   2. Create the Domain record (central DB).
     *
     *   3. Create the first admin User (tenant DB).
     *
     * Rollback strategy on failure:
     *   - $tenant->delete() fires TenantDeleted → Jobs\DeleteDatabase automatically
     *     drops the tenant database (if it was already created by the pipeline).
     *   - Domain records cascade-delete with the tenant record.
     */
    public function provision(array $data): Tenant
    {
        $tenant = null;

        try {
            // ── Step 1: Tenant record ─────────────────────────────────────────
            // Stancl's JobPipeline listener (CreateDatabase + MigrateDatabase)
            // fires synchronously here. CREATE DATABASE is DDL — it implicitly
            // commits any open MySQL transaction, so no DB::transaction() wrapper.
            $tenant = Tenant::create([
                'company_name'        => $data['company_name'],
                'company_email'       => $data['company_email'],
                'company_phone'       => $data['company_phone'] ?? null,
                'country'             => $data['country'],
                'timezone'            => $data['timezone'],
                'currency'            => $data['currency'],
                'language'            => $data['language'],
                'status'              => 'active',
                'owner_name'          => $data['owner_name'],
                'owner_email'         => $data['owner_email'],
                'subscription_status' => 'trialing',
                'trial_ends_at'       => now()->addMonths(3),
            ]);

            // ── Step 2: Domain record ─────────────────────────────────────────
            $tenant->domains()->create([
                'domain' => $data['subdomain'] . '.fakturalista.com',
            ]);

            // ── Step 3: Admin user (tenant DB) ────────────────────────────────
            // By this point the pipeline has already created and migrated the
            // tenant DB, so we can initialize tenancy and write the first user.
            tenancy()->initialize($tenant);
            try {
                User::create([
                    'name'     => $data['owner_name'],
                    'email'    => $data['owner_email'],
                    'password' => Hash::make($data['admin_password']),
                ]);
            } finally {
                try {
                    tenancy()->end();
                } catch (\Throwable $ex) {
                    Log::warning('tenancy()->end() failed after user creation', [
                        'error' => $ex->getMessage(),
                    ]);
                }
            }

            // ── Step 4: Welcome email ─────────────────────────────────────────
            // Isolated: a mail failure must never roll back a successfully
            // provisioned tenant. Plain password lives only in memory here
            // ($data['admin_password']) — it was never persisted to the DB.
            try {
                $loginUrl = 'https://' . $data['subdomain'] . '.fakturalista.com/login';

                Mail::send(new WelcomeTenantMail(
                    tenant:        $tenant,
                    adminEmail:    $data['owner_email'],
                    adminName:     $data['owner_name'],
                    plainPassword: $data['admin_password'],
                    loginUrl:      $loginUrl,
                ));
            } catch (\Throwable $mailEx) {
                Log::error('Welcome email failed after tenant provisioning', [
                    'tenant_id' => $tenant->getTenantKey(),
                    'error'     => $mailEx->getMessage(),
                ]);
            }

            return $tenant;

        } catch (\Throwable $e) {
            Log::error('Tenant provisioning failed', [
                'tenant_id' => $tenant?->getTenantKey(),
                'error'     => $e->getMessage(),
            ]);

            // If the Tenant record was persisted before the failure, delete it.
            // TenantDeleted event fires Jobs\DeleteDatabase automatically,
            // so the tenant database (if created) is also cleaned up.
            if ($tenant?->exists) {
                try {
                    $tenant->delete();
                } catch (\Throwable $cleanupEx) {
                    Log::warning('Rollback: could not delete tenant record', [
                        'tenant_id' => $tenant->getTenantKey(),
                        'error'     => $cleanupEx->getMessage(),
                    ]);
                }
            }

            throw new \RuntimeException(
                'Error al provisionar el tenant: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
