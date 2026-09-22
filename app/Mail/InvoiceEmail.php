<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\CurrencyFormatter;
use App\Services\TenantContextService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;

    public string $customerName;
    public string $companyName;
    public ?string $companyEmail;
    public ?string $companyPhone;
    public string $pdfContent;
    public ?string $customMessage;
    public ?string $paymentUrl;

    public function __construct(
        Invoice  $invoice,
        string   $customerName,
        string   $companyName,
        ?string  $companyEmail,
        ?string  $companyPhone,
        string   $pdfContent,
        ?string  $customMessage = null,
        ?string  $paymentUrl    = null,
    ) {
        $this->invoice       = $invoice;
        $this->customerName  = $customerName;
        $this->companyName   = $companyName;
        $this->companyEmail  = $companyEmail;
        $this->companyPhone  = $companyPhone;
        $this->pdfContent    = $pdfContent;
        $this->customMessage = $customMessage;
        $this->paymentUrl    = $paymentUrl;
    }

    public function build(): static
    {
        $filename = 'facture-' . $this->invoice->reference . '.pdf';

        // Currency (only) is tenant-driven here - Morocco Phase 1A. The
        // email copy itself stays French for every tenant, matching its
        // pre-existing behavior (it never varied by locale before this
        // phase); see docs/morocco-phase-1a-implementation.md §4 for why
        // that's a separate, deliberately-deferred concern from currency.
        $context   = app(TenantContextService::class);
        $formatter = app(CurrencyFormatter::class);
        $currency  = $context->currency();

        return $this
            ->subject("Facture {$this->invoice->reference} · {$this->companyName}")
            ->view('emails.invoice')
            ->with([
                'formattedTotal'    => $formatter->format((float) $this->invoice->total, $currency, 'fr'),
                'formattedSubTotal' => $formatter->format((float) $this->invoice->sub_total, $currency, 'fr'),
            ])
            ->attachData($this->pdfContent, $filename, ['mime' => 'application/pdf']);
    }
}
