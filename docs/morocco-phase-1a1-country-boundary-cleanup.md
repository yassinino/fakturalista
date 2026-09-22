# Morocco Phase 1A.1 — country boundary cleanup

**Status: implemented, tested.** Scope: exactly the two remaining
country-boundary issues flagged in `docs/morocco-phase-1a-implementation.md`
§11, plus documenting (not fixing) the third. No Moroccan customer-identity
rule was invented, no DGI/UBL/API claim was added, and the homepage was
not redesigned.

---

## 1. Spain-only F1 customer-NIF rule

**Where:** `app/Http/Controllers/InvoiceController::issueInvoice()`.

**What it was:** any F1 invoice (the only type the UI currently
produces) required the customer to have a `tax_id`, unconditionally,
citing RD 1619/2012 art. 6 — Spanish law. This is a real Spanish
requirement, not a general invoicing rule.

**Fix:** the check is now additionally gated on
`TenantContextService::isSpain()` — the exact same country boundary
introduced in Phase 1A, reused rather than a new scattered
`if (country === ...)` check:

```php
$resolvedType = $invoice->invoice_type ?: Invoice::TYPE_F1;
if ($resolvedType === Invoice::TYPE_F1 && $this->tenantContext->isSpain() && empty($customer?->tax_id)) {
    throw new \RuntimeException(
        'Este cliente no tiene NIF/CIF registrado. Añádelo en su ficha antes de emitir esta factura.'
    );
}
```

`TenantContextService` was added to `InvoiceController`'s constructor
(same DI pattern as its other services). Nothing else in
`issueInvoice()` changed — the company-tax-id-required check above it,
the rectification-series numbering, the state transition, and the
history logging are all untouched, per your "do not otherwise modify
invoice issuance" instruction.

**Explicitly not done:** no Moroccan customer tax-ID/identity rule was
added in its place. A Moroccan tenant issuing an F1 invoice today simply
skips this one check — Phase 1B defines what (if anything) Morocco
requires here, based on verified legal sources, not assumption.

**Tests** (`tests/Feature/InvoiceCountryBoundaryTest.php`, 2 new tests):
- `spanish_tenant_is_still_blocked_from_issuing_f1_without_customer_tax_id` —
  unchanged existing behavior: 422, invoice stays a draft.
- `moroccan_tenant_is_not_blocked_by_the_spanish_customer_nif_rule` —
  200, invoice is issued, proving the Spain-only rule doesn't reach a
  Moroccan tenant.

---

## 2. Homepage Spain/VERI\*FACTU marketing

**Where:** `resources/views/index.blade.php`, the `<section class="fk-spain"
id="facturacion-espana">` block (the one section flagged in Phase 1A's
report — the dedicated `/verifactu` page itself was left untouched, see
below).

**Fix:** the section is now wrapped in a single condition,
`@if(app()->getLocale() === 'es')` / `@endif` — no other markup, copy,
or structure changed. Locale is the only signal available for an
anonymous homepage visitor (there's no tenant/country context on a
public marketing page), and it's the one Phase 1A already made default
to French. This is a gate, not a redesign:

- **Spanish-locale visitors**: section renders exactly as before, same
  copy, same CTA to `/verifactu`.
- **French-locale visitors (the new default, i.e. Morocco)**: section is
  omitted entirely — no Spain claim, and critically, **no Morocco/DGI
  claim was introduced in its place**. No "DGI compliant," no "DGI
  certified," no future mandatory-e-invoicing deadline, no UBL/API/QR
  claim. The space is simply not there.
- **English-locale visitors**: also no longer shown the section (English
  isn't the Spanish context the section is meant for either; nothing in
  your instruction asked to preserve it there specifically).

The dedicated `/verifactu` marketing page (`resources/views/verifactu.blade.php`,
routed via `HomeController::verifactu()`) was **not** touched or gated —
its only normal entry point (the homepage CTA) is now hidden for
non-Spanish-locale visitors, so it's no longer being *presented* to a
default French/Moroccan visitor through the normal browsing flow. Gating
the page itself too would be a second, separate change beyond "the
section" your report referred to, and wasn't requested.

Verified via `Blade::compileString()` that the template still compiles
cleanly (no `@if`/`@endif` mismatch) — no automated test was added for
this specific rendering condition since it's a single boolean
locale-gate on already-existing, already-translated markup with no new
logic to regress; feel free to ask for one if you'd like it covered
explicitly.

---

## 3. Email language — still deferred (documented, not fixed)

Per your instruction, **not touched in this patch**.
`resources/views/emails/invoice.blade.php` (shared by `InvoiceEmail` and
`QuoteEmail`) remains 100% hardcoded French copy for every tenant,
regardless of `company_profiles.locale` — this predates Morocco Phase 1A
entirely (it never varied by locale, for any tenant, ever) and Phase 1A
only fixed its *currency* (§3 of `docs/morocco-phase-1a-implementation.md`).

This remains open for a later, dedicated localization phase: it needs
real Spanish/English copywriting (not a mechanical find-and-replace) for
the greeting, the line-item labels, the payment CTA, and the footer
text. Tracked here and in `docs/morocco-phase-1a-implementation.md` §11
so it isn't lost between phases.

---

## Files changed

- `app/Http/Controllers/InvoiceController.php` — constructor gains
  `TenantContextService`; the F1/customer-NIF check in `issueInvoice()`
  is now scoped to `isSpain()`.
- `resources/views/index.blade.php` — the Spain/VERI\*FACTU section
  wrapped in `@if(app()->getLocale() === 'es')`.
- `tests/Feature/InvoiceCountryBoundaryTest.php` — new, 2 tests.

## Country boundary used

`TenantContextService::isSpain()` (Phase 1A) for the invoice-issuance
check — the existing, single boundary, reused rather than duplicated.
`app()->getLocale() === 'es'` for the homepage section, since no
tenant/country context exists for an anonymous public-site visitor;
locale is the only available, already-Morocco-aligned signal there.

## Test suite result

```
Tests:    191 passed (479 assertions)
Duration: ~80s
```

189 pre-existing (172 from before Phase 1A + 17 added in Phase 1A) + 2
new in this patch, **0 failed, 0 regressions**.
