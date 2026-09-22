<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    protected $connection = 'mysql';
    protected $table = 'subscriptions';

    // pour autoriser tenant_id, plan_id, etc.
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'provider',
        'provider_subscription_id',
        'status',
        'trial_ends_at',
        'current_period_ends_at',
        // Was missing - every `update(['raw' => $stripeSub])` call in
        // SubscriptionController/StripeWebhookController was silently a
        // no-op without this, so `cancel_at_period_end` (the only way to
        // tell "still active" apart from "active, cancels at period end")
        // never actually reached the DB.
        'raw',
    ];

    protected $casts = [
        'trial_ends_at'          => 'datetime',
        'current_period_ends_at' => 'datetime',
        'raw'                    => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}