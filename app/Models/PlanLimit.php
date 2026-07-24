<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanLimit extends Model
{
    protected $connection = 'mysql';
    protected $guarded    = [];

    protected $casts = [
        'value' => 'integer',
    ];

    // List of known resource slugs and their human labels
    public const RESOURCES = [
        'invoices_per_month' => 'Factures / mois',
        'customers'          => 'Clients',
        'users'              => 'Utilisateurs',
        'products'           => 'Produits',
        'quotes'             => 'Devis',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isUnlimited(): bool
    {
        return is_null($this->value);
    }
}
