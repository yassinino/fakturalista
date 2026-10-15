<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One tracked public page view - see config/analytics.php for definitions. */
class AnalyticsPageview extends Model
{
    protected $connection = 'mysql';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'viewed_at'   => 'datetime',
        'view_date'   => 'date',
        'is_entrance' => 'boolean',
    ];
}
