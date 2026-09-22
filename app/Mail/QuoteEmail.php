<?php

namespace App\Mail;

use App\Models\Quote;
use App\Services\CurrencyFormatter;
use App\Services\TenantContextService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Quote   $quote;
    public string  $customerName;
    public string  $companyName;
    public ?string $companyEmail;
    public ?string $companyPhone;
    public string  $pdfContent;
    public ?string $customMessage;

    public function __construct(
        Quote   $quote,
        string  $customerName,
        string  $companyName,
        ?string $companyEmail,
        ?string $companyPhone,
        string  $pdfContent,
        ?string $customMessage = null,
    ) {
        $this->quote         = $quote;
        $this->customerName  = $customerName;
        $this->companyName   = $companyName;
        $this->companyEmail  = $companyEmail;
        $this->companyPhone  = $companyPhone;
        $this->pdfContent    = $pdfContent;
        $this->customMessage = $customMessage;
    }

    public function build(): static
    {
        $filename = 'devis-' . $this->quote->reference . '.pdf';

        $context   = app(TenantContextService::class);
        $formatter = app(CurrencyFormatter::class);
        $currency  = $context->currency();

        return $this
            ->subject("Devis {$this->quote->reference} · {$this->companyName}")
            ->view('emails.invoice')   // reuse the same email template
            ->with([
                'invoice'     => $this->quote,   // blade uses $invoice variable
                'isQuote'     => true,
                'formattedTotal'    => $formatter->format((float) $this->quote->total, $currency, 'fr'),
                'formattedSubTotal' => $formatter->format((float) $this->quote->sub_total, $currency, 'fr'),
            ])
            ->attachData($this->pdfContent, $filename, ['mime' => 'application/pdf']);
    }
}
