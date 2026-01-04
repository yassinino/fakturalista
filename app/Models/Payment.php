<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    protected $connection = 'mysql';
    protected $table = 'payments';

    protected $guarded = []; // plus simple pour toi

    protected $casts = [
        'paid_at'              => 'datetime',
        'billing_period_start' => 'datetime',
        'billing_period_end'   => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}