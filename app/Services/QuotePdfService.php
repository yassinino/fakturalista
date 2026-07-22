<?php

namespace App\Services;

use App\Models\Quote;
use App\Services\Pdf\TemplateRendererService;

class QuotePdfService
{
    public function __construct(private TemplateRendererService $renderer) {}

    /**
     * Generate and return the raw PDF binary for a quote using the active invoice template.
     *
     * @throws \Throwable if rendering fails
     */
    public function generate(Quote $quote): string
    {
        return $this->renderer->render($quote, 'quote');
    }
}
