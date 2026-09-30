<?php

return [
    'title'            => 'عرض سعر رقم :number',
    'quote_date'       => 'تاريخ عرض السعر',
    'expiry_date'      => 'تاريخ انتهاء الصلاحية',
    'status'           => 'الحالة',
    'bill_to'          => 'موجّه إلى',
    'phone'            => 'الهاتف: :phone',
    'email'            => 'البريد الإلكتروني: :email',
    'item_description' => 'الوصف',
    'quantity'         => 'الكمية',
    'unit_price'       => 'سعر الوحدة',
    'tax'              => 'ضريبة القيمة المضافة',
    'amount'           => 'المبلغ',
    'subtotal'         => 'المجموع الفرعي (بدون ضريبة)',
    'discount'         => 'خصم',
    'total'            => 'الإجمالي (شامل الضريبة)',
    'tax_line'         => 'ضريبة القيمة المضافة (:rate%)',
    // Morocco Phase 1C.2 (docs/morocco-phase-1c2-tax-configuration.md §13)
    'exempt'           => 'معفى من الضريبة',
    'total_tax'        => 'إجمالي ضريبة القيمة المضافة',
    'notes'            => 'ملاحظات',
    'valid_through'    => 'يظل عرض السعر هذا ساري المفعول حتى :date.',
    // Client Portal Step 4 - quote accept/reject responses
    'portal_cannot_accept' => 'لم يعد بالإمكان قبول هذا العرض.',
    'portal_cannot_reject' => 'لم يعد بالإمكان رفض هذا العرض.',
    'portal_accepted'      => 'تم قبول العرض.',
    'portal_rejected'      => 'تم رفض العرض.',
    // Client Portal Step 5 - accepted quotes are frozen, rejected ones are not convertible
    'locked_accepted'         => 'لقد قبل العميل هذا العرض ولم يعد بالإمكان تعديله.',
    'cannot_convert_rejected' => 'لا يمكن تحويل عرض مرفوض إلى فاتورة.',
    // Cleanup after Client Portal Step 5 - accepted quotes are not deletable
    'cannot_delete_accepted'    => 'لقد قبل العميل هذا العرض ولا يمكن حذفه.',
    'bulk_deleted'              => 'تم حذف :count عرض سعر.',
    'bulk_deleted_with_skipped' => 'تم حذف :count عرض سعر. تم تجاهل :skipped (قبلها العميل).',
    'deleted'                   => 'تم حذف عرض السعر.',
    'already_converted'         => 'تم تحويل هذا العرض إلى فاتورة مسبقًا.',
];
