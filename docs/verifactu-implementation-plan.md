# VERI*FACTU Implementation Plan — Phase 1: Gap Analysis & Architecture

**Status: PLANNING ONLY. No production code has been modified as part of this document.**

**Compliance disclaimer:** Fakturalista is **not currently VERI*FACTU-compliant, AEAT-certified, or AEAT-homologated in any way**. This document analyzes the gap between the current codebase and the official requirements, and proposes an architecture to close it. Nothing in this document should be read, quoted, or repurposed as a compliance claim.

---

## 0. Sources used (primary, official)

Every regulatory claim in this document is tied to one of these. Where I could not verify a detail against one of these primary sources, I've marked it **[NEEDS PHASE-2 VERIFICATION]** rather than guessing.

| # | Source | Type | URL |
|---|--------|------|-----|
| S1 | Real Decreto 1007/2023, de 5 de diciembre (BOE-A-2023-24840) | Law (reglamento) | https://www.boe.es/buscar/act.php?id=BOE-A-2023-24840 |
| S2 | Real Decreto 254/2025, de 1 de abril (BOE-A-2025-6600) — first deadline extension | Law (amends S1) | https://www.boe.es/buscar/doc.php?id=BOE-A-2025-6600 |
| S3 | Real Decreto-ley 15/2025 (BOE, 3 dic. 2025) — current deadlines | Law (amends S1) | https://www.boe.es | (exact BOE-A-ID not independently confirmed by primary text fetch — see note under §3) |
| S4 | Orden HAC/1177/2024, de 17 de octubre (BOE-A-2024-22138) | Ministerial order — technical/functional specs | https://www.boe.es/diario_boe/txt.php?id=BOE-A-2024-22138 |
| S5 | AEAT — "Sistemas Informáticos de Facturación" web service description, v1.0.3 (28/07/2025) | Official technical doc (PDF, 101 pp.) | https://sede.agenciatributaria.gob.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Veri-Factu_Descripcion_SWeb.pdf |
| S6 | AEAT — "Detalle de las especificaciones técnicas para generación de la huella o hash de los registros de facturación", v0.1.2 (27/08/2024) | Official technical doc (PDF, 13 pp.) | https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Veri-Factu_especificaciones_huella_hash_registros.pdf |
| S7 | AEAT — `SuministroLR.xsd` / `SuministroInformacion.xsd` (production) | Official XSD schemas | https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SuministroInformacion.xsd (+ `SuministroLR.xsd`, `ConsultaLR.xsd`, `RespuestaSuministro.xsd`, `RespuestaConsultaLR.xsd`, `SistemaFacturacion.wsdl` in the same directory) |
| S8 | AEAT — same schemas, **pre-production** environment | Official XSD schemas (test) | https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/ (same filenames as S7) |
| S9 | AEAT — "Ejemplos de declaraciones responsables de sistemas informáticos de facturación", v0.5.1 | Official worked examples (PDF) | https://sede.agenciatributaria.gob.es/static_files/Sede/Tema/IVA/Verifactu/EjemplosDeclaracionResponsable(V0.5.1).pdf |
| S10 | AEAT — VERI*FACTU / SIF portal | Official landing page | https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html |
| S11 | Real Decreto 1619/2012, de 30 de noviembre (invoicing obligations regulation — pre-existing, referenced by S1/S4) | Law | https://www.boe.es/buscar/act.php?id=BOE-A-2012-14696 |

**Note on S3:** I confirmed the *content* of Real Decreto-ley 15/2025 (the two 2027 dates you specified, matching what you gave me) through multiple independent secondary reports that all cite the same BOE publication date (3 December 2025) and the same effect (extending the RD 254/2025 dates of 1 Jan 2026 / 1 Jul 2026 to 1 Jan 2027 / 1 Jul 2027). I was not able to independently pull the raw BOE legal text of RD-ley 15/2025 itself in this session to quote its exact article wording — **before implementation, someone should pull the exact BOE-A identifier and article text for RD-ley 15/2025 directly**, the same way I did for S1/S2/S4. I'm flagging this rather than presenting secondary-source paraphrase as primary law.

**Note on hash test vectors (S6):** I independently re-implemented the three worked examples in Python (SHA-256 over the exact UTF-8 string specified) and they reproduce the exact hash values published in the document, byte for byte. These are safe to use as automated test fixtures — see §16.

---

## 1. Current Fakturalista invoice architecture

*(Established by direct codebase inspection — file:line citations throughout. Stack: Laravel 10.3, PHP 8.1, `stancl/tenancy` 3.9 with one MySQL database per tenant, Vue 3 frontend.)*

### 1.1 Invoice model & schema
- `app/Models/Invoice.php:11-59` — `SoftDeletes`, statuses as class constants: `draft`, `issued`, `paid`, `cancelled`. `LOCKED_STATUSES = [issued, paid, cancelled]`.
- Base table `database/migrations/tenant/2023_09_07_113049_create_invoices_table.php:14-34`: `customer_id`, `reference` (string, **not unique, no index**), `uuid` (unique — the routing key, not the legal number), `date`, `status`, `expiration_date`, `payment_terms`, `sub_total`, `discount_rate`, `discount_amount`, `vta` (combined VAT), `total`, `note`. Money columns are `double`, not `decimal`.
- Later ALTERs add: `vta4`/`vta10`/`vta21` (`decimal(15,2)`, per-rate breakdown — no `vta0` column), `issued_at`, `source_invoice_id`, Stripe fields (`stripe_session_id`, `paid_at`, `paid_via`, etc.).
- Line items live in a polymorphic `carts` table (shared with `Quote`), not a dedicated `invoice_items` table: `id`, `cartable_type/id`, `description`, `qty`, `price`, `discount`, `total`, `vta` (rate %, per line), `item_id`, `unite`.
- Audit trail: `invoice_history` table + `Invoice::logHistory()` (`Invoice.php:107-113`), action constants `created/issued/duplicated/paid/cancelled/sent/whatsapp`. **No actor/user_id column** — only "what happened when," never "who."

### 1.2 Numbering
`InvoiceController.php:589-594` and a byte-for-byte duplicate in `QuoteToInvoiceService.php:114-120`:
```php
$last = Invoice::lockForUpdate()->orderBy('id', 'desc')->first();
$n    = isset($last) ? $last->id + 1 : 1;
return 'INV-' . $n;
```
- Derived from the last **non-soft-deleted** invoice's auto-increment `id` (the `SoftDeletingScope` silently excludes deleted rows from this query).
- **Confirmed bug**: if the invoice currently holding the highest `id` is soft-deleted, the next call recomputes the same `id + 1` and reissues an **already-used reference string** — not just a gap, a collision.
- No DB unique constraint on `reference` to catch this.
- Numbering is assigned **at creation time** (draft), not at issuance — so a draft that's created and later deleted still "burns" a slot in a way that's inconsistent with itself once you account for the bug above.
- `CompanyProfile` already has `invoice_prefix`, `invoice_next_number`, `invoice_number_format` columns (`database/migrations/tenant/2025_12_18_142214_create_company_profiles_table.php`) — **completely unused by the numbering code**. This is the clearest "schema exists, logic doesn't" gap in the codebase.

### 1.3 Statuses & lifecycle transitions
- `draft → issued`: `InvoiceController::issue()` (237-257), sets `issued_at`, logs history. Also triggered as a **side effect** of `send()` (email) and `whatsapp()` (share) on a draft.
- `issued → paid`: `InvoiceController::markPaid()`, `InvoicePaymentsController::record()`, and the Stripe webhook handler (`StripeWebhookController::handleInvoicePayment()`) — three independent paths, all idempotent-guarded (`if ($invoice->isPaid()) return;` or `isIssued()` guards).
- `* → cancelled`: `InvoiceController::cancel()` (275-287) — **no guard preventing cancellation of a `paid` invoice**, and it's a pure status flip with no reversing document.
- **Editing** (`update()`, 204-233): blocked once `isLocked()` (issued/paid/cancelled) — returns HTTP 403. This is the one place the codebase already behaves the way VERI*FACTU needs. `syncCarts()` hard-deletes and recreates all line rows on every edit, even for allowed draft edits.
- **Deletion** (`destroy()`/`bulkDelete()`, 517-534): **no status check at all.** An issued, paid, or cancelled invoice can be soft-deleted through the same endpoint as a draft. Not logged to `invoice_history` either — deletion is invisible in the audit trail.
- **Payment status changes never touch financial fields** (`total`, `vta*`) — only `status`/`paid_at`/`paid_via`. This part is already aligned with what VERI*FACTU needs.

### 1.4 Rectification / credit notes
**Does not exist.** No model, table, status, or field for a "factura rectificativa." The only inter-invoice link (`source_invoice_id`) is used exclusively by a copy-to-draft "Duplicate" feature, not a legal rectification chain. `cancel()` is a status flip on the *same row*, not a new reversing document. One unimplemented TODO exists in Stripe refund webhook handling (`StripeConnectController.php:256`, comment only, no code).

### 1.5 Tax calculation
- VAT rate lives per cart line (`Cart.vta`, a plain percentage). UI hardcodes exactly **0/4/10/21%** (`CreateInvoiceForm.vue:226-229`).
- **No exemption reason codes** (a `0%` line carries no "why" — exempt, not-subject, reverse-charge are all indistinguishable).
- **No IRPF/retention field** anywhere (common on Spanish freelancer invoices).
- **No reverse-charge flag** anywhere.
- VAT/totals are computed **client-side in Vue** and POSTed as plain numbers; the backend does not recompute or validate them server-side. A second, independent computation happens again at PDF-render time in `TemplateRendererService.php:96-111` from the raw cart lines — meaning two code paths can silently diverge, and neither is the authoritative source of truth.

### 1.6 Seller & customer tax identity
- **Seller**: `CompanyProfile` (tenant DB, one row per tenant, `CompanyProfile::firstOrCreate([], ['legal_name' => ''])`). Has `legal_name`, `trade_name`, `tax_id` (comment: "NIF/CIF/ICE...", untyped/unvalidated), `vat_number`, `country_code` (defaults `'ES'`), full fiscal address. No AEAT-specific fields (software producer identity, VERI*FACTU opt-in, certificate reference) exist yet.
- **Customer**: `Customer` model — only a generic `vat_number` and a Moroccan `ice` field; **no dedicated Spanish NIF/CIF field**, no legal-name/trade-name distinction (unlike `CompanyProfile`).
- **Critical PDF finding**: neither party's tax ID is rendered anywhere in the active PDF template system (`resources/views/pdf/components/*` — confirmed zero matches for `vat_number`/`tax_id`/`NIF`). Two legacy hardcoded per-domain views bypass the template system entirely and print a NIF as literal HTML text unrelated to `CompanyProfile`.

### 1.7 PDF generation
- `barryvdh/laravel-dompdf` only. Active path: `TemplateRendererService.php` → `Pdf::loadView('pdf.document', ...)`. Tenant-configurable via `InvoiceTemplate` (colors, toggles, logo).
- The natural insertion point for a QR image + VERI*FACTU legend is `resources/views/pdf/components/_footer.blade.php` (or a new `_verifactu.blade.php` partial), with a new `qrImage`/hash-derived variable threaded through `TemplateRendererService::render()`'s `compact(...)` call.

### 1.8 Quote → Invoice conversion
`QuoteToInvoiceService::convert()` — reuses the identical (buggy) numbering logic, always creates a **draft** (not issued), recomputes `vta4/10/21` fresh from cart lines (quotes only have a single combined `vta` column), and logs `context.source = 'quote'`. No fast-track to `issued` — goes through the same `issue()` gate as any manually created invoice.

### 1.9 Tenancy & platform
- **True database-per-tenant** (not shared-DB/tenant_id scoping) — confirmed via `HasDatabase`/`TenantWithDatabase` on `Tenant`, `DatabaseTenancyBootstrapper` active, and the `CreateDatabase`/`MigrateDatabase` job pipeline in `TenancyServiceProvider.php:26-36` (run **synchronously**, not queued).
- Central DB (`mysql` connection) holds `Plan`, `PlanLimit`, `Subscription`, `BillingProfile`, `Payment`, `Tenant`/`Domain` themselves. Tenant DB holds everything invoicing-related (`Invoice`, `Customer`, `Item`, `Quote`, `CompanyProfile`, etc.) — no `$connection` override, they simply inherit whatever the tenancy bootstrapper points at.
- **Queues run synchronously today**: `QUEUE_CONNECTION=sync` (`.env:22`). No `jobs` table migration exists anywhere (only `failed_jobs`). Only one Job class exists in the whole app (`SeedTenantCountries`) and it deliberately swallows exceptions rather than using retry/backoff — there is no existing retry/backoff/idempotency pattern to inherit.
- **No XML, no certificate/signature, no encrypted-at-rest casts, no audit-log package, no Observers** anywhere in the codebase — all zero matches on direct search.
- Third-party integrations (Stripe, Anthropic) all follow the same shape: a plain `Service` class under `app/Services/`, secret read from `config/services.php` (itself reading `.env`), `Http::timeout(...)->post(...)` or a vendor SDK, typed exception thrown on failure, `Log::error` with structured context. This is the pattern any new AEAT client should match.
- No task scheduler entries exist (`app/Console/Kernel.php` schedule is empty/commented out).

---

## 2. Official requirements identified

### 2.1 Legal basis and what it actually requires
- **RD 1007/2023** (S1) approves the "Reglamento" requiring invoicing software to guarantee **integridad, conservación, accesibilidad, legibilidad, trazabilidad e inalterabilidad** of invoicing records (Art. 8.2, a/b/c per the fetched text: integrity = "no puedan ser alterados sin que el sistema informático lo detecte y avise"; traceability = records "deberán estar encadenados de manera que pueda verificarse su rastro"; conservation/accessibility/legibility of all records).
- **Art. 3.1.a** (RD 1007/2023) scopes "Impuesto sobre Sociedades" (corporate income tax) taxpayers as the group with the earlier deadline; the rest of Art. 3.1's scope gets the later one (see §3).
- **Art. 16** (RD 1007/2023) defines "VERI*FACTU" systems specifically as those that *effectively* transmit, by electronic means, **all** invoicing records to AEAT — and grants those systems a legal presumption of complying with the Art. 8 integrity/traceability requirements (i.e., VERI*FACTU is the "easy path" to compliance; a non-VERI*FACTU system must prove the same guarantees some other way, which this plan does not cover).
- **Art. 13** (RD 1007/2023) requires system producers to sign a **"declaración responsable"** — see §15.
- **Orden HAC/1177/2024** (S4) is the technical annex: Art. 7 defines chaining (each record references the immediately-chronologically-previous one's NIF/series/number/date and **first 64 characters of its hash**), Art. 13 is the hash-generation rule (developed in full technical detail by S6), and Arts. 20-21 define the QR code (see §11).

### 2.2 What "VERI*FACTU" specifically means operationally
Per S5 (§9, "Anexo II: Operativa de remisión voluntaria «VERI*FACTU»"): a VERI*FACTU-mode system must submit **every** generated invoicing record to AEAT **immediately, automatically, and securely, by electronic means** ("remitirán inmediatamente ... de forma automática y segura por medios electrónicos, todos los registros de facturación generados"). This is a real-time obligation, not a batch-at-month-end one — it directly shapes the architecture in §5-§7 (submission must be triggered at the moment of invoice issuance, not on a nightly cron).

### 2.3 A finding not in your brief, worth flagging
**Real Decreto 254/2025** (S2, 1 April 2025) sits between RD 1007/2023 and the RD-ley you cited: it first pushed the original 1 July 2025 deadline to 1 January 2026 (single date, before the split into two dates). RD-ley 15/2025 then split and pushed those further to the 1 Jan 2027 / 1 Jul 2027 dates you gave me. I mention this only so nobody is surprised to find a third decree in the chain when checking BOE — it doesn't change anything about the dates you already specified, which match what I found from RD-ley 15/2025.

---

## 3. Gap analysis (current vs. required)

| Area | Required (source) | Current Fakturalista | Gap |
|---|---|---|---|
| Unique, non-reusable invoice numbering | Implicit in Art. 8.2(b) traceability + general Spanish invoicing law (S11) | App-level `MAX(id)+1`, reuse-prone on delete, no DB unique constraint, assigned at draft-creation not issuance | **Blocking.** Must redesign (§6, §13). |
| Immutable financial fields once issued | Art. 8.2(a) integrity | `update()` already blocks edits once locked | **Compatible**, no change needed here. |
| Deletion of issued invoices | Art. 8.2(a)/(b) — an issued record must not simply vanish | `destroy()`/`bulkDelete()` have **no** status check; issued/paid/cancelled invoices can be soft-deleted, unaudited | **Blocking.** Must add a hard guard (§13). |
| Rectification (factura rectificativa, tipos R1–R5) | `ClaveTipoFacturaType` enum in S7 requires it; RD 1619/2012 (S11) underlies it | Does not exist at all — only a same-row status flip | **Blocking, greenfield.** (§13, open question in §18) |
| Registro de anulación (cancellation record) | Art. 7/13 Orden HAC (S4), XSD `RegistroAnulacion` (S7) | No concept of a reversing document; `cancel()` is a status flip | **Blocking, greenfield.** (§6) |
| Hash/chain per record | Art. 13 Orden HAC (S4), full spec in S6 | Nothing exists | **Blocking, greenfield.** (§7) |
| XML generation + XSD validation | S7 | Zero XML tooling anywhere in the codebase | **Blocking, greenfield.** (§8) |
| SOAP submission to AEAT | S5 | Zero SOAP/certificate tooling; no `ext-soap`, no HTTP-client precedent for mutual-TLS | **Blocking, greenfield.** (§9) |
| Invoice type classification (F1/F2/R1-R5/F3) | XSD `ClaveTipoFacturaType` (S7) | No such field on `Invoice` at all | **Blocking.** New column needed (§4). |
| Tax breakdown structure (base + cuota per rate, régimen/exención codes) | XSD `Desglose`/`DetalleDesglose` (S7) | Only aggregate `vta4/10/21` amounts; no régimen (`ClaveRegimen`), qualification (`CalificacionOperacion`), or exemption (`OperacionExenta`) codes anywhere | **Blocking.** New fields + aggregation logic (§4, §6). |
| Seller NIF validated & typed | Art. 8 general; XSD `NIFType` (9 chars) (S7) | `CompanyProfile.tax_id` is a free-text string, no format validation | Needs validation logic. |
| Customer NIF (Spanish) | XSD `PersonaFisicaJuridicaType`/`IDOtroType` (S7) | `Customer` has only a generic `vat_number` + Moroccan `ice`; no Spanish-NIF-typed field | Needs new field + validation. |
| QR code on invoice PDF | Orden HAC Art. 20-21 (S4) | Neither party's NIF nor any QR renders on the active PDF template at all | **Blocking, greenfield.** (§11) |
| Software producer identity + declaración responsable | RD 1007/2023 Art. 13, Orden HAC Art. 15, worked example S9 | Doesn't exist | **Blocking, greenfield, and a legal/business decision — see §15.** |
| Certificate-based authentication to AEAT | S5 §4.1 ("certificado electrónico cualificado reconocido") | Zero certificate handling anywhere; no encrypted-at-rest precedent | **Blocking, greenfield.** (§14) |
| Reliable async submission | Immediate-submission obligation (§2.2) + AEAT downtime must not lose records | `QUEUE_CONNECTION=sync`, no `jobs` table, no retry/backoff precedent anywhere | **Blocking infra prerequisite**, not just app code (§9, §12). |
| Actor identity in audit trail | Not explicitly mandated by the regs, but essential for any dispute/audit | `invoice_history` has no `user_id` column | Should fix alongside this work. |

---

## 4. Required database changes (tenant DB — every table below is per-tenant, see §13)

All additive; nothing here proposes altering existing columns' semantics except where flagged.

**`invoices` table — additive columns:**
- `tipo_factura` (string, e.g. `F1`/`F2`/`F3`/`R1`-`R5`, per XSD `ClaveTipoFacturaType`) — nullable initially for backfill, required going forward for any tenant with VERI*FACTU enabled.
- `tipo_rectificativa` (nullable string, `S`/`I`) — only for rectificative invoices.
- `descripcion_operacion` (string, max 500) — the XSD's mandatory `DescripcionOperacion`; distinct from the free-text `note` field, which stays as-is.
- `fecha_operacion` (nullable date) — optional per XSD, for continuous-supply/service-date cases distinct from `date`.
- Unique index on `reference` (**fixes the numbering-collision bug independently of VERI*FACTU** — worth doing regardless).

**New table `invoice_tax_details`** (replaces the aggregate `vta4/10/21` approach for VERI*FACTU purposes — the aggregate columns can stay for backward-compatible PDF rendering, but AEAT submission needs the structured per-line breakdown):
- `invoice_id`, `impuesto` (01 IVA / 02 IPSI / 03 IGIC / 05 Otros), `clave_regimen`, `calificacion_operacion` (S1/S2/N1/N2), `operacion_exenta` (E1-E8, nullable), `tipo_impositivo` (rate), `base_imponible`, `cuota_repercutida`. One row per distinct (tax, regime, rate) combination on the invoice, aggregated from `carts` at record-generation time.

**New table `verifactu_records`** (one row per generated "registro de facturación", alta or anulación):
- `id`, `invoice_id` (FK), `tipo_registro` (`alta`/`anulacion`), `nif_emisor`, `serie_numero`, `fecha_expedicion`
- Full snapshot of every field that participates in the hash (§7) — **stored, not re-derived**, so the hash can always be independently re-verified against exactly what was sent, even if the invoice's own display data later needs to change for unrelated reasons (it can't, per §13, but this is also good defensive design).
- `huella` (64-char hex), `huella_registro_anterior` (nullable — null only when `es_primer_registro = true`), `es_primer_registro` (bool)
- `xml_generado` (longtext — the exact XML body submitted, byte for byte, needed to prove what was hashed and signed)
- `fecha_hora_huso_gen_registro` (the ISO8601+offset timestamp used inside the hash — stored exactly as generated)
- `estado_envio` (enum, see §10): `pendiente`, `enviando`, `aceptado`, `aceptado_con_errores`, `rechazado`, `pendiente_reintento`
- `id_peticion` (AEAT's request ID, once submitted), `csv` (código seguro de verificación, once accepted)
- `codigo_error`, `descripcion_error` (nullable, from AEAT response)
- `intentos` (int, retry counter), `submitted_at`, `responded_at`, `response_raw` (longtext — the raw AEAT response XML, for audit)
- Standard timestamps, **no soft delete** — this table must never allow row deletion at the application layer (see §13).

**New table `verifactu_chain_state`** (one row per emitting NIF within the tenant — almost always exactly one row per tenant, but the XSD explicitly supports one installation serving multiple `obligados tributarios`, so don't hardcode a 1:1 assumption):
- `nif_emisor` (unique per tenant DB), `last_huella` (64-char hex, nullable only before the first record ever exists), `last_verifactu_record_id`, `updated_at`.
- This table is the thing you `lockForUpdate()` on when generating a new record, to serialize chain writes and guarantee no two records are ever generated concurrently with the same "previous hash" (see §7.4).

**`company_profiles` — additive columns:**
- `verifactu_enabled` (bool, default false — nothing submits to AEAT until a tenant explicitly turns this on)
- `verifactu_environment` (`test`/`production`)
- `verifactu_certificate_path` (string — path on the tenant's own filesystem disk, which is already suffixed per-tenant by `FilesystemTenancyBootstrapper`)
- `verifactu_certificate_passphrase` (string, **cast as `encrypted`** — the first use of Laravel's encrypted casting in this codebase; see §14)
- `verifactu_installation_number` (maps to XSD `NumeroInstalacion`)
- `verifactu_declaracion_responsable_at` (nullable timestamp — when the tenant accepted/acknowledged the terms, see §15)

**`invoice_history` — additive column:**
- `user_id` (nullable FK) — closes the "who did it" gap identified in §1.1, useful for VERI*FACTU audit defensibility even though not explicitly mandated by the regs.

**Central DB (not tenant):**
- A small config table or just hardcoded `config/verifactu.php` values for the **Fakturalista-as-SIF-producer** identity fields that don't vary per tenant: `NombreSistemaInformatico`, `IdSistemaInformatico`, software version, producer NIF/name/address (these belong to whoever legally produces Fakturalista, not to each tenant) — this is a business decision, not a technical one; see §15.

---

## 5. Proposed Laravel architecture

Matches the existing codebase's own conventions (plain `Service` classes under `app/Services/`, config-driven secrets, typed exceptions + structured `Log::error`, `ShouldQueue` jobs) rather than introducing a new framework-within-the-framework. Everything below lives in the tenant runtime context (never in `Filament`/central admin).

```
app/Services/Verifactu/
    VerifactuHashService.php        — pure function: build the hash input string per §7, SHA-256, hex-upper. No I/O, 100% unit-testable against S6's vectors.
    VerifactuChainService.php       — resolves "am I first?", locks verifactu_chain_state, persists a new VerifactuRecord + updated chain state atomically.
    VerifactuXmlBuilder.php         — Invoice (+ InvoiceTaxDetail rows) → RegistroAlta/RegistroAnulacion XML string, per the XSD in S7.
    VerifactuXsdValidator.php       — wraps DOMDocument::schemaValidate() against a locally vendored copy of the XSDs (never fetch XSDs over the network at request time).
    VerifactuSubmissionService.php  — builds the SOAP envelope, opens the mTLS connection using the tenant's certificate, POSTs, returns the raw response. Mirrors InvoiceAiService's shape (constants for URL/timeout, config-driven secrets, typed exceptions).
    VerifactuResponseParser.php     — AEAT response XML → structured accepted/rejected/error-code data, mapped to `estado_envio`.
    VerifactuQrService.php          — builds the AEAT validation URL + renders a QR image (needs a new composer dependency, see §11).
    VerifactuCertificateService.php — reads/decrypts a tenant's certificate + passphrase for use by VerifactuSubmissionService; the only place `Crypt`/`encrypted` casts get touched.
    VerifactuDeclaracionResponsableService.php — (stub until §15 business decisions are made) will eventually hold/expose the producer's declaración responsable text; does not generate or claim compliance on its own.

app/Models/Verifactu/
    VerifactuRecord.php
    VerifactuChainState.php
    InvoiceTaxDetail.php

app/Jobs/
    SubmitVerifactuRecordJob.php     — ShouldQueue, $tries + backoff (first use of this pattern in the codebase — see §12), idempotent via checking VerifactuRecord.estado_envio before doing anything.
    RetryFailedVerifactuSubmissionsCommand.php (app/Console/Commands/) — scheduled command; first entry in Kernel::schedule() (currently empty).

app/Exceptions/Verifactu/
    VerifactuException.php (base)
    VerifactuXsdValidationException.php
    VerifactuSubmissionException.php   — network/timeout/5xx from AEAT
    VerifactuRejectedException.php     — AEAT returned "Incorrecto" for the record
    VerifactuCertificateException.php  — missing/invalid/expired certificate
```

**Explicitly not in `InvoiceController`.** The controller's only new responsibilities are: (a) call `VerifactuChainService` synchronously inside the same DB transaction as `issue()`/`cancel()` so the record+hash are generated atomically with the status change (never issued without its regulatory record existing), and (b) dispatch `SubmitVerifactuRecordJob` afterward. All AEAT-facing logic lives in the services above.

---

## 6. AEAT field → Fakturalista field mapping

This covers the **mandatory** (`minOccurs=1`) fields for `RegistroAlta` and `RegistroAnulacion` per the XSD (S7). It is not the full exhaustive field list (see note at the end of this section).

### RegistroAlta

| AEAT field | Fakturalista field | Exists? | Transformation required | Missing? |
|---|---|---|---|---|
| `IDVersion` | — | N/A | Hardcoded constant `"1.0"` | — |
| `IDFactura/IDEmisorFactura` | `CompanyProfile.tax_id` | Yes (untyped) | Validate as 9-char Spanish NIF format | — |
| `IDFactura/NumSerieFactura` | `Invoice.reference` | Yes | Fix numbering (§13) before this is trustworthy | — |
| `IDFactura/FechaExpedicionFactura` | `Invoice.date` | Yes | Reformat `Y-m-d` → `DD-MM-YYYY` | — |
| `NombreRazonEmisor` | `CompanyProfile.legal_name` | Yes | None | — |
| `TipoFactura` | — | No | New field, mapped from a UI selection (defaulting to `F1`) | **Missing** |
| `DescripcionOperacion` | — | No | New field (distinct from free-text `note`) | **Missing** |
| `Desglose/DetalleDesglose[]` (1-12) | `carts.vta` + `carts.total`, aggregated | Partially | Must aggregate cart lines by rate into base+cuota pairs; `ClaveRegimen`/`CalificacionOperacion`/`OperacionExenta` don't exist and must be added (§4) | **Partially missing** |
| `CuotaTotal` | `Invoice.vta` | Yes | None (verify it's kept in sync once §1.5's dual-computation issue is fixed) | — |
| `ImporteTotal` | `Invoice.total` | Yes | None | — |
| `Encadenamiento/Huella` (of previous record) | `VerifactuChainState.last_huella` | No | New table (§4) | **Missing** |
| `SistemaInformatico/*` | Mix of `config/verifactu.php` (producer identity) + `CompanyProfile.verifactu_installation_number` | No | New config + new column | **Missing** |
| `FechaHoraHusoGenRegistro` | — | No | Generated at record-creation time, `CompanyProfile.timezone` (already defaults `Europe/Madrid`) | **Missing** |
| `TipoHuella` | — | N/A | Hardcoded constant `"01"` | — |
| `Huella` | — | No | Computed by `VerifactuHashService` | **Missing** |

### RegistroAnulacion

| AEAT field | Fakturalista field | Exists? | Transformation required | Missing? |
|---|---|---|---|---|
| `IDFacturaAnulada/IDEmisorFacturaAnulada` | `CompanyProfile.tax_id` | Yes | Same as above | — |
| `IDFacturaAnulada/NumSerieFacturaAnulada` | `Invoice.reference` (of the invoice being cancelled) | Yes | None | — |
| `IDFacturaAnulada/FechaExpedicionFacturaAnulada` | `Invoice.date` (of the invoice being cancelled) | Yes | Reformat | — |
| `Encadenamiento/Huella` | `VerifactuChainState.last_huella` | No | Same chain table | **Missing** |
| `SistemaInformatico/*` | Same as above | No | Same | **Missing** |
| `FechaHoraHusoGenRegistro` | — | No | Generated at cancellation time | **Missing** |
| `TipoHuella` / `Huella` | — | No | Same as above | **Missing** |

**Note:** the XSD (S7) also defines a large set of *optional* fields I have not exhaustively mapped here (`RefExterna`, `Tercero`, `Destinatarios[]`, `FacturasRectificadas[]`, `FacturasSustituidas[]`, `Macrodato`, `Cupon`, `FacturaSimplificadaArt7273`, etc.). Before writing the XML builder, someone should re-fetch the live XSD (URLs in §0) and walk every optional field against the actual invoice types Fakturalista needs to support (at minimum: does it need `Destinatarios[]` for multi-recipient invoices? Does it need `FacturasRectificadas[]` for rectificative invoices — almost certainly yes, tied to the open question in §18).

---

## 7. Hash / chaining design

Fully specified by S6, and I independently reproduced all three of its worked examples (see §0). This is the one part of this plan with zero ambiguity — implement it exactly as follows.

### 7.1 Algorithm
SHA-256 (the only algorithm currently permitted — S6 §2, "Lista L12"). Output: **hexadecimal, uppercase, 64 characters** (S6 §5). Stored in XSD field `Huella`/`TipoHuella="01"`.

### 7.2 Input fields, exact order (S6 §3)

**Alta:**
```
IDEmisorFactura, NumSerieFactura, FechaExpedicionFactura, TipoFactura,
CuotaTotal, ImporteTotal, Huella (of previous record), FechaHoraHusoGenRegistro
```

**Anulación:**
```
IDEmisorFacturaAnulada, NumSerieFacturaAnulada, FechaExpedicionFacturaAnulada,
Huella (of previous record), FechaHoraHusoGenRegistro
```

### 7.3 String construction rules (S6 §3, verbatim requirements)
- Concatenate as `campo1=valor1&campo2=valor2&...&campoN=valorN` — **not URL-encoded**, plain `&`/`=` separators.
- Trim leading/trailing whitespace from every value.
- Numeric values are decimal-equivalence-normalized before hashing (`123.1` and `123.10` must hash identically) — **do not hash the raw string representation from the database; normalize first** (round-trip through a fixed 2-decimal-places formatter).
- A field with no value still appears in the string as `campoN=` (name + `=`, nothing after) — this is exactly how the first record's empty `Huella=` is represented.
- Encode the final string as UTF-8 bytes before hashing.
- **First record in a chain**: `Huella=` (empty) in the input string, and the record's own `PrimerRegistro` flag is set to `S` in the XSD instead of populating `Encadenamiento/RegistroAnterior`. `VerifactuChainService` must check `verifactu_chain_state` for an existing `last_huella`; if none, this is the first-record path.
- Date/time field `FechaHoraHusoGenRegistro` is a full ISO-8601 timestamp **with UTC offset** (e.g. `2024-01-01T19:20:30+01:00`), matching the tenant's configured timezone (`CompanyProfile.timezone`, already defaults to `Europe/Madrid`).

### 7.4 Multi-tenant chain isolation
Because Fakturalista is already one MySQL database per tenant (§1.9), **chain isolation between taxpayers is structural, not something the application has to enforce with a `WHERE tenant_id = ?` clause that could be forgotten.** `verifactu_chain_state` and `verifactu_records` live in `database/migrations/tenant/`, exactly like `invoices` does today — there is no code path that can read Tenant A's chain state while running in Tenant B's request context, because Tenant B's request never has Tenant A's database connection active at all.

Within a single tenant, the chain is additionally scoped by `nif_emisor` in `verifactu_chain_state` (unique constraint), because the XSD explicitly allows one installation to serve multiple `obligados tributarios` (`IndicadorMultiplesOT`) — don't collapse this to a single global "last hash" per tenant if Fakturalista ever supports more than one legal entity per account.

**Concurrency**: two invoices for the same tenant+NIF must never be issued concurrently with the same "previous hash." `VerifactuChainService` must `SELECT ... FOR UPDATE` (`lockForUpdate()`, same primitive already used — if buggy — by the existing numbering code) on the `verifactu_chain_state` row before reading `last_huella`, generating the new record, writing it, and updating `last_huella`, all inside one DB transaction. This serializes record generation per NIF, which is exactly what the "chronological order of generation" requirement (S5 §9.1.1) demands.

### 7.5 Test vectors (verified, ready to use as PHPUnit fixtures)

**Case 1 — first record (alta), no previous hash:**
```
Input:  IDEmisorFactura=89890001K&NumSerieFactura=12345678/G33&FechaExpedicionFactura=01-01-2024&TipoFactura=F1&CuotaTotal=12.35&ImporteTotal=123.45&Huella=&FechaHoraHusoGenRegistro=2024-01-01T19:20:30+01:00
SHA-256 (hex, upper): 3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60
```
**Case 2 — second record (alta), chained to Case 1:**
```
Input:  IDEmisorFactura=89890001K&NumSerieFactura=12345679/G34&FechaExpedicionFactura=01-01-2024&TipoFactura=F1&CuotaTotal=12.35&ImporteTotal=123.45&Huella=3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60&FechaHoraHusoGenRegistro=2024-01-01T19:20:35+01:00
SHA-256 (hex, upper): F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97
```
**Case 3 — anulación, chained to Case 2:**
```
Input:  IDEmisorFacturaAnulada=89890001K&NumSerieFacturaAnulada=12345679/G34&FechaExpedicionFacturaAnulada=01-01-2024&Huella=F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97&FechaHoraHusoGenRegistro=2024-01-01T19:20:40+01:00
SHA-256 (hex, upper): 177547C0D57AC74748561D054A9CEC14B4C4EA23D1BEFD6F2E69E3A388F90C68
```
(All three reproduced independently in Python during this research session — see §0.)

### 7.6 Validation behavior
Per S6 §7: if AEAT recomputes the hash on its end and it doesn't match what was submitted, the record isn't outright rejected — it's marked **"Aceptado con errores."** This matters for §10's status design.

---

## 8. XML / XSD strategy

- **Never hand-write the XML structure from memory.** `VerifactuXmlBuilder` must be built directly against the XSDs at S7 (production) / S8 (pre-production) — vendor a local copy of both into the repo (e.g. `resources/verifactu/xsd/`) pinned to a specific downloaded date, with a note to re-check for AEAT schema updates periodically (S5's own revision history shows the schema has changed multiple times — v0.3.0 through v1.0.3 — since 2023).
- Build via PHP's `DOMDocument` (already a PHP core extension, no new dependency) rather than string concatenation, specifically to make schema validation trivial: `DOMDocument::schemaValidate($xsdPath)`.
- **Every generated XML document must pass `schemaValidate()` against the vendored XSD before being considered ready for submission** — treat a schema-validation failure as a bug in `VerifactuXmlBuilder`, not something to catch-and-submit-anyway.
- Encoding: UTF-8 (S5 §4.1, explicit requirement).
- String/numeric formatting rules from S5 §6.7-6.8 apply to the XML content itself (not just the hash input): no leading zeros in numeric fields except date components, trim whitespace at start/end of text fields, escape `&`/`<` per standard XML entity rules.
- SOAP envelope: SOAP 1.1, **`style="document"`, `use="literal"`** (S5 §4.2) — i.e., the whole request/response body is described by the XSD, no RPC-style wrapping.

---

## 9. AEAT communication strategy

### 9.1 Endpoints (S5 §7-§8)
| Environment | WSDL | Notes |
|---|---|---|
| Production | `https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl` | Real submissions, real tax consequences |
| Pre-production/test | `https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl` | For development/testing; requires a real qualified certificate but has no tax consequences (per secondary sources — confirm during Phase 2 setup) |

Same-directory XSDs for both environments: `SuministroInformacion.xsd`, `SuministroLR.xsd`, `ConsultaLR.xsd`, `RespuestaSuministro.xsd`, `RespuestaConsultaLR.xsd` (swap `www2.agenciatributaria.gob.es` for `prewww2.aeat.es` to get the test versions).

### 9.2 Authentication
S5 §4.1: submission "podrá ser efectuada por el obligado tributario, un apoderado suyo ... o un colaborador social, que deberá disponer de un **certificado electrónico cualificado reconocido**." All NIFs are validated against AEAT's own centralized database at submission time.

**Important, well-sourced finding**: per the official worked "declaración responsable" example (S9, example 1, point 1.g), a system that operates **exclusively as VERI*FACTU does not need to apply an explicit XML digital signature (`ds:Signature`) to each record** — the regulation treats the record as "signed" by virtue of being transmitted through the authenticated channel itself (the qualified certificate used for the HTTPS/SOAP connection). This significantly simplifies the crypto surface: **no XAdES/per-record XML signing needed for a VERI*FACTU-only system**, only mutual-TLS-style certificate-based transport authentication. This should still be double-checked against the certificate/authentication chapter of S5 directly (I read the WSDL/XSD/response-handling chapters in depth; the specific "how exactly does the cert get presented on the wire" mechanics — mutual TLS vs. a WS-Security header — is the one networking detail I'd mark **[NEEDS PHASE-2 VERIFICATION]** against S5's full text before writing `VerifactuSubmissionService`).

### 9.3 Batch behavior (S5 §3)
- Single message type carries both alta and anulación records mixed together.
- **Max 1,000 records per submission.**
- Synchronous: every submission gets an immediate response — no polling needed for the initial result (a separate query operation exists for later lookups, per `ConsultaLR.xsd`).
- Three-tier **global** result: `Correcto` (full acceptance) / `ParcialmenteCorrecto` (mixed) / `Incorrecto` (full rejection).
- Three-tier **per-record** result: `Correcto` / `AceptadoConErrores` / `Incorrecto`.
- Errors are classified **"No admisibles"** (record rejected outright) vs. **"Admisibles"** (record still registered by AEAT, but flagged) — this distinction should drive whether `SubmitVerifactuRecordJob` treats a response as terminal-success, terminal-with-warning, or needs a corrective resubmission.
- A malformed XML/header causes a SOAP Fault instead of a normal response — handle this as a distinct exception type (`VerifactuXsdValidationException`, which should already have been raised locally before ever reaching AEAT, so a live SOAP Fault here is itself a signal that local validation has a bug).

### 9.4 Duplicate / idempotency behavior — **AEAT is itself idempotent**
Confirmed from the response schema (S5, `RegistroDuplicado` block): if you resubmit a record AEAT already has on file (matched by the natural key — issuer NIF + series/number + date), AEAT does **not** create a second record. It rejects the *resubmission* and returns the *original* submission's `IdPeticion`, its stored status (`Correcta`/`AceptadaConErrores`/`Anulada`), and its error info. **This means Fakturalista's retry logic can safely resubmit a record it's unsure about without risking double-registration on AEAT's side** — `SubmitVerifactuRecordJob` should treat a "duplicate" response as equivalent to success and adopt the returned status, rather than treating it as a failure.

### 9.5 Retry/timeout strategy (proposed, not yet an official requirement)
- `SubmitVerifactuRecordJob`: `$tries = 5`, exponential `backoff()` (e.g. 30s/2m/10m/30m/2h), HTTP timeout in the 30-60s range (dompdf/InvoiceAiService already use 30s for a much simpler API — AEAT's own doc doesn't specify a client timeout, so this is an engineering judgment call, not a regulatory one).
- On exhausting retries: mark `estado_envio = pendiente_reintento`, surface it in the UI (§10), and let `RetryFailedVerifactuSubmissionsCommand` (scheduled, e.g. every 15 minutes) pick it back up — this requires fixing the queue infrastructure first (§12).
- Because AEAT is idempotent on the natural key (§9.4), retries are safe by construction as long as the XML body sent on retry is byte-for-byte the one originally hashed (hence storing `xml_generado` verbatim in `verifactu_records`, §4).

---

## 10. AEAT status UI (proposed)

Terminology deliberately mirrors AEAT's own response vocabulary (S5 §3) rather than inventing new terms, translated for a non-technical user:

| `estado_envio` | AEAT concept | User-facing label (ES) |
|---|---|---|
| `pendiente` | record generated locally, not yet sent | "Pendiente de envío" |
| `enviando` | job in flight | "Enviando a la AEAT…" |
| `aceptado` | `Correcto` | "Aceptada por la AEAT" |
| `aceptado_con_errores` | `AceptadoConErrores` | "Aceptada con incidencias" |
| `rechazado` | `Incorrecto` | "Rechazada — requiere corrección" |
| `pendiente_reintento` | network/timeout failure, retries exhausted for now | "Pendiente de reintento" |

A user should be able to see this status on the invoice list/detail (a badge next to the existing status badge) without ever seeing raw XML or AEAT error codes directly — but the raw `codigo_error`/`descripcion_error`/CSV should be visible in an expandable "detalle técnico" section for support/debugging purposes.

---

## 11. QR / PDF changes

Per Orden HAC/1177/2024 Arts. 20-21 (S4):
- **Size**: 30×30mm to 40×40mm, per ISO/IEC 18004.
- **Content**: an HTTPS URL to AEAT's validation service with four parameters: issuer NIF, invoice series+number, issue date, total amount. (Secondary sources give the exact query-parameter shape as `nif`, `numserie`, `fecha`, `importe` against `www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR` — **[NEEDS PHASE-2 VERIFICATION]**: I could not independently confirm this exact path/param-name against S4's primary text in this session; confirm the literal URL template from Art. 21 before hardcoding it.)
- **Required legend**: for a VERI*FACTU system, the invoice must carry the phrase **"Factura verificable en la sede electrónica de la AEAT"** or **"VERI*FACTU"**.
- **Placement**: per secondary-source consensus, at the start of the invoice with a minimum blank margin around it — **[NEEDS PHASE-2 VERIFICATION]** against S4's primary text for the exact margin figures before finalizing the PDF layout.

**Fakturalista changes needed:**
- New composer dependency for QR generation — none exists today (e.g. `endroid/qr-code`, a common Laravel-compatible choice; final pick is an engineering decision for Phase 2, not a regulatory one).
- `VerifactuQrService` builds the URL + renders a PNG/SVG.
- New Blade partial (`resources/views/pdf/components/_verifactu.blade.php`) included from `document.blade.php`, fed via `TemplateRendererService::render()`'s `compact(...)` call.
- The two **legacy hardcoded per-domain PDF views** (`invoices.yassine`, `invoices.tachua`) bypass the whole template system and would need the same treatment separately, or should be migrated onto the standard template system as part of this work (recommended — maintaining two parallel PDF pipelines for VERI*FACTU compliance is a real maintenance risk).
- Both parties' NIF must actually render on the PDF at all — currently doesn't, independent of VERI*FACTU (§1.6).

---

## 12. Invoice lifecycle changes

| Action | Today | Required change |
|---|---|---|
| Create (draft) | Unrestricted, numbering assigned immediately | **Move legal numbering assignment to the `issue()` step**, not creation — a draft that's abandoned and deleted shouldn't burn a slot in the legal sequence. Keep `uuid` as the internal/draft-time identifier (already is). |
| Edit (draft) | Unrestricted | No change. |
| Edit (issued/paid/cancelled) | Blocked (403) | No change — already correct. |
| **Issue** | Sets `issued_at`, status → `issued` | Must become one atomic transaction: assign final `reference` (fixed numbering), generate `VerifactuRecord` (alta) + hash via `VerifactuChainService`, persist, *then* dispatch `SubmitVerifactuRecordJob`. If any step before the dispatch fails, the whole transaction rolls back — **an invoice must never end up `issued` without a corresponding alta record existing.** |
| **Delete** | Unrestricted at any status | **Block entirely once a `VerifactuRecord` exists for the invoice** (i.e., once no longer `draft`). Only `draft` invoices remain deletable. |
| **Cancel** | Status flip, any status → `cancelled`, no reversing document | Must generate a `VerifactuRecord` (anulación), chained the same way, submitted the same way. Whether a **paid** invoice can be cancelled directly or must instead go through a proper rectificativa flow (R1-R5) is a business/legal decision — see open question in §18; this plan does not presume an answer. |
| Convert quote → invoice | Creates a draft via the same (buggy) numbering call | No numbering change needed here once numbering moves to issue-time (drafts don't need a legal number yet, regardless of origin). |
| Payment status change | Only touches `status`/`paid_at`/`paid_via` | No change — already compatible with "financial fields frozen post-issuance." |

---

## 13. Multi-tenant isolation strategy

1. **Structural isolation, not query-level isolation.** `verifactu_records`, `verifactu_chain_state`, and `invoice_tax_details` are tenant-DB tables (`database/migrations/tenant/`), exactly like `invoices` already is. There is no `tenant_id` column to forget a `WHERE` clause on, because there is no shared table to begin with — this is the single biggest structural advantage Fakturalista's existing architecture gives this project for free.
2. **Never introduce a central table for VERI*FACTU data "for convenience"** (e.g. a cross-tenant reporting table). If a future feature needs cross-tenant VERI*FACTU visibility (e.g. an internal ops dashboard), it must be built by querying into each tenant's DB explicitly (`tenancy()->runForMultiple(...)`, the same primitive `SeedTenantCountries` already uses), never by denormalizing chain/hash data into a shared table.
3. **Certificates are per-tenant**, stored on that tenant's own filesystem disk (already suffixed by `FilesystemTenancyBootstrapper`) with the passphrase encrypted via a tenant-DB column (`company_profiles.verifactu_certificate_passphrase`, `encrypted` cast) — never a single global `.env` certificate like the existing Stripe/Anthropic secrets pattern. Mixing this up (e.g. one shared certificate for all tenants) would misrepresent who is actually submitting each tenant's invoices to AEAT and must never happen.
4. **Multi-emisor-per-tenant is supported by the chain design** (§7.4) without any cross-tenant risk, since it's scoped by `nif_emisor` inside one tenant's own `verifactu_chain_state` table.
5. **Queue isolation**: `QueueTenancyBootstrapper` is already active (`config/tenancy.php:34-38`), meaning queued jobs already carry tenant context correctly when dispatched from within a tenant request — `SubmitVerifactuRecordJob` inherits this for free, but should be explicitly tested (§16) to confirm the job, when it eventually runs (possibly on a worker process, not inline), reinitializes the correct tenant's database connection before touching `verifactu_records`.

---

## 14. Security considerations

- **Certificates/private keys must never be committed to Git** — obvious, but worth stating as a hard rule given this plan introduces the first place in the codebase where a private key touches disk at all. Add a `.gitignore` rule scoped to wherever tenant certificate uploads land, and never let a certificate pass through application logs (audit every `Log::` call in `VerifactuSubmissionService`/`VerifactuCertificateService` for accidental payload dumping — the existing codebase's pattern of `Log::error(['body' => $response->body()])`-style structured logging, seen in `InvoiceAiService`, must NOT be copied verbatim here without stripping certificate material first).
- **Encryption at rest**: the certificate passphrase must use Laravel's `encrypted` Eloquent cast (first use of this in the codebase, §1.9/§4) — the certificate file itself should also live on a disk that isn't publicly served (confirm `local` disk, not `public`, per tenant).
- **Tenant isolation**: covered in §13 — the database-per-tenant model already does the heavy lifting; the main new risk surface is the certificate storage path and making sure `VerifactuCertificateService` always resolves the *current* tenant's certificate, never a cached/previous one across tenant-context switches within the same PHP process (a known class of bug in multi-tenant apps using long-running workers — needs explicit test coverage, §16).
- **Authorization**: only users with appropriate permission on a tenant should be able to view/rotate/upload that tenant's certificate or toggle `verifactu_enabled` — this should sit behind whatever role/permission gate `CompanyProfileController` already uses for other sensitive settings (Stripe Connect is a reasonable precedent to follow, since it's the closest existing "sensitive per-tenant credential" feature).
- **Logging**: `verifactu_records.response_raw`/`xml_generado` will contain real customer/financial data (NIFs, amounts) — treat these columns with the same sensitivity as the rest of the tenant's financial data (they already live in an isolated tenant DB, which is the existing baseline protection); don't additionally mirror them into `Log::` calls.
- **Backups**: whatever tenant-DB backup strategy already exists (not established in this codebase during this audit — flagged as a gap in general, not VERI*FACTU-specific) must include the certificate file storage path, or a restored tenant will silently lose VERI*FACTU submission capability without an obvious error.

---

## 15. Declaración responsable requirements

Per RD 1007/2023 Art. 13.1 (trigger) and Orden HAC/1177/2024 Art. 15.1 (content, 11 elements, S4), confirmed against a real official worked example (S9):

The declaración responsable is signed by the **software producer** (i.e., whoever operates/produces Fakturalista as a company — a business identity decision, not a per-tenant one) and must contain:

1. **1.a** Name of the informatic system ("Nombre del sistema informático")
2. **1.b** Identifying code of the system ("Código identificador")
3. **1.c** Full version identifier
4. **1.d** Components (hardware/software) + a description of what the system does and its main functionalities
5. **1.e** Whether the system is built to work **exclusively** as VERI*FACTU (S/N)
6. **1.f** Whether the system can be used by/for multiple obligados tributarios (S/N)
7. **1.g** Signature type(s) used for non-VERI*FACTU records (irrelevant if 1.e = S, per the worked example — see §9.2's finding)
8. **1.h** Producer's company name (or full name if an individual)
9. **1.i** Producer's Spanish NIF (or, for a foreign producer, identification number + type + issuing country)
10. **1.j** Producer's full postal address
11. **1.k** The compliance statement itself, naming RD 1007/2023, Orden HAC/1177/2024, and the AEAT sede electrónica specifications explicitly
12. **1.l** Date and place of signing

Plus an annex: **2.a** other contact details (phone/fax/email), **2.b** producer's website + product page + a link to the **historical archive of all past declaraciones responsables for every version** (implying Fakturalista would need to publish and permanently retain one of these per released version, publicly, once this ships), **2.c** a description of which specific technical implementations were chosen where the order allows options (e.g., which optional consolidation/transactional technique is used to tie invoice issuance to record generation in one DB transaction — which, incidentally, is exactly what §12's atomic-issue design already proposes).

**This plan does not draft or submit a declaración responsable, and does not claim Fakturalista qualifies for one yet.** That requires: (a) the technical work in this plan actually being built and correct, (b) the business decisions in §18 being made (especially whether Fakturalista ships as VERI*FACTU-exclusive, which materially simplifies 1.e/1.g), and (c) sign-off from whoever is legally authorized to make regulatory declarations on the company's behalf — not an engineering decision.

---

## 16. Testing strategy

- **Hash unit tests**: the three official vectors in §7.5, as literal PHPUnit assertions against `VerifactuHashService`. These are a correctness gate — any refactor that breaks them is a regression, full stop.
- **Chain tests**: first record (no previous hash, `PrimerRegistro=S`), second+ record (correctly references previous hash), a simulated concurrent-issue race (two invoices issued "simultaneously" for the same tenant+NIF) proving `lockForUpdate()` serializes them correctly with no duplicate/skipped hash.
- **Tenant isolation tests**: create two tenants, issue invoices in both, assert Tenant A's `verifactu_chain_state`/`verifactu_records` are structurally unreachable from Tenant B's connection (not just "different values" — actually prove the query surface can't cross over), using the same `tenancy()->run(...)` test helpers the existing test suite presumably already has access to via `stancl/tenancy`'s testing utilities.
- **XML generation + XSD validation tests**: build a `RegistroAlta` and a `RegistroAnulacion` from fixture invoices, assert `DOMDocument::schemaValidate()` passes against the vendored XSD for every supported `TipoFactura` value, and assert it *fails* for a deliberately malformed fixture (proving the validator actually validates, not just always passing).
- **QR generation tests**: assert the generated URL matches the confirmed parameter shape (once §11's [NEEDS PHASE-2 VERIFICATION] item is resolved) for a fixture invoice, and that the rendered image decodes back to that exact URL.
- **Cancellation tests**: issuing an anulación record correctly chains off the invoice's own alta record (not some other invoice's), and that a cancelled invoice's original alta record remains untouched/immutable.
- **AEAT response handling tests** (mocked HTTP/SOAP layer, no real network calls in CI): `Correcto` → `aceptado`; `AceptadoConErrores` → `aceptado_con_errores` with error fields populated; `Incorrecto`/rejected record → `rechazado`; a `RegistroDuplicado` response → treated as success with the original status adopted (§9.4); a SOAP Fault → `VerifactuXsdValidationException` path.
- **Timeout/retry tests**: simulate a connection timeout, assert `SubmitVerifactuRecordJob` retries per its backoff schedule and eventually lands on `pendiente_reintento` after exhausting `$tries`, without ever double-submitting a record with different content.
- **Idempotency tests**: dispatch the same job twice (simulating a queue worker restart mid-processing), assert no duplicate `VerifactuRecord` is created and no duplicate AEAT submission occurs (guard via checking `estado_envio` before doing any work, per §5).
- **Invoice immutability tests**: attempt to edit/delete an invoice that has a `VerifactuRecord`, assert both are hard-blocked with the correct error, at the model/service layer, not just the controller (so no future controller can accidentally bypass it).
- **Concurrency test on numbering**: since this plan also fixes the pre-existing numbering bug (§1.2), add a regression test proving deleting the highest-numbered invoice never causes the next issued invoice to reuse its reference.

---

## 17. Implementation phases (proposed order)

This phase (Phase 1) is this document. Proposed phases from here:

- **Phase 2 — Foundations, no AEAT connectivity yet.** Fix the numbering bug and move numbering to issue-time (independently valuable even without VERI*FACTU). Add the new DB columns/tables (§4). Build `VerifactuHashService` + chain tests against the official vectors (§7, §16) — pure, no I/O, safe to build and merge early. Add invoice-type (`tipo_factura`) and tax-breakdown (`invoice_tax_details`) modeling, including backfilling exemption/regime codes for existing data going forward.
- **Phase 3 — Lifecycle changes.** Block delete on non-draft invoices; wire `VerifactuChainService` into `issue()`/`cancel()` atomically (§12), still without actually calling AEAT (record generation only, `estado_envio` stuck at `pendiente` on purpose). This is a safe, fully-testable milestone that doesn't depend on having a real AEAT certificate yet.
- **Phase 4 — XML + XSD validation.** Build `VerifactuXmlBuilder`/`VerifactuXsdValidator` against the vendored XSDs, with the full field mapping from §6 completed against the live schema (closing the "not exhaustively mapped" note). Fully testable offline.
- **Phase 5 — Queue infrastructure prerequisite.** Add the `jobs` table migration, move `QUEUE_CONNECTION` off `sync` for at least the AEAT submission path, stand up a real queue worker process, and only then build `SubmitVerifactuRecordJob`/`RetryFailedVerifactuSubmissionsCommand` with real retry/backoff (§9.5, §12). This is an ops/infra milestone, not just application code.
- **Phase 6 — Certificate handling + pre-production AEAT connectivity.** `VerifactuCertificateService`, encrypted-at-rest passphrase, and `VerifactuSubmissionService` pointed at the **pre-production** endpoint (S8) only. Resolve the [NEEDS PHASE-2 VERIFICATION] items in §9.2 (auth mechanics) before this phase starts.
- **Phase 7 — QR + PDF.** `VerifactuQrService`, PDF template changes (§11), including migrating the two legacy hardcoded PDF views onto the standard template pipeline. Resolve the [NEEDS PHASE-2 VERIFICATION] items in §11 (QR URL param names, margins) before finalizing.
- **Phase 8 — Status UI.** Surface `estado_envio` in the Vue admin (§10), technical-detail drill-down, retry-visibility for support staff.
- **Phase 9 — Production cutover, per-tenant opt-in.** `company_profiles.verifactu_enabled` flag flips per tenant only after that tenant has uploaded a valid certificate and (once resolved, §15/§18) after Fakturalista's own declaración responsable exists. Point at the production endpoint (S5 §8) only at this stage, tenant by tenant, not globally.
- **Ongoing** — a recurring check against AEAT's own published schema/spec revision history (S5's own version table shows the schema has already changed multiple times since 2023) so `VerifactuXsdValidator` doesn't silently drift out of date.

---

## 18. Open questions requiring your legal/business decision before coding begins

1. **Rectification model**: when a paid or issued invoice needs correcting, does Fakturalista implement full `factura rectificativa` support (R1-R5, `FacturasRectificadas[]`, substitutive vs. incremental) in the same phase as basic VERI*FACTU, or ship with only `registro de anulación` support initially and treat rectification as a later phase? This materially changes the scope of §4/§6/§13.
2. **Can a `paid` invoice be cancelled directly**, or must that path always go through a rectificativa instead? Today's code allows it (bug, per §1.3); the correct regulated answer is a business/legal call, not something I should default.
3. **VERI*FACTU-exclusive or dual-mode?** Will Fakturalista only ever operate in VERI*FACTU mode (simplifying the declaración responsable per §15's 1.e/1.g and removing the need for any non-VERI*FACTU XML-signature path), or does it need to support tenants opting out (non-VERI*FACTU, self-certified integrity via some other mechanism this plan does not cover)?
4. **Who is the legal "producer" for the declaración responsable** — the company operating Fakturalista? This is required before §15 can ever be completed, and its NIF/address/name are needed as constants in `config/verifactu.php` (§4).
5. **Multi-emisor-per-tenant**: does Fakturalista's product roadmap ever need one tenant account to invoice under more than one legal NIF? The chain design (§7.4) already supports it, but confirming "no, never" would simplify UI/UX around certificate management.
6. **Existing invoices at cutover**: for tenants who enable VERI*FACTU after already having issued invoices under the old numbering scheme, does the chain start fresh (first record = first *post-cutover* invoice) or does someone need to backfill/attest to historical invoices some other way? This is likely to have a specific official answer (a "first record after enabling" provision probably exists in the regs) that needs to be found and confirmed, not assumed.
7. **Certificate provisioning UX**: does Fakturalista require each tenant to already own a qualified electronic certificate and simply upload it, or does the product need to help tenants obtain one (e.g. guidance/partnership with a certificate authority)? This is a product decision with support/onboarding implications well beyond the code in this plan.
8. **Timeline commitment**: given the real Jan/Jul 2027 deadlines (§2), what internal delivery date is Fakturalista targeting, and does that change the phase ordering in §17 (e.g., compressing phases 5-6 in parallel if the certificate/queue work can be de-risked earlier)?

---

## Summary

**What Fakturalista already supports:** true per-tenant database isolation (a major structural head start), a working invoice draft→issued→paid lifecycle with issued-invoice edit-locking already in place, a company-profile model with most of the right seller fields (if under-used), dompdf-based PDF generation with a tenant-configurable template system, and established codebase conventions (service classes, config-driven secrets, typed exceptions) that a VERI*FACTU integration can follow rather than invent.

**What is missing:** everything AEAT-facing is greenfield — no XML, no certificate/signature handling, no hash/chain, no SOAP client, no QR generation, no rectification/credit-note concept, and the queue infrastructure isn't actually asynchronous today (`QUEUE_CONNECTION=sync`, no `jobs` table).

**Current behaviors incompatible with VERI*FACTU:** invoice numbering can collide on deletion and is assigned before an invoice is legally issued; issued/paid/cancelled invoices can be deleted with zero restriction and zero audit trail; cancellation is a same-row status flip with no reversing document; neither party's tax ID renders on the actual PDF template in use.

**Recommended order:** fix numbering + build the (pure, easily-tested) hash service first — both are low-risk and independently valuable; then lifecycle/immutability enforcement; then XML+XSD; then the queue-infrastructure prerequisite; only then real AEAT connectivity (pre-production first); QR/PDF and status UI can happen in parallel with the AEAT-connectivity phase.

**Decisions needed before coding begins:** the eight questions in §18 — most importantly, the rectification-model scope (Q1) and the paid-invoice-cancellation policy (Q2), since both change the shape of the database schema and services proposed in §4-§6.
