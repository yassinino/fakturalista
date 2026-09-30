<?php

return [
    'title'            => 'Quote No. :number',
    'quote_date'       => 'Quote Date',
    'expiry_date'      => 'Expiry Date',
    'status'           => 'Status',
    'bill_to'          => 'Bill To',
    'phone'            => 'Phone: :phone',
    'email'            => 'Email: :email',
    'item_description' => 'Description',
    'quantity'         => 'Qty',
    'unit_price'       => 'Unit Price',
    'tax'              => 'Tax',
    'amount'           => 'Amount',
    'subtotal'         => 'Subtotal',
    'discount'         => 'Discount',
    'total'            => 'Total',
    'tax_line'         => 'Tax (:rate%)',
    'exempt'           => 'Exempt',
    'total_tax'        => 'Total Tax',
    'notes'            => 'Notes',
    'valid_through'    => 'This quote is valid through :date.',
    // Client Portal Step 4 - quote accept/reject responses
    'portal_cannot_accept' => 'This quote can no longer be accepted.',
    'portal_cannot_reject' => 'This quote can no longer be rejected.',
    'portal_accepted'      => 'Quote accepted.',
    'portal_rejected'      => 'Quote rejected.',
    // Client Portal Step 5 - accepted quotes are frozen, rejected ones are not convertible
    'locked_accepted'         => 'This quote has been accepted by the customer and can no longer be edited.',
    'cannot_convert_rejected' => 'A rejected quote cannot be converted to an invoice.',
    // Cleanup after Client Portal Step 5 - accepted quotes are not deletable
    'cannot_delete_accepted'    => 'This quote has been accepted by the customer and cannot be deleted.',
    'bulk_deleted'              => ':count quote(s) deleted.',
    'bulk_deleted_with_skipped' => ':count quote(s) deleted. :skipped skipped (accepted by the customer).',
    'deleted'                   => 'Quote deleted.',
    'already_converted'         => 'This quote has already been converted to an invoice.',
];
