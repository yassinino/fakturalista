<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantReminderLog extends Model
{
    protected $connection = 'mysql';
    protected $table = 'tenant_reminder_logs';

    protected $fillable = [
        'tenant_id',
        'reminder_type',
        'period_ends_at',
        'sent_at',
    ];

    protected $casts = [
        'period_ends_at' => 'datetime',
        'sent_at'        => 'datetime',
    ];
}
