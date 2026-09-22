<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'family_id',
        'uuid',
        'name',
        'unite',
        'type',
        'sales_price',
        'purchase_price',
        'vta',
        // Morocco Phase 1C.2 - the item's default tax treatment
        // (taxable/exempt/out_of_scope), populated onto a cart line when
        // this item/service is selected. See App\Services\Tax\TaxTreatment.
        'tax_treatment',
        'currency',
        'active',
        'reference',
        'description',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}