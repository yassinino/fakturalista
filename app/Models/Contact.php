<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contact extends Model
{
    use HasFactory;
  
    protected $fillable = [
        'contactable_type', 
        'contactable_id',
        'first_name',
        'last_name',
        'country_id',
        'email',
        'work_phone',
        'cell_phone',
        'address',
        'city',
        'post_code',
    ];

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}