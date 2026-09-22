<?php

return [
    /*
     * Internal address that receives the "new self-service registration"
     * notification (see App\Mail\AdminNewRegistrationMail, sent from
     * TenantProvisioningService::provision() after a self-service signup
     * has fully succeeded). Not customer-facing.
     */
    'admin_email' => env('FAKTURALISTA_ADMIN_EMAIL', 'contact@fakturalista.com'),
];
