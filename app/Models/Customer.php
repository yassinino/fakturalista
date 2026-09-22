<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;


class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name',
        'first_name',
        'last_name',
        'middle_name',
        'ice',
        // Morocco Phase 1B - optional, business-customer-only in practice,
        // but never enforced (see isBusiness()/isIndividual() below and
        // CustomerRequest - no customer is ever required to have any of
        // ICE/IF/RC).
        'if_number',
        'commercial_register',
        'type',
        'billing_country_id',
        'delivery_country_id',
        'uuid',
        'reference',
        'vat_number',
        'tax_id',
        // AEAT IDOtro (Orden HAC/1177/2024, Anexo, lista L7) - only for a
        // customer explicitly identified as foreign; a Spanish customer
        // keeps using `tax_id` exactly as before. See
        // VerifactuChainService::resolveDestinatario().
        'foreign_tax_id_type',
        'foreign_tax_id',
        'email',
        'phone',
        'website',
        'city_billing',
        'address_billing',
        'post_code_billing',
        'is_same_address',
        'city_delivery',
        'address_delivery',
        'post_code_delivery',
    ];

    protected $appends = [
       'name'
    ];

    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    public function billingCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'billing_country_id');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function getNameAttribute(){

        if($this->type == 1){
            return $this->company_name;
        }
        return $this->first_name.' '.$this->last_name;

    }

    /**
     * A) Business customer vs B) individual - Morocco Phase 1B (docs/morocco-phase-1b-identity.md §4).
     * Reuses the existing `type` column (already the company/individual
     * distinction behind getNameAttribute() above) rather than adding a
     * new one.
     */
    public function isBusiness(): bool
    {
        return (int) $this->type === 1;
    }

    public function isIndividual(): bool
    {
        return !$this->isBusiness();
    }

    /**
     * Identity fields to freeze onto an invoice at issuance time - see
     * Invoice::snapshotCustomer() / InvoiceController::issueInvoice().
     * Generic (not VERI*FACTU-specific): includes whichever of
     * NIF/foreign-ID (Spain) or ICE/IF/RC (Morocco) this customer
     * actually has, never fabricating a value it doesn't have.
     */
    public function identitySnapshot(): array
    {
        return [
            'name'                => $this->name,
            'company_name'        => $this->company_name,
            'first_name'          => $this->first_name,
            'last_name'           => $this->last_name,
            'type'                => $this->type,
            'ice'                 => $this->ice,
            'if_number'           => $this->if_number,
            'commercial_register' => $this->commercial_register,
            'tax_id'              => $this->tax_id,
            'vat_number'          => $this->vat_number,
            'foreign_tax_id_type' => $this->foreign_tax_id_type,
            'foreign_tax_id'      => $this->foreign_tax_id,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'reference'           => $this->reference,
            'address_billing'     => $this->address_billing,
            'city_billing'        => $this->city_billing,
            'post_code_billing'   => $this->post_code_billing,
            'country'             => $this->billingCountry?->name,
        ];
    }

    /**
     * True when this customer is identified via AEAT's IDOtro branch
     * (foreign tax/identity document) rather than a Spanish NIF. See
     * VerifactuChainService::resolveDestinatario() - the NIF branch
     * always takes priority when `tax_id` is set, for backward
     * compatibility with every customer that predates this field.
     */
    public function hasForeignTaxId(): bool
    {
        return empty($this->tax_id) && !empty($this->foreign_tax_id_type) && !empty($this->foreign_tax_id);
    }
}
