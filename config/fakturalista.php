<?php

return [
    /*
     * Internal address that receives the "new self-service registration"
     * notification (see App\Mail\AdminNewRegistrationMail, sent from
     * TenantProvisioningService::provision() after a self-service signup
     * has fully succeeded). Not customer-facing.
     */
    'admin_email' => env('FAKTURALISTA_ADMIN_EMAIL', 'contact@fakturalista.com'),

    /*
     * Customer-facing contact details shown on the public site (top bar,
     * /contact page). Single source of truth - never hardcode the phone
     * number or contact email independently in a Blade/Vue file.
     */
    'contact_email' => env('FAKTURALISTA_CONTACT_EMAIL', 'contact@fakturalista.com'),
    'contact_phone_display' => env('FAKTURALISTA_CONTACT_PHONE_DISPLAY', '+212 601 87 81 55'),
    'contact_phone_link' => env('FAKTURALISTA_CONTACT_PHONE_LINK', 'tel:+212601878155'),
];
