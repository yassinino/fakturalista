# Morocco Phase 1B — Moroccan business & customer identity

**Status: implemented, tested.** Scope: data model + UI + snapshot readiness
for Moroccan business/customer fiscal identity. No DGI integration, no
Moroccan VAT/tax-engine work, no electronic invoicing, no subscription
pricing, no Arabic/RTL, and no invented legal requirement anywhere in
this phase.

---

## 1. Fields already existing (audited first, reused where semantics matched)

| Field | Model | Already existed? | Reused for |
|---|---|---|---|
| `trade_name` | CompanyProfile | Yes | Morocco's "trade/commercial name" (item 1) - no new field needed |
| `website`, `bank_name`/`iban`/`swift` | CompanyProfile | Yes | Reused as-is for Morocco too |
| `address_line1/2`, `city`, `postal_code`, `country`/`country_code` | CompanyProfile | Yes | Reused as-is |
| `registration_number` | CompanyProfile | Yes, but effectively **unused by any backend logic** beyond storage/display (Settings' "Nº Registro Mercantil" field) | **Reused for Morocco's RC (Registre de Commerce)** - same underlying concept (an official commercial-registry number), just labeled differently per country. This avoided adding a third near-duplicate column. |
| `ice` | Customer | Yes (added in an earlier phase) | Unchanged; mirrored onto `CompanyProfile` (see §3) |
| `type` (1=business, 2=individual) | Customer | Yes - already backs `getNameAttribute()` | Reused directly for the "business vs individual" distinction (item 4) via two new helpers, `Customer::isBusiness()`/`isIndividual()` - no new column |
| `tax_id`, `vat_number`, `foreign_tax_id_type`/`foreign_tax_id` | CompanyProfile / Customer | Yes | **Left completely untouched** - these remain Spain/AEAT's own fields (see §9) |

## 2. New database fields

| Column | Table | Purpose | Format/validation |
|---|---|---|---|
| `ice` | `company_profiles` | Identifiant Commun de l'Entreprise - Morocco's primary business identifier, for the tenant itself (the customer-side `ice` already existed) | Nullable string, no format/checksum check |
| `if_number` | `company_profiles` | Identifiant Fiscal - Morocco's own tax-administration identifier. Named distinctly from `tax_id` (Spanish NIF/CIF) and `vat_number` (EU VAT) - it is neither | Nullable string |
| `if_number` | `customers` | Same concept, for a business customer that wants to record its own IF | Nullable string, `max:32` at the request layer |
| `commercial_register` | `customers` | Customer-side RC (the company side reuses `registration_number` instead - see §1) | Nullable string, `max:64` |
| `company_snapshot` | `invoices` | Generic identity snapshot of the issuer at issuance time (see §7) | JSON, nullable |
| `customer_snapshot` | `invoices` | Generic identity snapshot of the customer at issuance time (see §7) | JSON, nullable |

**No column was renamed. No existing value was converted.** `registration_number`'s semantics were judged "truly equivalent" between Spain (Registro Mercantil) and Morocco (RC) - both are "this company's official commercial-registry reference," just phrased differently - so it's reused rather than duplicated, per your explicit instruction to check for equivalence before adding a column.

## 3. Migrations

Three, all additive, all reversible:

- `2026_09_23_100000_add_moroccan_fiscal_fields_to_company_profiles_table.php` - adds `ice`, `if_number`.
- `2026_09_23_100001_add_moroccan_fiscal_fields_to_customers_table.php` - adds `if_number`, `commercial_register`.
- `2026_09_23_100002_add_identity_snapshots_to_invoices_table.php` - adds `company_snapshot`, `customer_snapshot` (JSON, nullable).

No backfill, no default value, no data conversion. Every existing row (Spanish or otherwise) gets `NULL` in every new column until explicitly filled in (Settings/customer form) or, for the snapshot columns, until the next invoice is issued.

## 4. `CompanyProfile` implementation

- `$fillable` gained `ice`, `if_number`.
- New `identitySnapshot(): array` method - returns exactly the identity-relevant fields (name, country_code, tax_id, vat_number, registration_number, ice, if_number, email, phone, website, address). This is what gets frozen onto an invoice at issuance (§7) and what the PDF reads for an issued invoice.
- `CompanyProfileController::update()` validates `ice`/`if_number` as `nullable|string|max:255` (matching the existing `tax_id`/`vat_number` convention on this model - no format guessed) and trims all four identifier fields (`ice`, `if_number`, `registration_number`, `tax_id`) before saving.

## 5. `Customer` implementation

- `$fillable` gained `if_number`, `commercial_register`.
- New `isBusiness()`/`isIndividual()` helpers, reusing the existing `type` column (no new column - item 4's "clean distinction" was already modeled).
- New `identitySnapshot(): array` method, mirroring `CompanyProfile`'s.
- `CustomerRequest` gained `ice`/`if_number`/`commercial_register` validation (previously **`ice` had no validation rule at all** - a pre-existing gap, closed here) plus a `prepareForValidation()` step that trims all four identifier fields.
- `CustomerController`'s `store()`/`update()`/`show()`/`edit()` (all of which use **explicit field arrays**, not mass-assignment) were each updated to read/write `if_number`/`commercial_register` alongside the existing `ice`.

**No customer is ever required to have ICE, IF, or RC.** Confirmed by test G - a customer with none of the three still creates successfully.

## 6. Invoice issuance — a real bug found and fixed

Testing item H (`issue()` must not be blocked by a missing customer ICE) surfaced a **second, deeper Spain-only assumption** in `InvoiceController::issueInvoice()`, beyond the one already fixed in Phase 1A.1:

```php
if (empty($company?->tax_id)) {
    throw new \RuntimeException('Tu empresa no tiene un NIF/CIF configurado...');
}
```

This unconditionally required the **issuer's own** `tax_id` to issue *any* invoice - correct for Spain, but wrong for Morocco, whose equivalent concept is `ice`, not `tax_id`. Left as-is, this would have silently blocked every Moroccan tenant from ever issuing an invoice, since the new country-aware Settings UI (§8) no longer even shows a `tax_id` field to a Moroccan tenant.

**Fixed the same way as the F1/customer-NIF check** (Phase 1A.1): scoped to `TenantContextService::isSpain()`. Morocco is deliberately **not** given a replacement "must have ICE" requirement - per your explicit "do not create blanket validation" instruction, a Moroccan tenant can issue an invoice with no fiscal identifier configured at all today. Whether Morocco should eventually require something here is a Phase 1C/legal-verification question, not assumed here.

```php
if ($this->tenantContext->isSpain() && empty($company?->tax_id)) {
    throw new \RuntimeException(...);
}
```

Spain's exact behavior is unchanged (still tested green: `InvoiceLifecycleTest::cannot_issue_without_company_tax_id`).

## 7. Invoice snapshot implementation

**A real, pre-existing gap was found and closed, for every tenant, not just Morocco.** Auditing "the current immutable invoice snapshot architecture" (as instructed) found that **no such generic layer existed**. `invoices` had no company/customer identity columns at all; the PDF always re-read the *live* `CompanyProfile::first()` and `$invoice->customer` relation. The only existing snapshot mechanism (`verifactu_records`' own snapshot fields) is VERI\*FACTU-specific, populated only for a Spanish tenant's AEAT submission XML, and isn't even wired into live invoice issuance (confirmed unchanged, again, in this phase).

This phase builds the missing generic layer:

- `company_snapshot` / `customer_snapshot` (JSON) added to `invoices`.
- Written **once**, inside `InvoiceController::issueInvoice()`'s existing `DB::transaction()`, using `CompanyProfile::identitySnapshot()` / `Customer::identitySnapshot()` - right where numbering is assigned and status flips to `issued`. Never touched again after that.
- **Not coupled to VERI\*FACTU** in any way - it exists and is populated for every tenant regardless of country or whether VERI\*FACTU is even reachable for them.
- Existing, already-issued invoices get `NULL` in both columns (no backfill - see §3) - rendering falls back to live data for those, exactly as it always has.

Tests I/J/K prove: a Moroccan invoice snapshots seller ICE/IF/RC at issuance; editing `CompanyProfile` a month later does not change the historical invoice's snapshot; editing the customer afterward does not change the historical invoice's customer identity either.

## 8. Country-aware UI

Reused the exact same client-side boundary from Phase 1A/1A.1 (`useTemplateStore().company.country`, or `form.country_code` where the settings form itself already has it loaded) - no new abstraction introduced.

- **Settings → Tax & Legal**: for `country_code === 'MA'`, shows ICE / IF (Identifiant Fiscal) / RC (Registre de Commerce, backed by `registration_number`). For everyone else, shows the unchanged NIF / VAT / Registro Mercantil. Never both at once.
- **Customer create/edit**: for a Moroccan tenant, shows ICE (prominent, first) / IF / RC, and **omits** the NIF field and the AEAT-specific "foreign customer" toggle entirely (per your "avoid creating Spain terminology in Moroccan forms" instruction). For everyone else, the form is pixel-identical to before.
- **PDF** (see §9 below): country-aware identity block, driven by the same `country_code` carried in the snapshot/live company data.

## 9. Onboarding

- `OnboardingController::show()` now also returns `country_code` (via `TenantContextService`), so the wizard knows whether to show ICE or NIF/VAT **before any `CompanyProfile` row exists yet** - resolved from the tenant's own provisioning-time value, not guessed.
- `Onboarding.vue` fetches this explicitly in `onMounted()` (deliberately not read from the Pinia store's `company.country`: this is the very first authenticated screen a brand-new tenant sees, so the store's `company_context` - normally populated by `/user`, fetched elsewhere in the app - is not guaranteed to have loaded yet here).
- For Morocco: only **ICE** is collected (optional, matching how `tax_id` was already optional for Spain in this same form) - address/city/phone/email fields are unchanged and already collected for everyone. IF and RC are **not** requested here, per your explicit "complete later in Settings" instruction.
- No VERI\*FACTU-related field was ever requested here for any country - unaffected, confirmed unchanged.

## 10. PDF changes

**Not a redesign** - the existing `pdf/document.blade.php` + components layout is untouched; only the identity block's *source* and *content* changed:

- `TemplateRendererService::render()` now resolves `$companyIdentity`/`$customerIdentity` **snapshot-first** for an issued invoice (falling back to live `CompanyProfile`/`Customer` data for quotes, drafts, or any invoice issued before this snapshot existed) - `$companyName`/`$companyAddress` are now built from this resolved identity, not a direct live read.
- `_header.blade.php`: for `country_code === 'MA'`, shows ICE / IF / RC (via 3 new lang keys: `invoice.ice`, `invoice.if_number`, `invoice.rc`, added to `en`/`es`/`fr`); otherwise shows the unchanged NIF line.
- `_addresses.blade.php`: the customer's tax-identity line does the same ICE-vs-NIF branching, and the customer's name/address/phone/reference now read from `$customerIdentity` (snapshot-first) instead of the live `$document->customer` relation.
- **No Moroccan VAT presentation, no QR code, no DGI/XML reference was touched or added** - out of scope, confirmed.
- A dedicated test (`issued_moroccan_invoice_pdf_renders_the_ice_if_and_rc_block_without_crashing`) renders a real issued Moroccan invoice's PDF end-to-end and asserts valid PDF output.

## 11. Spain / VERI\*FACTU compatibility

- **Nothing inside `app/Services/Verifactu/*` or `app/Models/Verifactu/*` was touched.**
- Spain's own identity fields (`tax_id`, `vat_number`, `foreign_tax_id_type`/`foreign_tax_id`) are completely unchanged in name, validation, or behavior.
- `VerifactuChainService::requireNif()` and `resolveDestinatario()` still read only `tax_id`/`foreign_tax_id_type`/`foreign_tax_id` - confirmed via direct code search that no VERI\*FACTU file references `ice`, `if_number`, `commercial_register`, `company_snapshot`, or `customer_snapshot`, and no generic `->toArray()` call anywhere in that subsystem could accidentally leak them into AEAT XML.
- Test L goes further: it populates `ice`/`if_number`/`commercial_register` with clearly-marked sentinel values on a **Spanish** tenant's company and customer (a case that shouldn't occur in practice, since the UI never shows these fields for `country_code=ES`, but is still tested defensively) and asserts none of those values appear anywhere in the generated AEAT XML.
- All 191 tests that existed before this phase (including every VERI\*FACTU test) are unmodified and still green.

## 12. Tests added

15 new tests across 2 new files:

| File | Tests | Covers |
|---|---|---|
| `tests/Feature/MoroccoIdentityTest.php` | 4 | A, B, C, D, E, F, G |
| `tests/Feature/InvoiceIdentitySnapshotTest.php` | 7 | H, I, J, K, L, N, plus a PDF-rendering smoke test |

Plus the pre-existing `InvoiceLifecycleTest::cannot_issue_without_company_tax_id` (unmodified) continues to cover the Spain side of the §6 fix, and the full pre-existing VERI\*FACTU suite covers M.

### Full test suite result

```
Tests:    202 passed (528 assertions)
Duration: 92.97s
```

191 pre-existing (baseline after Phase 1A.1) + 11 new this phase (4 + 7). **0 failed, 0 regressions.**

## 13. Legal assumptions deliberately NOT made

- No ICE/IF/RC format, length, or checksum validation - none of the three was verified against a primary Moroccan legal/administrative source in this phase. Max lengths (`ice`/`if_number`: 32, `commercial_register`: 64 at the customer request layer; 255 at the company layer, matching existing sibling-field convention) are storage-capacity choices, not asserted legal formats.
- No claim that ICE, IF, or RC is *legally mandatory* on a Moroccan invoice - all three remain optional at every layer (schema, validation, issuance).
- No Moroccan checksum/registry-lookup validation (unlike Spain, which has none either for these fields today).
- `registration_number` being reused for RC is a **data-model equivalence judgment** (both are "an official commercial-registry number"), not a claim that Moroccan and Spanish commercial-registry rules are the same.
- CNSS was **not** added anywhere, per your explicit instruction, since no existing product requirement was found to need it.

---

## Deferred to Morocco Phase 1C

- Moroccan VAT (TVA) architecture - rate set, how it's stored, how it interacts with the existing `vta4/vta10/vta21` columns.
- Invoice mandatory fiscal content for Morocco (what a Moroccan invoice must legally contain, beyond identity).
- Tax rates, exemptions, and any withholding/retention (retenue à la source) if applicable.
- Invoice numbering/legal rules requiring Moroccan-specific verification (today's numbering mechanism is country-agnostic and works for Morocco as-is, but no one has verified whether Morocco has its own sequencing/series requirements analogous to RD 1619/2012).
- Final Moroccan PDF layout/terminology beyond the minimal identity block added here.

## Deferred to a future DGI phase

- DGI API integration.
- Structured electronic invoice format (UBL or otherwise).
- Electronic signature.
- Submission/validation flow.
- QR code, if Morocco's e-invoicing framework ends up requiring one.
- DGI authentication/certificate model.

None of the above was guessed, stubbed, or scaffolded in this phase.
