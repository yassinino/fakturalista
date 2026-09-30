<?php

return [
    'title'            => 'Devis n° :number',
    'quote_date'       => 'Date du devis',
    'expiry_date'      => "Date d'expiration",
    'status'           => 'Statut',
    'bill_to'          => 'Destinataire',
    'phone'            => 'Tél : :phone',
    'email'            => 'E-mail : :email',
    'item_description' => 'Description',
    'quantity'         => 'Qté',
    'unit_price'       => 'Prix unitaire',
    'tax'              => 'TVA',
    'amount'           => 'Montant',
    'subtotal'         => 'Sous-total HT',
    'discount'         => 'Remise',
    'total'            => 'Total TTC',
    'tax_line'         => 'TVA (:rate%)',
    // Morocco Phase 1C.2 (docs/morocco-phase-1c2-tax-configuration.md §13)
    'exempt'           => 'Exonéré',
    'total_tax'        => 'Total TVA',
    'notes'            => 'Notes',
    'valid_through'    => 'Ce devis est valable jusqu\'au :date.',
    // Client Portal Step 4 - quote accept/reject responses
    'portal_cannot_accept' => 'Ce devis ne peut plus être accepté.',
    'portal_cannot_reject' => 'Ce devis ne peut plus être refusé.',
    'portal_accepted'      => 'Devis accepté.',
    'portal_rejected'      => 'Devis refusé.',
    // Client Portal Step 5 - accepted quotes are frozen, rejected ones are not convertible
    'locked_accepted'         => 'Ce devis a été accepté par le client et ne peut plus être modifié.',
    'cannot_convert_rejected' => 'Un devis refusé ne peut pas être converti en facture.',
    // Cleanup after Client Portal Step 5 - accepted quotes are not deletable
    'cannot_delete_accepted'    => 'Ce devis a été accepté par le client et ne peut pas être supprimé.',
    'bulk_deleted'              => ':count devis supprimé(s).',
    'bulk_deleted_with_skipped' => ':count devis supprimé(s). :skipped ignoré(s) (acceptés par le client).',
    'deleted'                   => 'Devis supprimé.',
    'already_converted'         => 'Ce devis a déjà été converti en facture.',
];
