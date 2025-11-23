<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use HasFactory, SoftDeletes;
  
    protected $fillable = [
        'templateable_type', 
        'templateable_id',
        'name',
        'value',
        'type',
    ];

    public function templateable(): MorphTo
    {
        return $this->morphTo();
    }
}