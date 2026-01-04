<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class InvoiceTemplate extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'version',
        'is_default',
        'is_active',
        'document_type',
        'engine',
        'paper_size',
        'orientation',
        'locale',
        'timezone',
        'css',
        'header_html',
        'body_html',
        'footer_html',
        'primary',
        'text',
        'muted',
        'table_header_bg',
        'table_header_text',
        'table_border',
        'font_family',
        'font_size',
        'logo_width_mm',
        'logo_position',
        'show_payment_terms',
        'show_customer_number',
        'show_customer_phone',
        'show_shipping_address',
        'billing_address_right',
        'show_discount',
        'show_tax_column',
        'show_subtotal',
        'show_tax_breakdown',
        'bold_total',
        'payment_note',
        'show_payment_note',
        'settings',
        'placeholders',
        'logo_path',
        'signature_path',
        'background_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'version' => 'integer',
        'margin_top_mm' => 'integer',
        'margin_right_mm' => 'integer',
        'margin_bottom_mm' => 'integer',
        'margin_left_mm' => 'integer',
        'logo_width_mm' => 'integer',
        'show_payment_terms' => 'boolean',
        'show_customer_number' => 'boolean',
        'show_customer_phone' => 'boolean',
        'show_shipping_address' => 'boolean',
        'billing_address_right' => 'boolean',
        'show_discount' => 'boolean',
        'show_tax_column' => 'boolean',
        'show_subtotal' => 'boolean',
        'show_tax_breakdown' => 'boolean',
        'bold_total' => 'boolean',
        'show_payment_note' => 'boolean',
        'settings' => 'array',
        'placeholders' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (InvoiceTemplate $template) {
            if (empty($template->version)) {
                $template->version = 1;
            }
            if (empty($template->document_type)) {
                $template->document_type = 'invoice';
            }
        });
    }
}
