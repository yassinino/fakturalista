<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingProfile extends Model
{
  
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'provider',
        'provider_customer_id',
        'email',
        'raw'
    ];

    protected $casts = [
        'raw' => 'array',   // 👈 important
    ];


    public function tenant() {
        return $this->belongsTo(\App\Models\Tenant::class, 'tenant_id');
    }

    public function subscriptions() {
         return $this->hasMany(Subscription::class); 
    }


}