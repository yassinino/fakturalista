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
        'reference',
        'description',
    ];

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}