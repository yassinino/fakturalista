# Morocco Phase 1A — country/currency/locale foundation

**Status: implemented, tested, awaiting review.** Scope: exactly the
country/currency/locale/timezone foundation approved from
`docs/morocco-phase-1-audit.md`. Nothing under `app/Services/Verifactu/*`,
`app/Models/Verifactu/*`, or their tests was touched. No Moroccan fiscal
field (ICE/IF/RC), no Moroccan tax rate, no DGI code, no Arabic/RTL work,
no subscription-pricing/currency change, and no tax-schema (`vta4/vta10/vta21`)
change were made — all explicitly out of scope for this phase.

---

## 1. Country resolution strategy

New class: **`app/Services/TenantContextService.php`**. Single place the
whole app asks "what country/currency/locale/timezone is this tenant" —
no `if (country === 'MA')` scattered through controllers/components.

```php
$context = app(TenantContextService::class);
$context->country();   // 'MA' | 'ES' | ...
$context->currency();  // 'MAD' | 'EUR' | ...
$context->locale();    // 'fr' | 'es' | 'en' (validated against config('app.supported_locales'))
$context->timezone();  // 'Africa/Casablanca' | 'Europe/Madrid' | ...
$context->isSpain();   // strtoupper(country()) === 'ES'
```

### Authoritative source (explicit, per your request)

1. **`CompanyProfile` (tenant DB) is authoritative once it exists.** It's
   the tenant's own, user-editable fiscal identity (editable in Settings),
   so it wins over everything else — including a tenant's own central
   `Tenant` record, if they've since edited their profile to say
   something different (e.g. a business that changed its declared
   fiscal country).
2. **`Tenant` (central DB) is the fallback** — used only when
   `CompanyProfile` doesn't exist yet, or one of its fields is empty.
   This is the tenant's provisioning-time configuration (set once, by
   whoever provisions the tenant — today only the Filament wizard).
3. **Hardcoded Morocco defaults are the last resort**, reached only when
   neither of the above has a value at all (a tenant with no
   `CompanyProfile` row and no central `Tenant.country/currency/language/timezone`
   set). This is a safety net, not a migration — it never touches an
   existing row (see §9).

Covered by `tests/Feature/MoroccoTenantContextTest.php` (7 tests): fresh
Morocco tenant → MA/MAD/fr/Africa-Casablanca; fresh Spain tenant →
ES/EUR/es/Europe-Madrid; an existing tenant's `CompanyProfile` is never
overwritten by a second `ensureCompanyProfile()` call; `CompanyProfile`
wins over `Tenant` when they disagree; `Tenant` is the fallback when no
`CompanyProfile` exists; Morocco is the final fallback when nothing is
set at all; `isSpain()` behaves correctly both ways.

### `ensureCompanyProfile()` — the actual bug this phase fixes

Several call sites did `CompanyProfile::firstOrCreate([], ['legal_name' => ''])`
directly — including, critically, `OnboardingController::store()`, the
onboarding wizard's own submit handler. Because that call's second
argument never set `country_code`/`currency`/`locale`/`timezone`, a brand
new tenant's profile silently got the table's **Spain-shaped schema
defaults** (`country_code=ES`, `currency=EUR`, `locale=es`,
`timezone=Europe/Madrid`) regardless of what country the tenant actually
was — this was a genuine pre-existing bug (found during the Phase 1
audit, confirmed and fixed here), not something introduced by Morocco.

Fixed by routing every one of those call sites through
`TenantContextService::ensureCompanyProfile()`, which seeds a **new**
row from the tenant's own provisioning-time `country/currency/language/timezone`
instead. It never touches an already-existing row (`firstOrCreate`'s
first argument is `[]`, matching any row — the seed values only apply
on the `create` branch).

Call sites updated: `OnboardingController::show()/store()`,
`CompanyProfileController::getProfile()`, `StripeConnectController`
(×2), `PaymentController` (×3), `StripeConnectService`.

---

## 2. Provisioning changes

**Filament `CreateTenant` wizard** (`app/Filament/Resources/TenantResource/Pages/CreateTenant.php`):
default country/timezone/currency/language flipped from
`ES`/`Europe/Madrid`/`EUR`/`es` to `MA`/`Africa/Casablanca`/`MAD`/`fr`.
Spain (and every other option) remains fully selectable — only the
pre-selected default changed. This is the actual "select Morocco vs.
select Spain" decision point today, since there is still no public
self-service signup (confirmed unchanged by the Phase 1 audit).

**`TenantProvisioningService`**: the first admin `User` row is now
created with `'locale' => $data['language']` explicitly. Without this,
the admin's own personal UI locale would fall back to the `users.locale`
schema default (`'es'`) regardless of the tenant's actual language — a
Moroccan tenant's founder would otherwise see a Spanish admin UI on
their very first login. This was a second, related bug found while
implementing this phase, fixed the same way as the `CompanyProfile` one.

**`OnboardingController::store()`** (the tenant's own admin, post-login
setup wizard): `country_code`/`locale`/`timezone` are **not** form fields
in that wizard — they're inherited from the central `Tenant` record
(already correctly set at the step above), written defensively on every
submit in case a `CompanyProfile` row was created earlier by another
entry point. `country` (free-text address label) and `currency` **are**
explicit choices in that form and always win — the currency `<select>`
already had a Morocco/MAD option before this phase; its default is now
`MAD` instead of forcing an explicit empty choice, and the country
dropdown now pre-selects "Morocco" (Spain remains fully selectable).

**Persistence, not re-inference** (per your explicit instruction):
these values are written once, at `Tenant::create()` /
`ensureCompanyProfile()` / `OnboardingController::store()` time, and read
back from the database afterward — nothing re-derives them from
"selecting Morocco" logic on every request.

---

## 3. Currency

New class: **`app/Services/CurrencyFormatter.php`** — the one centralized
formatter, reused by every fixed location.

```php
$formatter->format(1250.00, 'MAD', 'fr');  // "1 250,00 MAD"
$formatter->format(1250.00, 'EUR', 'es');  // "1.250,00 €"  (existing Spain formatting, unchanged)
$formatter->format(100.00,  'TND', 'en');  // "100.00 TND" - unknown currencies get their plain ISO code, never a guessed symbol
```

Separators come from **locale**, the suffix from **currency** — never a
blanket "replace € with MAD". Covered by
`tests/Feature/CurrencyFormatterTest.php` (4 tests).

**Backend locations fixed** (all now read the tenant's real currency via
`TenantContextService`):
- `app/Services/Pdf/TemplateRendererService.php` — `$formatMoney`/`$formatNumber` (feeds both invoice and quote PDFs).
- `app/Mail/InvoiceEmail.php` / `app/Mail/QuoteEmail.php` + `resources/views/emails/invoice.blade.php` — the total/subtotal amount highlight.

**Frontend locations fixed** — a parallel JS mechanism was added rather
than duplicating logic: `resources/js/main.js` gained two global Vue
helpers, `$toCurrency(value)` (drop-in replacement for the
`$toComma(value) + '&thinsp;€'` pattern used throughout) and
`$currencyLabel()` (just the symbol/code, for standalone display),
both reading the tenant's currency from a new `company` state in the
Pinia store (`resources/js/stores/template.js`), populated from a new
`company_context` block added to the `/login` and `/user` API responses
(`app/Http/Controllers/AuthController.php`). Applied to:
`invoices/{CreateInvoiceForm,EditInvoiceForm,edit}.vue`,
`quotes/{CreateQuoteForm,EditQuoteForm}.vue`, `payments.vue`,
`dashboard.vue`, `items/{create,edit}.vue` (currency field default),
`templates.vue` (the template-design preview's sample amounts).

**Deliberately left untouched** (all pre-existing, not introduced by
Morocco, and out of scope for this phase):
- `resources/views/pricing.blade.php`, `resources/views/index.blade.php`'s
  pricing section, `Plan`/`PlanSeeder`/`PlanController`,
  `resources/js/views/admin/subscription.vue` — **subscription/marketing
  pricing, explicitly excluded** per your instruction ("Marketing pricing
  is NOT part of this currency conversion yet").
- `resources/views/invoices/tachua.blade.php` and
  `resources/views/invoices/yassine.blade.php` — legacy, **host-string-hardcoded**
  per-tenant views for two specific existing hostnames, bypassing the
  shared template engine entirely (`InvoiceController::generateInvoicePdf()`).
  Left alone deliberately: they exist for specific real tenants, and
  touching them risks breaking those tenants' invoices for no benefit to
  the Morocco rollout.
- `resources/views/invoices/show.blade.php` and `resources/views/pdf/quote.blade.php` —
  confirmed dead code (no controller/route references either), left alone.

---

## 4. Locale (business-document language)

**The architectural bug, precisely**: `TemplateRendererService::render()`
used to compute `$locale = app()->getLocale() ?: config('app.locale', 'es')`
— i.e. whichever locale the currently-authenticated **staff member**
happens to have, from the Vue admin SPA's own session/localStorage.
`company_profiles.locale` existed in the schema but was never read
anywhere. So a Moroccan tenant's invoice language depended on an
accident (which staff member clicked "download"), not on the business's
own configuration.

**Fix**: `TemplateRendererService::render()` now resolves `$locale`
(and `$currency`) exclusively via `TenantContextService`, which reads
`CompanyProfile.locale` (falling back to `Tenant.language`, then
`'fr'`) — never `app()->getLocale()`. This is a **different, separate**
locale concept from the staff UI's own preference, which is completely
untouched: `App::setLocale()` is never called by the renderer, so
nothing about a staff member's own session locale changes as a side
effect of generating a PDF.

Covered by `tests/Feature/BusinessDocumentLocaleTest.php` (3 tests): a
Moroccan tenant's PDF resolves to French even when the logged-in staff
user's own UI locale is set to Spanish (and vice versa for a Spanish
tenant with a French-UI staff user), and a third test asserts rendering
a PDF never mutates `App::getLocale()` afterward — i.e. the staff UI
preference genuinely survives untouched.

**What was explicitly NOT changed, and why**: `resources/views/emails/invoice.blade.php`
(the transactional invoice/quote email, shared by `InvoiceEmail`/`QuoteEmail`)
was **already** 100% hardcoded French copy for every tenant, with no
locale branching at all — unlike the PDF, it was never "following the
staff user," because it never varied by anything. This phase fixed its
**currency** (§3) but left its language as-is. Translating this email's
copy into Spanish/English properly is a real, separate piece of work
(it needs correct professional wording, not a mechanical
find-and-replace) and risks shipping poor-quality copy if rushed — it's
called out below as a deferred item rather than silently left broken or
hastily mistranslated.

---

## 5. Morocco as default market

Selecting Morocco (Filament wizard, or its resulting tenant record)
establishes `MA`/`MAD`/`fr`/`Africa/Casablanca`, persisted once at
`Tenant::create()` and then again at `CompanyProfile` creation — never
re-derived. Selecting Spain establishes `ES`/`EUR`/`es`/`Europe/Madrid`
the same way. Both remain fully selectable in every dropdown that
already listed them (`TenantResource::countryOptions()`/`currencyOptions()`
already included Morocco/MAD before this phase, confirmed unchanged).

Also updated for consistency (not strictly the tenant-provisioning path,
but the same "what does a fresh, unconfigured context default to"
question): the admin SPA's own i18n default (`resources/js/i18n/index.js`)
and the `SignIn.vue` locale fallback now default to `'fr'` instead of
`'es'`, for a browser with no stored preference yet (e.g. first visit to
`/login`). Once a user logs in, their own saved `users.locale` still
wins immediately after, exactly as before.

---

## 6. VERI*FACTU gating

**Nothing under `app/Services/Verifactu/*`, `app/Models/Verifactu/*`,
`app/Jobs/SendVerifactuRecordToAeatJob.php`, or their existing tests was
touched.** The isolation is entirely about *who can reach* this code:

- **Backend**: new middleware `app/Http/Middleware/RequireSpainCountry.php`
  (alias `require.spain`), applied to the three certificate routes in
  `routes/tenant_api.php`. Refuses with `403 {"error": "not_available_for_country"}`
  for any tenant where `TenantContextService::isSpain()` is false —
  checked fresh on every request, not cached, and independent of
  whether any certificate data happens to exist (tested explicitly: a
  Moroccan tenant is refused even if a `VerifactuCertificate` row exists
  for its NIF).
- **Frontend**: `resources/js/views/admin/settings.vue` now computes
  `isVerifactuEligible` from the already-loaded `form.country_code`
  (from `/settings`), uses it to both drop the "VERI\*FACTU" entry from
  the Settings nav and to `v-if` the whole section — and skips even
  calling `GET /settings/verifactu/certificate` on mount for an
  ineligible tenant, so a Moroccan tenant never sees a 403 flash either.
- Hiding the UI is explicitly **not** the only protection — the backend
  gate is checked independently and is what actually stops a Moroccan
  tenant manually visiting the URL.

Covered by `tests/Feature/VerifactuCountryGateTest.php` (3 tests):
Moroccan tenant refused on GET/POST/DELETE; Spanish tenant unaffected
(existing behavior, still 200); Moroccan tenant still refused with a
certificate row present. Tenant isolation itself (a Moroccan tenant's
gate decision never affects, or is affected by, a neighboring Spanish
tenant) is guaranteed structurally by the pre-existing database-per-tenant
architecture, and is additionally exercised by the pre-existing
`VerifactuCertificateTest::tenant_a_certificate_cannot_resolve_for_tenant_bs_nif`
test, unmodified and still green.

---

## 7. Migrations

**None.** This phase deliberately needed no schema change — every fix
was either a code-level default (Filament wizard, onboarding form) or a
resolution-order fix (`TenantContextService`, `ensureCompanyProfile()`).
`country_code`/`currency`/`locale`/`timezone` already existed on both
`tenants` and `company_profiles` from prior work. No table was created,
altered, or backfilled.

---

## 8. Backward compatibility

- **No existing tenant was converted.** `ensureCompanyProfile()` only
  ever seeds a **new** row (`firstOrCreate`'s create-branch); every
  existing `CompanyProfile` row already has real, persisted values from
  its own history and is never touched. Explicitly tested
  (`an_existing_spanish_tenant_is_never_converted_to_morocco`).
- **No column default was changed at the database level** — only
  application-level defaults (Filament form defaults, a JS reactive
  default, `config('app.locale')`). An existing row's already-stored
  value is what every read path returns; nothing re-reads a table-level
  default for a row that already exists.
- **VERI\*FACTU is unaffected for Spain** — same routes, same responses,
  same 172 pre-existing tests, still green, for any tenant where
  `country_code === 'ES'`.
- **`config('app.locale')`/`fallback_locale`** changed from `'es'` to
  `'fr'` — this is the *global, no-session-yet* default only. Every
  existing user already has a real, persisted `users.locale` value (the
  column existed with a default before this phase, so no row is
  `NULL`), and `SetLocale` middleware checks that first — so no existing
  authenticated user's experience changes because of this config flip.

---

## 9. Tests added

17 new tests, 4 new files, all passing, zero changes needed to any
existing test:

| File | Tests | Covers |
|---|---|---|
| `tests/Feature/MoroccoTenantContextTest.php` | 7 | A, B, C — fresh Morocco/Spain tenant defaults, existing tenant never converted, authoritative-source order |
| `tests/Feature/CurrencyFormatterTest.php` | 4 | D, E — Morocco renders MAD/no €, Spain keeps €, unknown currencies get their plain code |
| `tests/Feature/BusinessDocumentLocaleTest.php` | 3 | F, G, H — PDF locale follows the tenant not the staff user, in both directions, and never leaks back into the staff UI's own locale |
| `tests/Feature/VerifactuCountryGateTest.php` | 3 | I, J — Moroccan tenant refused on every route (even with certificate data present), Spanish tenant unaffected |

**K (tenant isolation)**: guaranteed structurally by database-per-tenant
(no shared table, no `tenant_id` column to leak across), and additionally
exercised by the pre-existing, unmodified `VerifactuCertificateTest::tenant_a_certificate_cannot_resolve_for_tenant_bs_nif`.

**L (existing VERI\*FACTU tests stay green)**: confirmed — full suite
below.

### Full test suite result

```
Tests:    189 passed (475 assertions)
Duration: 79.00s
```

172 pre-existing + 17 new, **0 failed, 0 regressions**.

---

## 10. Remaining Spain-specific assumptions (unchanged, by design)

Everything the audit (`docs/morocco-phase-1-audit.md` §2) flagged as
Spain-specific and explicitly out of scope for this phase remains
exactly as it was:

- `invoices.vta4/vta10/vta21` — the fixed Spanish IVA rate columns.
  Untouched; Phase 1C's decision point per the audit's §10/§17.
- The F1-invoice-requires-customer-NIF issuance rule
  (`InvoiceController::issueInvoice()`) — still unconditional, not yet
  scoped to `country_code === 'ES'`. **Flag for Phase 1B/1C**: a
  Moroccan tenant issuing a normal invoice today would still be blocked
  by this Spain-specific rule if their customer has no `tax_id` — this
  phase did not touch invoice issuance logic at all (out of scope: "do
  not touch tax architecture yet" was read to include this adjacent
  issuance-validation rule, since fixing it correctly requires the same
  country-aware invoice-rules boundary Phase 1C is meant to design, not
  a quick patch here).
- The RD 1619/2012 rectification-series mandate — untouched, still
  unconditional for every tenant.
- Subscription/plan pricing and currency — untouched, exactly as scoped
  out.

## 11. Items intentionally deferred

- **Full translation of the transactional invoice/quote email** (§4) —
  currency fixed, language left as pre-existing hardcoded French for
  every tenant. Needs real ES/EN copywriting, not a mechanical pass.
- **Homepage/marketing content is not country-aware** — per your
  instruction not to redesign it yet, only the *default locale* changed
  (`config('app.locale')` → `fr`). This means the existing
  Spain/VERI\*FACTU marketing section (`index.blade.php`) is still
  rendered to every visitor regardless of country, just now in French by
  default instead of Spanish for a first-time visitor. **Worth flagging
  explicitly**: your brief's §7 also said "Do not advertise VERI\*FACTU
  to the default Moroccan visitor," which a locale-default change alone
  doesn't achieve — actually hiding that section requires either a
  country-aware homepage variant or geo-based routing, which is real
  homepage work and was deliberately not started here, consistent with
  "a larger Morocco homepage rewrite belongs to a later phase." This is
  a real gap between the two instructions in §7, surfaced here rather
  than silently resolved either way.
- **`templates.vue`'s design-preview sample data** — currency fixed
  (§3), but the sample customer phone number and payment-terms text are
  still Spanish-flavored placeholder content (`"657 985 633"`, `"Neto 30
  días"`) and the preview date still hardcodes `es-ES` formatting. Purely
  cosmetic (a design tool for the tenant's own admin, never customer-facing),
  left as a follow-up rather than redesigning the whole sample dataset
  in this phase.
- **The `verifactu_installation_number`/`country`/`country_code` free-text
  redundancy** flagged in the audit (§12/§5) — not touched here, since it
  wasn't load-bearing for this phase's goal and a column consolidation
  deserves its own reviewed migration.
- **Self-service public signup** does not exist (confirmed unchanged) —
  "Morocco as default market for new registrations" is implemented at
  the two entry points that actually exist today (Filament wizard,
  onboarding wizard); if/when self-service signup is built, it should
  read its defaults from the same `TenantContextService`/provisioning
  pattern rather than reinventing one.
