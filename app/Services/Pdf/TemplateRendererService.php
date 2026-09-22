<?php

namespace App\Services\Pdf;

use App\Models\CompanyProfile;
use App\Models\InvoiceTemplate;
use App\Services\CurrencyFormatter;
use App\Services\Tax\TaxTreatment;
use App\Services\Tax\TaxPresetService;
use App\Services\Tax\DocumentCalculationService;
use App\Services\TenantContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
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
        if ($docType === 'invoice' && method_exists($document, 'taxLines')) {
            $document->loadMissing('taxLines');
        }

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

        // Business-document locale/currency come from the TENANT's own
        // configuration, never from whichever staff member happens to be
        // logged in and rendering it (Morocco Phase 1A - see
        // docs/morocco-phase-1a-implementation.md §4). This is a separate
        // concept from the staff UI's own locale (App::getLocale()).
        $context  = app(TenantContextService::class);
        $locale   = $context->locale();
        $currency = $context->currency();

        // The comment above states the intent but, before this fix, never
        // enforced it: every `__()` call inside the PDF Blade components
        // (labels like invoice.date/status/billing_address/quantity/...)
        // still read Laravel's ACTIVE app locale, not this tenant-derived
        // `$locale` - which is whatever the currently logged-in user's own
        // `users.locale` happens to be (schema default 'es', unrelated to
        // the tenant being rendered). A Moroccan tenant's invoice, opened by
        // a user whose own UI locale is still Spanish, silently mixed French
        // labels (computed inline via match($locale), e.g. "Facture",
        // "Sous-total HT") with Spanish ones from `__()` (e.g. "Factura
        // Núm.", "Cantidad", "Importe") on the SAME PDF. Restored in a
        // `finally` below so this never leaks into the rest of the request
        // (e.g. a subsequent JSON response in the same send()/print() call
        // must keep using the staff user's own locale).
        $previousAppLocale = App::getLocale();
        App::setLocale($locale);

        $dateFmt = match ($locale) {
            'en'    => 'm/d/Y',
            'fr'    => 'd/m/Y',
            default => 'd-m-Y',
        };

        $formatter    = app(CurrencyFormatter::class);
        $formatMoney  = fn($v) => $formatter->format((float) $v, $currency, $locale);
        $formatNumber = fn($v) => $formatter->formatNumber((float) $v, $locale);

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

        // New invoices render their persisted breakdown, including treatment.
        // Quotes use the same calculator for their mutable preview. Legacy
        // invoices keep their stored Spanish amounts; no financial data is saved.
        $taxGroups = [];
        if ($docType === 'invoice' && $document->taxLines->isNotEmpty()) {
            foreach ($document->taxLines as $line) {
                $taxGroups[] = [
                    'rate' => (float) $line->rate,
                    'treatment' => $line->treatment ?? TaxTreatment::TAXABLE,
                    'base' => (float) $line->taxable_base,
                    'amount' => (float) $line->tax_amount,
                ];
            }
        } elseif ($docType === 'quote') {
            $calculation = app(DocumentCalculationService::class)->calculate(
                $document->carts->map(fn ($cart) => [
                    'quantity' => $cart->qty,
                    'unit_price' => $cart->price,
                    'discount' => $cart->discount,
                    'tax_rate' => $cart->vta,
                    'treatment' => $cart->tax_treatment ?? TaxTreatment::TAXABLE,
                ])->all(),
                (float) $document->discount_rate,
            );
            foreach ($calculation->taxBreakdown as $row) {
                $taxGroups[] = [
                    'rate' => $row['rate'], 'treatment' => $row['treatment'],
                    'base' => $row['taxable_base'], 'amount' => $row['tax_amount'],
                ];
            }
        } else {
            foreach ([4, 10, 21] as $rate) {
                if ((float) $document->{'vta' . $rate} != 0) {
                    $taxGroups[] = [
                        'rate' => $rate, 'treatment' => TaxTreatment::TAXABLE,
                        'amount' => (float) $document->{'vta' . $rate},
                    ];
                }
            }
        }
        $taxGroups = array_values(array_filter($taxGroups, fn ($group) =>
            $group['rate'] > 0 || $group['treatment'] !== TaxTreatment::TAXABLE
        ));
        $totalTaxAmount = (float) ($document->vta ?? 0);

        $subTotal       = (float) ($document->sub_total ?? 0);
        $discountAmount = (float) ($document->discount_amount ?? 0);
        $grandTotal     = (float) ($document->total ?? 0);

        // Identity (name/tax IDs/address) is snapshot-first for an ISSUED
        // invoice - Morocco Phase 1B (docs/morocco-phase-1b-identity.md §7).
        // An issued invoice must never change appearance because Settings
        // or the customer record were edited afterward. Quotes, drafts, and
        // any invoice issued before this snapshot existed (company_snapshot
        // is null) fall back to the live data exactly as before - this is
        // purely additive, nothing about the non-snapshot path changed.
        $companySnapshot  = ($docType === 'invoice') ? ($document->company_snapshot ?? null) : null;
        $customerSnapshot = ($docType === 'invoice') ? ($document->customer_snapshot ?? null) : null;

        $companyIdentity = $companySnapshot ?? $company?->identitySnapshot() ?? [];
        $taxCountry = $companyIdentity['country_code'] ?? $context->country();
        $isMoroccanTax = strtoupper($taxCountry) === 'MA';
        $taxPresets = app(TaxPresetService::class);
        $taxName = $taxPresets->taxName($taxCountry);
        $taxLabel = fn ($rate, $treatment) => $taxPresets->label($taxCountry, (float) $rate, $treatment);

        $customerIdentity = $customerSnapshot ?? $document->customer?->identitySnapshot() ?? [];

        $companyName  = $companyIdentity['trade_name'] ?? $companyIdentity['legal_name'] ?? config('app.name');
        $addressParts = array_filter([
            $companyIdentity['address_line1'] ?? null,
            $companyIdentity['address_line2'] ?? null,
            trim(implode(' ', array_filter([$companyIdentity['postal_code'] ?? null, $companyIdentity['city'] ?? null]))),
            $companyIdentity['country'] ?? null,
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
                'companyIdentity', 'customerIdentity',
                'fontSize', 'logoWidthPx', 'isLogoAbove',
                'locale', 'formatMoney', 'formatNumber',
                'docDate', 'expiryDate', 'statusData',
                'taxGroups', 'totalTaxAmount', 'subTotal', 'discountAmount', 'grandTotal',
                'isMoroccanTax', 'taxName', 'taxLabel',
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
        } finally {
            App::setLocale($previousAppLocale);
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
