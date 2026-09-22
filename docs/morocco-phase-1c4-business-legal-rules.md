# Morocco Phase 1C.4 — Business & legal rules audit

**Status: audited, two regression tests added, no blocking behavior added.**
Scope: verify Fakturalista's Moroccan invoice pipeline against actual
Moroccan invoicing rules, using real, cited research (not invention), and
close only the gaps that can be closed without inventing a legal
requirement or risking a live tenant's ability to invoice. No DGI
integration, no VAT withholding, no UBL/XML/QR/signature, no automatic
fiscal-regime detection, no translation work, no Spain removal.

## 0. A note on sourcing, read before the table below

The brief for this phase states the official Moroccan CGI 2026 is "already
referenced in the project/research." That is not accurate: `docs/morocco-phase-1-audit.md`
(Phase 1's own audit) explicitly states *"no Moroccan primary source was
fetched in this phase"* and recommends a dedicated legal-source pass before
any Moroccan field is made mandatory — that pass was never done in any
prior phase. Rather than either (a) proceeding with no legal grounding at
all, or (b) fabricating citations, this phase did real, live research
(`WebSearch`/`WebFetch`, September 2026) against independent Moroccan tax/
accounting sources that themselves cite the CGI (Code Général des Impôts)
directly, primarily **Article 145 CGI** (mandatory invoice mentions),
**Article 91 CGI** (auto-entrepreneur VAT-exclusion mention), **Article
117 CGI** (VAT withholding at source, 2024 Finance Law), **Article 211
CGI** (10-year retention), and the penalty articles (186, 187, 192, 198
ter).

**This is still not a primary-source read of the Bulletin Officiel or an
official DGI text** — it is multiple independent, converging secondary
sources (accounting/legal-services firms, e-invoicing vendors) citing
specific CGI articles consistently with each other. That is meaningfully
stronger grounding than the project had before this phase, but it is
labeled **"CONFIRMED (secondary sources)"** below, not "CONFIRMED (primary
text)," and nothing at that confidence level was turned into a *blocking*
validation rule — per the brief's own instruction, anything not
confidently verifiable stays non-blocking/optional. A genuine primary-text
legal review remains recommended before Fakturalista treats any of this as
a hard compliance guarantee it can put its name behind.

## 1. Audit table

| Requirement | Status | Source/Basis | Fakturalista behavior |
|---|---|---|---|
| Seller name, address | CONFIRMED (secondary sources) / ALREADY IMPLEMENTED | CGI art. 145 | `CompanyProfile` legal/trade name + address, snapshot-first on an issued invoice (Phase 1B) |
| Seller ICE | CONFIRMED (secondary sources) / ALREADY IMPLEMENTED | Loi de Finances 2016 (généralisation) + 2019 (facturation), décret 2-14-271 | `company_profiles.ice`, shown when set, **not enforced** — see §2 |
| Seller IF | CONFIRMED (secondary sources) / ALREADY IMPLEMENTED | CGI art. 145 | `company_profiles.if_number`, shown when set, **not enforced** |
| Seller RC | CONFIRMED, conditional on legal form (mandatory for commercial companies; not required for a self-employed/auto-entrepreneur) / ALREADY IMPLEMENTED | CGI art. 145; secondary sources note the self-employed exception | `company_profiles.registration_number` (reused from Spain's Registro Mercantil, Phase 1B), shown when set, **not enforced** — Fakturalista has no legal-form field to even determine which case applies, see §2 |
| Seller TP (taxe professionnelle / patente number) | CONFIRMED (secondary sources) / **MISSING** | CGI art. 145 lists it alongside ICE/IF/RC | No field exists for this anywhere in Fakturalista. Out of scope for this phase (§2 of the brief scopes company identifiers to ICE/IF/RC only) — flagged for a future phase, not added here. |
| Customer name, address | CONFIRMED / ALREADY IMPLEMENTED | CGI art. 145 | `Customer` snapshot-first identity (Phase 1B/1C.3) |
| Customer ICE (B2B only; not required B2C) | CONFIRMED (secondary sources) / ALREADY IMPLEMENTED | Loi de Finances 2019 | `customers.ice`, shown when set, **never required** — matches `Customer::isBusiness()/isIndividual()` distinction already in place; individual customers were never asked for it (test C, Phase 1C.3, still green) |
| Invoice number: sequential, chronological, gap-free | CONFIRMED / ALREADY IMPLEMENTED | CGI art. 145 ("missing numbers trigger presumed tax evasion") | `InvoiceNumberingService` — audited again this phase, §4, no bug found |
| Invoice date | CONFIRMED / ALREADY IMPLEMENTED | CGI art. 145 | `invoices.date`, rendered `d/m/Y` for the `fr` locale |
| Description of goods/services | CONFIRMED / ALREADY IMPLEMENTED | CGI art. 145 | `_items.blade.php` line description |
| Quantity, unit price HT | CONFIRMED / ALREADY IMPLEMENTED | CGI art. 145 | `_items.blade.php` |
| Discounts | CONFIRMED as commonly required to be shown / ALREADY IMPLEMENTED | CGI art. 145 (implicit in "montant HT") | Line + header discount, shown when configured (`show_discount` template flag) |
| Taxable base (HT) per rate | CONFIRMED / ALREADY IMPLEMENTED (this phase's predecessor) | CGI art. 145 | `Base TVA X%` row, added Phase 1C.3 |
| TVA rate + amount, broken down per rate | CONFIRMED / ALREADY IMPLEMENTED | CGI art. 145 | `invoice_tax_lines`, rendered per (rate, treatment) bucket |
| Sous-total HT / Total TVA / Total TTC | CONFIRMED / ALREADY IMPLEMENTED | CGI art. 145 | `_totals.blade.php`, Morocco-labeled (Phase 1C.2/1C.3) |
| Payment method / terms mention | CONFIRMED, generically / ALREADY IMPLEMENTED | Secondary sources cite Law 49-15 (payment-deadline law between businesses) | Free-text `payment_terms` field — covers the "mention payment terms" requirement generically; Fakturalista does not enforce Law 49-15's specific deadline rules (not asked for, would require a dedicated legal check) |
| Exempt-operation legal reference on the invoice | **UNCERTAIN / PENDING LEGAL CONFIRMATION** | See §6 below | Continues showing neutral `Exonéré` only, no article number |
| Correction document must reference the original's number + date, on the document itself | CONFIRMED as required / **MISSING** (both countries) | Secondary sources, consistent with Spain's own RD 1619/2012 art. 15 requiring the same | Fakturalista's rectification PDF does not currently print "corrects invoice X dated Y" anywhere — `rectifies_invoice_id`/`invoice_type`/`rectification_reason` are stored but never rendered (confirmed by grep, §7). Cross-cutting, affects Spain too, not implemented this phase — see §7 |
| Never silently edit an issued invoice; issue a new document instead | CONFIRMED / ALREADY IMPLEMENTED | Secondary sources: "forbidden to modify or delete an already-issued invoice" | `Invoice::isLocked()` + `InvoiceRectificationService` — audited §7, mechanism is country-neutral already |
| VAT withholding at source (partial/full) for certain operations | CONFIRMED to exist / **NOT IMPLEMENTED** (audit only, per explicit instruction) | CGI art. 117, Loi de Finances 2024 | See §9 — future design only, no code |
| Non-VAT-liable business can issue documents without forced VAT | CONFIRMED as a real scenario (auto-entrepreneur "hors champ", art. 91 mention) / ALREADY IMPLEMENTED (mechanism) | CGI art. 91 (mention), secondary sources on auto-entrepreneur VAT exclusion | See §8 — proven with a new regression test, no forced default found |
| 10-year document retention | CONFIRMED / ALREADY SATISFIED | CGI art. 211 | No auto-deletion/purge job exists anywhere in the codebase (checked); issued invoices are retained indefinitely by default |
| Stamp/signature on invoice | Secondary sources say **not required** | N/A | Not implemented, not needed — NOT APPLICABLE |

## 2. ICE / IF / RC policy decision

**No blocking validation was added for any of the three, for either the
seller or the customer.** All three remain: available, displayed when
configured, never required to create or issue an invoice.

This is a deliberate decision, not an oversight, for three reasons:

1. **Confidence level.** The research this phase did is secondary-source,
   not a primary-text read of the Bulletin Officiel/CGI itself. The brief's
   own instruction is explicit: *"If a requirement cannot be confidently
   verified, document it as pending instead of enforcing it."* Multiple
   converging sources citing the same article is meaningfully better than
   nothing, but it is not the same bar Phase 1B set for itself (and never
   cleared) before considering these fields mandatory.
2. **Fakturalista cannot safely determine which nuance applies.** RC's
   requirement is conditional on legal form (commercial company vs.
   self-employed) — Fakturalista has no "legal form" field at all, so it
   cannot tell which rule applies to a given tenant. Customer ICE is
   B2B-only — Fakturalista *can* determine this via `Customer::isBusiness()`,
   which is the one case closest to "safely determinable," but enforcing it
   would still mean blocking real, already-onboarded tenants' existing
   business customers that don't have an ICE on file yet.
3. **Product risk.** This is a live product with real tenants. A hard
   block on a field whose exact legal scope hasn't been primary-source
   verified risks stopping a paying customer from invoicing at all — the
   downside of being wrong is asymmetric (a missed soft reminder costs
   nothing; a wrong hard block stops revenue). This mirrors the same
   caution Phase 1B already applied to Spain's own NIF/CIF checks and to
   Morocco's ICE at issuance.

**Recommended, not implemented**: once a primary-source legal pass
specifically confirms seller-ICE-on-every-invoice and customer-ICE-on-B2B,
a *non-blocking* UI warning (e.g. "ICE recommended for Moroccan invoices,
required for your customer's VAT deduction") would capture most of the
practical benefit without the block risk. Not built this phase — no UI
touch was requested and none was made.

## 3. Customer identifier policy

Same conclusion as §2, applied to the customer side specifically:
`Customer::isBusiness()`/`isIndividual()` (Phase 1B) remains the only
distinction Fakturalista enforces. An individual customer is never asked
for ICE/IF/RC. A business customer's ICE is displayed when present, never
required. No change made.

## 4. Numbering — re-audited, no change

Re-verified this phase (no bug found, matching Phase 1C.3's own
conclusion):

- **Uniqueness**: DB-level unique constraint on `(invoice_series,
  invoice_number)`, plus row-locked sequence reservation.
- **Chronological consistency**: each series (`invoice-draft`,
  `invoice-default`, `invoice-rectification`) is independently correlative
  from its own start; a legal number is assigned exactly once, at
  `issueInvoice()`, never reassigned.
- **Issued invoice immutability**: `Invoice::isLocked()` blocks `update()`
  once issued; `cancel()` (checked this phase) flips status and stores a
  reason but never touches `invoice_number`/`reference` — a cancelled
  invoice's number is never freed or reused, matching the "missing numbers
  trigger investigation" concern directly.
- **Quote → invoice**: `QuoteToInvoiceService::convert()` assigns a draft
  label only; the legal number is still assigned solely at issuance.
- **Rectification numbering**: its own separate series, unchanged.

No Moroccan-specific numbering rule (a dedicated legal series requirement
analogous to Spain's RD 1619/2012 art. 6.5 for rectificativas) has been
found or is claimed here — this remains an open question, same as Phase 1B
left it.

## 5. Tax presentation — unchanged, preserved

`Sous-total HT` / `Base TVA X%` / `TVA X%` / `Total TVA` / `Total TTC`
(Phase 1C.3) is untouched this phase. Every figure still comes from
`invoice_tax_lines` (or the live calculator for a quote) — nothing in this
phase added an independent calculation anywhere.

## 6. Exempt operations — legal reference: still pending, by design

The research this phase did surfaced a real, specific finding: Moroccan
auto-entrepreneurs must print **"TVA non applicable, article 91 du CGI"**
on their invoices instead of VAT columns. However, this phase deliberately
does **not** wire that string into Fakturalista's `Exonéré` label, for a
reason specific to this finding, not just general caution:

- That mention is tied to a *specific legal status* (auto-entrepreneur,
  "hors champ d'application de la TVA" — out of scope of VAT), which is
  legally distinct from a general CGI art. 91/92 VAT *exemption*
  (exonération) for other operations (exports, certain goods, etc.).
  Sources are explicit that this is "an exclusion from scope, not an
  exemption under articles 91 or 92," which makes "article 91" itself a
  contested citation even among the secondary sources found.
- Fakturalista's `Exonéré` treatment is used generically today for *any*
  0%/non-taxable line, for any reason — it has no concept of *why* a line
  is exempt (auto-entrepreneur status vs. an actually-exempt operation vs.
  export vs. something else), and the brief explicitly forbids inventing
  automatic fiscal-regime/reason detection.

Printing "article 91" on every `Exonéré` line regardless of the actual
reason would be **worse** than the current neutral label — it would assert
a specific legal basis that may not apply to that tenant's situation.

**Conclusion: keep `Exonéré` as the neutral label, unchanged.** Flagged as
a future feature: a free-text, tenant-filled "exemption reason/mention"
field on the invoice line or document (the tenant states their own
applicable basis — e.g. "TVA non applicable, article 91 du CGI" for an
auto-entrepreneur, or their own export/exemption citation) would satisfy
this without Fakturalista ever guessing. Not implemented this phase — no
schema or UI change was made.

## 7. Rectification / correction documents

**Audited, no code change to the mechanism itself.** Fakturalista's
existing architecture already matches Moroccan practice closely:

- Moroccan sources: never edit/delete an issued invoice; for an unpaid
  invoice, issue a corrective invoice ("annule et remplace") with a new
  sequential number; for an already-paid invoice, issue a credit note
  (avoir) adjusting the amount. Both must reference the original invoice's
  number and date and state the correction's amount/VAT/reason.
- Fakturalista: `InvoiceRectificationService` already creates a *new*,
  separately-numbered document (`rectifies_invoice_id` links it to the
  original), leaves the original issued and immutable, and its
  `rectification_type` field (`S` = sustitución/full replace, `I` =
  diferencias/amount-only adjustment) maps structurally onto exactly the
  Moroccan "corrective invoice" vs. "avoir" distinction above — a
  coincidence of Spain's own rectificativa-vs-diferencias split being
  general enough to fit, not something built for Morocco.

**What is Spain-specific and was checked for leakage**: the `reason`
enum's business-level labels (`error_datos`, `importe_incorrecto`, etc.)
and the resulting AEAT `invoice_type` code (R1–R5) are Spanish/AEAT
vocabulary. Checked this phase and confirmed safe: **no frontend UI (Vue
or Filament) exists for rectification at all, for either country** — it is
API-only — and grepping every PDF Blade component confirms
`invoice_type`/`rectification_type`/`rectification_reason` are never
rendered anywhere. The new
`rectifying_a_moroccan_invoice_never_leaks_spanish_rectification_vocabulary_onto_the_pdf`
test proves this directly: a Moroccan rectification's `invoice_type` is
stored internally as `R1`, but that string (and `rectificativa`, `AEAT`,
`VERI*FACTU`) never appears anywhere in its rendered PDF. **No
country-isolation gap requiring a code fix was found.**

**What is a real, but shared (not Morocco-specific) gap**: neither country's
rectification PDF currently prints an explicit "this corrects invoice X
dated Y" reference block, even though both Moroccan practice and Spain's
own RD 1619/2012 art. 15 call for one. Not fixed this phase — it's a
PDF-content change affecting both countries equally, wasn't explicitly
asked for, and deserves its own scoped pass rather than a rushed addition
here.

## 8. Non-VAT / VAT-exempt businesses

**Audited and confirmed already correctly supported — the "must not force
TVA 20%" requirement was checked at every layer, not assumed:**

- Backend validation (`InvoiceRequest`): `carts.*.vta` is `nullable`, no
  minimum-nonzero rule; `carts.*.tax_treatment` is `nullable`, accepts
  `exempt` freely.
- The calculator (`DocumentCalculationService::calculate()`): a line with
  no `tax_rate` given defaults to `0`, not `20` — there is no code path,
  anywhere, that substitutes the tenant's *default preset* for a rate the
  client didn't submit. The tenant's `default_tax_code` (Phase 1C.2) is
  purely a **frontend pre-fill convenience** for a brand-new, untouched
  line — never a server-side fallback that could override an explicit
  choice.
- The tenant-level default itself is fully changeable at any time
  (`CompanyProfile.default_tax_code`, already settable to `MA_EXEMPT` since
  Phase 1C.2) — a non-VAT-liable tenant can set this once and every new
  line/item pre-fills as exempt from then on.

Proven end-to-end with a new test: a Moroccan invoice with every line
explicitly `vta: 0, tax_treatment: exempt` computes `sub_total == total`
(TTC = HT, zero VAT), issues without any blocking error, and its PDF shows
`Exonéré` with no `TVA 20%` anywhere.

**Proposed, not implemented** (per the brief's explicit "propose... but do
not implement a complex tax-regime engine"): a future nullable
`company_profiles.vat_status` (e.g. `assujetti` / `non_assujetti` /
`auto_entrepreneur`) could later (a) default `default_tax_code` sensibly
at onboarding instead of always landing on `MA_TVA_20`, and (b) gate the
§6 exemption-mention feature once it exists. Not built here — no schema
change was made this phase.

## 9. VAT withholding (Article 117) — future architecture, audit only

**Not implemented. No UI exposure. This section is a design proposal
only.**

CGI art. 117 (Loi de Finances 2024) requires certain payers (the State,
local authorities, public bodies, and — per later extensions — certain
private-sector payers for specified service categories) to withhold a
fraction (commonly 75%, per the decree-listed services) or the entirety of
the VAT they would otherwise pay their supplier, and remit it directly to
the Treasury themselves. Entities subject to public-procurement regulation
are explicitly carved out from this mechanism.

**Why this must never be confused with a discount, the normal VAT line, or
the invoice total**: withholding does not change what the seller is owed
or what the invoice legally states as due — the invoice's HT/TVA/TTC
figures represent the full, real transaction value and VAT liability,
unchanged. What changes is *how payment is settled*: part of the VAT the
customer owes the seller is instead paid straight to the Treasury by the
customer, on the seller's behalf. Confusing this with a discount would
wrongly shrink the taxable base; confusing it with the VAT line itself
would wrongly imply less VAT was charged than actually was; confusing it
with the total would wrongly change what the invoice says the customer
owes.

**Proposed future representation** (not built): a distinct field —
conceptually `withheld_vat_amount` — living alongside `total`, not inside
it. The invoice's own `sub_total`/`vta`/`total` continue to mean exactly
what they mean today (nothing about §5's presentation changes). A
withholding-aware view would show one additional, clearly-labeled line
*below* `Total TTC`, e.g.:

```
Total TTC                  8 200,00 MAD
TVA retenue à la source*     600,00 MAD   (art. 117 CGI)
Net à payer                 7 600,00 MAD
```

with `Net à payer` = `Total TTC − withheld_vat_amount`, computed and
displayed only, never fed back into `sub_total`/`vta`/`total` or
`invoice_tax_lines`, which remain the authoritative fiscal figures. This
mirrors how a Spanish IRPF retention line is conventionally handled in
comparable invoicing systems — a separate "amount actually transferred"
figure, not a rewrite of the tax breakdown. `withheld_vat_amount` would
default to `0`/null for every existing and new invoice until a future
phase explicitly implements it (additive, no historical impact by
construction, same pattern as every other Morocco-phase migration so
far). **No migration, model field, validation, or UI was added this
phase** — this section exists purely so a future phase has a documented,
non-guessed starting point instead of inventing the representation under
time pressure later.

## 10. Spain safety

Nothing under `app/Services/Verifactu/*` or `app/Models/Verifactu/*` was
touched. `RequireSpainCountry` middleware, IVA/EUR/NIF behavior, and the
existing Spanish rectification reason/R-code logic are all unchanged.
`InvoiceRectificationServiceTest` and the full `VerifactuXmlBuilderTest`/
`VerifactuChainServiceTest`/`VerifactuCountryGateTest` suites re-ran green
in the full suite below.

## 11. Historical safety

No migration, no backfill, no recalculation. `company_snapshot`/
`customer_snapshot`/`invoice_tax_lines`/legacy `vta4/vta10/vta21`/numbering
are all read-only from this phase's perspective — every audited item in
§1 either already worked or was left exactly as-is pending stronger
verification. The new
`saving_an_exempt_tenant_default_does_not_retroactively_change_an_already_issued_invoice`
test locks this in specifically for the tenant tax default, the one
setting this phase's own §8 conclusion depends on.

## 12. Files changed

- `tests/Feature/MoroccoBusinessLegalRulesTest.php` — new, 3 tests.
- This document.

**No production code was changed this phase.** Every audited requirement
was either already correctly implemented (verified, not assumed) or
deliberately left unenforced pending stronger legal confirmation, per the
brief's own explicit preference for "document as pending" over "invent and
enforce."

## 13. Tests

New: `tests/Feature/MoroccoBusinessLegalRulesTest.php`, 3 tests, all
passing (26 assertions) — covers §8 (non-VAT business, twice: the
mechanism itself and its historical safety) and §7 (rectification output
is country-neutral). No test encodes an unverified legal assumption; both
`§6` and `§9` findings are documented, not tested, because nothing was
implemented for them.

**Full backend suite**: `php artisan test --compact` → see §16 below for
the actual run at the end of this phase.

## 14. Remaining legal/compliance questions (unchanged from Phase 1C.3, plus this phase's additions)

- Whether ICE/IF/RC (seller and B2B customer) should become a hard
  requirement — pending a primary-source (Bulletin Officiel/DGI text)
  legal pass; current secondary-source research is supportive but not
  sufficient per the brief's own bar.
- Whether RC is conditional on legal form Fakturalista doesn't currently
  model — unresolved; would need a "legal form" concept added first.
- TP/Patente number — identified as commonly required (CGI art. 145) but
  entirely unimplemented; out of this phase's explicit scope (ICE/IF/RC
  only).
- Exact wording/legal basis for an exempt-operation mention — genuinely
  regime-dependent (auto-entrepreneur art. 91 exclusion vs. a true art.
  91/92 exemption vs. other cases); deliberately left as a tenant-filled
  free-text feature proposal rather than guessed.
- Whether a rectification document must print an explicit "corrects
  invoice X dated Y" reference on the PDF — likely yes (both Moroccan and
  Spanish sources point this way), not implemented, affects both
  countries, needs its own scoped phase.
- VAT withholding (art. 117) — only a documented future design, nothing
  built or exposed.
- Payment-deadline specifics under Law 49-15 — not enforced, only a
  generic free-text field exists.

## 15. Blockers before Morocco launch

None identified that block *using* Fakturalista today. The open items
above are refinements/compliance-hardening, not missing core mechanics —
every core invoicing capability audited this phase (identity, numbering,
tax presentation, exemption display, non-VAT businesses, correction
documents, historical immutability) was found to already work correctly
for a real Moroccan tenant.

Work stops at Phase 1C.4.
