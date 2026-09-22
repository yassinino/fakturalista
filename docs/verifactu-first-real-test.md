# VERI*FACTU — first real AEAT TEST submission (Phase 2D.1)

**This document does not authorize a real submission by itself.** It is
the step-by-step procedure for when a human (you) has a real certificate
in hand and has decided to run it. `verifactu:aeat-test` still requires
an explicit interactive confirmation (or `--force`) before it sends
anything, and it refuses anything but AEAT's TEST endpoint — see
`docs/verifactu-aeat-connectivity.md` for the full design this relies
on.

**Do not fabricate test taxpayer data.** Everything below uses a
*fictitious invoice* (fictitious customer, fictitious line items) issued
by your **own real company/taxpayer identity**, authenticated with your
**own real certificate**. Those are two different things — see §7 below
for why this distinction matters and cannot be worked around.

---

## 0. What "fictitious" does and doesn't mean here

- **Fictitious: the invoice content.** Customer, line items, amounts —
  invent these freely. Never use a real customer's data for this test.
- **Not fictitious: the issuer identity.** `CompanyProfile.tax_id` (the
  `NIF` sent in `ObligadoEmision`) and the certificate used to
  authenticate the connection must both be **real**, and must **match
  each other** — see §7.
- No AEAT-TEST-specific fictitious-identity mechanism (a documented
  "use this NIF for testing" convention) was found in any primary source
  reviewed across Phases 2A–2D.1 (RD 1007/2023, Orden HAC/1177/2024, the
  WSDL/XSDs, the SWeb description, the developer FAQ, or the July 2026
  FAQ pages). Absence of a finding is not proof none exists — if you know
  of one, it should be verified against an official AEAT source before
  relying on it. In the absence of one, the safe assumption used here is
  that AEAT's TEST environment authenticates and evaluates requests the
  same way production does, just against a non-legally-binding backend.

---

## 1. Obtain a certificate

You need your own **certificado electrónico cualificado reconocido**
(the same kind of certificate you'd use to file taxes with AEAT
normally) — FNMT persona física/jurídica, DNIe, or another
AEAT-recognized qualified certificate. There is no separate "AEAT TEST
certificate" — the TEST environment is reached with the same certificate
type as production, just pointed at the TEST endpoint (enforced in code,
never user-selectable — see `docs/verifactu-aeat-connectivity.md` §1).

**Format Fakturalista requires: PKCS#12 (`.p12` / `.pfx`)**, containing
both the certificate and its private key, protected by a password.
If your certificate is only available in another format (e.g. a
browser-installed certificate, or separate `.cer`/`.key` files), export
or convert it to a single password-protected `.p12`/`.pfx` file first —
Fakturalista's upload only accepts that combined format
(`VerifactuCertificateService::upload()` reads it via
`openssl_pkcs12_read()`).

You will need:
- The `.p12`/`.pfx` file itself.
- Its password (this is what "protects" the file — Fakturalista calls it
  the certificate's *passphrase*).

## 2. What Fakturalista extracts, stores, and never stores

(Full security model: `docs/verifactu-aeat-connectivity.md` §3.)

**Extracted from the file at upload time** (via `openssl_x509_parse()`),
stored as plain metadata in `verifactu_certificates`:
subject (DN), issuer (DN), serial number, valid-from date, valid-to
date. This is exactly what the Settings → VERI\*FACTU page displays back
to you — nothing more.

**Stored, encrypted at rest:**
- The **entire original `.p12`/`.pfx` file**, encrypted with Laravel's
  encrypter (`Crypt::encryptString()`, AES-256-CBC keyed by the
  application's `APP_KEY`) before being written to local disk storage.
- The **passphrase**, separately, via the model's `encrypted` cast (the
  same underlying Laravel encrypter/`APP_KEY` — this is encryption
  separation of the two values, not separate encryption keys or
  HSM/KMS-backed storage; see §3 of the connectivity doc for why that's
  an explicitly acknowledged limitation, not an oversight).

**Never stored, never logged, at any point:**
- The decrypted private key or decrypted certificate bytes, outside of
  one in-memory value object (`AeatClientCertificate`) that exists only
  for the duration of a single outgoing SOAP call, is never serialized,
  and is never passed into a queue payload.
- The passphrase, in any log line or any API response (`VerifactuCertificate::$hidden`
  hides both `passphrase` and `encrypted_file_path` from all
  serialization).
- Full response bodies or certificate contents in the `verifactu` log
  channel — only structured, secret-free fields (NIF, attempt number,
  outcome, duration).

**One acknowledged, documented exposure window:** because PHP's HTTP
client needs the certificate as a filesystem path (not an in-memory
value) to perform mutual-TLS, the private key touches disk once per
outgoing request, as a `0600`-permissioned temp file
(`AeatClientCertificate::writeTemporaryPemFile()`), deleted immediately
after the request completes (`finally { @unlink($pemPath); }` in
`AeatVerifactuClient::submit()`). This is a real, brief, root-only-owned
window on the application server's own filesystem, not a network or
log exposure.

## 3. Configure the test tenant

Pick (or create) a Fakturalista tenant to use for this. In `tinker`:

```php
$tenant = \App\Models\Tenant::find('<tenant-id>'); // or Tenant::create([...]) for a fresh one

$tenant->run(function () {
    $company = \App\Models\CompanyProfile::first(); // or ::create([...]) if none exists yet

    $company->update([
        'tax_id' => '<YOUR REAL NIF — must match the certificate, see §7>',
        'legal_name' => '<your real registered name>',
        // Required before any VerifactuRecord can be generated — no UI
        // field exists for this yet, tinker/DB is the only way today:
        'verifactu_installation_number' => '<a value you choose, e.g. an ISO timestamp — see connectivity doc §0>',
    ]);
});
```

`verifactu_installation_number` must never be reused for a different
"virtual SIF" later (`docs/verifactu-aeat-connectivity.md` §0) — pick it
once and leave it.

## 4. Upload the certificate

Via the UI: log into that tenant, go to **Settings → VERI\*FACTU**
(sidebar entry with the shield icon), and use the upload form (password
field + file input + "Upload"). On success the page shows the extracted
subject/issuer/validity/status — never the file or password back.

Equivalent in `tinker`, if you'd rather not go through the browser:

```php
$tenant->run(function () {
    app(\App\Services\Verifactu\VerifactuCertificateService::class)->upload(
        '<YOUR REAL NIF>',   // must match company_profiles.tax_id exactly
        file_get_contents('/path/to/certificate.p12'),
        '<the .p12 password>'
    );
});
```

## 5. Create a fictitious test invoice

Normal Fakturalista UI, nothing special: create a **fictitious**
customer (invented name/NIF) under **Admin → Customers**, then a draft
invoice for that customer under **Admin → Invoices → New Invoice**, with
invented line items. Do not use any real customer's data. The XSD only
constrains a NIF to exactly 9 characters (`NIFType`, no checksum/pattern
enforced at the schema level) — use any 9-character placeholder, it need
not pass a real NIF checksum.

## 6. Issue it

In the invoice's detail view (or the invoice list), use the normal
**"Issue"** action (`POST /invoices/{id}/issue`). This assigns the
definitive legal number and moves it to `issued` — the exact same code
path (`InvoiceController::issueInvoice()`) any real invoice goes
through. Note its invoice ID for the next step.

## 7. Why the issuer identity cannot be fictitious

The envelope Fakturalista sends has two separate identity signals, and
AEAT's TEST environment is expected to see both:

1. **Transport-level identity:** the mutual-TLS client certificate
   itself — this is *your* real certificate, tied to *your* real NIF, at
   the TLS handshake, before AEAT ever parses the SOAP body.
2. **Document-level identity:** the `Cabecera/ObligadoEmision/NIF`
   field inside the envelope, populated from `company_profiles.tax_id`
   (`AeatVerifactuClient::buildEnvelope()`).

No primary source reviewed states explicitly what AEAT does if these two
disagree, but there is no plausible reading of "your qualified
certificate authenticates you as a taxpayer" that allows one real
certificate to authenticate submissions declaring a different, invented
NIF as the issuer. **Fakturalista implementation decision: `tax_id` in
step 3 must be set to the real NIF the certificate in step 1 actually
belongs to** — not an invented one — precisely so this first test
doesn't fail for an identity mismatch instead of testing what it's meant
to test.

## 8. Generate and verify the VERI\*FACTU record and XML

Invoice issuance does **not** yet generate a `VerifactuRecord`
automatically — that wiring is deliberately not built yet (Phase 2D
scope explicitly excluded it; see `docs/verifactu-aeat-connectivity.md`
§9's note that `VerifactuSubmissionService` "is NOT called anywhere from
live invoice issuance yet"). Generate it manually in `tinker`, inside the
tenant:

```php
$tenant->run(function () {
    $invoice = \App\Models\Invoice::find(<invoice-id>);
    $company = \App\Models\CompanyProfile::first();

    $record = app(\App\Services\Verifactu\VerifactuChainService::class)
        ->recordAlta($invoice, $company);

    echo $record->id, "\n";

    // Eyeball the exact XML that will be sent:
    $xml = app(\App\Services\Verifactu\VerifactuXmlBuilder::class)->build($record);
    echo $xml, "\n";

    // Confirm it's schema-valid (verifactu:aeat-test will re-check this
    // itself, but there's no reason not to look now):
    app(\App\Services\Verifactu\VerifactuXmlValidator::class)->validate($xml);
    echo "XSD valid.\n";
});
```

If `recordAlta()` throws, the invoice/company data is incomplete for
VERI\*FACTU purposes (missing `descripcion_operacion`, missing
installation number, etc.) — fix the underlying data, not the check.

## 9. Run the supervised AEAT TEST command

```
php artisan verifactu:aeat-test <tenant-id> --record=<record-id>
```

(Omit `--force` for this first real run — you want to see and confirm
the pre-send banner by hand.) Before sending anything, it now prints:

```
ENTORNO: AEAT PRUEBAS (nunca producción)
Tenant: <tenant-id>
Contribuyente (NIF): <legal_name> (<tax_id>)
Factura/registro: <serie_numero> (<tipo_registro>)
Endpoint: https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP
Sujeto del certificado: <certificate subject DN>
Caducidad del certificado: <YYYY-MM-DD>
```

Read every line before typing `yes`. It refuses outright, before showing
this banner, if: the configured environment isn't `test`; the tenant or
record doesn't exist; the company has no NIF; there is no certificate on
file for that NIF; the certificate is expired, missing its file, or
fails to decrypt; or the record's XML fails to build or fails XSD
validation. Any prior manually-created record whose data was actually
unsupported would already have failed at step 8's `recordAlta()` call,
not here.

## 10. Inspect the AEAT response

The command prints its own summary right after sending:

```
Resultado:
  Estado: <accepted | accepted_with_errors | rejected | transport_error>
  EstadoEnvio (AEAT): <Correcto | ParcialmenteCorrecto | Incorrecto>
  EstadoRegistro (AEAT): <Correcto | Incorrecto | ...>
  CSV: <AEAT's CSV, if any>
```

Plus an `Error AEAT: [code] description` line if AEAT rejected the
record, or a transport-error line if the request never reached AEAT
successfully.

## 11. Verify the database rows

Still inside the tenant (tinker, or your DB client pointed at that
tenant's database):

```php
$tenant->run(function () use ($recordId, $submissionId) {
    dump(\App\Models\Verifactu\VerifactuSubmission::find($submissionId)->toArray());
    dump(\App\Models\Verifactu\VerifactuSubmissionAttempt::where('verifactu_submission_id', $submissionId)->get()->toArray());
});
```

Confirm: exactly one `VerifactuSubmissionAttempt` row per attempt made,
the submission's `status`/`aeat_estado_envio`/`aeat_estado_registro`/`csv`
match what the command printed, and `response_raw` holds the full AEAT
SOAP response body for your own audit trail.

## 12. UI status — what exists today, what doesn't

**Exists:** Settings → VERI\*FACTU shows the certificate's own
status (`ok` / `expiring_soon` / `expired`) computed from its
`valid_to` date.

**Does not exist yet:** there is currently **no invoice-level or
submission-level status UI** anywhere in the admin app — no "sent to
AEAT" badge on the invoice list, no submission history page. This is
expected: wiring submissions into the live invoice UI is out of scope
for Phase 2D/2D.1 by design (submissions are only ever created by the
manual command today). Verify submission status via steps 10–11
(the command's own output and the database), not the UI, until that
wiring exists.

---

## If something goes wrong

- **Refused before sending, with a clear message** → read the message;
  it names exactly which precondition failed (certificate, XML/XSD,
  environment, missing NIF). Fix that and re-run from step 9.
- **`transport_error` after sending** → check `last_error` on the
  submission row (step 11) and the `verifactu` log channel. Re-running
  the same `--record=` a second time will correctly send `Incidencia=S`
  (`docs/verifactu-aeat-connectivity.md` §12) since the submission's own
  `retry_count` is now `> 0`.
- **`rejected` / `accepted_with_errors`** → this means AEAT's TEST
  service actually evaluated the record and found a content problem;
  the `aeat_error_code`/`aeat_error_description` fields are AEAT's own
  words, not Fakturalista's — do not guess at what an unfamiliar code
  means, look it up against AEAT's own documentation before changing
  any code in response to it.
