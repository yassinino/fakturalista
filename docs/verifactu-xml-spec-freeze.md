# VERI*FACTU XML — Specification Version Freeze (Phase 2C / 2C.1)

**Phase 2C.1 update (2026-09-21):** D4 (Orden HAC/1177/2024) was re-fetched
directly as a PDF from BOE (not just referenced by URL as in Phase 2C) and
read in full for its Anexo §6 "Listas empleadas" - this is the primary
source for every AEAT code list (`ClaveRegimen`, `CalificacionOperacion`,
`OperacionExenta`, `Impuesto`, `TipoFactura`, `IDType`, etc.) used anywhere
in this codebase. See `docs/verifactu-phase-2c1-decisions.md` for how each
list is used and the one discrepancy found (E7/E8).

This document exists so a future developer can tell exactly which official
AEAT specification and schema versions the XML in `app/Services/Verifactu/`
was built against, and re-verify or re-freeze against a newer version
deliberately rather than by accident. **Do not change what these services
generate to match a newer AEAT revision without updating this document.**

All documents below were fetched directly from AEAT's own domains
(`agenciatributaria.gob.es` / `agenciatributaria.es`) via `curl`, not from
any blog, tutorial, or third-party implementation. Retrieved 2026-09-20.

## Frozen sources

| # | Document | Version / date declared in the document | Server `Last-Modified` | Official URL |
|---|---|---|---|---|
| X1 | `SuministroInformacion.xsd` (record content schema) | Not version-stamped inside the file itself | **Sun, 11 Jan 2026** | `https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SuministroInformacion.xsd` |
| X2 | `SuministroLR.xsd` (SOAP envelope schema — referenced for documentation only, not used by Phase 2C's validator) | Not version-stamped | Wed, 01 Oct 2025 (via `SistemaFacturacion.wsdl`, same directory) | `https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SuministroLR.xsd` |
| D1 | "Sistemas Informáticos de Facturación" (SWeb description, official worked XML examples) | **v1.0.3, 28/07/2025** (declared inside the PDF's own revision table) | Tue, 14 Oct 2025 | `https://sede.agenciatributaria.gob.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Veri-Factu_Descripcion_SWeb.pdf` |
| D2 | "Detalle de las especificaciones técnicas ... huella o hash" | v0.1.2, 27/08/2024 (unchanged from Phase 1/2B — not re-fetched in this phase since the hash algorithm itself did not change) | — | `https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Veri-Factu_especificaciones_huella_hash_registros.pdf` |
| D3 | "Preguntas frecuentes de empresas de desarrollo" | Updated 04/12/2025 | — | `https://sede.agenciatributaria.gob.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/FAQs-Desarrolladores.pdf` |
| D4 | Orden HAC/1177/2024, de 17 de octubre (BOE-A-2024-22138) | Consolidated text, no revision date on the PDF itself | `https://www.boe.es/boe/dias/2024/10/28/pdfs/BOE-A-2024-22138.pdf` (re-fetched in full, Phase 2C.1, 2026-09-21) |

## ⚠️ Known discrepancy — flagged, not resolved

**X1's `Last-Modified` (11 Jan 2026) is newer than D1's own declared
revision date (28/07/2025, v1.0.3).** I checked D1's full extracted text
for a newer revision entry and found none — the document content itself
still declares v1.0.3 as current. I could not locate a newer published
revision of D1 during this phase (checked AEAT's developer portal index
at `.../informacion-tecnica.html` and its sub-pages; several of those
pages — "Diseños de registro," "Esquemas," "Documento de validaciones y
errores" — currently render as near-empty stubs with no linked PDF beyond
the FAQ, last touched 26/marzo/2026, suggesting an in-progress portal
restructuring).

**This means:** the XSD structure this code validates against is
confirmed current (fetched live, moments before writing this document),
but I cannot confirm whether it differs *in content* from the schema D1's
worked examples were written against, beyond what I directly compared
(see below). Before Phase 2D (real AEAT submission), someone should
re-check for a newer SWeb description revision and re-diff X1 against
what's cited here.

**What I did verify directly, so this isn't purely trust-the-metadata:**
I parsed the live X1 XSD's `RegistroFacturacionAltaType` and
`RegistroFacturacionAnulacionType` element sequences by hand and compared
them element-by-element against D1's two full worked XML examples
(§9.1.1.1 "alta inicial" and §9.1.2.1/anulación) — they match exactly,
in the same element order, with no field present in one and absent in the
other. Whatever changed between D1's declared date and X1's
`Last-Modified` timestamp, it did not alter the alta/anulación element
shape in a way visible from these two examples.

## Namespaces

| Prefix (as used in AEAT's own examples) | URI | Role |
|---|---|---|
| `sf` (schema's own alias; `sum1` in AEAT's worked XML examples) | `https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd` | Record content — `RegistroAlta`, `RegistroAnulacion`, and everything inside them. **This is the only namespace Phase 2C's `VerifactuXmlBuilder` emits.** |
| `sfLR` / `sum` | `https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd` | SOAP envelope wrapper (`RegFactuSistemaFacturacion`, `Cabecera`, `RegistroFactura`) — **Phase 2D's concern, not built here.** |
| `ds` | `http://www.w3.org/2000/09/xmldsig#` | XML-DSig, imported by X1 for the optional `ds:Signature` element. Not emitted — see the vendored-XSD README for why the import still has to be resolvable. |
| `soapenv` | `http://schemas.xmlsoap.org/soap/envelope/` | SOAP 1.1 envelope — Phase 2D's concern. |

Note AEAT's own worked examples use the prefix `sum1` where the schema
file's own internal alias is `sf` — these are the same namespace URI, just
different prefixes chosen at each usage site. XML namespace correctness
is about the URI, not the prefix string, so `VerifactuXmlBuilder` is free
to pick its own prefix (it uses the default/no-prefix form, declaring `sf`'s
URI as the document's default namespace) as long as the URI matches.

## Vendored files

See `resources/verifactu/xsd/2026-09-20/README.md` for exact file
provenance, checksums, and the one intentional local edit (a `schemaLocation`
repoint so validation never needs network access).

## What Phase 2C does NOT cover

Per the explicit Phase 2C scope: the SOAP envelope (`RegFactuSistemaFacturacion`/`Cabecera`),
`ds:Signature`, AEAT submission, and QR generation are all out of scope
here. This document only freezes the version for `RegistroAlta`/
`RegistroAnulacion` generation and validation.
