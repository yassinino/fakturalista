<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceHistory extends Model
{
    const ACTION_CREATED    = 'created';
    const ACTION_ISSUED     = 'issued';
    const ACTION_DUPLICATED = 'duplicated';
    const ACTION_PAID       = 'paid';
    const ACTION_CANCELLED  = 'cancelled';
    const ACTION_SENT       = 'sent';
    const ACTION_WHATSAPP   = 'whatsapp';

    protected $table = 'invoice_history';

    protected $fillable = [
        'invoice_id',
        'action',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
