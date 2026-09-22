<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceNumberSequence extends Model
{
    protected $fillable = [
        'series_key',
        'next_number',
    ];

    protected $casts = [
        'next_number' => 'integer',
    ];
}
