# E-invoicing: Morocco DGI readiness

Internal engineering note - not a compliance claim. See the "Do not claim compliance" section below.

## Current state (implemented, Steps 1-5)

- **Generic UBL 2.1 generation.** `Invoice` → `App\Services\EInvoicing\InvoiceMapper` → `EInvoiceData` (a format-agnostic DTO) → `App\Services\EInvoicing\Ubl\UblInvoiceBuilder`, which builds a standards-based UBL 2.1 `Invoice` XML document (`urn:oasis:names:specification:ubl:schema:xsd:Invoice-2` + `CommonAggregateComponents-2`/`CommonBasicComponents-2`) via `DOMDocument`. Nothing in this path is Morocco-specific.
- **Official OASIS XSD validation.** `App\Services\EInvoicing\Ubl\UblValidator::validateXsd()` validates the generated XML against the real OASIS UBL 2.1 schemas, bundled locally under `resources/einvoicing/ubl/2.1/xsd/` (fetched from `docs.oasis-open.org/ubl/os-UBL-2.1/`, release 2013-11-04). Entirely offline, XXE-hardened.
- **XML export.** `GET /invoices/{invoice}/export/ubl` (`App\Http\Controllers\InvoiceController::exportUbl()`) downloads the invoice's UBL XML, only once it has passed XSD validation. Same tenant-isolation/authorization as every other invoice action (one database per tenant).
- **Profile architecture (Step 5).** `App\Services\EInvoicing\Contracts\EInvoiceProfileInterface` defines what any e-invoicing output format ("profile") must expose: `build()`, `validate()` (pre-build structural check), `validateOutput()` (post-build conformance check). `App\Services\EInvoicing\Profiles\GenericUbl21Profile` is the one profile that exists today, registered as `ubl_2_1` in `config('einvoicing.profiles')` and resolved through `App\Services\EInvoicing\EInvoiceProfileRegistry` - the one seam `InvoiceController` goes through, instead of coupling to a concrete builder/validator. A future profile is added by registering a new class here; nothing about `InvoiceMapper`, `EInvoiceData`, the generic UBL profile, or the controller needs to change for that.

## Waiting for official DGI specifications

None of the following exist in Fakturalista yet, and none should be guessed or implemented until Morocco's DGI (Direction Générale des Impôts) publishes the actual specification:

- **Official Morocco UBL profile/customization** - which UBL 2.1 elements DGI requires, forbids, or extends beyond the generic schema (a "CustomizationID"/profile identifier, additional mandatory fields, restricted code lists, etc.).
- **Mandatory business rules** - Schematron or equivalent rules beyond XSD structural validity (e.g. PEPPOL-BIS-style rules), which fields DGI actually requires versus what's merely UBL-legal.
- **Tax/category mappings** - how Moroccan TVA rates/treatments map to whatever tax category scheme DGI's profile expects (this may or may not resemble the generic UNCL5305 S/Z/E/O mapping `UblInvoiceBuilder` currently uses).
- **Identifiers** - which Moroccan identifiers (ICE, IF, RC, ...) DGI requires in which UBL element/scheme, and under what `schemeID`/`schemeAgencyID` values, if any.
- **Electronic signature requirements** - whether DGI requires a signed document, which signature format (XAdES or otherwise), and the key/certificate model. Not implemented; no code here signs anything.
- **QR code requirements, if any** - content, placement, and generation trigger, if DGI's scheme includes one. Not implemented.
- **Transmission protocol/API** - how a document reaches DGI (a submission API, a designated intermediary/PDP-equivalent, a file-based exchange, etc.). Nothing in Fakturalista submits or transmits an invoice anywhere today; `GET /invoices/{invoice}/export/ubl` only lets a user download the file.
- **Authentication** - how Fakturalista (or the tenant) would authenticate to whatever transmission channel DGI defines. No credentials, no client, no config for this exists.
- **Clearance/reporting workflow** - whether DGI uses a clearance model (approve-before-issue), a post-issuance reporting model, or something else, and how that interacts with Fakturalista's existing issue/lock lifecycle (`Invoice::STATUS_*`, `hasLegalNumber()`, snapshot-at-issuance).
- **Response/status/error codes** - DGI's own status/error vocabulary for a submitted document, and how that should surface back to a Fakturalista user.

## Why no transport contract was added in Step 5

Step 5 considered adding a small `Contracts/EInvoiceTransportInterface.php` (conceptually: `submit(document): result`) purely as a placeholder seam. It was **not** added: every one of its plausible details - synchronous vs. asynchronous submission, single vs. batch, what a "submission result" contains, what errors look like, whether authentication is per-request or session-based - depends entirely on the transmission protocol/API/authentication items above, none of which are known yet. An interface guessed now would very likely need a breaking change once the real DGI transmission spec appears, so it would not actually save future work. The right time to add this contract is the same step that first implements a real transport against a real, published spec.

## Do not claim compliance

Fakturalista's e-invoicing output today is a **generic, standards-based UBL 2.1 export** - valid against the official OASIS schema, nothing more. Until the items above are implemented against Morocco's actual published DGI requirements and verified against them, Fakturalista must **not** describe this feature as "DGI compliant", "Morocco compliant", "DGI certified", or as a "facture électronique DGI" - in code, UI copy, documentation, or marketing. See the Step 4 report for the exact wording already in place in the UI (`invoices.exportUblHelp`: "Export in standard UBL 2.1 format." / FR / ES equivalents) - that phrasing, not a compliance claim, is what should keep being used until a DGI profile actually exists and has been verified.
