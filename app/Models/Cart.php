<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cart extends Model
{
    use HasFactory, SoftDeletes;
  
    protected $fillable = [
        'cartable_type', 
        'cartable_id',
        'item_id',
        'description',
        'qty',
        'unite',
        'price',
        'total',
        'discount',
        'vta',
    ];

    public function cartable(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function product()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}