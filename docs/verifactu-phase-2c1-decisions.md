# VERI*FACTU Phase 2C.1 — Regulatory & Implementation Decisions

This document records every regulatory decision made while closing the
data/domain gaps Phase 2C surfaced, and cites the exact official source
for each. Every item is explicitly labeled:

- **OFFICIAL REQUIREMENT** — a fact taken directly from BOE/AEAT primary
  sources, quoted or closely paraphrased, with the exact citation.
- **FAKTURALISTA IMPLEMENTATION DECISION** — a choice made by this
  codebase given what Fakturalista's invoicing model can currently
  represent. Never a regulatory fact.

Primary sources used in this phase (in addition to those already frozen
in `docs/verifactu-xml-spec-freeze.md`):

- **D4** — Orden HAC/1177/2024, de 17 de octubre (BOE-A-2024-22138),
  fetched and read in full as a PDF from
  `https://www.boe.es/boe/dias/2024/10/28/pdfs/BOE-A-2024-22138.pdf`
  (2026-09-21). This is the primary source for every code list cited
  below (its Anexo, §6 "Listas empleadas en los bloques utilizados para
  componer los ficheros").

---

## 1. DescripcionOperacion

**OFFICIAL REQUIREMENT.** `DescripcionOperacion` is mandatory
(`minOccurs` unset, i.e. required) on every `RegistroAlta`, per
`SuministroInformacion.xsd`'s `RegistroFacturacionAltaType` and D4's own
field table ("DescripcionOperacion¹ ... Descripción del objeto de la
factura. Alfanumérico (500)").

**Gap found (Phase 2C):** `invoices.descripcion_operacion` existed as a
column since Phase 2A but nothing populated it — no UI field, no
controller logic.

**FAKTURALISTA IMPLEMENTATION DECISION.** Added:
- A user-facing field ("Descripción de la operación", helper text
  "Describe brevemente la operación realizada.") to both
  `CreateInvoiceForm.vue` and `EditInvoiceForm.vue`, i18n keys in
  `es.js`/`en.js`/`fr.js` (`invoices.form.descripcionOperacion{Label,Help,Placeholder}`).
- `InvoiceRequest::rules()`: `'descripcion_operacion' => 'nullable|string|max:500'`
  — nullable because drafts may stay incomplete (matches the existing
  Fakturalista UX: no other field is hard-required at draft-save time
  either).
- `InvoiceController::store()/update()/duplicate()/edit()` now
  read/write/return it.
- The hard block remains exactly where Phase 2C put it:
  `VerifactuChainService::requireDescripcionOperacion()` throws before
  generating an alta record if it's empty — issuance itself is
  unaffected (still Phase 2A's numbering/tax-ID rules only), since
  VERI*FACTU record generation isn't wired into issuance yet.
- **Never auto-generated.** No fallback to `note`, no templated string —
  an incomplete invoice simply cannot get a VERI*FACTU record until a
  human fills this in.

---

## 2. ClaveRegimen / CalificacionOperacion / OperacionExenta

**OFFICIAL REQUIREMENT — the code lists themselves**, D4 Anexo §6:

- **Lista L1 (Impuesto):** 01 IVA, 02 IPSI (Ceuta/Melilla), 03 IGIC
  (Canarias), 05 Otros.
- **Lista L8A (ClaveRegimen, desgloses de IVA):** 20 values — 01
  "Operación de régimen general", 02 Exportación, 03 bienes
  usados/antigüedades, 04 oro de inversión, 05 agencias de viajes, 06
  grupo de entidades (Nivel Avanzado), 07 criterio de caja, 08
  IPSI/IGIC, 09 agencias de viaje mediadoras, 10 cobros por cuenta de
  terceros, 11 arrendamiento de local de negocio, 14/15 IVA pendiente de
  devengo, 17 OSS/IOSS, 18 recargo de equivalencia, 19 REAGYP, 20
  régimen simplificado. (Lista L8B is the IGIC equivalent — not
  applicable, Fakturalista has no Canarias/IGIC support.)
- **Lista L9 (CalificacionOperacion):** S1 "Sujeta y no exenta - sin
  inversión del sujeto pasivo", S2 "... con inversión del sujeto
  pasivo", N1 "No sujeta artículo 7, 14, otros", N2 "No sujeta por
  reglas de localización".
- **Lista L10 (OperacionExenta):** E1 (art. 20), E2 (art. 21), E3 (art.
  22), E4 (arts. 23-24), E5 (art. 25), E6 (otros).

**⚠️ Discrepancy found, flagged not resolved:** the live XSD's
`OperacionExentaType` enumerates **E1 through E8**, but D4's Anexo §6
(the legal text) only defines **E1 through E6**. E7/E8 appear to have
been added to the schema after this Orden's publication (28/10/2024)
without (as far as I could find) a corresponding published legal
amendment. Irrelevant to Fakturalista today since it has no exemption
concept at all (see below), but must be re-checked before anyone ever
implements exemption support.

**FAKTURALISTA IMPLEMENTATION DECISION — which combination Fakturalista
uses, and why it's not a guess.** `VerifactuChainService` hardcodes
`ClaveRegimen=01` and `CalificacionOperacion=S1` for every Desglose row
(`CLAVE_REGIMEN_GENERAL`, `CALIFICACION_SUJETA_NO_EXENTA_SIN_INVERSION`
constants, each with the exact list citation in its docblock). This is
**not** "AEAT's example happens to use these" — it's a consequence of
what Fakturalista's invoicing model can represent today:

- No special-regime selector exists anywhere in the app (no OSS/IOSS, no
  criterio de caja, no recargo de equivalencia, no agencias de viaje
  flow) → L8A's other 19 values have no possible data source.
- No reverse-charge flag, no not-subject-to-VAT concept, and no
  exemption-reason selector exist anywhere → L9's S2/N1/N2 and all of
  L10 have no possible data source either.

Every invoice Fakturalista can currently construct describes an
ordinary, fully-taxable domestic sale — S1/01 is the *only* value that
could ever be true for it, so there is nothing to "choose" and no UI was
added. **If Fakturalista ever gains a reverse-charge, exemption, or
special-regime feature, this must become an explicit, user-set field —
never inferred from a 0% VAT rate — before any of those cases are
representable.**

**A new safety net that didn't exist in Phase 2C:** `buildTaxBreakdown()`
now verifies `Σ(BaseImponible) == ImporteTotal - CuotaTotal` (within a
1-cent rounding tolerance) before allowing a record to be generated.
This invariant holds for any correct Desglose regardless of how
per-line/invoice-level discounts were computed upstream (verified
algebraically against `CreateInvoiceForm.vue`'s actual formula, not
assumed — see the method's docblock). If it fails, there's taxable base
Fakturalista can't currently account for in any of the three supported
rates (e.g. a would-be 0%/exempt line), and the record generation is
**rejected**, not silently under-reported. Covered by
`an_invoice_with_unaccounted_taxable_base_is_rejected_rather_than_silently_submitted`.

---

## 3. ImporteRectificacion

**OFFICIAL REQUIREMENT.** D4's field table: `ImporteRectificacion` >
`BaseRectificada` ("Base imponible de la factura", Decimal 12,2),
`CuotaRectificada` ("Cuota repercutida o soportada de la factura",
Decimal 12,2), `CuotaRecargoRectificado` ("Cuota recargo de equivalencia
de la factura", Decimal 12,2, optional). The XSD's own annotation on
`DesgloseRectificacionType` is more specific about *whose* base/cuota
this is: "Desglose de Base y Cuota **sustituida** en las Facturas
Rectificativas **sustitutivas**" — i.e. the ORIGINAL invoice's base/cuota
being replaced, and only for `TipoRectificativa=S`. No AEAT worked
example of a rectificativa exists in the documents I could retrieve
(D1/D3 have none), so this is based on the field table + the XSD
annotation, cross-referenced and consistent with each other, not a
worked example.

**FAKTURALISTA IMPLEMENTATION DECISION.**
- `ImporteRectificacion` is populated **only** when
  `Invoice.rectification_type === 'S'` (sustitución); left absent for
  `'I'` (por diferencias) — matching the XSD's `minOccurs="0"` and the
  "sustitutivas" wording.
- `BaseRectificada`/`CuotaRectificada` = the ORIGINAL invoice's own
  `sub_total`/`vta` at the moment the rectification's own alta record is
  generated (`VerifactuChainService::buildRectificationSnapshot()`),
  read once and snapshotted onto the new `verifactu_records.importe_rectificacion_base/_cuota`
  columns — never recomputed, never re-read later.
- `CuotaRecargoRectificado` is **not implemented** — Fakturalista has no
  "recargo de equivalencia" concept anywhere in its invoicing model, so
  there is no value to put there. Optional in the schema, so its
  absence keeps the document valid.
- Tested separately for S (`rectificativa_sustitucion_uses_s_code`,
  asserting the exact `BaseRectificada`/`CuotaRectificada` values) and I
  (`rectificativa_xml_includes_tipo_rectificativa_and_facturas_rectificadas`,
  asserting `ImporteRectificacion` is *absent*).

---

## 4. Foreign customers / IDOtro

**OFFICIAL REQUIREMENT.** D4's field table for `Destinatarios/IDDestinatario`:
`NombreRazon` + a choice of `NIF` (Spanish, `FormatoNIF(9)`) or `IDOtro`
(`CodigoPais` [ISO 3166-1 alpha-2, optional within IDOtro],
`IDType` [lista **L7**, mandatory], `ID` [Alfanumérico(20), mandatory]).

**Lista L7 (IDType) — exact official values, D4 Anexo §6:**

| Code | Official description |
|---|---|
| 02 | NIF-IVA |
| 03 | Pasaporte |
| 04 | Documento oficial de identificación expedido por el país o territorio de residencia |
| 05 | Certificado de residencia |
| 06 | Otro documento probatorio |
| 07 | No censado |

(There is no code "01" — confirmed both in the XSD enumeration and D4's
own list, not an omission on Fakturalista's part.)

**FAKTURALISTA IMPLEMENTATION DECISION — schema and resolution order.**
- `customers` gained two additive, nullable columns: `foreign_tax_id_type`
  (must be one of the six L7 codes above, enforced via
  `CustomerRequest`) and `foreign_tax_id` (the ID value). The existing
  `tax_id` column (Spanish NIF, Phase 2A) is untouched.
- `CodigoPais` is **not** duplicated as a new column — it's derived from
  the customer's *existing* `billing_country_id` → `countries.code`
  (already ISO 3166-1 alpha-2 per `CountrySeeder`). `Customer::billingCountry()`
  relation added for this.
- Resolution order (`VerifactuChainService::resolveDestinatario()`,
  backward-compatible by construction): NIF branch wins whenever
  `tax_id` is set (every customer created before this phase, and any
  customer with a Spanish NIF, is unaffected); IDOtro branch applies
  only when `Customer::hasForeignTaxId()` (`tax_id` empty AND both
  foreign fields set); otherwise no destinatario is emitted at all
  (correct for F2, and for any customer with neither on file).
- CodigoPais is optional per the schema, so a foreign customer with no
  billing country on file still produces a valid document — tested
  (`foreign_customer_without_billing_country_omits_codigo_pais_but_stays_valid`).

**Real bug found and fixed alongside this (not originally part of Phase
2C.1's ask, but directly blocking it):** the customer form's field
labeled "NIF" (`customers.fields.nif`) was bound to `Customer.ice` (the
Moroccan identifier) instead of `Customer.tax_id`, in both
`create.vue`/`edit.vue`, and `CustomerController` never read or wrote
`tax_id` at all in `store()`/`update()`/`show()`/`edit()`. Confirmed via
direct code inspection, not assumption. Since this entire branch is
still unreleased/uncommitted (no production tenant has ever used this
form), fixing it directly (not a migration/back-compat concern) was the
correct call — otherwise no customer's Spanish NIF could ever reach a
VERI*FACTU record through the UI, regardless of anything else built in
this phase. Now: "NIF" → `tax_id`; the pre-existing field is relabeled
"Identificador fiscal (ICE)" and still bound to `ice`, unchanged in
meaning — nothing that previously worked for Moroccan-context customers
was removed. Covered by `tests/Feature/CustomerTaxIdentificationTest.php`.

---

## 5. Rectified-invoice snapshot (removing the `$invoice->rectifies` dependency)

**FAKTURALISTA IMPLEMENTATION DECISION** (this section is pure
architecture, not a regulatory question). Phase 2C's `VerifactuXmlBuilder`
read `$record->invoice->rectifies` at XML-*build* time to get the
original invoice's series/number/date for `FacturasRectificadas`. Phase
2C.1 removes this entirely:

- New columns on `verifactu_records`: `rectifica_num_serie`,
  `rectifica_fecha_expedicion` — copied from the original invoice **once**,
  at the rectification's own record-*generation* time
  (`VerifactuChainService::buildRectificationSnapshot()`), the same
  place every other mutable-source field (emisor name, destinatario)
  already gets snapshotted.
- No separate `rectifica_id_emisor` column: `IDFacturaARType`'s own
  `IDEmisorFactura` field is, per the XSD's own annotation, "cogido del
  NIF indicado en el bloque IDFactura" (AEAT derives it from the current
  record's own emisor) — and Fakturalista never rectifies across a NIF
  change, so it's always identical to the rectification's own
  `nif_emisor`. Adding a redundant column that could only ever equal an
  existing one would be pure duplication, not a real snapshot need.
- `VerifactuXmlBuilder::buildFacturasRectificadas()` now reads only
  `$record->rectifica_num_serie`/`rectifica_fecha_expedicion`/`nif_emisor`
  — confirmed via `grep` that zero references to `$record->invoice` (or
  any Invoice/Customer/CompanyProfile access at all) remain in the file.

**Regression test** (`xml_is_unchanged_after_the_source_invoice_customer_and_company_are_mutated`):
generates a full rectification alta XML, then — deliberately bypassing
Eloquent/app-level immutability guards via raw `DB::table(...)->update()`
calls, specifically to prove the *builder* doesn't depend on those
guards holding — mutates the company's legal name and installation
number, the customer's name and NIF, and the original invoice's own
reference and date. Regenerating the XML from the same stored
`VerifactuRecord` produces **byte-identical output**, and none of the
mutated strings ("Cambiado", "REF-CAMBIADA-999") appear in it.

---

## 6. Software producer identity (SistemaInformatico)

**OFFICIAL REQUIREMENT — the two entities are legally distinct.** D4
art. 15.1 requires the declaración responsable to identify "la persona o
entidad **productora** del sistema informático" — separately and
explicitly from the **taxpayer** (`ObligadoEmision`/`CompanyProfile` in
Fakturalista's terms) using the software. The Anexo's own clarifying
note on the `SistemaInformatico` block confirms this again: "dato de la
persona o entidad **productora** del sistema informático de facturación
(SIF) empleado... se deberán consignar los datos del productor
responsable del componente principal del SIF, según la definición dada
en el artículo 1.2.c)". These are never the same field in Fakturalista's
architecture: `config/verifactu.php` (producer, constant across every
tenant) vs. `CompanyProfile` (each tenant's own fiscal identity).

**OFFICIAL REQUIREMENT — a non-Spanish producer is explicitly
accommodated**, D4 art. 15.1.i, quoted directly: *"Número de
identificación fiscal (NIF) español de la persona o entidad productora
del sistema informático... **Si no dispone de NIF español, deberá hacer
constar otro número de identificación de que disponga, indicando de qué
tipo de identificación se trata y el país que lo ha emitido**, todo ello
de acuerdo con las especificaciones dadas al respecto en el apartado 2.6
del anexo."* This is unambiguous, direct statutory text — not an
inference from the XML schema's structure. Fakturalista being
developed/operated from Morocco does **not** require it to obtain a
Spanish NIF to be a valid VERI*FACTU software producer.

**FAKTURALISTA IMPLEMENTATION DECISION.** `config/verifactu.php` models
both branches (`producer.nif` OR `producer.id_country`+`id_type`+`id`,
using the same lista L7 codes as customer/destinatario identification).
**Every value defaults to `null` via `env()` and none has been set** —
`VerifactuXmlBuilder::buildSistemaInformatico()` throws rather than
inventing a value if neither branch is fully configured. Nothing here
claims or assumes what Fakturalista's real producer identity will be.

**What you will eventually need to provide, before VERI*FACTU can be
activated for any real tenant** (per D4 art. 15.1.a-l, the declaración
responsable's required content):
1. The software's commercial name (`VERIFACTU_SYSTEM_NAME`, ≤30 chars).
2. A short internal product code, self-assigned, unique across any other
   products you might produce (`VERIFACTU_SYSTEM_ID`, ≤2 chars).
3. A version identifier (`VERIFACTU_SYSTEM_VERSION`, ≤50 chars).
4. **Either** a Spanish NIF (`VERIFACTU_PRODUCER_NIF`) **or** a foreign
   identifier: country code, ID type (one of the L7 codes — most likely
   "06 Otro documento probatorio" for a Moroccan commercial-registry
   number, or "07 No censado" if none applies), and the ID itself
   (`VERIFACTU_PRODUCER_ID_COUNTRY`/`_ID_TYPE`/`_ID`).
5. The producer's legal name (`VERIFACTU_PRODUCER_NAME`).
6. Whether Fakturalista will operate exclusively as VERI*FACTU
   (`only_verifactu`) — this is a genuine product/legal decision (Phase
   1 §18 Q3, still open), not something I can default.
7. Whether it supports multiple obligados tributarios per installation
   (`multi_ot`) — almost certainly yes, since Fakturalista is
   multi-tenant SaaS, but this is your call to confirm.
8. Separately from all of the above: a full declaración responsable
   document itself (Phase 1 §15) — this phase does not draft one.

None of these values exist anywhere in the codebase today. I have not
invented any of them.

---

## 7. Installation identifier (`company_profiles.verifactu_installation_number`)

**OFFICIAL REQUIREMENT, D4 Anexo, `NumeroInstalacion` field description
(quoted in full since the exact wording matters):** *"Número de
instalación del sistema informático de facturación (SIF) utilizado.
**Deberá distinguirlo de otros posibles SIF utilizados para realizar la
facturación del obligado a expedir facturas**, es decir, de otras
posibles instalaciones de SIF pasadas, presentes o futuras utilizadas
para realizar la facturación del obligado a expedir facturas, **incluso
aunque en dichas instalaciones se emplee el mismo SIF de un
productor**."*

**What this settles, directly from the text:**
- **Scope: per taxpayer (obligado), not global.** It exists to
  distinguish one SIF installation from any *other* installation *used
  by the same taxpayer* — not to be globally unique across all AEAT
  filers.
- **Must remain stable over that taxpayer's history.** It has to
  distinguish this installation from that same taxpayer's own past,
  present, *and future* installations — so regenerating it while the
  same tenant continues using the same Fakturalista account would defeat
  its entire purpose.
- **Who assigns it:** not stated as explicitly as `IdSistemaInformatico`'s
  "dado por la persona o entidad productora", but the "must distinguish
  it from other installations" framing is inherently something only the
  producer (or its software) can track — AEAT has no way to pre-assign
  a number for an installation it doesn't yet know exists. Read
  together with `IdSistemaInformatico`'s explicit producer-assignment
  language immediately preceding it in the same block, this is a
  producer/software-assigned value, not an AEAT-issued one.

**FAKTURALISTA IMPLEMENTATION DECISION.** Given Fakturalista's
architecture — one isolated database per tenant, and no feature letting
one tenant run "multiple installations" — **one stable value per tenant**
(`company_profiles.verifactu_installation_number`, nullable) is the
correct mapping, confirmed correct rather than assumed. What Phase
2C.1 does **not** do: auto-generate a value. The column stays nullable
and unset, and `VerifactuChainService::requireInstallationNumber()`
throws rather than inventing one — exactly the same "stop and report"
treatment as every other missing-configuration item in this phase. A
generation strategy (e.g. a UUID assigned the first time a tenant
enables VERI*FACTU, written once and never touched again) belongs to
the eventual opt-in/activation UI, which is explicitly out of scope
here (no live wiring in this phase).

---

## 8. XML snapshot completeness audit

Re-read `VerifactuXmlBuilder.php` end to end after all of the above
changes and confirmed, by direct inspection (not just by running tests):

- **Zero** references to `Invoice`, `Customer`, or `CompanyProfile`
  anywhere in the file (`grep -n "record->invoice\|->invoice("` returns
  nothing).
- Every value used comes from: the `VerifactuRecord` row itself, its
  `taxDetails()` relation (`VerifactuRecordTaxDetail`, itself immutable
  and only ever created once, in `VerifactuChainService`), its
  `previousRecord()` relation (another immutable `VerifactuRecord`), or
  static `config('verifactu.*')`.
- The one dependency Phase 2C left in place — reading the original
  invoice via `$record->invoice->rectifies` for `FacturasRectificadas` —
  is now closed (§5 above).

This is no longer just an assertion — it's backed by the mutation
regression test in §5, which is the strongest evidence available short
of a static-analysis dependency check: it doesn't just check the
*code path* looks clean, it proves the *output* is unaffected by
mutating every mutable table this record could theoretically still
depend on.

---

## 9. Legacy / backward compatibility

Every migration this phase adds is additive (`ALTER TABLE ... ADD
COLUMN ... NULLABLE`, or a new table) — no existing column's type,
nullability, or default changed. Specifically:
- `customers.foreign_tax_id_type`/`foreign_tax_id` — nullable, existing
  customers get `NULL`/`NULL` and keep resolving via the pre-existing
  `tax_id` path, unchanged (`existing_customers_without_the_new_fields_remain_valid`).
- `verifactu_records`' new columns — all nullable, and `verifactu_records`
  itself has never been wired into live issuance, so there is no
  existing production row anywhere to migrate.
- `company_profiles.verifactu_installation_number` — nullable, unset for
  every tenant, VERI*FACTU generation for that tenant simply stays
  blocked (as it already was) until someone sets it.

No VERI*FACTU history was fabricated for any existing invoice — this
was already Phase 2A/2B/2C policy and remains unchanged. VERI*FACTU is
still not wired into `InvoiceController::issue()`/`cancel()` anywhere;
normal invoice creation, editing, viewing, and PDF generation are
provably unaffected (full existing suite green, including every legacy-
invoice test from Phase 2A/2C).

---

## 10. Tests

See the final report in chat for the full list and count. Summary of
what's new in this phase, by file:

- `tests/Unit/` — no changes (hash/XSD-validator tests unaffected).
- `tests/Feature/VerifactuChainServiceTest.php` — +2 tests (missing
  installation number, missing descripcion_operacion), fixtures updated.
- `tests/Feature/VerifactuXmlBuilderTest.php` — +9 tests (ImporteRectificacion
  S and I, foreign customer with/without billing country, customer with
  neither identification, software producer IDOtro, regime/qualification
  constants, unaccounted-base rejection, snapshot-immutability
  regression), 2 existing rectificativa tests strengthened.
- `tests/Feature/InvoiceLifecycleTest.php` — +3 tests
  (descripcion_operacion via the real store/update HTTP endpoints,
  including the "draft may stay incomplete" case).
- `tests/Feature/CustomerTaxIdentificationTest.php` — new file, 5 tests
  (Spanish NIF via HTTP, foreign ID via HTTP, invalid IDType rejected,
  update+edit round-trip, legacy-customer backward compatibility).
- `database/factories/CustomerFactory.php` — `foreignTaxId()` state
  added for test fixtures.

Full suite: see the final report.
