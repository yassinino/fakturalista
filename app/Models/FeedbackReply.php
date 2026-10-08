<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An administrator's emailed answer to a Feedback entry (central). Only
 * replies actually sent are stored - see FeedbackService::reply().
 */
class FeedbackReply extends Model
{
    protected $connection = 'mysql';

    protected $fillable = ['feedback_id', 'admin_user_id', 'message', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }
}
