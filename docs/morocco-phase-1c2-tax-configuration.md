# Morocco Phase 1C.2 — Country-aware tax configuration

## Scope and architecture

This phase completes the existing partial tax-configuration implementation on top of Phase 1C.1. `DocumentCalculationService` remains the authoritative, country-neutral calculator. Invoice and quote writes recompute subtotal, discounts, taxable bases, bucket amounts, total tax and grand total from line inputs; client-supplied totals are ignored. The frontend calculations are previews.

`app/Services/Tax/TaxPresetService.php` is the single catalog of selectable country presets. It resolves the current country through `TenantContextService`, and resolves the saved default from `CompanyProfile.default_tax_code`. A missing, unknown or foreign-country default falls back to the country's system default. Unknown countries receive an empty catalog, not Spanish or Moroccan choices. Add future countries in this catalog.

Authenticated `GET /api/tax-presets` returns `country`, `tax_name`, `presets` (code, country, rate, treatment, label, system-default flag, active flag), and `default_code` (the effective tenant default). The catalog's `is_default` flag identifies the system fallback; consumers use `default_code` for the tenant's choice. Controller dependencies are resolved per request so repeated requests cannot retain a previous country's context.

Vue's `useTaxPresets` fetches that endpoint; `TaxSelect` is shared by the four invoice/quote create/edit forms and the two item create/edit forms. Settings reads the same endpoint. No Vue component contains a country/rate catalog. Preset-load failures display a retry action and prevent saving uninitialized new taxes.

## Country presets

| Country | Code | Label | Rate | Treatment | System default |
| --- | --- | --- | --- | --- | --- |
| MA | MA_TVA_20 | TVA 20% | 20 | taxable | Yes |
| MA | MA_TVA_10 | TVA 10% | 10 | taxable | No |
| MA | MA_EXEMPT | Exonéré | 0 | exempt | No |
| ES | ES_IVA_21 | IVA 21% | 21 | taxable | Yes |
| ES | ES_IVA_10 | IVA 10% | 10 | taxable | No |
| ES | ES_IVA_4 | IVA 4% | 4 | taxable | No |

These are the requested system options. Choosing the legally appropriate option remains the user's responsibility; no automatic tax determination is implemented.

## Treatment persistence and calculation

`TaxTreatment` defines `taxable`, `exempt`, and `out_of_scope`. Only taxable and exempt have presets in this phase. Treatment is independent of numeric rate and is persisted in:

- `items.tax_treatment`: the catalog item's configured default.
- `carts.tax_treatment`: the invoice or quote line's own stored treatment.
- `invoice_tax_lines.treatment`: the invoice's persisted tax bucket.

The calculator groups by `(rate, treatment)`, so taxable 0%, exempt and out-of-scope rows remain separate even though all produce zero tax. A missing legacy treatment means `taxable`; no code infers exemption from a historical zero rate. HTTP validation and the calculator reject non-taxable treatments with nonzero rates. The calculator rejects unknown treatments.

The pre-existing generic numeric API remains compatible with Phase 1C.1; the catalog limits UI choices, not the generic calculator. There is no custom-rate creation UI or user-managed rate catalog. The legacy `vta4`, `vta10`, and `vta21` columns remain the Spanish compatibility bridge. A Moroccan 20% bucket is retained in generic tax lines and never assigned to a Spanish legacy bucket.

## Settings and defaults

Settings contains a simple Taxes section listing available presets and a Default tax selector. The existing Settings save flow persists `default_tax_code`, validated against the submitted company country. A country change must be saved before selecting that country's taxes; the old default is cleared in that save and presets reload.

The effective default initializes only unset new document lines and new items. Saving the default updates the company profile only. It does not update documents, tax lines, totals, existing items or historical invoices. An already populated form line keeps its tax when presets are refreshed.

## Items/services

New items initialize from the tenant default, with another preset selectable. Server-side item creation also applies the default if no tax was submitted. Existing item edits preserve the stored rate and treatment when those fields are omitted. An explicit taxable zero is never inferred to be exempt.

Selecting a catalog item on an invoice or quote copies its configured rate and treatment. Users can then override the line via the shared selector. Invoice AI catalog matching uses the same selection function. Quote writes retain the selected item reference as well as its copied tax fields.

The existing new-item page and navigation were already present, but the POST item route was disabled. This phase restores that route; existing authentication, onboarding, subscription and product-plan checks remain in effect.

## Invoice and quote behavior

Both document types fetch the same country presets and use the same selector. Selection changes rate and treatment together. New manual lines use the effective tenant default. Existing lines are initialized exclusively from their stored values. A saved value outside today's presets is displayed as a disabled saved option; it is not silently changed or offered as a new custom rate.

The summary preview iterates actual rate/treatment buckets, including 20% and exemptions. It no longer hardcodes three Spanish buckets or submits legacy per-rate totals. The backend continues computing the legacy bridge itself.

Invoice save, draft edit and duplication persist treatment. Quote save/edit/duplication retain line treatment, and quote-to-invoice conversion passes it through the calculator into invoice lines and tax buckets. Issued invoices remain locked against financial edits.

## PDF behavior

Invoice PDFs read persisted generic tax buckets, including exact decimal rates, bases, amounts and treatment. They never use the current tax default to interpret existing lines. Morocco displays `Sous-total HT`, `TVA 20%`, `TVA 10%`, `Total TVA`, and `Total TTC` as applicable. Exempt rows and line items display `Exonéré`. Spain continues to display IVA rates and its existing subtotal/total labels.

Tax naming uses the issued invoice's company-country snapshot when present. Later company-country/default changes do not turn an issued Moroccan invoice's TVA labels into IVA. The existing locale/currency behavior from Phase 1A is otherwise unchanged.

Quote PDFs derive their mutable breakdown through `DocumentCalculationService`, including both line and header discounts. Historical invoices without generic tax lines use their persisted Spanish compatibility amounts rather than recomputing them from current lines. Header totals are always the document's stored values. Rendering performs no financial writes.

## Historical and VERI*FACTU safety

There is no data migration recalculating old invoices and no Settings-triggered backfill. Existing numeric rates and totals remain untouched. Previously absent treatments default to `taxable`, preserving the distinction from deliberately selected exemptions going forward.

No VERI*FACTU implementation files were changed. Existing country gates remain in force, and the existing reconciliation check rejects a Moroccan 20% bucket in a Spanish AEAT mapping instead of omitting or relabeling it. The full suite includes the existing XML, chain, submission, certificate, country-gate and generic-tax regression tests.

## Additive tenant migrations

The two migrations already present in the partial Phase 1C.2 work are retained:

1. `database/migrations/tenant/2026_09_25_100000_add_tax_treatment_to_carts_items_and_tax_lines.php`: adds generic treatment strings with the legacy-compatible `taxable` default.
2. `database/migrations/tenant/2026_09_25_100001_add_default_tax_code_to_company_profiles_table.php`: adds the nullable tenant default preset code.

No Morocco-specific tax columns, removed compatibility columns, or historical recalculation/backfill are introduced. Both migrations run on fresh test tenants. Existing deployed tenants still need the normal tenant migration rollout before using the feature; no existing tenant databases were migrated as part of this task.

## Validation

Starting baseline: **223 backend tests passed**, 611 assertions. The sandbox initially blocked local MySQL; database suites were then run with approved local database access.

New backend coverage in `tests/Feature/CountryTaxConfigurationTest.php` and `tests/Unit/TaxTreatmentTest.php` includes:

- MA/ES presets and defaults, exact country isolation, and unsupported-country behavior.
- 5,000 MAD at 20%, 10%, and exempt; mixed 20%/10%; ES 21% and mixed 4%/10%/21%.
- Server authority over false submitted totals, tax bases and stored breakdowns.
- Separate taxable-zero/exempt buckets; treatment preservation through edit, duplicate and conversion.
- Shared quote preset input; service default inheritance, exemption override and item propagation to both documents.
- Settings default persistence, rejection of foreign presets, stale-default fallback, unchanged drafts and locked issued invoices.
- Actual PDF generation plus view-data/HTML assertions for Morocco, Spain, exemptions, quote discounts, historical stored amounts and country snapshots.
- Invalid treatment/rate combinations and generic out-of-scope semantics.

`tests/Frontend/tax.test.mjs` exercises actual Vue selector rendering, item tax copying, generic preview buckets, saved legacy zero handling, country option isolation, asynchronous default loading, preservation of existing lines and retry behavior. It uses installed Vue compiler/server-renderer and Node's test runner without adding dependencies.

Required commands:

```sh
php artisan test --compact
node --test tests/Frontend/tax.test.mjs
npm run build
```

Final results:

- Full backend suite: **248 passed, 0 failed, 819 assertions** (127.08 seconds).
- Tests added: **25 backend cases** (21 feature cases and 4 unit cases), plus **9 frontend cases**.
- Existing VERI*FACTU and AEAT regression tests: **passed in the full suite**, including the unsupported 20% mapping rejection.
- Frontend tests: **9 passed, 0 failed**.
- Production frontend build: **passed**; generated assets/manifest refreshed.
- PHP syntax checks and `git diff --check`: passed.
- Compared with the session-start file hashes: translation/i18n files and `app/Services/Verifactu/` are unchanged.

## Files involved

Completed or revised in this session:

- `app/Services/Tax/{TaxPresetService,TaxPreset,DocumentCalculationService}.php`
- `app/Rules/TaxRateTreatment.php`
- `app/Http/Controllers/{TaxPresetController,ItemController,QuoteController}.php`
- `app/Http/Requests/{InvoiceRequest,QuoteRequest,ItemRequest}.php`
- `app/Services/Pdf/TemplateRendererService.php`
- `resources/views/pdf/components/{_totals,_items}.blade.php`
- `resources/js/components/TaxSelect.vue`
- `resources/js/composables/useTaxPresets.js`
- `resources/js/utils/tax.mjs`
- `resources/js/views/admin/invoices/{CreateInvoiceForm,EditInvoiceForm}.vue`
- `resources/js/views/admin/quotes/{CreateQuoteForm,EditQuoteForm}.vue`
- `resources/js/views/admin/items/{create,edit}.vue`
- `resources/js/views/admin/settings.vue`
- `routes/tenant_api.php`
- `tests/Feature/CountryTaxConfigurationTest.php`
- `tests/Unit/TaxTreatmentTest.php`
- `tests/Frontend/tax.test.mjs`
- This document and generated `public/build` assets/manifest.

Existing partial-phase work reused: `TaxTreatment`, treatment-aware `DocumentCalculationResult`, `Cart`, `Item`, `InvoiceTaxLine`, `CompanyProfile`, invoice treatment persistence, quote conversion persistence, company-default validation, and the two migrations above. The workspace also contained changes from previous phases; they were preserved.

## Remaining limitations and excluded work

Only MA and ES have presets. Out-of-scope is representable but not offered as a preset. The API's pre-existing generic numeric-rate capability remains; user-created tax configuration is not provided. Quotes do not have a separate persisted tax-bucket table.

No DGI, Moroccan electronic invoicing, UBL, new XML/QR/signature work, VAT withholding, legal exemption references, automatic legal rate selection, accounting/reporting redesign or removal of Spanish compatibility columns is included. No translation dictionaries, locale files or i18n architecture were changed in this session; direct labels are intentional for this phase.

The existing PHPUnit configuration emits a deprecated-schema warning. The frontend build emits existing unresolved-font, CSS-minification syntax and large-chunk warnings. These do not fail the checks. No browser-driven end-to-end test was added; frontend behavior is checked by Vue component/composable tests and the production build.

Work stops at Phase 1C.2.
