<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;


class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name',
        'first_name',
        'last_name',
        'middle_name',
        'ice',
        'type',
        'billing_country_id',
        'delivery_country_id',
        'uuid',
        'reference',
        'vat_number',
        'email',
        'phone',
        'website',
        'city_billing',
        'address_billing',
        'post_code_billing',
        'is_same_address',
        'city_delivery',
        'address_delivery',
        'post_code_delivery',
    ];

    protected $appends = [
       'name'
    ];

    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function getNameAttribute(){
        
        if($this->type == 1){
            return $this->company_name;
        }
        return $this->first_name.' '.$this->last_name;

    }
}
