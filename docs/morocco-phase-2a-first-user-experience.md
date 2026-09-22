# Morocco Phase 2A — First-user experience & launch readiness

**Status: audited end-to-end, highest-impact fixes applied.** Scope: make
the real first-run experience for a Moroccan freelancer/small business
excellent, not another tax-engine phase. No DGI work, no i18n/translation
file reorganization, no Spain removal.

---

## 1. User journey audited

Traced the full path with four parallel focused audits (registration/
dashboard, customer/item forms, invoice editor, quote/send/payment/
reports) plus a direct rendering of a realistic Moroccan invoice PDF
(mixed 20%/10%/exempt rates, long description, bank details, discount) to
inspect actual output rather than just reading templates:

Register → tenant provisioning → company setup (onboarding) → dashboard →
create customer → create item → create quote → convert to invoice →
create invoice directly → preview/download PDF → send → register payment
→ view status → reports.

## 2. The single most important finding: PDF/document text silently mixed languages

**Root cause.** `TemplateRendererService::render()` (Phase 1A) correctly
computed the tenant's own business-document locale (`$locale =
TenantContextService::locale()`, e.g. `'fr'` for Morocco) and used it for
every *inline* label (`match($locale) { 'fr' => 'Facture', ... }` in
`_header.blade.php`, the "Sous-total HT"/"Total TVA"/"Total TTC" strings
in `_totals.blade.php`). But it never called `App::setLocale($locale)`,
so every `__()` translation call inside the same PDF Blade components
(`invoice.date`, `invoice.status`, `invoice.billing_address`,
`invoice.item_description`, `invoice.quantity`, `invoice.unit_price`,
`invoice.amount`, `invoice.discount`, `invoice.payment_terms`, ...) kept
reading whichever locale was **already active in the framework** -
`users.locale`, whose schema default is `'es'`. A Moroccan tenant's
invoice, opened or sent by any staff user who hadn't personally changed
their own UI locale, rendered a real, half-French/half-Spanish document:
"Facture" but "Factura Núm."; "Sous-total HT" but "Cantidad"/"Precio
unitario"/"Importe"/"Dto.". Confirmed by literally rendering one (see
`docs/morocco-phase-1c3-invoice-readiness.md`'s harness, reused here) and
reading the raw output before and after the fix.

A pre-existing test file, `tests/Feature/BusinessDocumentLocaleTest.php`
(Phase 1A), had already anticipated and named this exact class of bug in
its own docblock, but its assertions only checked that rendering
succeeded and that `App::getLocale()` was unchanged afterward - it never
inspected the rendered *text*, so it passed the whole time the bug was
live. That blind spot is now closed (see §14).

**Fix.** `TemplateRendererService::render()` now does:

```php
$previousAppLocale = App::getLocale();
App::setLocale($locale);
try {
    return Pdf::loadView('pdf.document', ...)->setPaper('a4')->output();
} catch (...) { ... } finally {
    App::setLocale($previousAppLocale);
}
```

Restored in a `finally` specifically so a subsequent JSON response in the
same request (e.g. `send()`'s success message) keeps using the staff
user's own UI locale - the fix only affects the PDF's own content, never
leaks out. This is also why `resources/views/emails/invoice.blade.php`
and quote PDFs (same `TemplateRendererService::render()` call) are fixed
by the same change, with no separate work needed.

This single fix is the biggest thing this phase did for real Moroccan
users - it affects every invoice/quote PDF ever generated for any tenant
whose viewing/sending staff member's own locale doesn't match the
tenant's.

## 3. Registration / tenant creation

**There is no public self-serve sign-up.** Account creation is
admin/staff-provisioned via a Filament wizard
(`app/Filament/Resources/TenantResource/Pages/CreateTenant.php`) -
internal tooling, not the end customer's own flow. Classified **B
(internal/non-user-facing)** - its own labels are 100% hardcoded Spanish,
but that's staff-only tooling, out of this phase's scope (and would be
translation-file work regardless).

What matters for the real end-user (the tenant's own first login, landing
on `Onboarding.vue`, "Company profile" step): already Morocco-first and
correct, confirmed by reading the code, not assumed:

- `TenantContextService::DEFAULT_COUNTRY = 'MA'`, `DEFAULT_CURRENCY =
  'MAD'`; `defaultsForCountry()` only returns ES/EUR/es/Europe-Madrid when
  the country is explicitly `'ES'`.
- The Filament wizard's own country selector defaults to `'MA'` already
  (Phase 1A).
- `Onboarding.vue` asks only for account/address basics + a single
  optional ICE field for Morocco (optional NIF/VAT for Spain) - no
  mandatory fiscal information, matching "minimum friction" (§3 of the
  brief). Confirmed unchanged, no fix needed.
- `TenantProvisioningService` already has an explicit fix (pre-existing,
  documented in its own comments) ensuring the tenant's first admin user's
  `locale` is set from the tenant's own language - without it, that
  user's UI would default to Spanish (`users.locale` schema default)
  even for a Moroccan tenant.

**No changes made here** - already correct.

## 4. Company setup

Settings' Tax & Legal section already shows ICE/IF/RC for Morocco (vs.
NIF/VAT/Registro Mercantil for Spain), per Phase 1B - re-verified,
unchanged. No TP/Patente added (explicitly out of scope this phase). No
change made.

## 5. Dashboard / onboarding — new "Getting started" checklist

**Real gap found.** A brand-new tenant's dashboard showed zero-value KPI
cards and an empty invoices table with no unifying guidance - only the
invoices table itself had a (good) empty state ("Aucune facture récente" +
a create-invoice button); the KPI cards and the activity-timeline card
either showed bare `0 MAD` or silently disappeared.

**Added**: a lightweight 4-item checklist card (`resources/js/views/admin/dashboard.vue`),
shown only while the tenant has zero invoices, using the exact structure
the brief asked for:

1. Company information completed *(always shown as done - the
   `require.onboarding` middleware already guarantees this by the time
   anyone reaches the dashboard)*
2. Add your first customer *(checked once `stats.customers > 0`, links to
   the create-customer screen)*
3. Add a service/product *(checked once `stats.items > 0`)*
4. Create your first invoice *(links to the create-invoice screen)*

Disappears automatically once the tenant issues its first invoice - the
normal dashboard (KPIs, recent invoices, cash overview) remains primary
from then on, per the brief's explicit instruction. Built entirely from
existing `db-card`/`db-card-head` CSS classes plus ~35 lines of new,
minimal, scoped CSS for the checklist rows - no new framework, no new
dependency.

**Labels are inline, locale-switched text (FR/ES/EN), not `$t()`/lang
files** - same "keep new labels local" pattern already used in the PDF
components, so this phase adds zero new translation-file entries despite
being a new feature.

## 6. Customer creation — real bug found and fixed

**ICE/IF/RC (Moroccan business-registration identifiers) were shown even
for an "Individual" customer.** The Morocco/Spain split was already
correctly gated by country (`isMorocco`), but never by `state.type` (1 =
business, 2 = individual) - unlike the name fields two cards above, which
already correctly hide/show based on type. A Moroccan user adding a
one-off individual client saw three meaningless company-identity fields
(ICE, IF, Registre de Commerce).

**Fixed** in both `customers/create.vue` and `customers/edit.vue`: the
whole fiscal-identity card/section is now gated `v-if="!isMorocco ||
state.type == 1"` for Morocco (hidden entirely for an individual), a
`v-else-if="!isMorocco"` for Spain (unchanged - NIF applies to
individuals too, so Spain's branch is untouched), and neither block shows
for a Moroccan individual customer. Matches the brief's explicit "for
individuals, do not force these fields" (§6/§3).

No other customer-form issue rose to fix/D-worthy in this phase's
priority order beyond what's documented in §13.

## 7. Service/item creation

Tax preset selection already correctly uses the shared, country-aware
`TaxSelect.vue`/`useTaxPresets` (TVA 20%/10%/Exonéré for Morocco, IVA
21%/10%/4% for Spain, per Phase 1C.2) - confirmed, no hardcoded rate
found, no change needed. One friction point documented but not fixed:
`family_id` (category) is a required field with no default, forcing a
"create category" detour on a tenant's very first item - not
Morocco-specific (affects Spain equally), so left for a general UX pass
rather than this phase's Morocco-focused scope.

## 8. Invoice editor changes

Two real Morocco-relevant bugs fixed here, beyond the locale fix (§2):

1. **`descripcion_operacion` (a VERI*FACTU/AEAT-only field, fed into
   `VerifactuXmlBuilder` for a Spanish tenant's AEAT submission) was
   rendered unconditionally on both `CreateInvoiceForm.vue` and
   `EditInvoiceForm.vue`, for every tenant.** A Moroccan user saw a
   meaningless "operation description" textarea with no context for why
   it exists on their invoice. Fixed: both forms now import
   `useTenantCountry()` and gate the whole field behind `v-if="isSpain"`.

2. **`EditInvoiceForm.vue` was almost entirely hardcoded in Spanish**, unlike
   its sibling `CreateInvoiceForm.vue` (8 `$t()` calls vs. ~40). Every
   label a user sees while editing any existing invoice - the page title,
   status chip, Duplicate/Issue/Save buttons, client field, date labels,
   "more options"/reference hint, the entire line-items table header,
   product/description placeholders, remove-line tooltip, "add line",
   notes label/placeholder, subtotal/total labels, and the sticky-footer
   hint/save button - was Spanish literal text, invisible to the existing
   FR/ES/EN locale switcher entirely. Since editing an invoice is the
   single most common action after creating it, this was arguably a
   bigger real-world impact than the create screen ever being wrong.
   **Fixed by wiring every one of these to the exact same `$t()` keys
   `CreateInvoiceForm.vue` already uses for the identical UI element** -
   confirmed each key already exists correctly in `fr.js`/`es.js`/`en.js`
   before using it, so **zero new translation-file entries were added**.
   Two small pieces of decorative-only text with no existing equivalent
   key (an edit-specific subtitle, a "number is auto-assigned" hint) were
   simply dropped rather than inventing new copy.

**Documented, not fixed** (real, but out of this phase's Morocco-specific
scope or too broad to safely land here):

- **Discount fields have no input anywhere.** `cart.discount` and
  `state.discount_rate` exist in the data model and are already computed
  into the live totals preview in both Create and Edit forms, but neither
  form has an actual input for either value - a user can never set a
  discount from the invoice screen even though the app is silently ready
  to apply one. Affects Spain and Morocco identically (not a leak), and
  fixing it properly means designing new UI, not a targeted bug fix - left
  for a dedicated pass.
- `payment_terms` exists on the edit form's own state (loaded from the
  invoice) but is never rendered as its own field anywhere - the old
  placeholder literally told users to put payment terms into the free-text
  Notes box instead. Documented, not restructured this phase.
- Date inputs (`flatpickr`) show raw `YYYY-MM-DD` with no French locale
  import and an English calendar UI - a real friction point for a
  French-locale user, but this is shared, global date-picker
  infrastructure used across invoices/quotes/items; fixing it touches
  every form in the app and carries real date-parsing regression risk
  without a dedicated pass. Documented as a recommended follow-up, not
  attempted here.
- Selecting a customer only previews the billing address, not ICE/phone
  - minor, left as-is.
- `TaxSelect.vue`'s "(saved)" suffix for a legacy/mismatched rate is
  hardcoded English - minor, left as-is.

## 9. Quote changes

Status labeling already clearly distinguishes a quote from an issued
invoice (draft/sent/converted/cancelled, correctly localized in
`quotes/index.vue`). Convert-to-invoice already creates a proper draft
invoice via the existing `QuoteToInvoiceService` (tax data preserved,
confirmed unchanged and still covered by `QuoteToInvoiceTaxTest`).

**Same fix as §8 applied to `EditQuoteForm.vue`** (only 2 `$t()` calls vs.
31 in `CreateQuoteForm.vue`, and using Spain/LatAm-specific vocabulary -
"Presupuesto" instead of a generic term - for the page title, every field
label, and both action buttons). Wired to the existing `quotes.form.*`/
`quotes.*` keys `CreateQuoteForm.vue` already uses correctly, plus the
script section's three hardcoded `"Ha ocurrido un error..."` fallback
messages switched to the pre-existing `t('quotes.errorGeneric')` (matching
what `EditInvoiceForm.vue`'s script already did correctly). Zero new
translation-file entries.

**Documented, not fixed**: after converting a quote, the UI doesn't
navigate the user to the newly created invoice draft - they have to find
it in the invoice list. Minor, not Morocco-specific.

## 10. PDF changes

Beyond §2's locale fix (which is the real PDF content fix this phase
made), a realistic Moroccan invoice was rendered and read end-to-end
(long description, 4 lines, mixed 20%/10%/exempt rates, 5% header
discount, bank details, notes) - confirmed correct: company/customer
identity block, `Facture n° .../Date de facture/Statut`, item table with
wrapped long description, `Base TVA X%`/`TVA X%` per rate (Phase 1C.3),
`Total TVA`/`Total TTC`, bank details block (Phase 1C.3), notes. No
additional Morocco-specific PDF layout problem was found once §2's fix
was applied - the underlying structure (Phase 1C.3) was already sound;
the bug was purely which language it spoke, not its layout. Spain PDFs
were re-verified correct after the fix (`BusinessDocumentLocaleTest`'s
Spanish-tenant test, strengthened, still green).

## 11. Send-invoice / email result

The send flow (`SendInvoiceModal.vue`, shared by invoices and quotes) is
fully localized already, shows the recipient email read from the
customer record, a pre-filled editable message, and states which PDF gets
attached - no Spain leak found here.

**Real bug found and fixed**: the Stripe Checkout line-item name was
hardcoded `'Factura ' . $invoice->reference` in *two* places
(`PaymentController::createSession()` and
`StripeConnectService::createConnectedCheckoutSession()`) - a Moroccan
customer paying online via Stripe would see "Factura FAC-0001" on the
actual payment page, regardless of tenant. Both now derive the
document-type word (`Facture`/`Factura`/`Invoice`) from the tenant's own
locale, the same way the PDF header already does.

**Documented, not fixed**: `InvoiceController`/`QuoteController` return
toast/confirmation messages that are hardcoded in an inconsistent mix of
Spanish, French, and English across roughly 15+ methods (issue, cancel,
duplicate, delete, print, etc.) - independent of tenant locale. This is a
real, visible leak, but fixing it properly means adding new translation
keys across every one of those messages, which is exactly the "global
translation cleanup" this phase was told to defer, not a small,
contained fix. Flagged clearly for the dedicated localization phase.

**Environment note (as the brief asked to document, not fix)**:
`.env.example` defaults `MAIL_MAILER=smtp`/`MAIL_HOST=mailpit` - a
Sail/Docker-only local SMTP catcher. Outside that setup, sending fails
with a generic (correctly non-crashing) error toast and no in-app warning
that mail simply isn't configured yet. A fresh/demo deployment needs its
own `MAIL_*` env vars before "send invoice" will actually deliver
anything. `InvoiceEmail`'s body text is deliberately French for every
tenant regardless of locale (a prior phase's documented, intentional
scoping decision) - flagged again here since it means a Spanish tenant's
customers currently receive a French-language email body even though the
PDF attached to it is correctly Spanish after this phase's fix.

## 12. Payment-flow result

Traced `payments.vue` → `InvoicePaymentsController` (not
`PaymentController`, which is Stripe-only). The `€` the audit was asked to
check (`payments.vue:1038`) is a CSS *comment*, not rendered text - the
actual amount-input prefix is the tenant's real currency code (`MAD` for
Morocco), confirmed correct, no fix needed.

**Real, product-level finding (not Morocco-specific, not fixed per the
brief's "do not build accounting features" instruction)**: there is no
partial-payment concept. `record()` accepts no amount - marking an
invoice paid is a binary status flip to the full `total`, with no
separate payment-amount entity, no outstanding-balance tracking, and no
multi-payment history per invoice. Double-registration can't happen
(a paid invoice drops out of the payable list) and overpayment can't
happen (amount is fixed to the total) - but only because the feature is
simpler than a real partial-payment system, not because either case is
actively handled. Documented as a known limitation, not built.

**Documented, not fixed**: `payments.vue`'s dashboard-card sub-labels ("En
proceso"/"Al día"/"Requiere atención"/"Todo en orden"/"Este mes"/"Sin
cobros aún", plus one hardcoded English search placeholder) have no
existing translation key to reuse - fixing them correctly requires adding
new keys to three lang files, which this phase's "don't touch translation
files" instruction reserves for the dedicated localization phase.

## 13. Reports result

`StatsController` returns pure numeric aggregates with no currency/locale
assumption. `dashboard.vue`'s own reporting widgets are fully localized
(44 `$t()` calls) and already route every amount through
`Intl.NumberFormat` keyed on the tenant's real currency (`store.company.currency
|| "MAD"`, a documented Phase 1A fix) - no hardcoded €/EUR/IVA found. No
change needed.

## 14. Spain-first leaks found/fixed (classification summary)

| Finding | Class | Action |
|---|---|---|
| PDF/email `__()` labels followed staff locale, not tenant's | **C** | **Fixed** (§2) |
| ICE/IF/RC shown to individual Moroccan customers | **C** | **Fixed** (§6) |
| `descripcion_operacion` shown to all tenants | **C** | **Fixed** (§8) |
| Stripe checkout line-item hardcoded "Factura" | **C** | **Fixed** (§11) |
| `EditInvoiceForm.vue` almost entirely Spanish | **C** | **Fixed** (§8) |
| `EditQuoteForm.vue` almost entirely Spanish | **C** | **Fixed** (§9) |
| VERI*FACTU/Settings/RequireSpainCountry gating | A | Re-verified, correct, untouched |
| Filament tenant-creation wizard, 100% Spanish | B | Internal-only, not touched |
| `InvoiceController`/`QuoteController` toast messages | C (broad) | **Documented**, deferred - needs new i18n keys |
| `payments.vue` KPI sub-labels | C (small) | **Documented**, deferred - needs new i18n keys |
| flatpickr date format/locale | D | **Documented**, deferred - broad, regression-risky |
| Discount UI missing | D (not Spain-specific) | **Documented**, out of scope |
| `payment_terms` field missing from UI | D (not Spain-specific) | **Documented**, out of scope |
| `family_id` required friction | D (not Spain-specific) | **Documented**, out of scope |
| Email body always French regardless of tenant locale | D | **Documented**, pre-existing scoped decision |

`__('invoice.*')`/`__('quote.*')` PHP lang files (`resources/lang/*`) and
the Vue `resources/js/i18n/locales/*.js` files were **not modified** - every
fix this phase made either reused an existing key or used inline,
locale-switched text local to the component, exactly as instructed.

## 15. Responsive fixes

Reviewed the CSS for the critical flow (dashboard, customers, items,
invoice editor, invoice view) - existing `@media (max-width: 768px)`
rules already collapse the two-column layouts, hide low-priority table
columns (`inv-hide-mobile`), and stack the sticky-footer/top-bar actions
sensibly across every form touched this phase (confirmed present in both
`EditInvoiceForm.vue` and `EditQuoteForm.vue`'s existing scoped styles).
No obvious breakage found in the files this phase touched; no dedicated
mobile redesign was needed or attempted, per the brief.

## 16. Error / empty states

- Zero invoices: now has both the pre-existing table-level empty state
  and the new getting-started checklist (§5).
- Zero customers/items on their own list pages: pre-existing empty states
  were not found to be broken during this audit (not the focus of the
  parallel audits, no issue surfaced).
- Missing company information: structurally impossible to reach the
  dashboard without it (`require.onboarding` middleware), so this case
  doesn't occur in practice.
- Customer without email: the send-invoice button is already correctly
  disabled with a localized tooltip (`invoices.noClientEmail`/
  `quotes.noClientEmail`) in every form checked.
- Invoice without bank information: correctly renders no section at all
  (Phase 1C.3, re-verified) - no empty box.
- Tax-preset load failure: already shows a retry action and blocks saving
  an uninitialized line (Phase 1C.2) - its exact wording is hardcoded
  English, documented in §8, not fixed.

## 17. Files changed

- `app/Services/Pdf/TemplateRendererService.php` - locale fix (§2).
- `tests/Feature/BusinessDocumentLocaleTest.php` - strengthened with real
  content assertions (§2/§14).
- `app/Http/Controllers/PaymentController.php` - Stripe line-item locale
  fix (§11).
- `app/Services/StripeConnectService.php` - same fix, Connect path (§11).
- `resources/js/views/admin/customers/create.vue`,
  `resources/js/views/admin/customers/edit.vue` - ICE/IF/RC business-only
  gating (§6).
- `resources/js/views/admin/invoices/CreateInvoiceForm.vue`,
  `resources/js/views/admin/invoices/EditInvoiceForm.vue` -
  `descripcion_operacion` Spain-only gating + (Edit only) full i18n wiring
  (§8).
- `resources/js/views/admin/quotes/EditQuoteForm.vue` - full i18n wiring
  (§9).
- `resources/js/views/admin/dashboard.vue` - getting-started checklist
  (§5).

## 18. Migrations

**None.**

## 19. Remaining localization issues (for the dedicated phase, not this one)

- `InvoiceController`/`QuoteController` response messages: inconsistent
  Spanish/French/English, ~15+ call sites, needs new translation keys.
- `payments.vue` KPI sub-labels: 6 Spanish + 1 English string, needs new
  keys.
- Email body (`InvoiceEmail`) hardcoded French for every tenant - a prior
  phase's explicit, documented scoping decision, worth revisiting once
  full i18n work starts.
- flatpickr has no French/Spanish locale pack loaded anywhere in the
  codebase - every date picker shows English chrome and raw ISO dates.
- `fr.js`'s `colUnit: "Ud."` is a leftover Spanish abbreviation (should
  read something like "Unité") - found incidentally, not fixed (lang-file
  edit, reserved for the localization phase).

## 20. Remaining blockers before giving Fakturalista to real Moroccan testers

**None that block a first pilot.** The core mechanics (identity, tax
presentation, numbering, snapshots, non-VAT businesses, rectification,
and now first-run UX and document language) all work correctly for a
Moroccan tenant. Recommended before a *wider* launch, in rough priority
order:

1. Fix the remaining `InvoiceController`/`QuoteController` message
   inconsistency (§11/§19) - visible on nearly every action.
2. Configure real mail delivery in the actual deployment environment
   (§11) - "send invoice" is a core feature and currently depends on
   environment setup the repo doesn't guarantee.
3. Decide on and build the discount UI (§8) - the backend already
   computes it; only the input is missing.
4. French-localize the date pickers (§8/§19).
5. The remaining smaller i18n gaps (§19).

Work stops at Phase 2A.
