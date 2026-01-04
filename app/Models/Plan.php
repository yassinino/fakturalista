<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{

    protected $connection = 'mysql';
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'amount' => 'integer',
    ];

}