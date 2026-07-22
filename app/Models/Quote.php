<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Quote extends Model
{
    use HasFactory, SoftDeletes;
  
    const STATUS_DRAFT     = 'draft';
    const STATUS_SENT      = 'sent';
    const STATUS_CONVERTED = 'converted';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_id',
        'invoice_id',
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

    public function isPaid(): bool
    {
        return false; // quotes are never "paid" — method required by shared blade template
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    public function carts(): MorphMany
    {
        return $this->morphMany(Cart::class, 'cartable');
    }

}