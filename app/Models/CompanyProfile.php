<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_name',
        'trade_name',
        'industry',
        'country_code',
        'tax_id',
        'vat_number',
        'registration_number',
        'email',
        'phone',
        'website',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'logo_path',
        'stamp_path',
        'brand_color',
        'invoice_footer_note',
        'invoice_prefix',
        'invoice_next_number',
        'invoice_number_format',
        'timezone',
        'locale',
        'currency',
        'bank_name',
        'iban',
        'swift',
    ];

    protected $casts = [
        'invoice_next_number' => 'integer',
    ];
}
