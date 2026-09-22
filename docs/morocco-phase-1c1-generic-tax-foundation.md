# Morocco Phase 1C.1 — Generic server-side tax calculation foundation

**Status: implemented, tested.** Scope: exactly what the phase name says — a generic, authoritative, server-side calculation foundation. No Moroccan tax preset, no Moroccan-rate UI, no exemption/withholding rules, no DGI, no `TaxRate` catalog table, and no removal of `vta4`/`vta10`/`vta21` were introduced. Nothing under `app/Services/Verifactu/*` was touched.

---

## 1. Architecture

New namespace: `App\Services\Tax`.

- **`DocumentCalculationService`** — the single, authoritative calculator. `InvoiceController`, `QuoteController`, and `QuoteToInvoiceService` all call the *same* instance type via constructor injection — there is exactly one calculation formula and one rounding policy in the application, not one per document type or per layer.
- **`DocumentCalculationResult`** — a plain, country-neutral value object: `lines[]`, `subTotal`, `discountAmount`, `taxBreakdown[]` (`{rate, taxable_base, tax_amount}`), `totalTax`, `grandTotal`, plus a `legacySpanishRateAmounts()` bridge method (see §5). Nothing in either class is named after a country or a specific tax (no `iva21`/`tva20`/`morocco_tax`) — a rate is just a number.
- **`InvoiceTaxLine`** (model) + `invoice_tax_lines` (migration) — the persisted, generic, invoice-level tax breakdown (see §4).

The calculator's public surface is one method:

```php
DocumentCalculationService::calculate(array $lines, float $headerDiscountRate = 0.0): DocumentCalculationResult
```

where each line is `['quantity' => ..., 'unit_price' => ..., 'discount' => ..., 'tax_rate' => ...]`. No rate is ever whitelisted, hardcoded, or specially handled — 0, 4, 10, 20, 21, or any other non-negative value all flow through identically.

## 2. Calculation formula

Per line, in order (each step rounded once — see §3):

```
gross            = round(quantity × unit_price)
discount_amount  = round(gross × line_discount% / 100)
taxable_base     = round(gross − discount_amount)
```

Lines are grouped by rate; each rate's raw base is the exact sum of its already-rounded per-line bases. The whole-document (header) discount is then applied **proportionally to each rate's own base** before computing that rate's tax — algebraically identical to reducing the tax afterward (multiplication is commutative), but expressed this way so `taxBreakdown`'s own `taxable_base` is *literally* the number `tax_amount` was computed from (`taxable_base × rate / 100 == tax_amount`, exactly, for every row):

```
subtotal               = round(Σ raw_base[rate])                         — shown pre-header-discount, matching the existing UI convention
header_discount_amount = round(subtotal × header_discount% / 100)
adjusted_base[rate]    = round(raw_base[rate] × (1 − header_discount%/100))
tax_amount[rate]       = round(adjusted_base[rate] × rate / 100)
total_tax              = Σ tax_amount[rate]
grand_total            = round(subtotal − header_discount_amount + total_tax)
```

This exactly reproduces the *intended* semantics already implicit in the pre-existing frontend/`QuoteToInvoiceService` formulas (subtotal shown without the header discount baked in, discount shown as its own line, tax reduced proportionally by the header discount) — it does not change what a normal Spanish invoice's numbers *mean*, only makes them consistently, verifiably correct everywhere.

## 3. Rounding policy — the ONE policy, used everywhere

- **Precision**: 2 decimal places, always.
- **Mode**: round-half-up, implemented with **bcmath on decimal strings**, never native float `round()`. This matters concretely: PHP's native `round(2.675, 2)` returns `2.67` (binary floating-point can't represent `2.675` exactly), not the mathematically correct `2.68` — exactly the class of bug the Phase 1C audit traced some of the rounding inconsistencies to. `DocumentCalculationService::round()` operates on the exact decimal string and gets this right (verified by test J: `round(2.675) === 2.68`).
- **Where rounding happens — exactly three points, never more, never fewer**: (1) per line (gross → discount → taxable base, each an exact subtraction of two already-rounded numbers), (2) per rate bucket (adjusted base, tax amount), (3) document totals (subtotal, header discount amount, grand total — these are sums of already-rounded components, so this step is mostly a safety net, not a source of new rounding).
- **Every consumer uses this exact policy** — invoice creation/update, quote creation/update, quote→invoice conversion, and PDF rendering (via the persisted breakdown, see §6) all derive from the same `DocumentCalculationResult`. Nothing recalculates independently anymore.

## 4. Generic tax breakdown — persistence design

Per the Phase 1C audit's "Option B" recommendation (least disruptive, builds on the existing free-form-rate-per-line grain rather than introducing a rate catalog table):

**New table: `invoice_tax_lines`** (migration `2026_09_24_100000_create_invoice_tax_lines_table.php`) — `id`, `invoice_id` (FK, cascade delete), `rate` (`decimal(7,3)`), `taxable_base` (`decimal(15,2)`), `tax_amount` (`decimal(15,2)`), timestamps. One row per distinct rate actually used on that invoice.

```json
[
  {"rate": 10, "taxable_base": 1000, "tax_amount": 100},
  {"rate": 20, "taxable_base": 2000, "tax_amount": 400}
]
```

Deliberately **not** shaped after Spain/AEAT — no `impuesto`/`clave_regimen`/`calificacion_operacion` columns like the existing, VERI\*FACTU-only `verifactu_record_tax_details` table has (see §8). `rate`/`taxable_base`/`tax_amount` mean the same thing for any country.

**Written by**: `InvoiceController::persistTaxBreakdown()` (called from `store()`/`update()`/`duplicate()`) and `QuoteToInvoiceService::convert()`. **Never written for a locked invoice** — by the time an invoice is `issued`/`paid`/`cancelled`, `InvoiceController::update()` already refuses the request outright (pre-existing `isLocked()` guard, untouched), so nothing ever calls the write path again. This is what makes an issued invoice's breakdown immutable in practice, without needing a separate "snapshot at issuance" step.

**Quotes do not get this table.** A quote is always a mutable draft; its PDF already recomputes live from cart lines (unchanged, rate-agnostic, see §6), and nothing downstream ever reads a quote's per-rate breakdown. Quotes *do* use the same `DocumentCalculationService` for their aggregate `sub_total`/`discount_amount`/`vta`/`total` (§10) — only the persisted per-rate table is invoice-specific.

## 5. Legacy `vta4`/`vta10`/`vta21` bridge

**Not removed, not renamed, not reinterpreted.** `DocumentCalculationResult::legacySpanishRateAmounts()`:

```php
public function legacySpanishRateAmounts(): array
{
    $byRate = [];
    foreach ($this->taxBreakdown as $row) {
        $byRate[(int) round($row['rate'])] = $row['tax_amount'];
    }
    return ['vta4' => $byRate[4] ?? 0.0, 'vta10' => $byRate[10] ?? 0.0, 'vta21' => $byRate[21] ?? 0.0];
}
```

Every write path (`InvoiceController::store/update/duplicate`, `QuoteToInvoiceService::convert`) calls this and writes the result into `invoices.vta4/vta10/vta21` exactly as before — a rate of 4/10/21 bridges into its legacy column; any other rate (0%, or a future Moroccan rate) simply has no legacy column to bridge into, but is **never lost** — it remains fully represented in `invoice_tax_lines` and in the aggregate `invoices.vta` (`= totalTax`, the sum across *every* rate, not just the three legacy ones). This is the direct fix for the Phase 1C audit's flagged data-loss bug (§7).

## 6. How the PDF discount/rounding bug was fixed

**Root cause (from the audit)**: `TemplateRendererService::render()` independently recomputed `$taxGroups` from raw `qty × price`, applying **no discount at all** (neither line nor header), and rounding per line before summing — a different formula, with different inputs, than whatever produced the stored `invoices.vta4/vta10/vta21` (which *did* apply discounts). For any discounted invoice, the PDF's displayed tax and the AEAT-reported tax could already disagree, before this phase.

**Fix**: `TemplateRendererService::render()` now prefers the **already-persisted, authoritative** `invoice_tax_lines` for an invoice:

```php
if ($docType === 'invoice' && $document->relationLoaded('taxLines') && $document->taxLines->isNotEmpty()) {
    // build $taxGroups from the persisted rows - no independent recomputation
}
// else: unchanged, pre-existing cart-based computation (fallback)
```

For an invoice with `invoice_tax_lines` rows, the PDF is now reading the *exact same numbers* `DocumentCalculationService` computed and that were written to `vta4/vta10/vta21` — by construction, not by coincidence, they cannot disagree anymore. Test N proves this directly (a discounted 21% line: stored `taxable_base`/`tax_amount` reflect the discount correctly, and the PDF renders from that same data without crashing).

**The fallback (unchanged, pre-existing cart-based computation) is preserved exactly as it was**, for: quotes (always — no persisted breakdown exists for them), and any invoice with no `invoice_tax_lines` rows at all — i.e., one issued before this phase existed. This is the same "snapshot-first, live-fallback" pattern already used for company/customer identity in Phase 1B, reused rather than reinvented.

## 7. How the Quote→Invoice tax-loss bug was fixed

**Root cause (from the audit)**: `QuoteToInvoiceService::convert()`'s `calcVta(int $rate)` closure filtered cart lines with `(int) $c->vta === $rate`, called only for `$rate` in `{4, 10, 21}`. Any line at any other rate (0%, or a future Moroccan rate) was silently excluded from all three buckets — no exception, no log, no fourth bucket.

**Fix**: `convert()` now builds a plain line array from the quote's cart lines (verbatim, no rate filtering) and calls `DocumentCalculationService::calculate()` once. Every rate present survives into `taxBreakdown`/`invoice_tax_lines`; the legacy bridge (§5) captures whichever of 4/10/21 happen to be present, and the aggregate `vta` always includes every rate's tax regardless. Test L creates a quote with a 20% line and proves it survives conversion intact (`InvoiceTaxLine` row present, correct base/amount, non-zero aggregate `vta`) instead of vanishing.

## 8. VERI\*FACTU compatibility — verified, not just asserted

**Nothing under `app/Services/Verifactu/*` or `app/Models/Verifactu/*` was touched.** The exact compatibility boundary documented in the Phase 1C audit was: *`VerifactuChainService::buildTaxBreakdown()` must keep receiving `invoice->vta4/vta10/vta21/vta/total/sub_total` with the same values/semantics it reads today.* This phase satisfies that boundary by construction (§5's bridge writes exactly those columns, with the same meaning: a currency amount, not a rate), and it's verified empirically:

- **Test Q**: an invoice built by `DocumentCalculationService` with 21%/10%/4% lines, run through the real, unmodified `VerifactuChainService::recordAlta()` + `VerifactuXmlBuilder::build()`, produces XML with exactly the expected `TipoImpositivo`/`BaseImponibleOimporteNoSujeto`/`CuotaRepercutida` values for all three rates.
- **Test R**: an invoice with a 21% line *and* a 20% (Moroccan-style, legacy-unrepresentable) line correctly makes `recordAlta()` **throw** — `buildTaxBreakdown()`'s own pre-existing reconciliation check (`base derived from vta4/10/21 ≠ total − vta`) catches the gap and refuses, exactly as it already does today for any exempt/0%/unsupported-rate line. This is the proof that a future/Moroccan rate cannot silently reach AEAT mischaracterized as something else — it fails loudly and safely instead, using a safety net that already existed, not a new one added for Morocco.

The full pre-existing VERI\*FACTU test suite (`VerifactuChainServiceTest`, `VerifactuXmlBuilderTest`, `VerifactuCertificateTest`, `SendVerifactuRecordToAeatJobTest`, `VerifactuAeatTestCommandTest`, `VerifactuCountryGateTest`) is unmodified and still green.

## 9. Historical invoice strategy

**No backfill, no recalculation, ever, for an existing issued invoice.** Three independent guarantees combine to make this true, not just documented intent:

1. `invoice_tax_lines` is purely additive (§4) — an invoice issued before this migration simply has zero rows there, forever, unless something explicitly writes to it.
2. The only code that ever writes `invoice_tax_lines` or recomputes `sub_total`/`vta`/`vta4/10/21`/`total` is `InvoiceController::store()`/`update()`/`duplicate()` and `QuoteToInvoiceService::convert()` — and `update()` already refuses to run at all once `$invoice->isLocked()` (issued/paid/cancelled), a pre-existing guard this phase didn't touch.
3. `issueInvoice()` itself (the method that transitions draft → issued) was **not modified** in this phase — it only assigns legal numbering and flips status; it has never called any calculation logic, so issuance itself cannot trigger a recalculation either.

Test S proves this directly: an invoice created with deliberately "wrong" (i.e., not what the new engine would compute) historical values and no `invoice_tax_lines` rows, then issued and locked — attempting to edit it is refused (unchanged 403), its stored values remain exactly as originally set, it gains no `invoice_tax_lines` rows, and its PDF still renders correctly via the unchanged fallback path.

## 10. Quotes

`QuoteController::store()`/`update()` now call the same `DocumentCalculationService` for `sub_total`/`discount_amount`/`vta`/`total` — no second tax formula exists for quotes. One care point found and fixed during implementation: `update()`'s pre-existing behavior leaves a quote's cart lines untouched when none are submitted (`if (count($carts) > 0)` guard, unchanged) — recalculating from an *empty* submitted array in that case would have wrongly zeroed the quote's totals while its real lines stayed intact in the database. Fixed by recomputing from the quote's *existing* persisted cart lines whenever none are submitted in the request, so totals are always consistent with whatever lines actually exist afterward.

`QuoteToInvoiceService::convert()` uses the identical service (§7). Tests L/M cover quote → persisted calculation → convert-to-invoice → verify financial values remain consistent (test M: a 3-line, 3-rate quote's `sub_total`/`vta`/`total` reproduce exactly on the resulting invoice).

## 11. Frontend

**No UI redesign.** The existing Spanish 0/4/10/21% dropdowns, the six duplicated JS calculation copies, and the Vue forms themselves are all untouched — that's explicitly deferred. What changed is purely about *authority*: the frontend's own computed totals are now, in effect, a preview only, since the backend recomputes and persists its own authoritative figures regardless of what was submitted (test O proves a client-submitted lie is ignored).

`store()`/`update()`'s JSON responses now additionally include the recalculated `invoice` block (`sub_total`, `discount_amount`, `vta`, `vta4/10/21`, `total`, `tax_breakdown`) alongside the existing `message` key — purely additive, since the current Vue forms only read `message` today. No Vue component was changed to consume this; it's available for a future phase. In practice, the existing "save → navigate away → the invoice detail view re-fetches via `show()`/`edit()`" flow already displays the new authoritative values correctly without any frontend change at all, since `show()`/`edit()` read the same `sub_total`/`vta`/`total` columns this phase now populates authoritatively.

## 12. Migrations

One, additive, reversible: `database/migrations/tenant/2026_09_24_100000_create_invoice_tax_lines_table.php`. No existing column was dropped, renamed, or backfilled.

## 13. Tests

21 new tests across 4 files, covering the full A–S checklist:

| File | Tests | Covers |
|---|---|---|
| `tests/Feature/DocumentCalculationServiceTest.php` | 11 | A, B, C, D, E, F, G, H, I, J, K |
| `tests/Feature/InvoiceTaxCalculationIntegrationTest.php` | 6 | O, P (×3), N, S |
| `tests/Feature/QuoteToInvoiceTaxTest.php` | 2 | L, M |
| `tests/Feature/VerifactuTaxEngineRegressionTest.php` | 2 | Q, R |

### Full test suite result

```
Tests:    223 passed (611 assertions)
Duration: 98.86s
```

202 pre-existing + 21 new, **0 failed, 0 regressions**.

## 14. Remaining limitations (nothing here blocks 1C.2, all explicitly deferred)

- `InvoiceRectificationService::createRectification()` was **not** touched — it still copies `vta4/vta10/vta21` verbatim from the original invoice and does not create `invoice_tax_lines` rows for the new rectification draft. A rectification's PDF will use the unchanged cart-based fallback (§6) until it's next saved through `InvoiceController::update()`, which *will* populate it correctly. Not a regression (rectifications never had a persisted breakdown before this phase either), just not yet upgraded to the new path.
- The six duplicated frontend calculation copies (audit §5) are untouched, per explicit scope — they remain previews only now, but still duplicated code, still hardcoded to 0/4/10/21%.
- `pdf/quote.blade.php` (confirmed dead/unrouted in the Phase 1C audit) and the legacy per-tenant `invoices/tachua.blade.php`/`invoices/show.blade.php` views were not touched — they still hardcode exactly 3 Spanish columns read directly from `invoices.vta4/vta10/vta21`, bypassing `TemplateRendererService` entirely. Out of scope for this phase (no PDF redesign requested), and `vta4/vta10/vta21` are still correctly populated for them regardless.
- No numeric validation was added for `sub_total`/`vta`/`vta4/10/21`/`total` themselves in `InvoiceRequest`/`QuoteRequest` — they're simply no longer read for calculation purposes, so their value doesn't matter (test O proves this), but they remain present in the request payload for now (frontend compatibility, §11).
- Reports (`StatsController`, `InvoicePaymentsController`) were confirmed by the audit to be fully rate-agnostic already (`total`/`sub_total` only) and were not touched — no work was needed there.
