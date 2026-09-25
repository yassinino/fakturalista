<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_name',
        'trade_name',
        'industry',
        'country_code',
        'tax_id',
        'vat_number',
        'registration_number',
        // Morocco Phase 1B (docs/morocco-phase-1b-identity.md) - Moroccan
        // fiscal identity, distinct from tax_id (Spanish NIF/CIF) and
        // vat_number (EU VAT). "RC" (Registre de Commerce) reuses
        // registration_number instead of a third new column.
        'ice',
        'if_number',
        // Morocco Phase 1C.2 (§11) - a TaxPreset code (e.g. "MA_TVA_20"),
        // used only as a convenience default for NEW lines. Never read by
        // DocumentCalculationService and never affects an existing
        // invoice/quote.
        'default_tax_code',
        'email',
        'phone',
        'website',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'logo_path',
        'stamp_path',
        'brand_color',
        'invoice_footer_note',
        'invoice_prefix',
        'invoice_next_number',
        'invoice_number_format',
        'rectification_prefix',
        'verifactu_installation_number',
        'timezone',
        'locale',
        'currency',
        // Settings > Notifications (App\Services\NotificationPreferencesService
        // resolves this against sensible defaults - null/partial here is
        // expected and safe for every existing tenant).
        'notification_preferences',
        'bank_name',
        'iban',
        'swift',
        // Stripe Connect
        'stripe_account_id',
        'stripe_connection_status',
        'onboarding_completed',
        'charges_enabled',
        'payouts_enabled',
        'stripe_connected_at',
        // Onboarding wizard
        'onboarding_completed_at',
    ];

    protected $casts = [
        'invoice_next_number'       => 'integer',
        'onboarding_completed'      => 'boolean',
        'charges_enabled'           => 'boolean',
        'payouts_enabled'           => 'boolean',
        'stripe_connected_at'       => 'datetime',
        'onboarding_completed_at'   => 'datetime',
        'notification_preferences' => 'array',
    ];

    /**
     * Identity fields to freeze onto an invoice at issuance time - see
     * Invoice::snapshotCompany() / InvoiceController::issueInvoice().
     * Generic (not VERI*FACTU-specific) - includes whichever identity
     * fields this company actually has (NIF/VAT/Registro Mercantil for
     * Spain, ICE/IF/RC for Morocco), never fabricating a value.
     */
    public function identitySnapshot(): array
    {
        return [
            'legal_name'           => $this->legal_name,
            'trade_name'           => $this->trade_name,
            'country_code'         => $this->country_code,
            'tax_id'               => $this->tax_id,
            'vat_number'           => $this->vat_number,
            'registration_number'  => $this->registration_number,
            'ice'                  => $this->ice,
            'if_number'            => $this->if_number,
            'email'                => $this->email,
            'phone'                => $this->phone,
            'website'              => $this->website,
            'address_line1'        => $this->address_line1,
            'address_line2'        => $this->address_line2,
            'city'                 => $this->city,
            'state'                => $this->state,
            'postal_code'          => $this->postal_code,
            'country'              => $this->country,
        ];
    }
}
