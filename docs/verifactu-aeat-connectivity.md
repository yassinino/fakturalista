# VERI*FACTU — AEAT Connectivity (Phase 2D, TEST only)

**This document does not claim VERI*FACTU compliance.** It records what
was built to talk to AEAT's external TEST web service only, and exactly
which official source backs each design decision.

Every statement below is labeled **OFFICIAL REQUIREMENT** (quoted/closely
paraphrased primary source, with citation) or **FAKTURALISTA
IMPLEMENTATION DECISION** (a choice this codebase made, given what's
official). Sources re-fetched or newly read in this phase, in addition to
those already frozen in `docs/verifactu-xml-spec-freeze.md`:

| # | Document | Retrieved | Official URL |
|---|---|---|---|
| W1 | `SistemaFacturacion.wsdl` | 2026-09-20 (Phase 2C), re-read in full Phase 2D | `https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl` |
| W2 | Same WSDL, fetched from the **pre-production** host to cross-check | 2026-09-22 | `https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl` (byte-identical port/address content to W1) |
| X3 | `RespuestaSuministro.xsd` | 2026-09-20 (Phase 2C), re-read in full Phase 2D | same directory as W1 |
| D3 | FAQs-Desarrolladores.pdf, §4 "Cómo identificar un SIF..." and §5 "Arquitecturas de los SIF" | Already downloaded Phase 2C.1, re-read in full Phase 2D | `https://sede.agenciatributaria.gob.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/FAQs-Desarrolladores.pdf` (updated 04/12/2025) |
| D1 | SWeb description, §4.3 "Medio de envío", §6.4.4.1 "Mecanismo de control de flujo" | Already downloaded Phase 2C, re-read in full Phase 2D | `.../Veri-Factu_Descripcion_SWeb.pdf` (v1.0.3, 28/07/2025) |

---

## 0. NumeroInstalacion — re-verified for multi-tenant SaaS

**OFFICIAL REQUIREMENT.** D3 §4 states a SIF is universally identified by
the concatenation of three fields: `NIF (Id.OEF) + Id.SIF + NúmeroInstalación`.
Quoted directly on `NúmeroInstalación`: *"El Nº de instalación es una
forma de completar una identificación unívoca de cada SIF -por si tuviera
varios- de un mismo OEF, y así distinguirse de cualquier otro SIF de ese
OEF **en cualquier momento del tiempo (pasado, presente o futuro)**...
no puede repetirse nunca: por ejemplo, incluso si se formatea el ordenador
donde estaba instalado un SIF y se reinstala el mismo software de nuevo
en ese mismo ordenador, **el nuevo SIF así constituido debe llevar otro
nº de instalación diferente al anterior**."*

**OFFICIAL REQUIREMENT — the exact SaaS/multi-billing case, directly on
point:** *"si se utiliza un SIF que permite llevar distintas
facturaciones... **cada una de esas facturaciones distintas... debe
tener un nº de instalación propio y distinto al resto** (pasado,
presente o futuro) porque **se consideran SIF independientes, como si
fueran "SIF virtuales"**, dentro de un producto SIF más completo que los
gestiona y administra."* This is the load-bearing sentence for
Fakturalista: each tenant's own separate invoicing ("facturación") is
its own "virtual SIF" and needs its own distinct, never-reused
installation number — **confirming, not assuming**, Phase 2C's original
"one stable number per tenant" design.

**OFFICIAL — recommended (not mandated) values, D3 §4:** either (a) a
timestamp (at least to the second) of when that installation was
established, or (b) an organization-internal sequential counter that
never repeats across that OEF's installations.

**OFFICIAL — who assigns it:** not stated as explicitly as
`IdSistemaInformatico` ("dado por la persona o entidad productora"), but
read together with that adjacent, explicit producer-assignment language,
and given AEAT has no way to pre-know an installation before it exists,
this is a producer/software-assigned value, never an AEAT-issued one.

**Remaining ambiguity — explicitly flagged, not resolved by guessing:**
D3's own illustrative example (reinstalling software on a reformatted
computer needs a new number) is framed around single-machine desktop
software. It does not explicitly address a pure cloud/SaaS backend
*infrastructure* change (server migration, redeploy, horizontal
scaling) for a tenant whose account/facturación itself never stopped
existing. **FAKTURALISTA IMPLEMENTATION DECISION (interpretation, not a
verbatim rule):** since the "virtual SIF" the regulation cares about is
tied to the *tenant's own continuous facturación*, not to which physical
server happens to run Fakturalista's code that week, ordinary backend
infrastructure changes must **not** trigger a new installation number as
long as the same tenant record persists. A new number is only warranted
if a tenant's account is fully deleted and a genuinely new, unrelated
one is created later. **This interpretation should be reconfirmed with
AEAT or a tax advisor before relying on it in production** — it is the
most defensible reading of the text, not a literal statement.

**Pause/resume:** not addressed distinctly from full reinstallation in
any source found. Given "never repeats, ever" is the one unambiguous
rule, the safe implementation is: never regenerate an existing tenant's
installation number for any reason short of that tenant's virtual SIF
genuinely ceasing to exist.

**Rotation:** **OFFICIAL REQUIREMENT — never.** The number must
permanently distinguish one SIF from every other past/present/future SIF
of the same OEF; rotating or reusing it would directly violate that.

**Conclusion:** Phase 2C/2C.1's `company_profiles.verifactu_installation_number`
(one stable, nullable, never-auto-generated value per tenant) is
confirmed correct. No change made in Phase 2D beyond this
re-verification.

---

## 1. Test environment only

**OFFICIAL REQUIREMENT — exact TEST endpoint, read directly out of the
live WSDL's `<wsdl:service name="sfVerifactu">` block (W1, cross-checked
against W2):**

| WSDL port name | WSDL's own comment | Address |
|---|---|---|
| `SistemaVerifactu` | "Entorno de PRODUCCION" | `https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` |
| `SistemaVerifactuSello` | "PRODUCCION, acceso con certificado de sello" | `https://www10.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` |
| **`SistemaVerifactuPruebas`** | **"Entorno de PRUEBAS"** | **`https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP`** |
| `SistemaVerifactuSelloPruebas` | "PRUEBAS, acceso con certificado de sello" | `https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP` |

`SistemaVerifactuPruebas` (bold) is the endpoint Phase 2D uses -
confirmed by the WSDL's own inline comment naming it a test port, not
inferred from the hostname pattern alone. Binding: SOAP 1.1,
`style="document"`, `use="literal"`, `soapAction=""` (empty). The
"Sello" ports (organizational seal certificate, a different certificate
type from a personal/representative one) are documented but **not**
wired up in Phase 2D - Fakturalista's V1 "each taxpayer uses their own
certificate" model most naturally maps to the non-Sello ports, and
detecting which certificate type a tenant actually holds is out of
scope here (see §2).

**FAKTURALISTA IMPLEMENTATION DECISION.** `config('verifactu.aeat.environment')`,
env var `VERIFACTU_AEAT_ENV`, **only accepted value: `test`**. Any other
value (including `production` or a typo) makes
`AeatEndpointResolver::endpoint()` throw rather than resolve anything -
there is no code path in Phase 2D that can produce the production URL,
even if someone hand-configures it, because the production address is
**not present anywhere in the codebase**. Documented and tested
(`production_environment_value_is_rejected`, `unknown_environment_value_is_rejected`).

---

## 2. Authentication architecture

**OFFICIAL REQUIREMENT, D1 §4.3 "Medio de envío" (quoted in full,
including nothing being said beyond this):** *"Protocolo: HTTPS...
Certificado: Las aplicaciones que envían información a los servicios web
deberán autenticarse con **certificado electrónico cualificado
reconocido**."* D1 §4.1 repeats: submission may be done by the taxpayer,
an authorized representative, or a "colaborador social", each of whom
"deberá disponer de un certificado electrónico cualificado reconocido."

**What this does and does not say, explicitly:** it states authentication
happens via a qualified certificate over HTTPS. It does **not** use the
words "TLS mutua" or "client certificate" anywhere in any document
retrieved. **FAKTURALISTA IMPLEMENTATION DECISION (standard
interpretation, not a verbatim quote):** this is implemented as
mutual-TLS client-certificate authentication at the HTTPS transport
layer - the universal mechanism this exact phrasing means for AEAT/
Spanish-public-administration web services, and consistent with there
being no separate SOAP-level authentication header defined anywhere in
the WSDL or XSDs. No WS-Security header, no per-request XML signature
for authentication purposes is defined for the VERI*FACTU submission
operation.

**Distinct from this:** the optional `ds:Signature` element inside
`RegistroAlta`/`RegistroAnulacion` (frozen in
`docs/verifactu-xml-spec-freeze.md`) is a **content-level** signature
requirement that only applies to NON-VERI*FACTU mode systems (D3 §5.II
explicitly: "para la modalidad NO VERI*FACTU... la firma del registro
por parte del sistema emisor"), confirming Phase 1's earlier finding.
Fakturalista, targeting VERI*FACTU-only, never needs to populate it.

**Taxpayer vs. third-party certificate:** the WSDL's `Sello`/non-`Sello`
port pairs are the only endpoint-level distinction found; the
description text (D1 §4.1) separately allows the taxpayer, an
"apoderado", or a "colaborador social" to submit, each with their own
qualified certificate, without further technical distinction. Nothing
found requires Fakturalista itself to hold any certificate for this -
only whoever is submitting on the taxpayer's behalf.

**FAKTURALISTA IMPLEMENTATION DECISION — the abstraction.**

```
App\Services\Verifactu\Auth\AeatAuthenticationProvider   (interface)
    resolveCertificate(string $nif): AeatClientCertificate

App\Services\Verifactu\Auth\CustomerCertificateProvider  (first, only implementation)
    reads app\Models\Verifactu\VerifactuCertificate for that NIF,
    decrypts on demand, returns a transient, in-memory-only value object

App\Services\Verifactu\Auth\AeatClientCertificate         (value object)
    decrypted PEM certificate + key material + passphrase - never
    persisted, never logged, never placed on a queue payload (see §9)
```

This lets a second strategy (e.g. a Fakturalista-held "colaborador
social" certificate submitting on behalf of tenants who don't want to
manage their own) be added later as a second class implementing the same
interface, without touching `AeatVerifactuClient`.

---

## 3. Certificate security model

**Explicitly not claimed:** this is not KMS/HSM-grade private-key
custody. It is documented as such, not disguised.

**FAKTURALISTA IMPLEMENTATION DECISION - Phase 2D TEST-appropriate
design:**

- The uploaded PKCS#12 (`.p12`/`.pfx`) file's raw bytes are encrypted
  with `Crypt::encryptString()` (Laravel's AES-256-CBC, keyed by
  `APP_KEY`) before being written anywhere, and stored on the `local`
  disk (never `public`, never web-served) at a per-tenant, per-NIF path
  (`verifactu-certificates/{nif}.p12.enc`).
- The PKCS#12 **passphrase** is a **separate** secret: an `encrypted`
  Eloquent cast on its own DB column, never concatenated with the
  certificate bytes - key/password separation, so leaking one file
  doesn't leak both.
- Metadata (subject, issuer, serial, validity dates) is extracted once
  at upload time via `openssl_pkcs12_read()`/`openssl_x509_parse()` and
  stored in plaintext columns - safe to store and display (§4);
  everything else stays encrypted.
- Decryption only ever happens transiently, inside
  `CustomerCertificateProvider`, for the duration of a single SOAP call,
  inside the correct tenant's DB context. PHP's `SoapClient` requires
  `local_cert` to be a **filesystem path**, not a PEM string - the
  decrypted PEM is written to a `tempnam()`'d file with `chmod 0600`,
  used for exactly one request, and unlinked in a `finally` block even
  on exception. This transient-file window is a real, acknowledged
  exposure surface (documented, not hidden) inherent to PHP's SOAP
  extension.
- **What this explicitly does NOT solve:** `APP_KEY` is one shared
  secret for the whole application, not hardware-backed, not
  tenant-scoped, not independently rotatable/auditable per secret.
  Anyone with DB + filesystem + `APP_KEY` access can decrypt every
  tenant's certificate. **Real production custody of taxpayer private
  keys should use a dedicated secrets manager or HSM** (AWS
  KMS/Secrets Manager, GCP KMS, HashiCorp Vault, or a real HSM) where
  the unwrapping key never lives in the application's own config and
  can be scoped/rotated/audited per tenant independently of `APP_KEY`.
  This is flagged, not built, in Phase 2D.
- **Replacement/deletion:** `VerifactuCertificateService::replace()`/
  `delete()` remove the old encrypted file from disk before/when writing
  the new one - no orphaned encrypted material left behind.
- **Expiration:** `VerifactuCertificate::isExpired()`/`isExpiringSoon()`
  helpers; surfaced in the settings UI (§4), not enforced by a
  scheduled job in this phase.
- **Access auditing:** every certificate upload/replace/delete/use is
  logged via a dedicated `verifactu` log channel with tenant/NIF context
  only - never certificate bytes, key material, or passphrase (see §15).
- **Tenant isolation:** `verifactu_certificates` is a tenant-DB table,
  structurally isolated the same way `verifactu_chain_states` already
  is - no shared table, no `tenant_id` column to forget a `WHERE` on.
- **Backups:** flagged, not solved here - whatever backup strategy
  exists for tenant databases must also cover the certificate file
  storage path, and a DB-only restore without the matching encrypted
  files (or vice versa, or with a different `APP_KEY`) would silently
  break certificate access for that tenant. Not a new gap Phase 2D
  introduces, but one it now depends on.

---

## 4. Certificate settings UI

`Settings → VERI*FACTU → Certificado` (Vue): shows configured (yes/no),
subject, issuer, valid-from/-to, last-validated-at, environment (always
"TEST" in this phase), status (`ok`/`expiring_soon`/`expired`/`invalid`).
Never renders the private key, raw certificate bytes, or passphrase -
those never leave the backend's encrypted storage or transient decrypt
buffer. Upload validates the file is a readable PKCS#12 with the given
passphrase before accepting it, with ES/EN/FR error messages for a bad
password, a non-PKCS#12 file, or a missing passphrase.

---

## 5. Submission data model

Two new tenant-DB tables, deliberately separate from the immutable
`verifactu_records` (never mutated by network activity):

- **`verifactu_submissions`** — one row per "this record needs to reach
  AEAT". Columns: `verifactu_record_id` (FK), `nif`, `environment`
  (always `test`), `payload_xml` (the exact RegistroAlta/RegistroAnulacion
  XML - see §6), `payload_checksum` (SHA-256), `status`
  (`pending`/`sending`/`accepted`/`accepted_with_errors`/`rejected`/`transport_error`),
  `http_status`, `aeat_estado_envio` (`Correcto`/`ParcialmenteCorrecto`/`Incorrecto`),
  `aeat_estado_registro` (`Correcto`/`AceptadoConErrores`/`Incorrecto`),
  `aeat_error_code`, `aeat_error_description`, `csv`,
  `duplicate_of_id_peticion` (from `RegistroDuplicado`, when present),
  `response_raw` (the raw AEAT response XML), `submitted_at`,
  `response_received_at`, `completed_at`, `retry_count`, `last_error`
  (sanitized, no secrets).
- **`verifactu_submission_attempts`** — one row per actual network
  attempt (a submission can have several). `verifactu_submission_id`
  (FK), `attempt_number`, `started_at`, `finished_at`, `outcome`
  (`success`/`transport_error`/`tls_error`/`soap_fault`/`timeout`),
  `http_status`, `error_message` (sanitized), `duration_ms`.

Neither table, nor anything the queue job does, ever writes to
`verifactu_records` or its snapshot columns - fiscal-record state and
submission/network state are fully separate, as required.

---

## 6. Exact XML sent

**FAKTURALISTA IMPLEMENTATION DECISION, chosen for auditability.**
`verifactu_submissions.payload_xml` stores the **exact
`VerifactuXmlBuilder::build()` output**, generated **once**, at
submission-creation time, from the immutable `VerifactuRecord` snapshot
- never regenerated later, never re-derived from mutable data. A
SHA-256 `payload_checksum` is stored alongside it so any later
byte-for-byte tampering (accidental or otherwise) is detectable.

The full SOAP envelope (`RegFactuSistemaFacturacion`/`Cabecera`/
`RegistroFactura`) is **not** separately persisted - it's
deterministically reconstructible from `payload_xml` + the tenant's own
`CompanyProfile` NIF/name (`ObligadoEmision`) at read time for
audit/debug display, and storing it verbatim would only duplicate data
that's already fully captured. If this decision needs revisiting (e.g.
because AEAT ever rejects based on envelope-level details not visible
in `payload_xml`), `AeatVerifactuClient` builds the envelope in one
place (`buildEnvelope()`), so switching to full-envelope persistence
later is a small, contained change.

---

## 7. AEAT SOAP client

`App\Services\Verifactu\Aeat\AeatVerifactuClient`:
- Builds the SOAP 1.1 document/literal envelope per W1's binding by hand
  via `DOMDocument` (same rigor as `VerifactuXmlBuilder` - never string
  concatenation), wrapping the already-built `payload_xml` in
  `RegFactuSistemaFacturacion`/`Cabecera`/`RegistroFactura`.
- Uses `AeatEndpointResolver` (test-only, §1) for the URL - never a
  hardcoded string duplicated elsewhere.
- Obtains the client certificate via an injected `AeatAuthenticationProvider`.
- **Sends via Laravel's `Http` facade (Guzzle), not PHP's native
  `SoapClient`** - a deliberate implementation choice, not an official
  requirement: SOAP 1.1 is "just" an HTTP POST with a specific
  `Content-Type`/`SOAPAction` and an XML body, and using the same HTTP
  client this codebase already uses for Stripe/Anthropic (Phase 1
  finding) keeps the AEAT client trivially testable via `Http::fake()`
  and avoids `SoapClient`'s own WSDL-fetching side effects. Guzzle's
  `cert`/`ssl_key` request options, like `SoapClient`'s `local_cert`,
  require filesystem paths - the same transient temp file from §3 is
  passed to both.
- Distinguishes, as **typed exceptions/results** (never a bare
  bool): `AeatTransportException` (DNS/connect/timeout),
  `AeatTlsException` (certificate/handshake failure - detected by
  inspecting the underlying transport exception, since PHP's SoapClient
  surfaces TLS failures as a generic `SoapFault`/`\Exception` with no
  dedicated type), `AeatSoapFaultException` (a real SOAP `<Fault>`),
  and - on a structurally valid response - an `AeatSubmissionResult`
  DTO (§8) covering accepted / accepted-with-errors / rejected.
- Contains **zero** invoice/customer business logic - it only ever sees
  a `VerifactuSubmission`'s already-built `payload_xml` and a NIF.

Never called from `InvoiceController` or any HTTP controller - only from
`SendVerifactuRecordToAeatJob` (§9) and the manual test command (§17).

---

## 8. Response parser / states

**OFFICIAL REQUIREMENT - exact AEAT terminology, `RespuestaSuministro.xsd`
(X3), not invented:**

- **Global** (`EstadoEnvioType`): `Correcto` | `ParcialmenteCorrecto` |
  `Incorrecto` ("Correcto" = every record in the batch is Correcto;
  "ParcialmenteCorrecto" = at least one Incorrecto or
  AceptadoConErrores; "Incorrecto" = every record Incorrecto - D1 §6.5.2,
  list **L18**).
- **Per-record** (`EstadoRegistroType`, list **L19**): `Correcto` |
  `AceptadoConErrores` | `Incorrecto`.
- `CodigoErrorRegistro` (integer) / `DescripcionErrorRegistro` (text,
  ≤1500 chars) - present when a record isn't plain `Correcto`.
- `RegistroDuplicado` - present only when a record is rejected
  specifically for being a duplicate of one AEAT already has; carries
  the *original* submission's `IdPeticionRegistroDuplicado` and its
  stored `EstadoRegistroDuplicado`.
- `CSV` - only generated when the envelope itself wasn't rejected.

**FAKTURALISTA IMPLEMENTATION DECISION** — `AeatResponseParser` maps
these directly onto `VerifactuSubmission.status` without collapsing to a
bare boolean:

| AEAT `EstadoRegistro` | `verifactu_submissions.status` |
|---|---|
| `Correcto` | `accepted` |
| `AceptadoConErrores` | `accepted_with_errors` |
| `Incorrecto` | `rejected` |
| (transport/TLS/SOAP-fault, no AEAT response reached) | `transport_error` |

`aeat_estado_envio`/`aeat_estado_registro` store AEAT's own literal
strings too (not just the mapped bucket), so nothing about the original
response is lost even after mapping. Raw response XML is always kept
(`response_raw`) for audit/debugging, never shown to end users directly
(§14).

---

## 9. Queue / asynchronous sending

`InvoiceController` (still not wired to any of this - see §19) would,
in a future phase, create a `VerifactuSubmission` row and dispatch
`App\Jobs\SendVerifactuRecordToAeatJob` with only the **submission ID**
as its payload - never a certificate, never decrypted key material,
never the tenant model itself (queue payloads are serialized to the
queue backend's own storage; putting secrets there would leak them into
Redis/DB queue tables). The job re-initializes the correct tenant
context from the ID (mirroring how `QueueTenancyBootstrapper` already
tags jobs dispatched from within a tenant request, confirmed in Phase 1
research) and re-resolves the certificate fresh, inside that context,
every time it runs.

Network I/O happens **entirely inside the job**, never inside the
invoice-issuance DB transaction - a temporarily-unreachable AEAT test
endpoint can never roll back or corrupt the immutable fiscal record,
because the record is already committed before the job is even
dispatched.

---

## 10. Retry / idempotency

**OFFICIAL REQUIREMENT - mandatory flow control, Orden HAC/1177/2024
art. 16.2 (quoted directly, D1 §6.4.4.1):** *"Los sistemas informáticos
«VERI*FACTU» deberán implementar un mecanismo de control de flujo
basado en el tiempo de espera entre envíos, el cual tomará **inicialmente
el valor de 60 segundos**... Los mensajes de respuesta... informarán
sobre el valor de este parámetro, el cual deberá ser tenido en cuenta
para el siguiente envío."* Mechanism, quoted/paraphrased: before every
submission, wait either `t` seconds since the previous submission (`t`
starts at 60, and AEAT can update it via `TiempoEsperaEnvio` in each
response) or until enough records have queued up to hit the per-batch
limit, whichever comes first.

**This is a real requirement Phase 1 never surfaced** - it's a mandatory
per-NIF rate limit, not just a resilience nicety. **FAKTURALISTA
IMPLEMENTATION DECISION:** `verifactu_chain_states` (already the per-NIF
singleton from Phase 2B) gains a `next_submission_not_before` timestamp,
updated from `TiempoEsperaEnvio` on every response (defaulting to
"now + 60s" before any response has ever been received). Before
actually sending, `SendVerifactuRecordToAeatJob` checks this and
**releases itself back onto the queue with a delay** rather than sending
early, if it's not yet time.

**OFFICIAL REQUIREMENT - AEAT is itself idempotent on the natural key**
(issuer NIF + series/number + date), confirmed already in Phase 1 via
the `RegistroDuplicado` response block: resubmitting a record AEAT
already has does not create a duplicate registration - AEAT rejects the
resubmission and returns the *original* submission's status. **This
means a retry can never cause double-registration on AEAT's side.**

**FAKTURALISTA IMPLEMENTATION DECISION - what "retry" means here:** a
retry is **always** a new `VerifactuSubmissionAttempt` row against the
**same** `VerifactuSubmission` (and the same `payload_xml`, byte-for-byte
- never regenerated) - never a new `VerifactuRecord`, never a new
fiscal document. A `RegistroDuplicado` response is treated as
**success**, adopting the original's recorded status, exactly per
Phase 1's finding. Double-send races (e.g. two workers picking up the
same submission) are prevented with a DB-level lock:
`SendVerifactuRecordToAeatJob` `lockForUpdate()`s the `VerifactuSubmission`
row and checks `status` is still `pending`/`transport_error` before
proceeding - a submission already `sending`/terminal is a no-op.

---

## 11. Multi-tenancy

**OFFICIAL REQUIREMENT** (Phase 1, §9.3): a single submission batch
(`RegistroFactura[]`) must correspond to one taxpayer. **FAKTURALISTA
IMPLEMENTATION DECISION:** enforced structurally, not just by
convention - `AeatVerifactuClient::submit()` takes exactly **one**
`VerifactuSubmission` at a time (no batch-building API exists at all in
Phase 2D), and every table involved (`verifactu_certificates`,
`verifactu_submissions`, `verifactu_submission_attempts`) is a tenant-DB
table, so there is no code path, shared table, or query that could ever
mix two tenants' records or certificates - the same structural guarantee
`verifactu_chain_states`/`verifactu_records` already rely on. Tested:
tenant A's certificate cannot resolve for tenant B's NIF; a submission
created in tenant A's context is unreachable from tenant B's connection.

---

## 12. Phase 2D.1 — pre-flight review (July 2026 FAQ re-verification)

Performed before attempting any first real AEAT TEST submission. Adds one
more primary source to the table above:

| # | Document | Retrieved | Official URL |
|---|---|---|---|
| F1 | "Preguntas frecuentes (FAQ)" — Sistemas VERI\*FACTU, HTML pages, "Actualizadas a 21 de julio de 2026" | 2026-09-20 | `sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/*.html` |

**Note on D3:** re-fetched and compared by SHA-256 against the Phase
2C.1/2D copy — byte-identical. The "21 de julio de 2026" update lives
only in F1's HTML FAQ pages, not in the developer PDF.

**OFFICIAL REQUIREMENT — Incidencia flag (F1, "Sistemas VERI\*FACTU"
section), a genuine new finding, not present in D3/D1/W1/X3:** after a
connectivity incident (network/service outage) prevents a submission, a
resend "deberá reintentarse periódicamente... incorporando en el envío
la 'S' en el campo 'Incidencia'" (`CabeceraType/RemisionVoluntaria/Incidencia`,
schema type `S`/`N`). **FAKTURALISTA IMPLEMENTATION DECISION (fix applied
this phase):** `AeatVerifactuClient::buildEnvelope()` now sets
`Incidencia=S` whenever the `VerifactuSubmission` being sent already has
`retry_count > 0` (i.e., a prior attempt on this exact submission already
failed) — no new state was needed, since `retry_count` already existed
for backoff purposes. First attempts (`retry_count === 0`) omit the
element entirely, matching D3's schema (optional). Covered by two new
tests: `first_attempt_does_not_set_incidencia`,
`retry_after_a_transport_failure_sets_incidencia_s`.

**Re-checked against F1, no change needed (structural design already
matches):**
- Authentication with the taxpayer's own qualified certificate — F1
  describes the same client-certificate model as D1; §2/§3 stand as-is.
- SaaS/multi-tenant behavior and one-taxpayer-per-batch — F1 does not
  contradict §0/§11's "one virtual SIF per tenant, never batched across
  taxpayers" conclusions. No occurrence of "instalaci" (as in
  "instalación") anywhere in F1 beyond D3's already-reviewed language —
  §0's conclusion is unchanged.
- AEAT's own duplicate-detection idempotency (resubmitting an
  already-known record returns the original outcome rather than
  double-registering) — confirmed, matches §10 as already implemented.
- QR implications after successful submission — F1's QR content is
  about client-side/recipient verification only; no submission-side
  code implication. QR generation remains out of scope (Phase 2E).
- TEST environment / endpoint — see the WSDL re-verification below.
- Certificate requirements — F1 does not add new documented requirements
  beyond D1/D3; see §5 of `docs/verifactu-first-real-test.md` for what
  this means practically for the first real test.

**WSDL/TEST endpoint re-verification:** W1 re-fetched fresh (not reused
from cache) and compared byte-for-byte against the Phase 2D copy —
unchanged. TEST port is still `SistemaVerifactuPruebas`, endpoint still
`https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP`,
still explicitly commented in the WSDL as "Entorno de PRUEBAS". No
discrepancy found.

**Command-level hardening (item 4 of this review, not a FAQ finding):**
`verifactu:aeat-test`'s pre-send banner now also prints the tenant ID and
the certificate's expiration date, and the command now performs three
checks *before* showing that banner or creating any `VerifactuSubmission`
row: (1) resolves the certificate through the exact same
`AeatAuthenticationProvider` path the real send will use, so missing,
expired, or undecryptable/invalid certificates are all refused with the
same clear message *before* anything is sent or persisted; (2) builds
the record's XML via `VerifactuXmlBuilder` and validates it against the
vendored XSD via `VerifactuXmlValidator`, refusing with a clear message
if either step fails. "Unsupported invoice/tax scenario" was found to
already be refused structurally further upstream: a `VerifactuRecord`
cannot exist for an unsupported scenario in the first place (
`VerifactuChainService::recordAlta()` already throws before one is
created), so no separate command-level check was needed there. The
production-endpoint refusal (`AeatEndpointResolver`/environment check)
and the missing-taxpayer-NIF refusal already existed unchanged from
Phase 2D.

**Secondary bug found and fixed while reviewing the certificate-refusal
path:** `SendVerifactuRecordToAeatJob`'s `catch (VerifactuCertificateException $e)`
block called `$this->fail($e)` but never called `markTransportError()`,
so a submission that failed this way was left stuck at `STATUS_SENDING`
with no `last_error` recorded — misleading for anyone inspecting the
`verifactu_submissions` table after a failure, and inconsistent with
every other failure branch in the same method. Fixed to call
`markTransportError()` first, same as the TLS/transport-error branches,
so the row correctly lands in `STATUS_TRANSPORT_ERROR` (a retryable
state) with the certificate error message recorded.

**No real AEAT TEST submission was performed in this phase** — no real
qualified certificate is available in this development environment. See
`docs/verifactu-first-real-test.md` for the step-by-step procedure for
when one is obtained, and the final Phase 2D.1 report for what this
means for readiness.

---

## Bugs found and fixed via testing (not by inspection)

- `AeatVerifactuClient` set `Content-Type: text/xml; charset=utf-8` via
  `withHeaders()`, but the later `withBody($envelope, 'text/xml')` call
  silently overwrote it back to bare `text/xml` (Laravel's `withBody()`
  sets Content-Type from its own second argument). Fixed by passing the
  full desired value there instead. Caught only because a test asserted
  the literal header on the faked request - inspection alone missed it.
- `AeatResponseParser::parse()` called `DOMDocument::loadXML()` without
  `libxml_use_internal_errors(true)`, so a malformed/non-XML AEAT
  response would throw a raw `ErrorException` instead of resolving to
  the intended safe `Incorrecto` default. Fixed to match
  `VerifactuXmlValidator`'s existing pattern.
- (Phase 2D.1) Missing `Incidencia=S` flag on resends after a
  connectivity failure — a genuine regulatory gap found only by
  re-reading F1 (§12), not by inspecting the existing code.
- (Phase 2D.1) `SendVerifactuRecordToAeatJob`'s certificate-exception
  branch never recorded `last_error`/`STATUS_TRANSPORT_ERROR`, leaving
  the submission stuck at `STATUS_SENDING` after a certificate failure —
  found while reviewing that exact branch for §12's command-hardening
  work, not by a failing test (no prior test asserted the submission's
  post-failure status for this specific branch).

## Known unresolved questions

- Whether Fakturalista will ever need the "Sello" (organizational seal
  certificate) endpoint variant, and how to detect which a given
  taxpayer's certificate requires - not addressed in Phase 2D.
- The exact mechanics of a TLS handshake failure as surfaced by PHP's
  `SoapClient`/`stream_context` are inferred from general PHP behavior,
  not from an AEAT document (AEAT doesn't describe client-side error
  handling) - `AeatTlsException` detection is a best-effort
  classification, not a guaranteed-exhaustive one.
- No real AEAT TEST request has been performed in this phase or in
  Phase 2D.1 (no real qualified certificate available in this
  environment) - see `docs/verifactu-first-real-test.md` and the final
  Phase 2D.1 report for what this means for validation confidence.
- The `NumeroInstalacion`-for-SaaS interpretation in §0 should be
  reconfirmed with AEAT or a tax advisor before relying on it for real
  tenants.
- The `Incidencia=S` heuristic (§12) equates "this exact submission row
  has a prior failed attempt" with "resending after a connectivity
  incident". This is correct for every failure branch that currently
  produces a `STATUS_TRANSPORT_ERROR`/retry (TLS, generic transport,
  SOAP fault, certificate errors), but F1 does not give an exhaustive
  definition of "incidencia" - if AEAT ever considers some other kind of
  retry as not qualifying, this heuristic would need revisiting.
