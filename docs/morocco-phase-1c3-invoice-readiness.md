# Morocco Phase 1C.3 — Invoice/quote structural readiness

**Status: implemented, tested.** Scope: audit the current Moroccan invoice/quote
output end-to-end and close the structural gaps found (seller/customer
identity, tax presentation, numbering, snapshots, PDF, legacy paths,
rectification). No DGI integration, no VAT withholding, no UBL/XML, no QR
codes, no electronic signatures, no VERI*FACTU internals changed, no Spain
support removed, no translation/i18n work.

---

## 1. Audit method

Before changing anything, the current Moroccan invoice/quote pipeline was
read end-to-end: `InvoiceController`/`QuoteController` → `DocumentCalculationService`
→ `invoice_tax_lines` → `TemplateRendererService` → `resources/views/pdf/**`,
plus the email template, the legacy per-tenant PDF views, and
`InvoiceRectificationService`. Phases 1B/1C.1/1C.2 turned out to already
implement most of what this phase's brief asked for — this phase's own
findings are almost entirely about two real, pre-existing gaps (§6, §8
below) plus documentation. No Moroccan legal requirement was invented
anywhere; anything not already verified in a prior phase's docs is called
out below as still pending.

## 2. Current structure (confirmed by reading the code, not assumed)

**Seller** (`_header.blade.php`, source: `$companyIdentity`, snapshot-first
for an issued invoice via `invoices.company_snapshot`, live `CompanyProfile`
otherwise): trade name (falls back to legal name), address (line1/line2,
postal code + city, country), and for `country_code === 'MA'` — ICE, IF
(`if_number`), RC (`registration_number`) — each only if actually set, none
required. Phone and email if present. For any other country, the unchanged
NIF line. Bank/payment info was **not** rendered anywhere before this phase
(§11).

**Customer** (`_addresses.blade.php`, source: `$customerIdentity`,
snapshot-first): name (business name or individual name, via
`Customer::getNameAttribute()`), billing address, phone if the template
enables it, and for Morocco — ICE only if set. No ICE/IF/RC is ever
required; an individual customer with none of the three renders identically
to before (confirmed by `MoroccoIdentityTest` test G, pre-existing).

**Invoice metadata** (`_header.blade.php`): reference (`Facture N° ...`),
issue date, due date (quotes only — `expiration_date`), status badge. Due
date as a distinct concept for invoices (payment terms) is a free-text
`payment_terms` field shown in the footer, not a second date — unchanged,
matches the existing design.

**Tax presentation**: `_items.blade.php` shows each line's tax as `X%` for
a taxable rate or the treatment's own label (`Exonéré`) for exempt —
already correct before this phase. `_totals.blade.php` showed only each
rate's tax **amount**, not its taxable base — fixed in §6.

**Totals**: `Sous-total HT` / per-rate tax / `Total TVA` / `Total TTC` for
Morocco (`Subtotal`/`IVA X%`/`Total` labels for Spain, unchanged). Sourced
from `invoice_tax_lines` for an invoice that has them, the live calculator
for a quote, or the legacy `vta4/vta10/vta21` columns as a last-resort
fallback for an invoice issued before Phase 1C.1 — unchanged, all three
paths pre-date this phase.

**Numbering**: audited, no bug found (§5).

**Snapshots**: `company_snapshot`/`customer_snapshot` (Phase 1B), written
once at issuance, never touched again — confirmed still true; extended in
spirit (not in the snapshot columns themselves) by copying the tax
breakdown onto a rectification (§8).

## 3. Fixes made this phase

### 3.1 Moroccan tax summary now shows the taxable base per rate (§6/§8 of the brief)

`resources/views/pdf/components/_totals.blade.php` now renders a `Base
TVA X%` / `Base <treatment label>` row immediately above each rate's own
tax-amount row, sourced from the already-persisted `invoice_tax_lines.taxable_base`
(or the live calculator's `taxable_base` for a quote) — never recomputed in
the view. Gated to `$isMoroccanTax` only, so Spain's totals block is
byte-for-byte unchanged (no base row shown there, and the legacy
`vta4/vta10/vta21` fallback path — which has no per-rate base at all —
correctly never shows one either, for either country).

```
Sous-total HT              7 000,00 MAD
Base TVA 20 %              5 000,00 MAD
TVA 20 %                   1 000,00 MAD
Base TVA 10 %              2 000,00 MAD
TVA 10 %                     200,00 MAD
Total TVA                  1 200,00 MAD
Total TTC                  8 200,00 MAD
```

A mixed 20% + 10% + exempt document keeps all three buckets — confirmed
by the pre-existing `CountryTaxConfigurationTest::test_pdf_country_labels_and_stored_breakdown`
(unchanged, still green) and the new `moroccan_quote_renders_mixed_taxable_and_exempt_lines_in_mad` test.

### 3.2 Bank/payment info block (§11 of the brief — a real, pre-existing gap, not Morocco-specific)

`CompanyProfile.bank_name/iban/swift` were fully collectible in Settings
(a whole "Banking" section, `settings.vue`) but were **never rendered on
any invoice PDF, for any country.** `resources/views/pdf/components/_footer.blade.php`
now shows a small block with whichever of the three fields is actually
set, invoice-only, right after payment terms and before the notes
section. No Moroccan-specific bank field was invented — the same three
existing fields Settings already collects for every country. When none of
the three is configured, no section renders at all (no empty box).

Read from the **live** `CompanyProfile`, not the identity snapshot: a bank
account is payment-routing information, not the invoice's frozen fiscal
identity (company name/ICE/IF/RC), and a tenant may legitimately switch
banks between invoices — customers should be able to see where to actually
send payment today. This is a deliberate design call, flagged here in case
the business decides bank details should instead be frozen per-invoice
like identity is; nothing currently requires that, and no prior phase's
docs asked for it.

### 3.3 Rectification path — generic tax breakdown was silently dropped (§17 of the brief)

**Confirmed the exact gap Phase 1C.1 flagged as deferred**
(`docs/morocco-phase-1c1-generic-tax-foundation.md` §14): `InvoiceRectificationService::createRectification()`
copied `sub_total/discount_amount/vta/vta4/vta10/vta21/total` and the cart
lines verbatim from the original invoice, but never touched
`invoice_tax_lines`. For a Spanish 4/10/21% original this was invisible
(those three rates still round-trip through the legacy columns), but for
**any rate that doesn't fit those three buckets — a Moroccan 20%/10%/exempt
line — the rectification's own tax summary would render completely empty**
until the draft was next saved through `update()` (which does populate it).
That silent, temporary disappearance of the tax breakdown on a freshly
created Moroccan rectification is exactly the "no rate may silently
disappear" failure mode the brief calls out (§8).

**Two considered fixes, one applied:**

- *Recompute from `$original->carts` via `DocumentCalculationService`*
  (mirroring what `duplicate()` already does) — **rejected.** Proven
  unsafe by writing the fix and running the existing VERI*FACTU
  regression suite: `VerifactuXmlBuilderTest`'s own rectification fixtures
  (`issuedInvoice()`) build invoices with stored totals but **no cart
  rows**, so recomputing from `$original->carts` would silently zero out
  the rectification's own tax data whenever the original's carts aren't
  the authoritative source — the exact "risks VERI*FACTU behavior" case
  the brief said to stop and document rather than risk. This was verified
  empirically (the fix was written, the regression tests were run, and
  `rectificativa_xml_includes_tipo_rectificativa_and_facturas_rectificadas`
  / `rectificativa_sustitucion_uses_s_code` failed with "no puede generar
  un registro de alta... la factura no tiene importe de IVA"), then
  reverted in favor of the option below.
- *Copy the original's own already-computed `invoice_tax_lines` rows
  verbatim onto the new rectification draft* — **applied.** Zero risk to
  VERI*FACTU: `vta4/vta10/vta21/sub_total/vta/total` are still copied
  exactly as before (untouched), so `VerifactuChainService::buildTaxBreakdown()`
  reads exactly the same inputs it always did. This only adds rows to a
  table VERI*FACTU never reads. Consistent with the "clone-then-edit"
  design already implied by copying every other field verbatim — a fresh,
  unedited rectification numerically mirrors its original until the user
  edits and saves it (at which point `InvoiceController::update()`'s
  existing `persistTaxBreakdown()` replaces these rows with a freshly
  computed set, exactly as for any other draft).

A second, smaller instance of the same bug was fixed alongside it: the
per-line `tax_treatment` (taxable/exempt/out_of_scope) also wasn't copied
onto the rectification's cart rows, so a rectified exempt line would
silently revert to the Cart column's `taxable` default. Fixed by adding
`'tax_treatment' => $cart->tax_treatment ?? TaxTreatment::TAXABLE` to the
line copy, mirroring `duplicate()`'s existing line-copy shape exactly.

Both fixes are contained entirely inside `InvoiceRectificationService::createRectification()`
— no constructor signature change (so the existing manual instantiations
in `InvoiceRectificationServiceTest` and `VerifactuXmlBuilderTest` needed
no changes), no migration, no change to what gets written to
`vta4/vta10/vta21/sub_total/vta/total`, no change to any R1–R5/rectificativa-vs-anulación
decision logic.

### 3.4 One incidental, pre-existing test bug fixed

`MoroccoProductExperienceTest::test_settings_save_returns_fresh_country_context_and_keeps_historical_invoice_data`
captured its "before" snapshot from the in-memory model returned by
`Invoice::create([...])` without refreshing it — `toArray()` on a
non-refreshed model only includes explicitly-set attributes, not every DB
column, so the later `assertSame()` against a fully-refreshed `Invoice::first()`
could never have passed, regardless of any actual historical-data
regression. Reproduced in complete isolation from this phase's changes
(fails identically on a clean checkout of the pre-existing, uncommitted
1C.2 work). One-line fix: `$invoice->fresh()->load(...)` instead of
`$invoice->load(...)`. Same category as the two incidental bugs Phase 1B
found and fixed (`docs/morocco-phase-1b-identity.md` §6) — unrelated to
Morocco specifically, fixed because it was blocking an honest "full suite
green" result.

## 4. Numbering audit (§4 of the brief — no bug found, no change made)

`InvoiceNumberingService` (three independent, gap-free, tenant-scoped
sequences: `invoice-draft`, `invoice-default`, `invoice-rectification`,
each reserved under a row-level lock inside a transaction):

- **Uniqueness**: enforced twice — the reservation itself is
  lock-serialized, and `invoices` has a DB-level unique constraint on
  `(invoice_series, invoice_number)` (`InvoiceLifecycleTest::database_rejects_a_duplicate_series_and_number_combination`,
  pre-existing, still green).
- **Sequential behavior**: confirmed gap-free from each series' own start
  (`draft_default_and_rectification_series_number_independently`).
- **Tenant isolation**: `invoice_number_sequences` is a per-tenant-database
  table (multi-tenant-by-database architecture) — no cross-tenant sequence
  sharing is possible by construction.
- **Duplicate invoice action**: `InvoiceController::duplicate()` assigns a
  new **draft** label (`nextDraftLabel()`), never a legal number — a
  duplicate is a new draft, not a re-issue.
- **Draft vs. issued**: a draft's `reference` is a provisional label from
  the draft series; the definitive legal number is assigned only inside
  `issueInvoice()`'s transaction, once, at the moment status flips to
  `issued`. Rectifications use the same pattern from their own separate
  series (`assignRectificationNumber`), per RD 1619/2012 art. 6.5 — this
  is Spanish law and doesn't apply to Morocco, but the mechanism itself
  (a distinct series for a distinct document class) is country-neutral and
  was left as-is.
- **Quote → invoice conversion**: `QuoteToInvoiceService::convert()` also
  assigns a draft label, never a legal number directly — the resulting
  invoice still goes through the normal issue step.

No numbering bug was found. **Whether Morocco has its own legal
sequencing/series requirements (analogous to RD 1619/2012 for Spain) is
still an open legal question, unchanged from Phase 1B's own deferred list**
— today's numbering is country-agnostic and works correctly for Morocco
as-is, but no one has verified it satisfies a Moroccan-specific legal rule,
because no such rule has been sourced yet.

## 5. Legacy PDF paths (§16 of the brief)

| View | Reachable? | Status |
|---|---|---|
| `resources/views/invoices/tachua.blade.php` | Yes — `InvoiceController::generateInvoicePdf()`, gated to `documentCountry === 'ES' && host === 'tachua.fakturalista.com'` | Reads `invoice->vta4/vta10/vta21/sub_total/total` **directly from the persisted columns**, never recomputes — by construction these are the exact same authoritative figures Phase 1C.1's bridge writes, so this view cannot disagree with the main template for the rates it displays (4/10/21%). Left as-is: it already consumes authoritative data, just narrowly (three Spanish buckets only, by design — it's a fixed legacy layout for one specific real Spanish tenant). |
| `resources/views/invoices/yassine.blade.php` | Yes — same controller, `documentCountry === 'ES' && host === 'client1s.fakturalista.test'` | Same situation — reads `sub_total`/`total` directly, no independent computation. **Noted but not fixed** (out of scope, cosmetic, pre-existing, Spain-only): the document header hardcodes the French word "Devis" (quote) even though this method only ever renders **invoices** — looks like a copy-paste artifact from an earlier version. Also hardcodes `€` and the developer's own personal bank details — both are fine exactly because this view is permanently ES-only and belongs to one specific real tenant. |
| `resources/views/invoices/show.blade.php` | **No** — grepped the entire `app/` and `routes/` trees; nothing references it | Confirmed dead code, unchanged (per the brief: do not delete blindly). |
| `resources/views/pdf/quote.blade.php` | **No** — `QuoteController::downloadPdf()`/`send()` both go through `QuotePdfService` → `TemplateRendererService`, confirmed by reading `QuotePdfService` directly | Confirmed dead code (matches Phase 1C.1's own audit finding), unchanged. |

**Moroccan isolation, verified at the code level, not just by absence of a
matching hostname**: both reachable legacy views require `documentCountry === 'ES'`
as their *first* condition, evaluated before the hostname check — a
Moroccan tenant's `company_snapshot['country_code']` (or live
`TenantContextService::country()` for a draft) is never `'ES'`, so the
branch is structurally unreachable regardless of which host the request
arrives on. Proven, not just read: the new
`moroccan_invoice_pdf_never_reaches_the_spain_only_legacy_views` test
fakes the exact two gating hostnames on a Moroccan tenant's request and
confirms `generateInvoicePdf()` still renders the main template both times.

**No PDF template produces conflicting totals from another** for any
currently-reachable path — the two legacy views and the main template all
ultimately read the same persisted columns (either `invoice_tax_lines` or
the legacy bridge columns Phase 1C.1 made authoritative), none of them
independently recomputes.

## 6. Spain / VERI*FACTU isolation (§13 of the brief — confirmed, not re-implemented)

- `RequireSpainCountry` middleware (`app/Http/Middleware/RequireSpainCountry.php`)
  already backend-gates every VERI*FACTU route to `TenantContextService::isSpain()`
  — a Moroccan tenant hitting a VERI*FACTU endpoint directly gets a 403,
  independent of any frontend hiding.
- Grepped every active PDF view, the invoice email template, and the two
  reachable legacy views for `AEAT`/`VERI*FACTU`/`Verifactu` — zero matches
  outside PHP comments. `note`/`descripcion_operacion` are pure
  user-supplied text on every write path (`store()`/`update()`/`duplicate()`/
  `createRectification()`) — nothing ever auto-injects Spanish legal
  boilerplate into either field, for any country.
- New regression test `moroccan_invoice_pdf_never_contains_verifactu_or_aeat_markers`
  renders every PDF component for an issued Moroccan invoice and asserts
  none of `VERI*FACTU`/`VERIFACTU`/`AEAT` appear anywhere in the output.
- Nothing under `app/Services/Verifactu/*` or `app/Models/Verifactu/*` was
  touched this phase.

## 7. Currency (§9 of the brief)

Grepped every active Vue form, every active Blade PDF component, and
`TemplateRendererService`/`CurrencyFormatter` for a hardcoded `€`/`EUR`.
The only literal `€` found is inside the two confirmed-dead views
(`invoices/show.blade.php`, `pdf/quote.blade.php`) and the two ES-only
gated legacy views (§5) — none reachable by a Moroccan tenant. Everywhere
else, currency flows from `TenantContextService::currency()` through
`CurrencyFormatter`, tenant-scoped, already correct since Phase 1A. No
global find-and-replace was performed; Spain's EUR is untouched.

## 8. Quotes (§15 of the brief)

`QuotePdfService` → `TemplateRendererService::render($quote, 'quote')` —
same country-aware label/currency/terminology logic as invoices, computed
live via `DocumentCalculationService` each time (quotes have no persisted
`invoice_tax_lines` table, unchanged from Phase 1C.1 — they're always
mutable drafts). New test `moroccan_quote_renders_mixed_taxable_and_exempt_lines_in_mad`
confirms a 20% + 10% + exempt quote renders `TVA 20%`, `TVA 10%`,
`Exonéré`, `Total TVA`, `Total TTC`, no `IVA`, and the correct MAD subtotal.
`QuoteToInvoiceService::convert()` already passes every rate/treatment
through the same calculator (Phase 1C.1 §7) — unchanged, still green
(`QuoteToInvoiceTaxTest`).

## 9. Validation (§18 of the brief — unchanged, already sufficient)

Obviously-invalid financial input (negative quantity, negative tax rate,
discount over 100%) is already rejected server-side by Phase 1C.1's
validation (`InvoiceTaxCalculationIntegrationTest` tests P, unchanged,
still green). No new validation was added — nothing in this phase's audit
found a gap here.

## 10. Files changed

- `resources/views/pdf/components/_totals.blade.php` — per-rate `Base
  TVA X%` row, Morocco-only.
- `resources/views/pdf/components/_footer.blade.php` — bank/payment info
  block, invoice-only, shown only when configured.
- `app/Services/InvoiceRectificationService.php` — copies the original's
  `invoice_tax_lines` and each cart line's `tax_treatment` onto a new
  rectification draft.
- `tests/Feature/MoroccoInvoiceReadinessTest.php` — new, 9 tests (§11
  below).
- `tests/Feature/MoroccoProductExperienceTest.php` — one-line, incidental
  pre-existing test fix (§3.4).
- This document.

## 11. Migrations

**None.** Every fix reuses existing columns/tables
(`company_profiles.bank_name/iban/swift`, `invoice_tax_lines`,
`carts.tax_treatment`) — nothing new was needed.

## 12. Tests

New file `tests/Feature/MoroccoInvoiceReadinessTest.php`, 9 tests, all
passing:

| Test | Covers (brief's checklist) |
|---|---|
| `bank_details_render_on_the_invoice_pdf_when_configured` | §11 |
| `bank_details_section_is_absent_when_not_configured` | §11 |
| `moroccan_tax_summary_shows_the_taxable_base_per_rate` | F, G, H (base display) |
| `spanish_invoice_totals_never_show_a_base_line` | R (Spain isolation) |
| `rectifying_a_mixed_rate_moroccan_invoice_preserves_every_rate` | §17 fix, H |
| `rectifying_an_invoice_with_an_exempt_line_preserves_the_exempt_treatment` | §17 fix, I, J |
| `moroccan_invoice_pdf_never_reaches_the_spain_only_legacy_views` | U, §13 |
| `moroccan_invoice_pdf_never_contains_verifactu_or_aeat_markers` | T |
| `moroccan_quote_renders_mixed_taxable_and_exempt_lines_in_mad` | P, J |

Everything else in the brief's A–U checklist was already covered by
existing, unmodified test files and re-verified green in the full run
below — not duplicated here: A/B/C (`MoroccoIdentityTest`), D/E
(`CountryTaxConfigurationTest`), K (`DocumentCalculationServiceTest`),
L/M/N (`InvoiceIdentitySnapshotTest`, `CountryTaxConfigurationTest`), O
(`CountryTaxConfigurationTest::test_issued_pdf_tax_identity_survives_a_later_country_and_default_change`),
Q (`QuoteToInvoiceTaxTest`), S (`VerifactuTaxEngineRegressionTest`,
`VerifactuXmlBuilderTest`, `VerifactuChainServiceTest`, etc.).

**Full backend suite**: `php artisan test --compact` → **265 passed, 0
failed, 968 assertions** (123s). Baseline before this phase was 264 tests
(248 after 1C.2 + `MoroccoProductExperienceTest`'s 8 not yet counted in
that doc, roughly); this phase added 9 and fixed 1 pre-existing failure,
net 265/265 green.

**Frontend tests**: `node --test tests/Frontend/tax.test.mjs` → **9
passed, 0 failed** (unchanged from 1C.2, re-run to confirm no regression).

**Frontend build**: `npm run build` → succeeded, same pre-existing
large-chunk warning as documented in 1C.2, no new warnings or errors. No
Vue/JS file was changed this phase, so this is a clean rebuild, not a
functional check of new frontend code.

## 13. Remaining legal/compliance questions (carried forward, none invented or resolved here)

- Whether ICE/IF/RC should ever be *mandatory* on a Moroccan invoice — still
  unverified against a primary Moroccan legal source (Phase 1B's own
  position, unchanged).
- Whether Morocco has its own legal invoice-sequencing/series rules — still
  unverified (§4 above).
- Detailed exemption legal references (which Moroccan article/reason
  justifies a given `Exonéré` line) — deliberately still just a neutral
  label, per the brief's explicit instruction not to invent one.
- Whether bank/payment info should be frozen per-invoice like seller
  identity is, or intentionally always reflect the current account (§3.2)
  — a product decision, not a technical one; currently implemented as
  "always current."
- DGI e-invoicing, UBL, QR codes, electronic signatures, VAT withholding —
  all still entirely out of scope, nothing scaffolded.

## 14. Blockers before the next Morocco phase

None identified. The invoice/quote pipeline is structurally consistent for
Morocco: identity, tax presentation (including the previously-missing
base-per-rate line and bank info), numbering, snapshots, and the
rectification path all now converge on the same authoritative data with no
observed path that can silently drop a rate or show conflicting totals.

Work stops at Phase 1C.3.
