<?php

use App\Services\EInvoicing\Profiles\GenericUbl21Profile;

/*
|--------------------------------------------------------------------------
| E-Invoicing (Step 5 - profile registry)
|--------------------------------------------------------------------------
|
| Nothing here is Morocco-specific. This config registers which "profile"
| classes (App\Services\EInvoicing\Contracts\EInvoiceProfileInterface)
| exist and which one is used by default when none is specified -
| resolved via App\Services\EInvoicing\EInvoiceProfileRegistry, never
| instantiated directly by a caller.
|
| A Morocco DGI profile is intentionally NOT listed here yet - it will be
| added once that specification is actually available, not invented now.
| See docs/einvoicing-morocco-readiness.md.
|
*/

return [

    'default_profile' => env('EINVOICING_DEFAULT_PROFILE', 'ubl_2_1'),

    'profiles' => [
        'ubl_2_1' => GenericUbl21Profile::class,
    ],

    /*
    |----------------------------------------------------------------------
    | Default unit code
    |----------------------------------------------------------------------
    |
    | UN/CEFACT Recommendation 20 code used by
    | App\Services\EInvoicing\Ubl\UblUnitCodeMapper for
    | cbc:InvoicedQuantity/@unitCode whenever a Cart/Item unit label isn't
    | one of the small set of labels it recognises. "C62" (piece/unit) is
    | the safest generic default - it never claims a specific unit
    | (weight, time, ...) that the label didn't actually say.
    |
    */

    'default_unit_code' => env('EINVOICING_DEFAULT_UNIT_CODE', 'C62'),

    /*
    |----------------------------------------------------------------------
    | UBL 2.1 XSD schema path
    |----------------------------------------------------------------------
    |
    | Used by App\Services\EInvoicing\Ubl\UblValidator::validateXsd() to
    | validate XML produced by UblInvoiceBuilder against the official
    | OASIS UBL 2.1 Invoice schema. Bundled locally under
    | resources/einvoicing/ubl/2.1/xsd/ (maindoc/UBL-Invoice-2.1.xsd plus
    | every common/ schema it imports) - validation never depends on
    | downloading schemas at runtime.
    |
    */

    'xsd_schema_path' => env(
        'EINVOICING_UBL_XSD_PATH',
        resource_path('einvoicing/ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd')
    ),

];
