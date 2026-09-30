<?php

return [
    'title'            => 'Presupuesto Núm. :number',
    'quote_date'       => 'Fecha del presupuesto',
    'expiry_date'      => 'Fecha de validez',
    'status'           => 'Estado',
    'bill_to'          => 'Dirigido a',
    'phone'            => 'Tel: :phone',
    'email'            => 'Email: :email',
    'item_description' => 'Descripción',
    'quantity'         => 'Cant.',
    'unit_price'       => 'Precio unitario',
    'tax'              => 'IVA',
    'amount'           => 'Importe',
    'subtotal'         => 'Subtotal',
    'discount'         => 'Descuento',
    'total'            => 'Total',
    'tax_line'         => 'IVA (:rate%)',
    'exempt'           => 'Exento',
    'total_tax'        => 'Total IVA',
    'notes'            => 'Notas',
    'valid_through'    => 'Este presupuesto es válido hasta el :date.',
    // Client Portal Step 4 - quote accept/reject responses
    'portal_cannot_accept' => 'Este presupuesto ya no se puede aceptar.',
    'portal_cannot_reject' => 'Este presupuesto ya no se puede rechazar.',
    'portal_accepted'      => 'Presupuesto aceptado.',
    'portal_rejected'      => 'Presupuesto rechazado.',
    // Client Portal Step 5 - accepted quotes are frozen, rejected ones are not convertible
    'locked_accepted'         => 'Este presupuesto ha sido aceptado por el cliente y ya no se puede modificar.',
    'cannot_convert_rejected' => 'Un presupuesto rechazado no se puede convertir en factura.',
    // Cleanup after Client Portal Step 5 - accepted quotes are not deletable
    'cannot_delete_accepted'    => 'Este presupuesto ha sido aceptado por el cliente y no se puede eliminar.',
    'bulk_deleted'              => ':count presupuesto(s) eliminado(s).',
    'bulk_deleted_with_skipped' => ':count presupuesto(s) eliminado(s). :skipped omitido(s) (aceptados por el cliente).',
    'deleted'                   => 'Presupuesto eliminado.',
    'already_converted'         => 'Este presupuesto ya ha sido convertido en factura.',
];
