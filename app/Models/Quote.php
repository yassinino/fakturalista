<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Quote extends Model
{
    use HasFactory, SoftDeletes;
  
    protected $fillable = [
        'customer_id', 
        'reference',
        'uuid', 
        'date',
        'status',
        'expiration_date',
        'payment_terms',
        'sub_total',
        'discount_rate',
        'discount_amount',
        'vta',
        'total',
        'note',
    ];

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function carts(): MorphMany
    {
        return $this->morphMany(Cart::class, 'cartable');
    }

}