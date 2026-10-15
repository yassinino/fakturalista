<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A completed conversion (e.g. a self-service free-trial signup). No personal data. */
class AnalyticsConversion extends Model
{
    public const FREE_TRIAL_SIGNUP = 'free_trial_signup';

    protected $connection = 'mysql';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'converted_at'    => 'datetime',
        'conversion_date' => 'date',
    ];
}
