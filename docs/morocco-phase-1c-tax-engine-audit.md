# Morocco Phase 1C — Tax Engine Audit (audit only, no implementation)

**Status: audit only.** No code, migration, or config was changed while producing this document. Nothing here implements Moroccan tax rates, DGI, withholding, or a tax-engine refactor — this is exclusively the map required before any of that can be safely designed.

**Method note.** Everywhere this document describes current behavior, it is backed by direct code reads (file:line citations throughout) — not memory or assumption. Everywhere it proposes something for the future, it is explicitly labeled a proposal, and alternatives are evaluated rather than a single design being asserted as "the" answer, per your explicit instruction.

---

## 1. Current tax schema — every tax-related column, table by table

### `invoices` (tenant DB)

| Column | Type | Default | Introduced | Represents |
|---|---|---|---|---|
| `sub_total` | `double` (no fixed scale) | nullable | `2023_09_07_113049_create_invoices_table.php:23` | Taxable base **amount**, pre-tax |
| `discount_rate` | `double` | nullable | same file `:24` | Header-level discount, a **percentage** |
| `discount_amount` | `double` | nullable | same file `:25` | Header-level discount, an **amount** |
| `vta` | `double` | nullable | same file `:26` | **Total VAT amount** across the whole invoice (a cuota in currency units, not a rate) |
| `total` | `double` | nullable | same file `:27` | Grand total **amount** |
| `vta4` | `decimal(15,2)` | `0` | `2025_11_06_181650_add_vta4_10_21_to_invoices.php:15` | VAT **amount** (cuota) attributable to the 4% bucket |
| `vta10` | `decimal(15,2)` | `0` | same file `:16` | VAT amount attributable to the 10% bucket |
| `vta21` | `decimal(15,2)` | `0` | same file `:17` | VAT amount attributable to the 21% bucket |

**Type inconsistency, already present today, independent of Morocco:** `sub_total`/`vta`/`total` are native `double` (binary float, no exact decimal guarantee); `vta4`/`vta10`/`vta21` are fixed-point `decimal(15,2)`. `Invoice::$casts` (`app/Models/Invoice.php:87-94`) casts none of these seven columns, so Eloquent returns the `decimal` ones as PHP strings (via PDO) and the `double` ones as PHP floats — a generic engine must normalize this, not inherit it.

Non-tax columns added later (for completeness): lifecycle (`issued_at`, `source_invoice_id`), Stripe fields, legal numbering/rectification fields, and the Phase 1B identity snapshots (`company_snapshot`, `customer_snapshot`, JSON, nullable) — these are relevant to §9/§10 as a precedent (see below), not tax data themselves.

### `carts` (polymorphic line items, shared by invoices and quotes)

| Column | Type | Represents |
|---|---|---|
| `qty` | `double` | Quantity (fractional allowed, e.g. `1.5`) |
| `price` | `double` | Unit price, pre-tax |
| `discount` | `double` | **Line-level percentage rate** (not an amount) — confirmed from the frontend formula that populates it |
| `total` | `double` | The line's taxable base **amount**, already net of the line discount (`qty*price - qty*price*discount/100`) |
| `vta` | `double` | The line's tax **rate as a plain number** (e.g. `21` meaning 21%), *not* an amount |

This is the single most important existing fact for the redesign: **`Cart.vta` already stores a free-form numeric rate per line**, not a foreign key to anything, and nothing in the schema restricts it to `{4,10,21}` — the restriction to exactly those three values exists only in application code (frontend dropdowns, `QuoteToInvoiceService`, `VerifactuChainService`), never in the database.

### `items`

| Column | Type | Represents |
|---|---|---|
| `sales_price` | `double` | Catalog unit price, pre-tax |
| `vta` | `decimal(5,2)`, default `0` | The item's **default tax rate** (a plain percentage number), added only in `2026_07_23_210001_add_pricing_status_fields_to_items_table.php:15` — the original 2023 `items` table had no tax-rate column at all |
| `currency` | `string(3)` | Item's own currency (unrelated to VAT, flagged for completeness) |

No accessor/mutator, no relationship to any "TaxRate" concept, and (per Explore agent confirmation) no seeder/factory sets a value for it — a bare free-form decimal.

### `quotes`

| Column | Type | Represents |
|---|---|---|
| `sub_total`, `discount_rate`, `discount_amount`, `vta`, `total` | same types as `invoices`' equivalents | Same meanings |

**Quotes never received `vta4`/`vta10`/`vta21` columns at all** — no migration ever added them (confirmed: no migration touches `quotes` after `2026_07_03_000001_add_invoice_id_to_quotes_table.php`). A quote only ever has one aggregate `vta` amount; the per-rate breakdown is invented on the fly, only at Quote→Invoice conversion time, purely from that quote's `carts` (see §3).

### `verifactu_records` / `verifactu_record_tax_details` (Spain/AEAT-only, untouched by this audit's proposals — see §8)

`verifactu_record_tax_details` is the **one table in the whole schema that already separates rate, base, and amount into three distinct columns** per tax bucket: `tipo_impositivo` (`decimal(5,2)`, the rate), `base_imponible` (`decimal(15,2)`, the taxable base), `cuota_repercutida` (`decimal(15,2)`, the tax amount) — plus `impuesto`/`clave_regimen`/`calificacion_operacion`/`operacion_exenta` (AEAT code-list values). This shape is directly relevant to §10's generic model proposal, but it is hard-wired to VERI*FACTU today (FK to `verifactu_records.id`, not `invoices.id`; AEAT-specific column defaults) and is populated only when a VERI*FACTU alta record is generated — see §8.

### `payments` (central DB, not tenant — different concern entirely)

No tax column of any kind. This table records the **tenant's own SaaS subscription billing to Fakturalista** (Stripe/PayPal), not a tenant's invoice/VAT data — must not be conflated with invoice tax architecture.

---

## 2. Invoice line calculation flow

**Finding, independent of Morocco: the backend is not authoritative for tax math today.** `app/Models/Invoice.php` and `app/Models/Cart.php` contain zero arithmetic — no accessors, no computed attributes. `InvoiceController::store()` (`:113-160`), `::update()` (`:212-242`), and `::duplicate()` (`:401-458`) all take `sub_total`/`discount_rate`/`discount_amount`/`vta`/`vta4`/`vta10`/`vta21`/`total` **verbatim from the request payload** and write them straight into the database — no server-side recomputation from the submitted cart lines, ever. The private `syncCarts()` helper (`:795-815`) does the same for each cart line's `total` (the taxable base) — it is never derived from `qty * price * (1 - discount/100)` server-side, only trusted from the client.

`InvoiceRequest` (`app/Http/Requests/InvoiceRequest.php:22-34`) validates only `customer_id`, `date`, `expiration_date`, and cart presence — **no `numeric`, `min:0`, or cross-field consistency rule exists** on any of the money/tax fields, on either the invoice or its cart lines.

**No `round()` call exists anywhere in `Invoice.php`, `Cart.php`, or `InvoiceController.php`** — all rounding happens client-side, before the request is ever sent (see §5). The only place invoice totals are ever independently reconstructed/validated is `VerifactuChainService::buildTaxBreakdown()` — and that only runs when a VERI*FACTU alta record is generated (not wired into live issuance), throws rather than corrects, and only for Spanish tenants.

**Calculation chain today (client-side, see §5 for exact code):** `qty × price` → subtract line `discount%` → line taxable base (`cart.total`) → per-rate tax (`cart.vta% × cart.total`, but computed by two independently-duplicated formulas within the same form, see §5's reconciliation-risk finding) → subtract header `discount_rate%` → grand total.

## 3. Quotes

**Duplicated, not shared, and structurally poorer than invoices** — `QuoteController::store()`/`update()` re-implement the identical verbatim-pass-through pattern independently from `InvoiceController` (own copy of the same non-validation, own copy of the same trust-the-client model). `QuoteRequest` has the same absence of numeric validation as `InvoiceRequest`.

The **one** place server-side PHP actually derives a per-rate VAT breakdown is `QuoteToInvoiceService::convert()` (`app/Services/QuoteToInvoiceService.php:41-50`):

```php
$discountRate = (float) ($quote->discount_rate ?? 0);

$calcVta = function (int $rate) use ($quote, $discountRate): float {
    $raw = $quote->carts
        ->filter(fn ($c) => (int) $c->vta === $rate)
        ->sum(fn ($c) => ($c->total * $rate) / 100);

    return round($raw - ($raw * $discountRate / 100), 2);
};
```
invoked at `:65-67` for exactly `$calcVta(4)`, `$calcVta(10)`, `$calcVta(21)`.

**What happens to a cart line at any other rate (0%, a Moroccan 7/14/20% rate, or a non-integer-truncating value):** `(int) $c->vta === $rate` is an exact match against `{4, 10, 21}`. Any line outside that set is **silently excluded from all three buckets** — no exception, no log, no fourth "other" bucket. The line's taxable base still flows into the new invoice's cart rows and the quote's own raw `vta`/`total` are still copied across, so the grand total may still look right, but the per-rate breakdown — the only place the app records "how much tax at what rate" — silently loses that line's tax. **This is the exact mechanism a Moroccan TVA rate would hit today, unchanged.**

## 4. Items/Services

`app/Models/Item.php` has no hardcoded rate list, no accessor, no relationship to a tax-rate concept — `vta` is a bare `decimal(5,2)` column, unconstrained at the model layer, and `ItemController` accepts any value for it (`'vta' => $request->vta ?? 0`, no `in:4,10,21` rule). **The hardcoding of `{4,10,21}` lives entirely in application/UI code downstream** (the item's own rate picker in the Vue form, `QuoteToInvoiceService`, `VerifactuChainService`) — not in the Item model or its migrations. An item can already technically be given a value like `7` or `20`; nothing downstream currently knows what to do with it correctly.

## 5. Frontend

**Every tax-rate picker in the app is hardcoded to exactly `{0, 4, 10, 21}`**, in six places (four live, two dead/unrouted):

- `resources/js/views/admin/invoices/CreateInvoiceForm.vue:225-230` and `EditInvoiceForm.vue:200-205` — cart-line rate `<select>`.
- `resources/js/views/admin/quotes/CreateQuoteForm.vue:161-167` and `EditQuoteForm.vue:179-185` — same.
- `resources/js/views/admin/items/create.vue:194-213` (radio chips) and `items/edit.vue:115-124` (`<select>`) — item default-rate picker.
- (Dead, unrouted, still shipped in the bundle) `resources/js/views/admin/layouts/documents/create.vue:199-201` and `.../edit.vue:200-202`.

No country/tenant branching exists anywhere in these pickers — a Moroccan tenant sees the identical Spanish IVA bracket set today.

**Hardcoded raw "IVA" text (not `$t()`-wrapped)**: `EditInvoiceForm.vue:137,272,278,284` and `EditQuoteForm.vue:121,234,240,246` (`IVA %` column header, `IVA 4%`/`IVA 10%`/`IVA 21%` totals labels). By contrast, `CreateInvoiceForm.vue` and `CreateQuoteForm.vue` already route the tax label through `$t('documents.taxLabel', { rate })`, which resolves per-locale (`"IVA(:rate%)"` / `"VAT(:rate%)"` / `"TVA(:rate%)"`) — i.e. the **Create** forms are already i18n-clean on the label text; the **Edit** forms are not (a pre-existing regression, not something this audit needs to fix, but relevant since a future generic engine's UI work would need to fix the Edit forms' i18n too if it touches these forms at all).

**Calculation logic is duplicated six times, not shared via a composable.** `CreateInvoiceForm.vue`, `EditInvoiceForm.vue`, `CreateQuoteForm.vue`, `EditQuoteForm.vue`, and the two dead `documents/{create,edit}.vue` files each define their own verbatim copy of `totalRow` / `subTotal` / `vtaTotal` / `calcVta` / `vtaTotal4|10|21` / `discountTotal` / `total` as local computeds. A comment in `CreateInvoiceForm.vue:456` even says *"same logic as CreateDocument"*, confirming the duplication was carried forward rather than factored out.

**A genuine, pre-existing internal-consistency bug, found by this audit, independent of Morocco:** within the *same* form, `vtaTotal` (→ `state.vta`, sent to the backend) and `vtaTotal4+vtaTotal10+vtaTotal21` (→ `state.vta4/10/21`) are computed by two **different, independently-rounded formulas**:

```js
// vtaTotal - uses the already-rounded cart.total, itself unrounded:
const vtaTotal = computed(() => {
  const vta = state.carts.reduce((acc, cart) => acc + (cart.vta * cart.total) / 100, 0);
  return vta - (vta * state.discount_rate) / 100;   // <- no .toFixed()
});

// calcVta(rate) - recomputes qty*price-discount% FROM SCRATCH, rounds per bucket:
const calcVta = (rate) => {
  const raw = state.carts.reduce((acc, cart) => {
    if (Number(cart.vta) === rate) {
      const row = Number(cart.qty * cart.price) - (Number(cart.qty * cart.price) * cart.discount) / 100;
      return acc + (row * rate) / 100;
    }
    return acc;
  }, 0);
  const final = raw - (raw * Number(state.discount_rate || 0)) / 100;
  return Number(final.toFixed(2));                  // <- rounded
};
```
There is no guarantee `vtaTotal.value === vtaTotal4.value + vtaTotal10.value + vtaTotal21.value` in cents, on the same invoice, today, for any tenant, Spanish or otherwise — this is a pre-existing rounding-methodology divergence, not something introduced by a future generic engine, and worth fixing regardless of the Morocco work (see §13).

**Rounding summary (client-side):** per-line total: rounded (`.toFixed(2)`). Subtotal: rounded. Aggregate `vtaTotal`/`discountTotal`: **not rounded**, sent raw. Per-rate `vtaTotal4/10/21`: rounded per bucket. Grand total: rounded. The display helper `$toComma`/`$toCurrency` (`resources/js/main.js:86-92`) **truncates rather than rounds** (`d.slice(0, decimals)`) — display-only, doesn't feed back into submitted values, but means on-screen cents can visibly differ from what gets saved.

## 6. PDF

`app/Services/Pdf/TemplateRendererService.php:104-113` computes `$taxGroups` **dynamically, keyed by whatever integer rate is found on each cart line** — genuinely rate-agnostic, no hardcoded 4/10/21 anywhere in this method:

```php
$taxGroups = [];
foreach (($document->carts ?? []) as $cart) {
    $rate = (int) ($cart->vta ?? 0);
    if ($rate <= 0) { continue; }
    $lineBase         = (float) $cart->qty * (float) $cart->price;
    $taxGroups[$rate] = ($taxGroups[$rate] ?? 0) + round($lineBase * $rate / 100, 2);
}
ksort($taxGroups);
```
`_totals.blade.php:8-38`'s `@foreach($taxGroups as $rate => $taxAmount)` loop renders however many distinct rates are found — no assumption of exactly 3. `_items.blade.php:44` prints `{{ $cart->vta ?? 0 }}%` per line, also rate-agnostic. **This layer needs no rate-related changes for Morocco.**

**A second genuine, pre-existing bug, found by this audit, independent of Morocco:** this PDF computation uses **raw `qty * price`, applying neither the line-level `discount` nor the invoice-level `discount_rate`** — unlike every other place in the codebase that computes per-rate tax (`calcVta()` client-side, `QuoteToInvoiceService::calcVta()` server-side), both of which apply the line discount (via `cart.total`, already discount-adjusted) and the header `discount_rate`. It also **rounds per line before summing**, whereas `QuoteToInvoiceService` sums raw per-line amounts across a rate bucket and rounds once at the end. **Consequence: for any invoice with a line-level or header-level discount, the PDF's displayed per-rate VAT will not equal the stored `invoices.vta4/vta10/vta21` — and therefore will not equal what VERI*FACTU reported to AEAT for that same invoice, today, before any tax-engine change.** This is a real, currently-live discrepancy risk for Spanish tenants with discounted invoices and should be flagged as a bug to fix (see §13), separate from the Morocco redesign.

**Legacy/dead views** (`resources/views/invoices/tachua.blade.php:303-314`, still live for one specific hostname; `resources/views/invoices/show.blade.php:302-317`, confirmed dead/unrouted) both hardcode exactly the 3 Spanish columns, reading `invoice->vta4/vta10/vta21` directly rather than via any cart-derived structure — both would need rewriting or retiring under a generic model.

## 7. Reports

**Fully rate-agnostic already — zero changes needed.** `StatsController` (`counts()`, `cashOverview()`) uses only `Invoice::count()` and `SUM(total)`. `InvoicePaymentsController::summary()`/`index()`/`payable()` use only `invoices.total`/`status`/dates. `PaymentController` uses only `invoices.total` (for the Stripe line item amount). A repo-wide grep confirms `vta4`/`vta10`/`vta21` are referenced **only** in the invoice/quote write path (`InvoiceController`, `QuoteToInvoiceService`, `InvoiceRectificationService`) and in `VerifactuChainService` — never in any reporting/stats/payments code. None of those files need to change under a generic tax model.

## 8. VERI*FACTU — dependency map and compatibility boundary

**This is the highest-stakes constraint in the whole audit.** `VerifactuChainService.php:42-62` hardcodes:

```php
private const VAT_RATES = [4 => 'vta4', 10 => 'vta10', 21 => 'vta21'];
private const IMPUESTO_IVA = '01';
private const CLAVE_REGIMEN_GENERAL = '01';
private const CALIFICACION_SUJETA_NO_EXENTA_SIN_INVERSION = 'S1';
```
and `buildTaxBreakdown()` (`:354-427`) reads `$invoice->{$column}` for each of the 3 fixed columns directly, deriving the taxable base algebraically (`base = cuota / (rate/100)`) rather than from cart lines, then reconciling the sum against `invoice->total - invoice->vta` (0.02 tolerance) and throwing `\RuntimeException` if either no rate has an amount, or the reconciliation fails.

`VerifactuXmlBuilder::buildDesglose()` (`:201-234`) never reads `Invoice` at all — it reads only the already-persisted `VerifactuRecordTaxDetail` rows that `buildTaxBreakdown()`'s output populated, and formats them into `DetalleDesglose`/`ClaveRegimen`/`CalificacionOperacion`/`TipoImpositivo`/`BaseImponibleOimporteNoSujeto`/`CuotaRepercutida` XML elements.

**`verifactu_record_tax_details`** is schema-close to a generic tax-breakdown row (rate/base/amount as three separate columns — see §1) but is hard-wired to VERI*FACTU three ways: its only FK is to `verifactu_records.id` (not `invoices.id`); its column defaults (`'01'`, `'01'`, `'S1'`) are AEAT-specific; it's populated exclusively inside `recordAlta()`.

### The exact compatibility boundary (verbatim from the audit)

> A future generic tax engine must either keep writing `invoices.vta4/vta10/vta21/vta/total/sub_total` with the same values/semantics it has today, **or** `VerifactuChainService::buildTaxBreakdown(Invoice $invoice): array` (and the three other `$invoice->{vta,total,sub_total}` reads in `recordAlta()`/`buildRectificationSnapshot()`) must be fed an object/adapter that answers those exact property reads with a `[rate => cuota]` map restricted to `{4,10,21}` plus consistent `vta`/`total`/`sub_total` scalars — **nothing else in the VERI\*FACTU subsystem needs to change.**

Concretely, whatever the new architecture looks like, for a Spanish tenant it must guarantee:
1. `$invoice->vta4`, `->vta10`, `->vta21`, `->vta`, `->total`, `->sub_total` keep resolving (as real columns, or via an Eloquent accessor with identical read semantics) to the same values a Spanish tenant's data produces today.
2. The reconciliation invariant `round(Σ(cuota/(rate/100)), 2) ≈ round(total - vta, 2)` keeps holding, since `recordAlta()` throws otherwise.
3. Only rates 4/10/21 are ever populated for a Spanish invoice — introducing a 4th value into these specific columns for a Spanish tenant would either be silently ignored by `buildTaxBreakdown()` (if it's a genuinely new column outside `VAT_RATES`) or require actually editing VERI*FACTU code (explicitly out of scope).
4. **This audit does not modify, and does not propose modifying, any file under `app/Services/Verifactu/*` or `app/Models/Verifactu/*`.**

**A related rounding-safety note**: `VerifactuHashService::normalizeAmount()` (`:111`, `number_format((float) $value, 2, '.', '')`) canonicalizes `CuotaTotal`/`ImporteTotal` before hashing (the huella) — any future engine must ensure the values reaching this step are byte-identical in formatted form to today's, or a Spanish tenant's hash chain could change shape (it wouldn't break cryptographically, but would be a needless behavioral change to an already-frozen, audited subsystem).

## 9. Historical data — backward-compatible strategy

**Principle: never reinterpret a historical invoice under new tax configuration.** Two categories of pre-existing precedent already established in this codebase point the way:

1. **Additive-only schema changes with no backfill** — every migration in Phases 1A/1B (`ice`/`if_number`/`company_snapshot`/`customer_snapshot`, etc.) added nullable columns with zero data conversion, and every consumer was written to fall back to the pre-existing behavior when the new column is `NULL`. The same pattern applies directly here: any new generic tax-breakdown table must be populated **only for invoices issued after the new engine exists** — never backfilled by re-deriving values for old invoices, even though it would be *algebraically possible* to derive an old invoice's per-rate base from its existing `vta4/vta10/vta21` (the exact formula `VerifactuChainService::buildTaxBreakdown()` already uses). Doing so would risk **reinterpreting** an old invoice if the derivation methodology it's built on ever needs adjusting later (see §13's rounding-inconsistency findings) — a backfilled row could subtly disagree with what was actually shown to the customer at the time.
2. **Snapshot-first, live-fallback rendering** — Phase 1B's `TemplateRendererService` pattern (`$companyIdentity = $companySnapshot ?? $company?->identitySnapshot() ?? []`) is the direct precedent for how tax rendering should work too: **if a new generic tax-breakdown structure exists for this invoice, use it; otherwise, fall back to the exact rendering logic that exists today** (`$taxGroups` derived from `Cart::$vta`, or ultimately `vta4/vta10/vta21` for anything reading those columns directly).

**Proposed strategy** (not implemented here): the legacy `vta4`/`vta10`/`vta21`/`vta` columns on `invoices` are **never dropped, renamed, or reinterpreted**. A new generic tax-breakdown table (§10) is populated only going forward. Every consumer (PDF, VERI\*FACTU, any future report) checks for the new structure first and falls back to the legacy columns/cart-derived computation when absent — exactly the same shape of decision Phase 1B already proved out for company/customer identity, reused rather than reinvented.

## 10. Proposed generic tax model — evaluated, not decided

Your strawman:
```
TaxRate: country_code, code, name, rate, active
InvoiceLine: tax_rate / tax_rate_id, tax snapshot
```

### Option A — Reference `TaxRate` table + FK on `carts` + snapshot table on `invoices`

- New `tax_rates` table (`id`, `country_code`, `code` e.g. `IVA_21`/`TVA_20`, `name`, `rate`, `treatment` [see §11], `active`) — a per-country catalog, seeded only once real rates are legally verified (explicitly NOT this phase).
- `carts` gains a nullable `tax_rate_id` FK (additive; existing rows keep `NULL`, keep using the existing free-form `carts.vta` numeric column exactly as today — **not replaced**, since every historical cart row only has the numeric rate, never a rate-catalog reference, and rewriting that meaning retroactively is exactly the "reinterpreting historical data" this audit is told to avoid).
- New `invoice_tax_lines` table (one row per distinct rate actually used on that invoice, populated at issuance, mirroring the shape `verifactu_record_tax_details` already has): `invoice_id`, `tax_code`, `rate`, `treatment`, `taxable_base`, `tax_amount`.
- **Pros:** rate catalog is centrally managed and auditable per country; UI dropdowns become data-driven instead of hardcoded; naturally extensible to new treatments (exempt/out-of-scope) without new columns.
- **Cons:** a real new concept (rate catalog) that needs its own admin UI and seed-data governance; `tax_rate_id` FK on `carts` is only *optional* metadata (since it can't be retrofitted onto history), so two ways to know "the rate" coexist (`carts.vta` numeric, and `tax_rate_id` reference) unless carefully documented which is authoritative.

### Option B — Keep rate as a plain number per line (no catalog table), generalize only the invoice-level breakdown

- `carts.vta` stays exactly as it is today (a free-form numeric rate, no FK) — no change to the line-item model at all.
- A per-country **config/constants list** (not a DB table) defines which rates are *offered* in the UI for a given `country_code` (e.g. Spain: `[0,4,10,21]`; Morocco: to be defined in Phase 1C.2, not here) — analogous to how `TenantContextService`/currency formatting already branch on country today, reused rather than a new abstraction.
- Same new `invoice_tax_lines` table as Option A, populated by grouping cart lines by their raw numeric `vta` value at issuance (exactly what `TemplateRendererService::render()`'s `$taxGroups` loop already does, moved server-side and made authoritative instead of PDF-render-time-only).
- **Pros:** smallest possible schema change; matches the grain of the *existing* design (rate-as-a-number-per-line is already how the whole app works, from `Cart.vta` to `TemplateRendererService`'s `$taxGroups`) rather than introducing a new relational concept; a "rate catalog" can be added later (evolving into Option A) without another migration if `invoice_tax_lines.tax_code`/`rate` are already free-form strings/decimals.
- **Cons:** no central place to mark a rate "inactive" or attach country-specific metadata (treatment, legal citation) beyond what's hardcoded in application config; less discoverable/auditable than a real table for a future admin screen.

### Option C — Generalize `verifactu_record_tax_details`'s shape directly onto invoices

- Repoint (via a *new*, additive column, not by altering the FK) or duplicate `verifactu_record_tax_details`'s column shape (`impuesto`/`clave_regimen`/`calificacion_operacion`/`tipo_impositivo`/`base_imponible`/`cuota_repercutida`) into a new, invoice-scoped table, since that shape is already proven to model "rate + base + amount + treatment" correctly for one country.
- **Pros:** shape is already battle-tested (it's what AEAT's own schema demands); minimal new design work.
- **Cons:** the shape is AEAT-flavored by naming and by its `impuesto`/`clave_regimen`/`calificacion_operacion` fields, which are Spanish/AEAT code-list concepts (lista L1/L8A/L9) — reusing Spanish naming/semantics for a Moroccan concept risks exactly the "assume Moroccan VAT is the Spanish system with different rates" mistake this phase is explicitly told not to make. **Not recommended as-is**, but its *existence* is useful evidence that a 6-column (code/regime/qualifier/rate/base/amount) shape is suffinal for representing tax breakdowns in general — Option A/B's `invoice_tax_lines` should learn from its shape without copying its Spanish-specific field names.

### Recommendation (for your decision, not decided here)

**Option B now, structured so it can grow into Option A later without another destructive migration.** Rationale: it changes the least of what already works (line-item rate storage is untouched), it directly reuses the exact pattern (`TemplateRendererService`'s rate-grouping) already proven rate-agnostic in production, and it avoids introducing a rate-catalog admin surface before any real Moroccan rate has been legally verified (which is explicitly out of scope for this phase and possibly all of Phase 1C). Making `invoice_tax_lines.tax_code`/`rate` plain strings/decimals now, rather than a `tax_rate_id` FK, keeps the door open to add a `tax_rates` catalog table later (Option A) and backfill `tax_rate_id` only for newly-created rows going forward, without ever touching historical `invoice_tax_lines` rows.

## 11. Morocco readiness (concepts, not rates or rules)

The `treatment` field structure proposed in §10 must be able to represent, without yet assigning any actual Moroccan rate/rule:

- **Standard rate** — the general TVA rate.
- **Reduced/specific rate(s)** — plural, since Morocco's TVA (unlike Spain's clean 3-rate structure) is understood to have more than one reduced-rate tier — **not verified in this audit, deliberately**; the model must not assume exactly 3 rates the way `VAT_RATES` does today.
- **Exempt operation** — a line with no tax due, but still a real, taxable-in-principle transaction (distinct from...).
- **Non-taxable / out-of-scope operation** — a line outside the tax's scope entirely (a different legal category from "exempt," and Spain's own model has no representation for either today — see §13/VERI*FACTU section).
- **VAT withholding** — reserved as a concept/column placeholder only (e.g. a nullable `withholding_amount` or a `treatment` value that's never populated yet) — **explicitly not implemented, not even partially, per your instruction**.

None of these should be hardcoded as an enum with fixed Moroccan values in this phase — the `treatment` field should be a plain string/small lookup that Phase 1C.2 populates once verified, not a closed set decided here.

## 12. Spain preservation

Every proposal above is additive and preserves Spain's exact current behavior by construction:
- `invoices.vta4/vta10/vta21/vta/total/sub_total` are never dropped or reinterpreted (§9).
- `VerifactuChainService`/`VerifactuXmlBuilder`/`verifactu_record_tax_details` are never touched (§8) — the compatibility boundary is a hard constraint any implementation phase must satisfy before merging.
- `Cart.vta`'s existing free-form-numeric-rate semantics are preserved (§10 Option B), so no existing Spanish cart line's meaning changes.
- The existing (already rate-agnostic) PDF/report code needs no Spain-specific change at all (§6/§7) — Spain simply continues to be "the country whose rates happen to be 4/10/21," not a special case in the engine.

## 13. Rounding — audited, with two real pre-existing inconsistencies flagged

| Layer | Decimal precision | Rounding point | Mode |
|---|---|---|---|
| Frontend per-line total | 2dp | Per line | `.toFixed(2)` (JS round-half-away-from-zero-ish, IEEE754 caveats apply) |
| Frontend subtotal | 2dp | Once, from raw per-line sums | `.toFixed(2)` |
| Frontend aggregate `vtaTotal` (→ `invoices.vta`) | **unrounded** | Never | Raw float sent as-is |
| Frontend discount amount (→ `invoices.discount_amount`) | **unrounded** | Never | Raw float sent as-is |
| Frontend per-rate `vtaTotal4/10/21` | 2dp | Once per rate bucket, at the end | `.toFixed(2)` |
| Frontend grand total | 2dp | Once, final | `.toFixed(2)` |
| `QuoteToInvoiceService::calcVta()` | 2dp | Once per rate bucket (raw sum → round) | PHP `round()`, half-away-from-zero |
| PDF `TemplateRendererService` per-rate tax | 2dp | **Per line, before summing** (opposite order from the above) | PHP `round()` |
| `VerifactuChainService::buildTaxBreakdown()` | 2dp | Per rate bucket (base derivation) + once at the total (reconciliation) | PHP `round()` |
| `VerifactuXmlBuilder::formatImporte/formatTipo()` | 2dp | Formatting only, not arithmetic | `number_format()` |
| Display helper `$toComma`/`$toCurrency` | 2dp | On-screen only | **Truncates**, does not round |

**Two real, pre-existing inconsistencies identified (both independent of Morocco, both worth fixing regardless of this phase's outcome):**

1. **§5's finding**: within one invoice form, `vtaTotal` (unrounded, sum-then-implicit-truncate-on-save) and `vtaTotal4+vtaTotal10+vtaTotal21` (rounded per bucket) are not guaranteed to sum identically.
2. **§6's finding**: the PDF's displayed per-rate tax (raw `qty*price`, no discounts applied, rounded per line before summing) can diverge from the stored `invoices.vta4/vta10/vta21` (which does apply discounts, and is summed differently) for any invoice with a line or header discount — meaning the PDF a customer receives and the figure VERI\*FACTU reports to AEAT for the same invoice are not guaranteed to match today, before any redesign.

**Recommendation for the future engine (not this phase):** adopt one canonical order — round per line, then sum per rate bucket, then round the bucket total once — and apply it identically everywhere (frontend calculation, a new backend-authoritative recomputation this audit found is currently missing entirely, PDF rendering, and the input `VerifactuChainService` receives). This is a design decision for Phase 1C.1, flagged here, not made here.

---

## 14. Files/classes likely to change (future phases, not this one)

**Backend:** `app/Models/Invoice.php`, `app/Models/Cart.php`, `app/Models/Quote.php`, `app/Models/Item.php` (new relations/accessors); `app/Http/Controllers/InvoiceController.php` (`store`/`update`/`duplicate`/`syncCarts` — likely gains backend-authoritative recomputation, a currently-missing safeguard); `app/Http/Controllers/QuoteController.php` (same); `app/Services/QuoteToInvoiceService.php` (`calcVta()` replaced by a generic per-rate aggregator); `app/Services/InvoiceRectificationService.php` (copies `vta4/vta10/vta21` today, line 232-234 — needs the same generalization); `app/Http/Requests/InvoiceRequest.php`/`QuoteRequest.php` (add real numeric validation — a pre-existing gap, not Morocco-specific, worth closing alongside this work); a new `TaxCalculationService` or similar (not yet designed) to be the single source of truth replacing the 6 duplicated frontend copies' backend equivalent.

**Frontend:** `resources/js/views/admin/invoices/{CreateInvoiceForm,EditInvoiceForm}.vue`, `resources/js/views/admin/quotes/{CreateQuoteForm,EditQuoteForm}.vue`, `resources/js/views/admin/items/{create,edit}.vue` (rate picker becomes country-driven, not hardcoded); a new shared composable to de-duplicate the 6 copies of `totalRow`/`calcVta`/etc.; `resources/js/views/admin/layouts/documents/{create,edit}.vue` (recommend retiring — dead code, currently still shipped).

**PDF:** `app/Services/Pdf/TemplateRendererService.php` (`$taxGroups` computation - already rate-agnostic, but needs to read from the new generic structure once it exists, snapshot-first per §9); `resources/views/pdf/components/_totals.blade.php` (minimal change, already loop-based); `resources/views/invoices/tachua.blade.php` and `show.blade.php` (need rewriting or retiring — both hardcoded to exactly 3 Spanish columns).

**Never changed:** anything under `app/Services/Verifactu/*`, `app/Models/Verifactu/*`, `database/migrations/tenant/2026_09_20_1000*_*verifactu*` (§8's hard constraint).

## 15. Risks

- **Silent data loss for unsupported rates already exists today** (§3) — any Moroccan tenant using a rate outside `{4,10,21}` on a quote, converted to an invoice, already silently loses that line's tax from the per-rate breakdown, right now, before any redesign. This is not a new risk introduced by Phase 1C — it's a live bug the redesign must fix, and worth being aware it exists in production today for any tenant who's tried a non-standard rate.
- **The PDF/VERI\*FACTU discount-handling divergence (§6/§13)** is a live risk for Spanish tenants today, independent of Morocco — recommend fixing regardless of this phase's timeline.
- **Backend trusting client-submitted totals entirely** (§2/§3) means a future generic engine inherits a system with no server-side source of truth for tax math at all — introducing backend-authoritative recomputation (recommended for Phase 1C.1) is itself a behavior change that needs careful, separate testing against every existing Spanish invoice-creation path, since it's the first time the server would ever reject an internally-inconsistent submission.
- **Migration sequencing risk**: any new `invoice_tax_lines`-style table must be proven, via tests, to produce byte-identical VERI\*FACTU XML for a representative set of existing Spanish invoices before Spain's own rendering is ever pointed at it (§8's compatibility boundary is the acceptance test).
- **Six-way frontend duplication** (§5) means any UI change (new rate picker, new treatment concept) must currently be made in 4 live places (plus judgment call on the 2 dead ones) — a real velocity/consistency risk for Phase 1C.3 onward if not de-duplicated first.

## 16. Tests that will be required (future phases)

- Byte-identical VERI\*FACTU XML regression test: generate the same Spanish invoice's AEAT XML before and after any generic-engine change, assert equality (the single most important acceptance test for the whole redesign, per §8).
- Full existing VERI\*FACTU suite (all tests under `tests/Feature/Verifactu*Test.php`) must remain green, unmodified, throughout.
- Historical-invoice rendering test: an invoice created *before* the new engine exists must render its PDF identically before/after the migration (no `invoice_tax_lines` row exists for it — proves the fallback path, §9).
- New-invoice generic-breakdown test: an invoice created *after* the new engine exists, at a non-{4,10,21} rate, correctly produces a tax line (proves the actual fix to §3's bug) — without yet asserting any specific Moroccan rate.
- Rounding-consistency test: for a representative invoice with line and header discounts, assert the PDF's displayed per-rate tax equals the stored breakdown equals what a (future) backend recomputation would produce — closing §13's two flagged inconsistencies.
- Tenant isolation test for any new table (`invoice_tax_lines` or `tax_rates`), matching the existing pattern already used throughout Phases 1A/1B.
- Quote→Invoice conversion test at a non-{4,10,21} rate, proving the line is no longer silently dropped.

---

## Proposed implementation phases (proposed, not started)

- **Morocco Phase 1C.1 — Generic tax foundation.** The `invoice_tax_lines`-style table (§10, Option B), backend-authoritative recomputation in `InvoiceController`/`QuoteController` (closing §2/§3's trust gap), a de-duplicated calculation composable on the frontend (closing §5's six-way duplication), and the byte-identical-VERI\*FACTU-XML regression test (§8/§16) as the hard gate before anything else proceeds. No Moroccan rate introduced yet.
- **Morocco Phase 1C.2 — Morocco VAT configuration.** Once legally verified (separate legal-research task, not code): the actual Moroccan rate set and `treatment` values, entered as data/config, not hardcoded logic.
- **Morocco Phase 1C.3 — Morocco invoice calculation.** Wire the verified rates into the Item/Cart rate pickers for `country_code = MA`, using the foundation from 1C.1.
- **Morocco Phase 1C.4 — Morocco PDF/tax summary.** Country-aware tax breakdown labels/presentation (HT, TVA, treatment labels), reusing the already-rate-agnostic `$taxGroups` mechanism.
- **Morocco Phase 1C.5 — Reports.** Extend reporting only if/when a report needs per-rate/per-treatment breakdowns (none currently do, per §7) — otherwise no work needed here at all.
- **Morocco Phase 1C.6 — Advanced Moroccan VAT cases.** Exempt/out-of-scope operations, withholding, and any multi-rate edge cases verified as legally required.

---

## Test suite baseline (run before any implementation)

```
Tests:    202 passed (528 assertions)
Duration: 94.43s
```

**0 failed.** This is the clean baseline every future 1C sub-phase must be measured against — in particular, the full VERI\*FACTU suite within this run is the exact regression surface §8's compatibility boundary must never move.
