<?php

namespace App\Services\Pdf;

use App\Models\CompanyProfile;
use App\Models\InvoiceTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TemplateRendererService
{
    private const DEFAULTS = [
        'primary'               => '#E91E63',
        'text'                  => '#1f1c1a',
        'muted'                 => '#6b6764',
        'table_header_bg'       => '#E91E63',
        'table_header_text'     => '#ffffff',
        'table_border'          => '#e5e0db',
        'font_family'           => 'Arial, sans-serif',
        'font_size'             => 'medium',
        'logo_width_mm'         => 50,
        'logo_position'         => 'left',
        'show_payment_terms'    => true,
        'show_customer_number'  => false,
        'show_customer_phone'   => false,
        'show_shipping_address' => false,
        'billing_address_right' => false,
        'show_discount'         => false,
        'show_tax_column'       => true,
        'show_subtotal'         => true,
        'show_tax_breakdown'    => true,
        'bold_total'            => true,
        'payment_note'          => '',
        'show_payment_note'     => false,
    ];

    /**
     * Render an Invoice or Quote to a PDF binary string using the active template.
     *
     * @param  object  $document  Invoice or Quote model (must have customer & carts loaded)
     * @param  string  $docType   'invoice' | 'quote'
     */
    public function render(object $document, string $docType): string
    {
        $document->loadMissing('customer', 'carts');

        $template = InvoiceTemplate::where('is_active', true)
            ->orderByDesc('is_default')
            ->latest('created_at')
            ->first();

        $design  = $this->mergeDesign($template);
        $company = CompanyProfile::first();
        $logoSrc = $this->resolveLogoSrc($design['logo_path'] ?? null, $company);

        Log::info('PDF render', [
            'doc_type'    => $docType,
            'doc_id'      => $document->id ?? null,
            'template_id' => $template?->id,
            'template'    => $template?->name ?? 'default',
        ]);

        $locale           = app()->getLocale() ?: config('app.locale', 'es');
        $supportedLocales = config('app.supported_locales', ['es', 'fr', 'en']);
        if (!in_array($locale, $supportedLocales, true)) {
            $locale = config('app.locale', 'es');
        }

        [$decimalSep, $thousandsSep, $dateFmt] = match ($locale) {
            'en'    => ['.', ',', 'm/d/Y'],
            'fr'    => [',', ' ', 'd/m/Y'],
            default => [',', '.', 'd-m-Y'],
        };

        $formatMoney  = fn($v) => number_format((float) $v, 2, $decimalSep, $thousandsSep) . ' €';
        $formatNumber = fn($v) => number_format((float) $v, 2, $decimalSep, $thousandsSep);

        $fontSize    = match ($design['font_size'] ?? 'medium') {
            'small' => 13,
            'large' => 16,
            default => 14,
        };
        $logoWidthPx = ($design['logo_width_mm'] ?? 50) * 3.78;
        $isLogoAbove = ($design['logo_position'] ?? 'left') === 'above';

        $docDate    = !empty($document->date)
            ? Carbon::parse($document->date)->format($dateFmt)
            : Carbon::now()->format($dateFmt);
        $expiryDate = ($docType === 'quote' && !empty($document->expiration_date))
            ? Carbon::parse($document->expiration_date)->format($dateFmt)
            : null;

        $statusData = $this->resolveStatus($document->status ?? 'draft', $docType, $locale);

        // Tax breakdown computed from cart lines (works for both Invoice and Quote)
        $taxGroups = [];
        foreach (($document->carts ?? []) as $cart) {
            $rate = (int) ($cart->vta ?? 0);
            if ($rate <= 0) {
                continue;
            }
            $lineBase         = (float) $cart->qty * (float) $cart->price;
            $taxGroups[$rate] = ($taxGroups[$rate] ?? 0) + round($lineBase * $rate / 100, 2);
        }
        ksort($taxGroups);

        $subTotal       = (float) ($document->sub_total ?? 0);
        $discountAmount = (float) ($document->discount_amount ?? 0);
        $grandTotal     = (float) ($document->total ?? 0);

        $companyName  = $company?->trade_name ?: ($company?->legal_name ?: config('app.name'));
        $addressParts = array_filter([
            $company?->address_line1,
            $company?->address_line2,
            trim(implode(' ', array_filter([$company?->postal_code, $company?->city]))),
            $company?->country,
        ]);
        $companyAddress = implode("\n", $addressParts);

        // Stripe payment link - invoices only, when not paid/cancelled and Stripe is configured
        $pdfPaymentUrl = null;
        if (
            $docType === 'invoice'
            && method_exists($document, 'isPaid')
            && !$document->isPaid()
            && method_exists($document, 'isCancelled')
            && !$document->isCancelled()
            && !empty(config('services.stripe.secret'))
        ) {
            $pdfPaymentUrl = request()->getSchemeAndHttpHost() . '/pay/' . $document->uuid;
        }

        try {
            return Pdf::loadView('pdf.document', compact(
                'document', 'docType', 'design', 'company',
                'companyName', 'companyAddress', 'logoSrc',
                'fontSize', 'logoWidthPx', 'isLogoAbove',
                'locale', 'formatMoney', 'formatNumber',
                'docDate', 'expiryDate', 'statusData',
                'taxGroups', 'subTotal', 'discountAmount', 'grandTotal',
                'pdfPaymentUrl'
            ))
            ->setPaper('a4')
            ->output();
        } catch (\Throwable $e) {
            Log::error('PDF rendering failed', [
                'doc_type'    => $docType,
                'doc_id'      => $document->id ?? null,
                'template_id' => $template?->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function mergeDesign(?InvoiceTemplate $template): array
    {
        $design = self::DEFAULTS;

        if (!$template) {
            return $design;
        }

        // Settings JSON column (lower priority)
        foreach ($template->settings ?? [] as $key => $val) {
            if (array_key_exists($key, $design)) {
                $design[$key] = $val;
            }
        }

        // Direct template DB columns override settings JSON
        foreach (array_keys(self::DEFAULTS) as $key) {
            $val = $template->{$key} ?? null;
            if ($val !== null) {
                $design[$key] = $val;
            }
        }

        // logo_path is not in DEFAULTS but should flow through
        if (!empty($template->logo_path)) {
            $design['logo_path'] = $template->logo_path;
        }

        return $design;
    }

    private function resolveLogoSrc(?string $logoPath, ?CompanyProfile $company): ?string
    {
        if (empty($logoPath) && !empty($company?->logo_path)) {
            $logoPath = $company->logo_path;
        }
        if (empty($logoPath)) {
            return null;
        }

        if (preg_match('~^https?://~i', $logoPath)) {
            $logoPath = parse_url($logoPath, PHP_URL_PATH) ?? '';
        }

        if (!empty($logoPath) && is_file($logoPath)) {
            return 'file://' . $logoPath;
        }

        $logoDisk  = Storage::disk('public');
        $candidate = ltrim($logoPath, '/');
        if (str_starts_with($candidate, 'storage/')) {
            $candidate = substr($candidate, strlen('storage/'));
        }
        if (!empty($candidate) && $logoDisk->exists($candidate)) {
            return 'file://' . $logoDisk->path($candidate);
        }

        return null;
    }

    private function resolveStatus(string $status, string $docType, string $locale): array
    {
        if ($docType === 'invoice') {
            $labels = match ($locale) {
                'fr'    => ['draft' => 'Brouillon', 'issued' => 'Émise',   'paid' => 'Payée',  'cancelled' => 'Annulée'],
                'es'    => ['draft' => 'Borrador',  'issued' => 'Emitida', 'paid' => 'Pagada', 'cancelled' => 'Cancelada'],
                default => ['draft' => 'Draft',     'issued' => 'Issued',  'paid' => 'Paid',   'cancelled' => 'Cancelled'],
            };
            $colors = ['draft' => '#6b7280', 'issued' => '#0284c7', 'paid' => '#16a34a', 'cancelled' => '#dc2626'];
        } else {
            $labels = match ($locale) {
                'fr'    => ['draft' => 'Brouillon', 'sent' => 'Envoyé',  'converted' => 'Converti',  'cancelled' => 'Annulé'],
                'es'    => ['draft' => 'Borrador',  'sent' => 'Enviado', 'converted' => 'Convertido', 'cancelled' => 'Cancelado'],
                default => ['draft' => 'Draft',     'sent' => 'Sent',    'converted' => 'Converted',  'cancelled' => 'Cancelled'],
            };
            $colors = ['draft' => '#6b7280', 'sent' => '#0284c7', 'converted' => '#16a34a', 'cancelled' => '#dc2626'];
        }

        return [
            'label' => $labels[$status] ?? ucfirst($status),
            'color' => $colors[$status] ?? '#6b7280',
        ];
    }
}
