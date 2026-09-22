# Vendored VERI*FACTU XSDs — retrieved 2026-09-20

Fetched directly from AEAT's production static file server (`curl`, not a
blog/tutorial/third-party mirror) as part of Phase 2C. Vendored so
`DOMDocument::schemaValidate()` never fetches anything over the network at
request time (see docs/verifactu-implementation-plan.md §8).

## Files

| File | Source URL | Server `Last-Modified` | SHA-256 |
|---|---|---|---|
| `SuministroInformacion.xsd` | `https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SuministroInformacion.xsd` | Sun, 11 Jan 2026 23:01:15 GMT | `ee4c1655175644de44c4c25055ffeb8e5f4bb4bc3834ce8254d4222ef18c8aa1` |
| `xmldsig-core-schema.xsd` | `http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd` | (W3C standard, not versioned by AEAT) | `d102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d` |
| `SuministroInformacion.local.xsd` | Derived from `SuministroInformacion.xsd` | — | — |

`SuministroInformacion.local.xsd` is byte-identical to the pristine
`SuministroInformacion.xsd` except for one line: the `<import>`'s
`schemaLocation` for the `ds:` (XML-DSig) namespace is repointed from
AEAT's absolute `http://www.w3.org/TR/...` URL to the vendored
`xmldsig-core-schema.xsd` sitting next to it, so schema compilation never
needs network access. This is the file `VerifactuXmlValidator` actually
loads. `ds:Signature` itself is `minOccurs="0"` in every type that
references it and Phase 2C never emits it (see the main spec-freeze doc:
VERI*FACTU-only systems don't need per-record XML signing), so this import
is only there because the schema *declares* the type, not because any
generated document uses it.

**Do not hand-edit `SuministroInformacion.xsd` or `xmldsig-core-schema.xsd`.**
If AEAT publishes a new version, fetch it fresh into a new dated directory
(e.g. `resources/verifactu/xsd/2027-01-15/`), re-derive a new
`.local.xsd` the same way, and update
`docs/verifactu-xml-spec-freeze.md` plus `VerifactuXmlValidator`'s
referenced path. Never silently overwrite a dated directory in place.

## Not vendored (not needed to validate a standalone RegistroAlta/RegistroAnulacion)

`SuministroLR.xsd` (the SOAP envelope wrapper), `ConsultaLR.xsd`,
`RespuestaSuministro.xsd`, `RespuestaConsultaLR.xsd`, and
`SistemaFacturacion.wsdl` all exist at the same source URL but belong to
the AEAT *submission* (Phase 2D), not to generating/validating a single
record. `RegistroAlta` and `RegistroAnulacion` are top-level global
elements in `SuministroInformacion.xsd` with fully self-contained type
definitions, so they validate standalone without the envelope schema.
