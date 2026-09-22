# Morocco Phase 1 — Audit (audit only, no implementation)

**Status: audit only.** Nothing in this document has been implemented. No production code, migration, or config was changed while producing it. It is a map of what exists today and a proposal for what should change — every proposal is explicitly labeled as a proposal, not a decision already made.

**Method note on legal/fiscal claims.** Everywhere this document states what Spanish law/AEAT requires, that traces back to primary sources (BOE, AEAT) already cited in `docs/verifactu-*.md` from prior phases. Nowhere does this document assert what Moroccan law (DGI, CGI) requires — no Moroccan primary source was fetched in this phase, per your explicit instruction not to invent Moroccan legal requirements. Every Moroccan field proposed below is labeled with a confidence class (A/B/C, defined in §11) and, where it's anything other than "already exists as a generic field," is flagged as needing a dedicated legal-source review before being made mandatory or relied upon.

---

## 1. Current architecture

- Laravel 10 / PHP 8.1, Vue 3 admin SPA, `stancl/tenancy` with **true database-per-tenant**: one central DB (`tenants`, `domains`, `plans`, `subscriptions`, billing) and one full application DB per tenant (`company_profiles`, `customers`, `invoices`, `carts`, `verifactu_*`, etc.).
- **Tenant provisioning is 100% internal/admin-driven today** — there is no public self-service signup. A super-admin creates a tenant through a Filament backoffice wizard (`app/Filament/Resources/TenantResource/Pages/CreateTenant.php`), which calls `app/Services/TenantProvisioningService.php`. The public "free trial" form (`HomeController::freeTrial()`) only sends a lead email — it creates nothing. This matters for Morocco: there is a single, well-defined choke point (`TenantProvisioningService` + the Filament wizard) where country/locale/currency defaults are set, not dozens of scattered signup paths.
- After provisioning, the tenant's own admin completes `app/Http/Controllers/OnboardingController.php`, a wizard that writes to `CompanyProfile` (country, currency, address, tax fields are all user-supplied here — not hardcoded at this step).
- **Country and currency are already first-class columns**, not something to bolt on:
  - `tenants.country` (char2), `tenants.currency` (char3), `tenants.timezone`, `tenants.language` (char5) — central DB, added by `database/migrations/2026_07_22_000001_add_management_columns_to_tenants_table.php`.
  - `company_profiles.country_code` (char2), `company_profiles.country` (free text, redundant with the former), `company_profiles.currency` (char3), `company_profiles.locale` (char10), `company_profiles.timezone` — tenant DB, from `database/migrations/tenant/2025_12_18_142214_create_company_profiles_table.php`.
  - Both default to Spain (`ES`/`EUR`/`es`/`Europe/Madrid`) at the schema level, but nothing prevents a different value — it's a **default problem, not an architecture problem**.
- **A capability/plan system already exists** (`Plan` ⟶ `Feature` via `plan_features`, `PlanService::hasFeature()`) and is the codebase's real authorization pattern (there is no Laravel `Gate::` usage anywhere). No `verifactu` feature slug exists yet, but the mechanism a VERI*FACTU visibility gate would hook into is already built and already used for other opt-in capabilities (Stripe, WhatsApp share, etc.).
- **A fully-seeded `countries` reference table already includes Morocco** (`database/seeders/CountrySeeder.php` → `['name' => 'Morocco', 'code' => 'MA']`), used today for `Customer.billing_country_id`/`delivery_country_id`. Filament's tenant wizard already has Morocco/MAD as selectable options (`TenantResource.php` → `'MA' => 'Marruecos'`, `'MAD' => 'MAD - Dírham marroquí'`) — someone already anticipated this expansion at the admin-tooling layer, even though nothing downstream consumes it yet.
- **VERI*FACTU is not wired into live invoice issuance** — confirmed again in this audit. `InvoiceController::issue()`/`cancel()` never call into `app/Services/Verifactu/*`. The only paths that trigger a real AEAT interaction are the certificate-settings UI and the manual `verifactu:aeat-test` command. `VerifactuChainService`'s own docblock already states (Phase 2B note) that wiring it into live issuance needs "a per-tenant opt-in gate (`company_profiles.verifactu_enabled`, not yet built)" — i.e. the codebase already anticipated needing exactly the kind of gate Morocco scoping now also needs, for a different reason.
- **The invoice lifecycle itself (draft → issued → paid/cancelled, immutability once issued, rectification via a dedicated series) is generic and reusable.** What's Spain-specific is layered on top: the F1-requires-customer-NIF rule, the RD 1619/2012 rectification-series mandate, and the fixed `vta4/vta10/vta21` rate columns — not the state machine.

## 2. Spain-specific assumptions discovered

| # | Assumption | Where | Impact on Morocco |
|---|---|---|---|
| 1 | Default country/currency/locale/timezone = Spain, at 3 separate layers (Filament wizard defaults, `company_profiles` schema defaults, `config/app.php`) | `CreateTenant.php:60,67,73,79`; `2025_12_18_142214_create_company_profiles_table.php:22,50-52`; `config/app.php:85,98` | Every new tenant silently becomes Spanish unless every one of these is overridden by hand |
| 2 | Exactly three IVA rates (4/10/21%), encoded as **column names**, not data | `invoices.vta4/vta10/vta21`; `VerifactuChainService::VAT_RATES` (comment: "Fixed VAT rates Fakturalista's UI supports"); `QuoteToInvoiceService::$calcVta(4\|10\|21)` | Moroccan TVA rates (20/14/10/7%) cannot be represented without schema/logic changes — this is the single largest "invoice/tax" blocker |
| 3 | F1 invoice issuance requires the **customer's** Spanish-style tax ID | `InvoiceController::issueInvoice()` (RD 1619/2012 art. 6 citation) | Would incorrectly block issuing a normal Moroccan invoice to a customer whose only identifier is an ICE, unless this rule is scoped to ES tenants |
| 4 | Rectification must use a dedicated numbering series | `InvoiceNumberingService::assignRectificationNumber()` (RD 1619/2012 art. 6.5) | Not necessarily wrong for Morocco, but currently justified *only* by Spanish law in the code comments — needs its own justification (or to stay opt-in) for MA |
| 5 | `€` hardcoded in money-formatting closures instead of reading `company_profiles.currency` | `TemplateRendererService.php:77`; `pdf/quote.blade.php:17`; `invoices/show.blade.php:20`; `emails/invoice.blade.php:44,70`; `index.blade.php:1157,997,1001` | Every Moroccan invoice/email/PDF would show amounts suffixed with `€` today, regardless of the tenant's actual MAD currency |
| 6 | PDF/document language resolved from the **logged-in staff user's** `users.locale`, not the tenant's country or `company_profiles.locale` | `SetLocale.php:17`; `company_profiles.locale` column exists but is **never read anywhere** (dead schema) | A Moroccan tenant's invoices would be French only by accident (whichever locale the logged-in user happens to have), not by design |
| 7 | `CompanyProfile` (the taxpayer/emitter) has no foreign-tax-ID branch — only `Customer` and the software-producer identity in `config/verifactu.php` do | `CompanyProfile` fillable list has no `foreign_tax_id*` fields; `VerifactuChainService::requireNif()` only checks `empty($company->tax_id)` | Not a blocker for Morocco (Moroccan tenants won't run VERI*FACTU at all — see §15), but confirms VERI*FACTU's emitter side is 100% Spain-only by construction, which is exactly what we want to preserve/isolate |
| 8 | One Stripe Price ID pair (monthly/yearly) per plan row, i.e. one currency per plan | `plans.stripe_price_id_monthly/yearly`; `Plan::formattedPrice()` hardcodes comma-decimal formatting | Morocco needs either duplicate plan rows (EUR plan + MAD plan) or a currency-variant mechanism that doesn't exist today |
| 9 | Plan pricing currency defaults to `'eur'` at schema, seeder, and API-fallback level | `create_plans_table.php:19`; `PlanSeeder.php:36,73,112`; `PlanController.php:71` | A MAD plan would have to be created and wired by hand; nothing auto-derives it |
| 10 | Homepage has a permanent, hardcoded "Spain / VERI\*FACTU" marketing section, present (translated, but not content-adapted) in all 3 locales including French | `index.blade.php:1206-1253`; `resources/lang/{es,fr,en}/site.php:415,683-755` | Must not be shown to Moroccan visitors — this is explicit in your brief (§12) and confirmed as currently unconditional |
| 11 | Test/seed factories encode Spanish formats as the only shape | `CustomerFactory.php`: Spanish CIF pattern, `+34` phone, `ES` VAT prefix | Needed before any Morocco-flavored automated test can be written (§18) |
| 12 | `foreign_tax_id_type` on `Customer` is validated against a **fixed AEAT code list** (`02,03,04,05,06,07`) | `CustomerRequest.php` | Fine to leave as-is (it's specifically the AEAT IDOtro list for VERI*FACTU), but must not be reused as a generic "foreign ID type" validator for Morocco — a Moroccan customer's ICE is not one of these codes and isn't meant to be |

## 3. Hardcoded EUR/IVA/Spanish terminology discovered

Consolidated from the tables above and the sub-agent reports; see §2 for the tax-rate items (repeated here only where currency/terminology, not tax logic, is the issue):

- **Currency (`€` / `EUR`)**: `TemplateRendererService.php:77`, `pdf/quote.blade.php:17`, `invoices/show.blade.php:20` (dead code), `emails/invoice.blade.php:44,70`, `index.blade.php:997,1001,1157`, `pricing.blade.php:286` (partial exception — does branch on `$plan->currency`, but only maps `EUR`→`€`, everything else falls back to a bare currency code with no symbol), `PlanController.php:68`, `app/Filament/Resources/PlanResource.php:130,275`, `app/Filament/Widgets/SubscriptionStatsWidget.php:29`, `TenantResource.php:412`, `CreateTenant.php:73`, `settings.vue:341,930`, `dashboard.vue:443`, `items/{create,edit}.vue`, `Onboarding.vue:238` (only `<option value="EUR">` exists — no other currency selectable in the onboarding wizard today), `create_plans_table.php:19`, `PlanSeeder.php:36,73,112`, `create_payments_table.php:26`.
- **Spanish-only invoice terminology hardcoded outside the translation system**: `resources/views/invoices/tachua.blade.php` (fully Spanish, literal "Factura Núm.", "NIF", "IVA(4%)/(10%)/(21%)") — one of two **legacy, host-string-hardcoded** per-tenant views (`InvoiceController::generateInvoicePdf()` special-cases `tachua.fakturalista.com` and `client1s.fakturalista.test` by HTTP host, bypassing the template engine entirely). `resources/views/invoices/show.blade.php` is dead code with the same pattern, unreferenced by any route/controller.
- **Locale/date-label logic duplicated inline instead of via lang files** (not Spanish text per se, but a maintainability gap worth fixing alongside Morocco work): `_header.blade.php:35,37` and `pdf/quote.blade.php:258` both `match($locale)` inline for "Devis/Presupuesto/Quote" and "Facture/Factura/Invoice" instead of using `__()`; `TemplateRendererService::resolveStatus()` duplicates the same pattern for status labels.
- **Meta tags never localized**: `layouts/master.blade.php:20-27` — `<meta description>`, `<meta keywords>`, all `og:*` tags are raw hardcoded Spanish, not run through `__()`, regardless of which of the 3 locales is active.
- **`es` as a fallback/default string** in a dozen places (full list in §2 item 1 and the agent findings) — this is a *default*, not text rendered to a user, but it's the mechanism by which Spanish becomes the accidental default everywhere else.

## 4. Current localization architecture

Two entirely separate i18n systems, confirmed still separate:

1. **Public marketing site + PDF/email templates** — Laravel's native `__()`/`resources/lang/{en,es,fr}/{site,invoice,quote}.php`. Locale resolved by `app/Http/Middleware/SetLocale.php`: authenticated user's `users.locale` → session `locale` (set by the public site's language-switcher, `HomeController::setLocale()`) → `config('app.locale')` (`'es'`). Supported set: `config('app.supported_locales') = ['es','fr','en']`.
2. **Tenant-admin Vue SPA** — `resources/js/i18n/locales/{es,en,fr}.js` (996 lines each), driven by `resources/js/i18n/index.js` (`supportedLocales = ["es","en","fr"]`, `defaultLocale = "es"`), persisted in `localStorage`, independent of the Blade-side system above.

Both trees are **feature-complete for French already** — `fr/invoice.php`, `fr/quote.php`, `fr/site.php`, and the Vue `fr.js` all exist and are fully populated, not stubs. This means **the Morocco locale-default work is a wiring/defaults problem, not a translation problem.** No Arabic locale exists in either tree.

**Arabic/RTL — what would actually be required later (not implemented, per your instruction):**
- A 4th locale file in both trees (`resources/lang/ar/*`, `resources/js/i18n/locales/ar.js`), added to both `supported_locales` lists.
- RTL is a **layout** concern, not just a translation concern: the PDF templates (`pdf/document.blade.php` + components) build their CSS from `$design` tokens with implicit LTR assumptions (left/right toggles for address blocks, etc. — `_addresses.blade.php` already has a "left/right layout toggle" per the PDF audit, which is a reasonable starting point but not RTL-aware); the Vue admin SPA has no `dir="rtl"` handling anywhere found; DomPDF's Arabic/RTL text-shaping support is a known real constraint worth testing early once this phase starts, not assumed to "just work."
- Recommendation: treat Arabic/RTL as its own phase (Morocco Phase 1E, per your suggested structure) rather than folding it into the French-default work, precisely because the layout risk is independent of the translation-content risk.

## 5. Current CompanyProfile structure

Full current schema (`app/Models/CompanyProfile.php` + 5 chronological tenant migrations), 33 fillable columns:

```
legal_name, trade_name, industry, country_code, tax_id, vat_number,
registration_number, email, phone, website, address_line1, address_line2,
city, state, postal_code, country, logo_path, stamp_path, brand_color,
invoice_footer_note, invoice_prefix, invoice_next_number, invoice_number_format,
timezone, locale, currency, bank_name, iban, swift,
stripe_account_id, stripe_connection_status, onboarding_completed,
charges_enabled, payouts_enabled, stripe_connected_at, onboarding_completed_at,
rectification_prefix, verifactu_installation_number
```

Notable pre-existing findings, useful for the Morocco design:

- **`country_code` (ISO2, default `ES`) and `country` (free-text label) both exist and overlap** — a pre-existing redundancy, not something Morocco introduces. Worth consolidating (keep `country_code` as the canonical machine-readable field; either drop `country` or make it a derived display label) as part of the country-aware refactor rather than adding a third column.
- **`tax_id`'s own migration comment already reads `// NIF/CIF/ICE...`** — whoever wrote the original schema already anticipated a non-Spanish identifier living in this same column. It is validated as `nullable|string|max:255` (no Spain-only format constraint) at the controller level, so `tax_id` can safely hold a Moroccan ICE today without a schema change — the constraint is entirely in `VerifactuChainService::requireNif()`, which is Spain/VERI*FACTU-only code Moroccan tenants won't run.
- **`invoice_next_number` is dead**: present in schema/model/validation but never read by `InvoiceNumberingService` (which uses the separate `invoice_number_sequences` table). Not a Morocco-specific issue, but flagged since it'll be touched by anyone auditing numbering.
- `bank_name`/`iban`/`swift` are generic enough to hold Moroccan bank details (RIB) as-is — no schema change needed, though Moroccan RIB has its own 24-digit structure distinct from IBAN, worth a UI label change rather than a new column.
- No `capital_social` (share capital) or `registre_commerce_ville` (RC city) concept exists — see §11 for whether these are needed.

## 6. Current Customer fiscal structure

Full current schema (`app/Models/Customer.php` + 4 chronological tenant migrations), 25 real columns:

```
id, billing_country_id, delivery_country_id, uuid, reference, ice, type,
company_name, first_name, last_name, middle_name, vat_number, tax_id,
foreign_tax_id_type, foreign_tax_id, email, phone, website,
city_billing, address_billing, post_code_billing, is_same_address,
city_delivery, address_delivery, post_code_delivery
```

- **`ice` already exists as a customer field**, added specifically for Morocco per its own migration's docblock (`2025_11_05_123232_add_ice_to_customers_name.php`) — this predates this audit and confirms Morocco was already on someone's mind when customer fiscal fields were extended. (Flagged bug, unrelated to Morocco: that migration's `down()` re-adds `ice` as non-nullable instead of dropping it — worth a one-line fix whenever that migration is next touched, not urgent.)
- `tax_id` (Spanish NIF/CIF/NIE) and `foreign_tax_id_type`/`foreign_tax_id` (AEAT IDOtro, restricted to AEAT's own code list `02–07`) are both VERI*FACTU-driven fields — **not suitable to reuse for Morocco's ICE/IF** (the type-code list is AEAT-specific, not a generic "any foreign ID" list).
- `country_id`-style FKs (`billing_country_id`/`delivery_country_id`) point at a fully-seeded `countries` table that **already contains Morocco** — no data gap there, only that nothing currently branches UI/validation behavior on the resulting country.
- `CustomerRequest::rules()` validates only 3 fields (`tax_id`, `foreign_tax_id_type`, `foreign_tax_id`) — no `required` rule exists for name/type/address, meaning **there is effectively no server-side validation gap to fight when adding Morocco fields**: nothing today would reject an ICE-only, no-`tax_id` customer.

## 7. Current invoice/tax architecture

- **Lifecycle** (`InvoiceController.php`, `InvoiceRectificationService.php`, `InvoiceNumberingService.php`) — draft/issued/paid/cancelled state machine, immutability once issued (locked statuses block `update()`), hard-delete restricted to drafts, rectification via a dedicated series and R1–R5 AEAT type codes. The **mechanism** (row-locked sequence counters, configurable prefix/format template) is generic; the **rules that trigger it** (F1-needs-customer-NIF, rectification-series-is-mandatory) are Spain-specific and are exactly the layer a Morocco fiscal-rules module needs to override rather than inherit.
- **Tax representation is the core blocker**: `invoices.vta` (a general/legacy field) plus three **named** columns `vta4`/`vta10`/`vta21` are the only way a rate is stored — there is no generic "tax rate + base + amount" table at the invoice level (VERI*FACTU has its own parallel `verifactu_record_tax_details` table, but that's a snapshot of an already-issued Spanish record, not something the ordinary invoice/quote flow reads or writes to). `QuoteToInvoiceService` explicitly filters cart lines by `=== 4`, `=== 10`, `=== 21`; any other rate on a cart line is silently dropped from the invoice's tax columns (though it still flows into `sub_total`/`total`).
- **Line items already carry a flat, rate-agnostic `vta` column** (`Cart.vta`, `Item.vta`) — the rate-bucketing problem is introduced only when a `Quote`/`Invoice` aggregates cart lines into named columns, not at the line-item level. This matters for the proposed architecture in §10: the line-item layer needs no change at all.
- **PDF rendering of taxes is already mostly rate-agnostic** (see §8) — the one place hardcoded to 4/10/21 is the legacy `tachua.blade.php` view, which is dead-end/per-tenant code, not the shared template engine.

## 8. Current PDF architecture

- Shared engine: `pdf/document.blade.php` (layout) + `pdf/components/{_header,_addresses,_items,_totals,_footer}.blade.php`, driven by `app/Services/Pdf/TemplateRendererService.php` (DomPDF). A second, standalone `pdf/quote.blade.php` duplicates the same logic independently (not confirmed wired to any controller — `QuotePdfService` uses the shared engine, so `pdf/quote.blade.php`'s status is unclear and worth a follow-up check, not urgent for this audit).
- **Field labels are properly translation-driven** (`__('invoice.*')`/`__('quote.*')`, backed by `resources/lang/{es,fr,en}/{invoice,quote}.php`) — no raw Spanish label text in the active templates.
- **Date formatting is already locale-branching and already correct for French** (`'fr' => 'd/m/Y'`) — no change needed once locale resolution itself is fixed (see next point).
- **Two real gaps**: (1) `$locale` is taken from `app()->getLocale()` — i.e. whichever staff user is logged in — never from `$company->locale`/`$company->country_code`, so a Moroccan tenant's invoice language today depends on accident, not configuration; `company_profiles.locale` is dead schema, never read. (2) `$formatMoney` hardcodes `' €'` regardless of `$company->currency`.
- **No VERI*FACTU visual footprint exists in the PDF today** (no QR, no hash/CSV display) — good news for isolation: nothing needs to be hidden from Moroccan PDFs, because nothing Spain-specific is rendered there yet.
- Two **legacy, host-string-hardcoded, per-tenant Blade views** bypass the engine entirely (`invoices/yassine.blade.php`, `invoices/tachua.blade.php`) — these are pre-existing technical debt independent of Morocco, but should not be used as a template for how Morocco-specific rendering is added (they're exactly the kind of per-tenant hardcoding the shared engine was presumably built to replace).

## 9. Current subscription/pricing architecture

- No Laravel Cashier — a custom integration directly on `stripe/stripe-php`. Plans are DB rows (`plans` table, central DB) with **one price pair (monthly/yearly) and one Stripe Price ID pair per plan row** — i.e. one currency per plan, fixed at Stripe-object-creation time in the Stripe Dashboard.
- `plans.currency` defaults to `'eur'` at the schema, seeder, and `PlanController` fallback level. `Plan::formattedPrice()` always uses comma-decimal formatting regardless of tenant locale.
- The dedicated `/pricing` page **does** read `$plan->currency` and would print `MAD` for a MAD-priced plan (not silently wrong), but only maps `EUR` → `€`; every other currency falls back to a bare code with no symbol. The **homepage** pricing widget, by contrast, hardcodes `€` unconditionally — an inconsistency between the two pricing surfaces.
- No per-tenant/per-country plan concept exists (no `country` column on `plans`/`plan_features`, no filtering by tenant country anywhere). An admin could create a MAD-priced plan row today via Filament, but nothing would automatically show the right plan set to the right tenant, and the homepage would still render `€` on it.
- Stripe Connect (tenant → their own end-customers, i.e. invoice payment links) already reads `$company->currency` correctly (`StripeConnectService.php`, `PaymentController.php`) — this part is already currency-aware; it's specifically the **subscription/platform-billing** side that isn't.

## 10. Proposed country-aware architecture

**Guiding principle (matches your stated preference): one core invoice system, with country-specific fiscal rule layers on top — not parallel invoice systems.**

Proposed shape, at the conceptual level (interfaces/placeholders only where they genuinely help — no speculative abstraction beyond what's justified):

```
Core (country-agnostic)
├── Invoice/Quote lifecycle (draft/issue/pay/cancel) — already generic, keep as-is
├── Numbering service (sequence + template) — already generic, keep as-is
├── Line items (Cart/Item, flat per-line tax rate) — already generic, keep as-is
├── CompanyProfile + Customer — country_code-aware, extended with per-country optional fiscal fields
└── FiscalComplianceProvider (new, thin interface — see §16)
    ├── SpainFiscalRules  → wraps existing: F1/NIF-required-to-issue, RD1619 rectification-series
    │                        mandate, VERI*FACTU (VerifactuChainService et al., untouched)
    └── MoroccoFiscalRules → new, minimal: which fields are required to issue, TVA rate set
                              (config-driven, not hardcoded — see §11), no DGI submission yet
```

Concretely:

1. **`company_profiles.country_code` becomes the single source of truth for "which fiscal ruleset applies to this tenant."** Consolidate the redundant `country` free-text column into it (display label derived from `country_code`, not stored separately) as part of this work, not as a separate cleanup.
2. **Tax rates move from named columns to a small, generic, country-scoped rate concept.** The minimal version that doesn't disrupt the existing `vta4/vta10/vta21` data: keep those columns for existing (Spanish) invoices, and add a generic mechanism for new rate sets — either (a) a `tax_rates` reference table keyed by `country_code` (e.g. `MA → [20,14,10,7,0]`) that drives the UI dropdown and a new generic `invoice_tax_breakdown` table (mirroring the *shape* of `verifactu_record_tax_details` but for ordinary, non-VERI*FACTU invoices), or (b) a lighter JSON column on `Invoice` for the tax breakdown, if a full breakdown table is judged premature. This is the one piece of this whole audit that needs an actual design decision before Phase 1C starts — flagged here, decided later, not decided in this document.
3. **`FiscalComplianceProvider` interface**: a genuinely thin contract — something like "does this invoice need pre-issuance validation beyond the generic rules, and does issuing/cancelling it need to trigger any external submission." `SpainFiscalRules` becomes a documented, minimal adapter around the *existing* Spain-only checks already in `InvoiceController`/`InvoiceRectificationService`/`VerifactuChainService` (moving call sites, not rewriting logic). `MoroccoFiscalRules` starts as a no-op/minimal-validation implementation (no DGI submission — see §16). This interface is justified because it's exactly the seam §9/§15 of your brief already asks for (VERI*FACTU scoping, future DGI) — it is not speculative, it's the same seam needed for two already-requested purposes.
4. **Currency**: `company_profiles.currency` (already present) becomes authoritative for all money formatting. `TemplateRendererService::$formatMoney`, the invoice/quote email Blade views, and the homepage/pricing views all read it instead of hardcoding `€`. A small currency→symbol map (`EUR→€`, `MAD→DH` or `MAD→د.م.` depending on locale — needs a product decision, not a legal one) replaces the current `pricing.blade.php` EUR-only special case.
5. **Locale**: PDF/email rendering reads `company_profiles.locale` (currently dead) instead of `app()->getLocale()`, so a Moroccan tenant's documents are French by configuration, not by which staff member is logged in. The public-site and admin-SPA locale switchers are unaffected (they're for the *person browsing*, not the *document being generated*).
6. **VERI*FACTU**: scoped entirely by `company_profiles.country_code === 'ES'` at every current entry point (§15) — no change to any VERI*FACTU internals.

## 11. Proposed Morocco fields

Per your instruction, every field is classified rather than assumed. **None of these classifications are backed by a Moroccan primary-source legal review in this phase** — that review is recommended explicitly before Phase 1B locks in which fields are mandatory (see §17 risks).

| Field | Classification | Rationale |
|---|---|---|
| **ICE** (Identifiant Commun de l'Entreprise) | **A** — clearly necessary | Already partially implemented (`Customer.ice`); it is the standard, widely-known Moroccan cross-administration business identifier appearing on essentially all Moroccan commercial documents. Needs to be added to `CompanyProfile` (currently only on `Customer`) since the *tenant itself* (the issuer) needs one too. Exact legal citation for "mandatory on every invoice" not verified here — recommend a short, targeted legal check (this one field, not a full audit) before making it a hard-required field at issuance. |
| **IF** (Identifiant Fiscal) | **A** — clearly necessary | The DGI-assigned tax identification number; near-universally required on Moroccan invoices in commercial practice. No dedicated column exists today (`CompanyProfile.tax_id` is generic enough to reuse, but a distinct `if_number` field is cleaner than overloading the same column three different countries already share). Legal-citation verification recommended, same as ICE. |
| **RC** (Registre de Commerce number + city) | **B** — useful/commonly included | Commonly printed on Moroccan invoices/letterhead, but "commonly included" and "legally mandatory on every invoice" are not the same claim — needs verification before being required. |
| **Patente / Taxe professionnelle number** | **C** — needs legal verification | Appears on some Moroccan invoices in practice but is far less consistently documented than ICE/IF in general references; do not implement without a dedicated check. |
| **CNSS number** | **C** — needs legal verification, likely not applicable to invoicing | CNSS is a social-security/payroll identifier; it is not commonly an invoice field (it belongs on payslips/social declarations, not sales invoices). Recommend explicitly *not* adding this to the invoice-facing `CompanyProfile` fields unless a specific legal citation says otherwise — listed here only because your brief mentioned it as "where appropriate," and the honest answer is "probably not appropriate for invoicing." |
| **Capital social** (share capital, for SARL/SA) | **C** — needs legal verification | Common on French/Moroccan commercial-law documents for certain company forms, not universal (doesn't apply to a sole proprietor / auto-entrepreneur). Needs verification of exactly which legal forms require it and whether it belongs on the invoice itself vs. just the company's registration documents. |
| **Address / city / country** | **A** — already exists, generic | No new field needed — `CompanyProfile.address_line1/2, city, postal_code, country_code` already cover this generically. |
| **Phone / email / website** | **A** — already exists, generic | No new field needed. |
| **Bank information (RIB)** | **B** — useful/optional | `bank_name/iban/swift` already exist and are structurally reusable; only the UI label ("RIB" instead of "IBAN") may need to vary by country — not a schema change. |
| **Currency (MAD)** | **A** — already exists as a generic field | No new column; just a different value in the already-existing `currency` field, plus the rendering fixes in §10. |

**Recommendation**: before Phase 1B, do a short, targeted, source-cited pass specifically on "which of ICE/IF/RC/Patente is legally required on a Moroccan sales invoice, and under what conditions" — the same rigor already applied to Spain's VERI*FACTU work (BOE/AEAT citations), scoped down to just these four fields rather than a full Moroccan CGI review. That's a small, well-bounded task, not a blocker to starting Phase 1A (locale/currency/country foundation), which needs none of this.

## 12. Proposed database migrations

**No destructive migrations are proposed anywhere in this plan.** Every item below is additive (new nullable column, new table with a sensible default) or a *default value* change for **new** rows only — never a backfill that rewrites existing Spanish tenant data.

| # | Migration (proposed, not created) | What it does | Destructive? |
|---|---|---|---|
| 1 | Change new-row defaults only (no data touched) | New tenants provisioned after this phase should default to `country=MA, currency=MAD, language=fr, timezone=Africa/Casablanca` — this is a **code change in `TenantProvisioningService`/`CreateTenant.php` and the `OnboardingController` wizard**, not a schema migration; existing rows are untouched because defaults only apply to `INSERT`, and even those are only Laravel/Filament-level defaults, not DB-level, so nothing here needs a migration at all if implemented at the application layer (recommended) rather than by changing the column's DB default (also possible, lower-risk than it sounds since a changed column default never touches existing rows either, but the application-layer approach keeps intent visible in one place) |
| 2 | Add `if_number` (Identifiant Fiscal) to `company_profiles` | nullable string, no default | No |
| 3 | Add `rc_number`, `rc_city` (Registre de Commerce) to `company_profiles`, if §11's legal check confirms they're needed | nullable strings | No |
| 4 | Add `ice` to `company_profiles` (mirroring the existing `Customer.ice`) | nullable string | No |
| 5 | Consolidate `country`/`country_code` redundancy | Either drop the free-text `country` column (only after confirming nothing reads it besides the one PDF-rendering reference found — `TemplateRendererService.php:118`, which should be repointed at a `country_code`-derived label first) or leave both and just stop writing to `country` going forward | Only "destructive" in the narrow sense of a column drop — recommend doing this as its own tiny, reversible, well-tested migration, separate from any Morocco-specific migration, and only after confirming the one call site is repointed |
| 6 | New generic tax-breakdown mechanism (table or JSON column — design decision flagged in §10) | Additive only; existing `vta/vta4/vta10/vta21` columns stay exactly as they are for every existing invoice | No |
| 7 | New `tax_rates` reference table (if the table-based design from §10 is chosen), seeded with **Moroccan rates only after they're confirmed**, not guessed | New table | No |
| 8 | New `verifactu` feature slug in the existing `features`/`plan_features` tables (see §15) | Additive seed row | No |
| 9 | Any Arabic-locale-related schema change (none identified — locale is already a free string column everywhere) | N/A | N/A |

**Backward compatibility**: since VERI*FACTU tables have no `tenant_id` column at all (isolation is structural — each tenant has its own database), and since every proposed change above is additive, **existing Spanish tenants and their VERI*FACTU records are never touched by any part of this plan.**

## 13. Proposed onboarding changes

- Change the Filament `CreateTenant` wizard's defaults (`country`, `timezone`, `currency`, `language` selects) from Spain to Morocco, per your instruction ("for the initial launch, Morocco should be the default country"). Spain remains fully selectable in the same dropdowns — nothing is removed, only the default selection changes.
- Same default change in `OnboardingController`'s wizard (the tenant-admin-facing setup flow) — currently accepts `country`/`currency` as user input already, so this is a default-value change on the form, not new logic.
- Fix the `CompanyProfile::firstOrCreate([], ['legal_name' => ''])` call sites flagged in the audit (`StripeConnectController.php`, `PaymentController.php`, `StripeConnectService.php`, `OnboardingController.php`) that can silently create a `CompanyProfile` row with schema defaults (currently Spain) *before* onboarding has run — this is a pre-existing latent bug, not something Morocco introduces, but it becomes actively harmful once the default flips (a Moroccan tenant could get an accidental Spanish-defaulted profile if one of these paths fires first). Recommend auditing call order, not necessarily changing the defaults resolution itself.
- No changes needed to the actual field validation in `OnboardingController` — it's already generic (`country`, `currency` are freely accepted strings/selects, not Spain-locked).

## 14. Proposed UI changes

- **Onboarding wizard** (`resources/js/views/admin/Onboarding.vue`): currency `<select>` currently has only `<option value="EUR">` — needs a `MAD` option (and ideally is driven by a small shared currency list rather than a second hardcoded option, so the next country doesn't repeat this gap).
- **Settings** (`settings.vue`): currency/timezone inputs already accept free text/placeholders (not hard-restricted to EUR/Madrid) — mostly fine as-is; the VERI*FACTU section needs the visibility gate from §15.
- **Item/price forms** (`items/create.vue`, `items/edit.vue`): currency prefix currently hardcoded to `'EUR'` string — should read the tenant's `company_profiles.currency` instead.
- **Dashboard** (`dashboard.vue`): `currency: "EUR"` and the `es-ES`-keyed `localeMap` fallback should be driven by the tenant's actual locale/currency, not a hardcoded map that silently defaults non-`{es,en,fr}` locales to Spanish formatting.
- **`templates.vue:963`**: `new Date().toLocaleDateString("es-ES")` ignores the current locale entirely — should use the active locale.
- **Public site**: language switcher already supports `fr` — no new switcher UI needed for the French-default change itself, only the default and the content changes in §3/§12(website).
- **Homepage**: the permanent Spain/VERI\*FACTU section (§2 item 10) needs to become conditional — shown only when the visitor's context indicates Spain (this is a marketing/geo decision, not a technical blocker; simplest correct approach is likely a country-specific landing route rather than client-side hiding, to avoid ever flashing Spain-only claims to a Moroccan visitor).
- **Meta tags** (`layouts/master.blade.php:20-27`): move the hardcoded Spanish `<meta description/keywords>`/`og:*` tags into the existing `site.php` lang files so they actually localize instead of staying Spanish under every locale.

## 15. Proposed VERI*FACTU isolation strategy

**Nothing in `app/Services/Verifactu/*`, `app/Models/Verifactu/*`, `app/Jobs/SendVerifactuRecordToAeatJob.php`, or their tests changes.** The isolation is entirely about *who can reach* this code, not the code itself:

1. **Backend routes** (`routes/tenant_api.php:69-71`, the three certificate endpoints): wrap in a check against `CompanyProfile::first()->country_code === 'ES'` (or, more robustly, the already-existing `PlanService::hasFeature('verifactu')` pattern — see next point), returning 403/404 for non-Spanish tenants. This is a small addition to the existing route-group middleware chain, not a rewrite.
2. **Recommended mechanism: reuse the existing Plan/Feature system**, not a new bespoke country check. Add a `verifactu` feature slug (`database/seeders/FeatureSeeder.php`, alongside the existing `stripe`/`whatsapp_share`/etc. slugs) attached only to plans available to Spanish tenants, and gate both the backend routes and the Vue settings section with `PlanService::hasFeature('verifactu')`. This piggybacks on a pattern the codebase already trusts and tests, rather than introducing a second, parallel "is this tenant Spanish" check that could drift out of sync with the country-based one. (`country_code === 'ES'` remains a fine *default* for which plans include the feature — the two aren't mutually exclusive.)
3. **Frontend**: `settings.vue`'s VERI*FACTU `<section>` (lines 528-577) and its nav entry (`billingSections` array) both need a `v-if` on the same capability check the backend enforces — today both render unconditionally.
4. **Console command** (`verifactu:aeat-test`): already takes an explicit `{tenant}` argument and is a manually-invoked developer tool, not tenant-self-service — no change strictly needed for isolation (a developer choosing to run it against a non-Spanish tenant would simply fail immediately, since that tenant has no `tax_id`/certificate/AEAT-shaped data), but it's cheap to add the same feature check for consistency.
5. **What Moroccan tenants must never see**, mapped 1:1 to your brief: AEAT settings (gated per point 3), certificate upload (same section), VERI\*FACTU status/actions (there are currently none exposed outside settings — confirmed no invoice-level VERI\*FACTU UI exists anywhere yet, so there's nothing extra to hide there).
6. **Marketing**: the homepage/dedicated VERI\*FACTU page (§14) is a separate, public-facing concern from the tenant-app gating above — handled there, not here.

This is a small, additive change (one feature slug + a handful of `v-if`/route-guard checks) specifically because VERI*FACTU was already architecturally isolated by construction (its own tables, its own services, no shared code paths with ordinary invoicing) — the audit found no entanglement that would make this harder than it sounds.

## 16. Future DGI extension point

Per your explicit instruction: **no endpoint, XML schema, UBL profile, authentication mechanism, certificate requirement, API shape, QR format, or signature scheme is guessed here.** What's proposed is only the seam:

```php
interface FiscalComplianceProvider
{
    // Deliberately minimal — exact method shape TBD when real DGI specs exist.
    // Placeholder only, to give SpainFiscalRules (wrapping existing VERI*FACTU
    // code) and a future MoroccoFiscalRules (no-op today) a common seam.
}
```

- `SpainFiscalRules` would be a thin adapter around the **existing, unmodified** `VerifactuChainService`/`VerifactuSubmissionService` — not a rewrite.
- `MoroccoFiscalRules` today does nothing beyond whatever minimal issuance validation Morocco Phase 1C decides on (§11's confirmed-mandatory fields, if any) — explicitly **not** a DGI client, not a stub that pretends to submit anywhere.
- When official DGI technical specifications become available (endpoint, schema, auth), that becomes its own phase (your "Morocco Phase 2"), built the same way the VERI*FACTU work was: primary sources fetched and cited first, code written against confirmed specs second, never guessed in between.
- This interface is justified now only because §15 and this section independently arrive at needing the same seam — it is not being introduced speculatively ahead of need.

## 17. Risks

- **Legal risk**: shipping any Moroccan fiscal field (ICE/IF/RC placement, mandatory-on-invoice claims) without the targeted legal-source pass recommended in §11 risks either omitting something legally required or presenting something optional as mandatory. Mitigate by doing that small, scoped check before Phase 1B, not by guessing now.
- **Silent-default risk**: the `CompanyProfile::firstOrCreate([], [...])` call sites (§13) could hand a new Moroccan tenant a Spain-defaulted profile if they fire before onboarding completes — needs to be checked, not assumed fixed by just changing the wizard defaults.
- **Tax-breakdown design risk**: §10's tax-rate mechanism (table vs. JSON column) is the one real design decision left open in this document. Getting it wrong is more expensive to unwind than the other items here (it touches `Invoice`, `Quote`, PDF rendering, and reporting) — recommend deciding this explicitly at the start of Phase 1C, with a short spike, rather than defaulting to whichever is fastest to type.
- **Currency-symbol/plan-duplication risk**: supporting MAD subscription billing needs either duplicate Stripe Price objects per plan or a currency-variant mechanism — this is a real scope item for Phase 1D, not a trivial config flip, and has direct revenue/billing consequences if done carelessly (e.g., a MAD-priced plan silently billing in EUR because a Price ID was mis-wired).
- **VERI*FACTU regression risk**: any change to shared code that VERI*FACTU also touches (`CompanyProfile`, `Invoice`, `InvoiceController`) must be re-run against the existing 172-test VERI*FACTU suite before merging (§18) — this is a real risk only if country-aware changes are made carelessly to shared files; the recommended approach (gate at the edges, don't touch `app/Services/Verifactu/*` internals) minimizes this.
- **RTL/Arabic effort risk**: likely larger than it looks from the translation side alone, because of PDF/DomPDF RTL rendering and Vue layout direction — recommend treating Morocco Phase 1E as its own timeboxed spike before committing to a ship date.
- **No self-service signup**: Morocco-as-primary-market presumably implies wanting more organic signups than the current fully-manual Filament-wizard provisioning supports — this audit doesn't propose building self-service signup (out of scope for "audit only"), but flags it as a likely near-term follow-up question, not a Phase 1 blocker.

## 18. Tests required

All additive — nothing in the existing 172-test suite should need to change as a result of this work if the isolation approach in §15 is followed (gate at the edges, don't modify `app/Services/Verifactu/*`).

- **Morocco tenant smoke test**: provision a tenant with `country_code=MA`, confirm default `currency=MAD`, `locale=fr`, `timezone=Africa/Casablanca` land correctly through the (updated) provisioning wizard.
- **Spain tenant smoke test**: same, confirming `country_code=ES` still defaults exactly as it does today — a regression guard for the default-flip in §13.
- **Currency isolation**: a MAD-tenant invoice/PDF/email renders `MAD`/the chosen MAD symbol, never `€`; an EUR-tenant continues to render `€` exactly as today.
- **Locale isolation**: a MA-tenant's PDF/email is French by default regardless of which staff user (with whatever personal `users.locale`) triggers it — this specifically tests the §10 point 5 fix (reading `company_profiles.locale` instead of `app()->getLocale()`).
- **Tax isolation**: a MA-tenant can create an invoice using a Moroccan TVA rate (once §11 confirms the rate set) without touching `vta4/vta10/vta21`; an ES-tenant's existing 4/10/21 flow is byte-for-byte unchanged.
- **Fiscal-field isolation**: MA-tenant `CompanyProfile` can hold `ice`/`if_number` without requiring Spanish `tax_id` format; ES-tenant issuance rules (F1 needs customer tax_id) are unaffected for ES tenants and **do not apply** to MA tenants.
- **PDF differences**: snapshot/diff test that a MA invoice PDF and an ES invoice PDF differ exactly where expected (currency, language, any Morocco-only fields shown) and are identical in layout/structure otherwise.
- **VERI\*FACTU unavailable for MA**: the three certificate routes return 403/404 for a MA tenant; the settings.vue VERI\*FACTU section does not render (component-level test) for a MA tenant/feature-flag state.
- **VERI\*FACTU available for ES**: re-run of the existing suite (`VerifactuCertificateTest`, `VerifactuChainServiceTest`, `VerifactuXmlBuilderTest`, `SendVerifactuRecordToAeatJobTest`, `VerifactuAeatTestCommandTest` — all currently green) plus one new test confirming the feature-gate itself doesn't block a properly-flagged ES tenant.
- **Tenant isolation**: confirm (mirroring the existing `TenantNotFoundTest`/cross-tenant VERI*FACTU tests' pattern) that a MA tenant's data/config can never be read from an ES tenant's context or vice versa — this is largely already guaranteed structurally by database-per-tenant, but worth one explicit test given it's new, country-branching logic living in otherwise-shared code.

## 19. Exact files/classes likely to change

**Backend — country/currency/locale foundation:**
`app/Services/TenantProvisioningService.php`, `app/Filament/Resources/TenantResource/Pages/CreateTenant.php`, `app/Http/Controllers/OnboardingController.php`, `app/Http/Middleware/SetLocale.php` (only if PDF/email locale resolution moves here rather than into `TemplateRendererService`), `config/app.php` (only the *default* value, not the mechanism).

**Backend — CompanyProfile/Customer:**
`app/Models/CompanyProfile.php`, `app/Http/Controllers/CompanyProfileController.php`, new migration(s) per §12, `app/Models/Customer.php` (no schema change expected, `ice` already exists), `app/Http/Requests/CustomerRequest.php` (only if new server-side rules are added for MA-specific fields).

**Backend — invoices/tax:**
`app/Models/Invoice.php`, `app/Http/Controllers/InvoiceController.php` (specifically `issueInvoice()`'s F1/NIF check needs a country branch), `app/Services/InvoiceNumberingService.php` (only if the rectification-series-mandatory rule needs a country branch), `app/Services/InvoiceRectificationService.php`, `app/Services/QuoteToInvoiceService.php` (the `$calcVta(4|10|21)` hardcode), new tax-breakdown table/model per §10's design decision.

**Backend — PDF/currency rendering:**
`app/Services/Pdf/TemplateRendererService.php` (`$formatMoney`, `$locale` resolution), `resources/views/pdf/quote.blade.php`, `resources/views/emails/invoice.blade.php`, `resources/views/pricing.blade.php`, `resources/views/index.blade.php`, `resources/views/layouts/master.blade.php` (meta tags).

**Backend — VERI\*FACTU isolation:**
`routes/tenant_api.php` (3 routes), `database/seeders/FeatureSeeder.php` (+1 slug), `app/Services/PlanService.php` (no change expected, just consumed), `app/Http/Controllers/VerifactuCertificateController.php` (add the gate check).

**Backend — billing:**
`database/seeders/PlanSeeder.php` (new MAD plan rows, once pricing is decided — not part of Phase 1 per your "do not change prices yet"), `app/Http/Controllers/PlanController.php`, `app/Models/Plan.php` (`formattedPrice()`), a currency→symbol map (new small helper).

**Frontend (Vue):**
`resources/js/views/admin/Onboarding.vue`, `resources/js/views/admin/settings.vue` (VERI\*FACTU section gating), `resources/js/views/admin/dashboard.vue`, `resources/js/views/admin/templates.vue`, `resources/js/views/admin/items/{create,edit}.vue`, `resources/js/i18n/index.js` (default locale), `resources/js/main.js` (if the shared money-formatting helper gains currency-awareness).

**Tests (new, additive):**
New feature test files mirroring existing patterns — e.g. `tests/Feature/MoroccoTenantOnboardingTest.php`, `tests/Feature/CurrencyRenderingTest.php`, `tests/Feature/VerifactuCountryGateTest.php` — none of the existing VERI*FACTU test files need modification if §15's edge-gating approach is followed.

## 20. Recommended implementation phases

(Matches the structure you proposed; included here as the audit's own recommendation, not yet started.)

- **Morocco Phase 1A — country/currency/locale foundation.** Provisioning defaults flip to MA/MAD/fr/Africa-Casablanca (Spain stays fully selectable); `company_profiles.locale`/`currency` become the actual source of truth for PDF/email rendering (fixing the two dead-schema/hardcoded-€ gaps); currency→symbol map; onboarding wizard gets a MAD option. No new fiscal fields yet, no tax-rate changes yet, no VERI*FACTU gating yet — this phase is pure plumbing and is the highest-leverage, lowest-risk starting point since it touches shared infrastructure (currency/locale resolution) that every later phase depends on.
- **Morocco Phase 1B — CompanyProfile + customer Moroccan identity.** The scoped legal-source check from §11, then whichever of ICE/IF/RC/etc. it confirms, added as nullable `CompanyProfile` fields (mirroring the already-existing `Customer.ice`).
- **Morocco Phase 1C — Moroccan invoices/taxes/PDF.** The §10 tax-breakdown design decision, Moroccan TVA rate set (only once confirmed, never guessed), F1/NIF-style issuance rule scoped to `country_code === 'ES'`, PDF/label adjustments for Morocco-specific fields.
- **Morocco Phase 1D — onboarding + website + pricing.** Homepage Spain/VERI\*FACTU section made conditional/geo-routed, meta tags localized, MAD subscription plan rows + Stripe Price wiring (pricing decision explicitly deferred to you, per "do not change prices yet").
- **Morocco Phase 1E — Arabic/RTL.** Own timeboxed spike given the DomPDF/Vue-layout risk flagged in §17; translation content itself can start any time since the lang-file structure already supports adding a 4th locale trivially.
- **Morocco Phase 2 — DGI integration**, only when official Moroccan e-invoicing technical specifications are published and fetched as primary sources — built with the same discipline as the VERI*FACTU work (cite BOE/AEAT-equivalent Moroccan sources, never guess a schema/endpoint/cert requirement).

---

## Test suite baseline (run before any implementation)

```
Tests:    172 passed (418 assertions)
Duration: 73.75s
```

**0 failed. 0 pre-existing failures to report** — this is a clean, fully-green baseline to implement Morocco Phase 1A against. No test in the current suite assumes Morocco doesn't exist in a way that would need to change for this plan (the existing VERI*FACTU tests are all Spain-specific by design and are expected to stay green untouched throughout, per §15's edge-gating approach).
