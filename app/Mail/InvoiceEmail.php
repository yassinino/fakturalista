<?php

namespace App\Mail;

use App\Models\Invoice;
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

        return $this
            ->subject("Facture {$this->invoice->reference} · {$this->companyName}")
            ->view('emails.invoice')
            ->attachData($this->pdfContent, $filename, ['mime' => 'application/pdf']);
    }
}
