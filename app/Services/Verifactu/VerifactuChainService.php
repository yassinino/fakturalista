<?php

namespace App\Services\Verifactu;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\Verifactu\VerifactuChainState;
use App\Models\Verifactu\VerifactuRecord;
use App\Models\Verifactu\VerifactuRecordTaxDetail;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Generates VERI*FACTU registros de facturación (alta/anulación) and
 * chains them per Orden HAC/1177/2024 art. 7: each record references the
 * immediately-previous one's hash, scoped per emitting NIF (see
 * VerifactuChainState).
 *
 * Phase 2B scope: this service is intentionally NOT wired into
 * InvoiceController::issue()/cancel() yet. It builds and persists a
 * correct, chained, concurrency-safe record - nothing here submits
 * anything to AEAT (no HTTP/SOAP call exists yet - that's Phase 2D).
 * Wiring this into the live issuance flow is a separate, explicitly-
 * approved step, since it changes production behavior for every invoice
 * issuance and first needs a per-tenant opt-in gate
 * (company_profiles.verifactu_enabled, not yet built - see
 * docs/verifactu-implementation-plan.md §4/§18).
 *
 * Phase 2C/2C.1: this is also where the full XML-reproducible snapshot
 * gets captured - fields that come from MUTABLE sources
 * (CompanyProfile.legal_name, Customer.name/tax_id, a rectification's
 * ORIGINAL invoice) are copied in here, once, so VerifactuXmlBuilder
 * never has to (and never should) re-read Invoice/Customer/CompanyProfile
 * later. See docs/verifactu-phase-2c1-decisions.md for the full citation
 * per field.
 */
class VerifactuChainService
{
    /** Fixed VAT rates Fakturalista's UI supports (Invoice.vta4/vta10/vta21). */
    private const VAT_RATES = [4 => 'vta4', 10 => 'vta10', 21 => 'vta21'];

    // Orden HAC/1177/2024, Anexo, lista L1 (Impuesto).
    private const IMPUESTO_IVA = '01';

    // Lista L8A (ClaveRegimen para desgloses de IVA) has 20 values (01
    // régimen general, 02 exportación, ... 20 régimen simplificado).
    // Fakturalista's invoicing model has no data for any regime other
    // than the ordinary general one - no OSS/IOSS, no criterio de caja,
    // no recargo de equivalencia, no agencias de viaje, etc. - so this is
    // the only value ever written, and buildTaxBreakdown() has nothing
    // else to choose from, not an assumption made per-invoice.
    private const CLAVE_REGIMEN_GENERAL = '01';

    // Lista L9 (CalificacionOperacion): S1/S2/N1/N2. Fakturalista has no
    // reverse-charge flag, no not-subject-to-VAT concept, and no
    // exemption-reason selector (lista L10, OperacionExenta) anywhere in
    // its invoicing model - every invoice it can currently construct
    // describes an ordinary, fully-taxable domestic sale, i.e. S1.
    private const CALIFICACION_SUJETA_NO_EXENTA_SIN_INVERSION = 'S1';

    public function __construct(private VerifactuHashService $hasher)
    {
    }

    /**
     * Generate the "registro de alta" for a just-issued invoice.
     *
     * @throws \RuntimeException if the invoice has no definitive legal
     *         number yet, the company has no NIF/CIF on file, the invoice
     *         has no DescripcionOperacion, has no VAT amount on any of
     *         the three supported rates, its derived tax base doesn't
     *         reconcile with its own total (signalling an unrepresented
     *         0%/exempt line), or - for a rectificativa - the original
     *         invoice isn't itself correctly numbered.
     */
    public function recordAlta(Invoice $invoice, CompanyProfile $company): VerifactuRecord
    {
        $nif                = $this->requireNif($company);
        $installationNumber = $this->requireInstallationNumber($company);
        $this->requireLegalNumber($invoice);
        $this->requireDescripcionOperacion($invoice);
        $taxBreakdown = $this->buildTaxBreakdown($invoice);

        $invoice->loadMissing('customer');
        $destinatario = $this->resolveDestinatario($invoice->customer);

        $rectificationSnapshot = $invoice->isRectification()
            ? $this->buildRectificationSnapshot($invoice)
            : [];

        return DB::transaction(function () use (
            $invoice, $company, $nif, $installationNumber, $taxBreakdown, $destinatario, $rectificationSnapshot
        ) {
            $chain            = $this->lockChain($nif);
            $huellaAnterior   = $chain->last_huella;
            $previousRecordId = $chain->last_verifactu_record_id;
            $timestamp        = $this->generatedAtTimestamp($company);

            $tipoFactura  = $invoice->invoice_type ?: Invoice::TYPE_F1;
            $cuotaTotal   = (float) $invoice->vta;
            $importeTotal = (float) $invoice->total;

            $huella = $this->hasher->hashAlta([
                'id_emisor_factura'            => $nif,
                'num_serie_factura'            => $invoice->reference,
                'fecha_expedicion_factura'     => Carbon::parse($invoice->date)->format('d-m-Y'),
                'tipo_factura'                 => $tipoFactura,
                'cuota_total'                  => $cuotaTotal,
                'importe_total'                => $importeTotal,
                'huella_registro_anterior'     => $huellaAnterior,
                'fecha_hora_huso_gen_registro' => $timestamp,
            ]);

            $record = VerifactuRecord::create(array_merge([
                'invoice_id'                   => $invoice->id,
                'tipo_registro'                => VerifactuRecord::TIPO_ALTA,
                'nif_emisor'                    => $nif,
                'nombre_razon_emisor'           => $company->legal_name,
                'serie_numero'                  => $invoice->reference,
                'fecha_expedicion'               => $invoice->date,
                'tipo_factura'                   => $tipoFactura,
                'descripcion_operacion'          => $invoice->descripcion_operacion,
                'destinatario_nombre_razon'      => $destinatario['nombre_razon'] ?? null,
                'destinatario_nif'               => $destinatario['nif'] ?? null,
                'destinatario_id_pais'           => $destinatario['id_pais'] ?? null,
                'destinatario_id_type'           => $destinatario['id_type'] ?? null,
                'destinatario_id'                => $destinatario['id'] ?? null,
                'tipo_rectificativa'             => $invoice->rectification_type,
                'numero_instalacion'             => $installationNumber,
                'cuota_total'                    => $cuotaTotal,
                'importe_total'                  => $importeTotal,
                'huella_registro_anterior'       => $huellaAnterior,
                'previous_verifactu_record_id'   => $previousRecordId,
                'es_primer_registro'             => is_null($huellaAnterior),
                'fecha_hora_huso_gen_registro'   => $timestamp,
                'huella'                         => $huella,
                'estado_envio'                   => VerifactuRecord::ESTADO_PENDIENTE,
            ], $rectificationSnapshot));

            foreach ($taxBreakdown as $detail) {
                VerifactuRecordTaxDetail::create(array_merge($detail, [
                    'verifactu_record_id' => $record->id,
                ]));
            }

            $this->advanceChain($chain, $record);
            $this->logAudit($invoice, $record);

            return $record;
        });
    }

    /**
     * Generate the "registro de anulación" for an invoice that already has
     * its own alta record.
     *
     * @throws \RuntimeException if the invoice has no legal number, or no
     *         prior alta record exists for it - annulling something that
     *         was never registered doesn't correspond to any real AEAT
     *         operation.
     */
    public function recordAnulacion(Invoice $invoice, CompanyProfile $company): VerifactuRecord
    {
        $nif                = $this->requireNif($company);
        $installationNumber = $this->requireInstallationNumber($company);
        $this->requireLegalNumber($invoice);

        $hasAlta = VerifactuRecord::where('invoice_id', $invoice->id)
            ->where('tipo_registro', VerifactuRecord::TIPO_ALTA)
            ->exists();

        if (!$hasAlta) {
            throw new \RuntimeException(
                'Esta factura no tiene un registro de alta VERI*FACTU; no se puede generar su anulación.'
            );
        }

        return DB::transaction(function () use ($invoice, $company, $nif, $installationNumber) {
            $chain            = $this->lockChain($nif);
            $huellaAnterior   = $chain->last_huella;
            $previousRecordId = $chain->last_verifactu_record_id;
            $timestamp        = $this->generatedAtTimestamp($company);

            $huella = $this->hasher->hashAnulacion([
                'id_emisor_factura'            => $nif,
                'num_serie_factura'            => $invoice->reference,
                'fecha_expedicion_factura'     => Carbon::parse($invoice->date)->format('d-m-Y'),
                'huella_registro_anterior'     => $huellaAnterior,
                'fecha_hora_huso_gen_registro' => $timestamp,
            ]);

            $record = VerifactuRecord::create([
                'invoice_id'                    => $invoice->id,
                'tipo_registro'                 => VerifactuRecord::TIPO_ANULACION,
                'nif_emisor'                     => $nif,
                'serie_numero'                   => $invoice->reference,
                'fecha_expedicion'                => $invoice->date,
                'huella_registro_anterior'        => $huellaAnterior,
                'previous_verifactu_record_id'    => $previousRecordId,
                'numero_instalacion'               => $installationNumber,
                'es_primer_registro'              => is_null($huellaAnterior),
                'fecha_hora_huso_gen_registro'     => $timestamp,
                'huella'                           => $huella,
                'estado_envio'                     => VerifactuRecord::ESTADO_PENDIENTE,
            ]);

            $this->advanceChain($chain, $record);
            $this->logAudit($invoice, $record);

            return $record;
        });
    }

    private function requireNif(CompanyProfile $company): string
    {
        if (empty($company->tax_id)) {
            throw new \RuntimeException('No se puede generar un registro VERI*FACTU sin el NIF/CIF del emisor.');
        }

        return trim($company->tax_id);
    }

    private function requireInstallationNumber(CompanyProfile $company): string
    {
        if (empty($company->verifactu_installation_number)) {
            throw new \RuntimeException(
                'No se puede generar un registro VERI*FACTU: falta configurar el número de instalación '
                . '(company_profiles.verifactu_installation_number) para esta empresa.'
            );
        }

        return trim($company->verifactu_installation_number);
    }

    private function requireLegalNumber(Invoice $invoice): void
    {
        if (empty($invoice->invoice_series) || empty($invoice->invoice_number)) {
            throw new \RuntimeException(
                'No se puede generar un registro VERI*FACTU para una factura sin numeración legal definitiva.'
            );
        }
    }

    private function requireDescripcionOperacion(Invoice $invoice): void
    {
        if (empty(trim((string) $invoice->descripcion_operacion))) {
            throw new \RuntimeException(
                'No se puede generar un registro de alta VERI*FACTU: falta la descripción de la operación '
                . '(DescripcionOperacion es obligatorio para la AEAT y esta factura no la tiene).'
            );
        }
    }

    /**
     * Resolve which AEAT identification branch (if any) represents this
     * customer, snapshotting exactly what was used - never re-read by
     * VerifactuXmlBuilder later.
     *
     * NIF (Spanish) always takes priority when `tax_id` is set, for
     * backward compatibility with every customer created before the
     * foreign-ID fields existed. IDOtro (Orden HAC/1177/2024, Anexo, lista
     * L7) applies only when the customer has been explicitly identified
     * as foreign (Customer::hasForeignTaxId()) - CodigoPais comes from the
     * customer's existing billing country when set, but is optional in
     * the schema so its absence doesn't block the IDOtro branch.
     *
     * Returns [] when neither applies (correct for F2 - "sin
     * identificación del destinatario" - and a documented gap for any F1
     * customer with neither a Spanish NIF nor a foreign ID recorded,
     * though issueInvoice() already blocks issuing an F1 without a
     * customer tax_id today).
     *
     * @return array{nombre_razon?: string, nif?: string, id_pais?: ?string, id_type?: string, id?: string}
     */
    private function resolveDestinatario(?Customer $customer): array
    {
        if (!$customer) {
            return [];
        }

        if (!empty($customer->tax_id)) {
            return [
                'nombre_razon' => $customer->name,
                'nif'          => trim($customer->tax_id),
            ];
        }

        if ($customer->hasForeignTaxId()) {
            $customer->loadMissing('billingCountry');

            return [
                'nombre_razon' => $customer->name,
                'id_pais'      => $customer->billingCountry?->code,
                'id_type'      => $customer->foreign_tax_id_type,
                'id'           => trim($customer->foreign_tax_id),
            ];
        }

        return [];
    }

    /**
     * Snapshot everything needed to build FacturasRectificadas (always)
     * and ImporteRectificacion (TipoRectificativa=S only) from the
     * ORIGINAL invoice, once, here - VerifactuXmlBuilder must never load
     * $invoice->rectifies itself.
     *
     * IDEmisorFactura inside AEAT's own IDFacturaARType is, per the XSD's
     * own annotation, "cogido del NIF indicado en el bloque IDFactura" (i.e.
     * AEAT derives it from the CURRENT record's own emisor, not from a
     * separately-submitted value) - since Fakturalista never rectifies
     * across a NIF change, this is simply the same $nif as the
     * rectification's own record, not a value read from $original.
     *
     * ImporteRectificacion (DesgloseRectificacionType) per Orden
     * HAC/1177/2024's field table and the XSD's own annotation
     * ("Desglose de Base y Cuota sustituida en las Facturas Rectificativas
     * sustitutivas") represents the ORIGINAL's own base/cuota being
     * substituted - only meaningful for TipoRectificativa=S. Read directly
     * from the original invoice's own sub_total/vta, which Phase 2A
     * already guarantees are frozen once issued.
     *
     * @throws \RuntimeException if the original is missing or was never
     *         issued with a definitive legal number - a rectificativa
     *         must never reference an original that doesn't legally exist.
     */
    private function buildRectificationSnapshot(Invoice $invoice): array
    {
        $original = $invoice->rectifies;

        if (!$original || empty($original->invoice_series) || empty($original->invoice_number)) {
            throw new \RuntimeException(
                'No se puede generar un registro VERI*FACTU de una factura rectificativa sin la factura '
                . 'original correctamente numerada.'
            );
        }

        $snapshot = [
            'rectifica_num_serie'        => $original->reference,
            'rectifica_fecha_expedicion' => $original->date,
        ];

        if ($invoice->rectification_type === Invoice::RECTIFICATION_MODE_SUSTITUCION) {
            $snapshot['importe_rectificacion_base']  = round((float) $original->sub_total, 2);
            $snapshot['importe_rectificacion_cuota'] = round((float) $original->vta, 2);
        }

        return $snapshot;
    }

    /**
     * Derive the mandatory per-rate Desglose breakdown from the invoice's
     * own already-stored, already-authoritative per-rate cuota columns
     * (vta4/vta10/vta21) - base = cuota / (rate/100), the exact algebraic
     * inverse of how that cuota was computed, never a re-derivation from
     * `carts`. See the verifactu_record_tax_details migration docblock.
     *
     * Every detail row uses ClaveRegimen=01 (régimen general) and
     * CalificacionOperacion=S1 (sujeta y no exenta, sin inversión) - the
     * only combination Fakturalista's invoicing model can currently
     * represent (see the class constants' docblocks). This is a
     * consequence of what data exists, not an assumption made because
     * AEAT's own worked examples happen to use the same values.
     *
     * A reconciliation check guards against a scenario Fakturalista
     * cannot currently detect from vta4/10/21 alone: an invoice with a
     * mix of taxed lines and an (unsupported) 0%/exempt line. The
     * invariant ImporteTotal = CuotaTotal + Σ(BaseImponible) must hold
     * for any correct Desglose, independent of how per-line/invoice-level
     * discounts were computed upstream (verified algebraically against
     * CreateInvoiceForm.vue's actual calculation, not assumed) - if it
     * doesn't, some taxable base exists that isn't accounted for in any
     * of the three supported rate buckets, and this must not be silently
     * dropped from the submitted Desglose.
     *
     * @return array<int, array<string, mixed>>
     * @throws \RuntimeException if the invoice has no VAT amount on any
     *         of the three supported rates, or if the derived base
     *         doesn't reconcile with the invoice's own total.
     */
    private function buildTaxBreakdown(Invoice $invoice): array
    {
        $details = [];

        foreach (self::VAT_RATES as $rate => $column) {
            $cuota = (float) ($invoice->{$column} ?? 0);

            if ($cuota <= 0) {
                continue;
            }

            $details[] = [
                'impuesto'               => self::IMPUESTO_IVA,
                'clave_regimen'          => self::CLAVE_REGIMEN_GENERAL,
                'calificacion_operacion' => self::CALIFICACION_SUJETA_NO_EXENTA_SIN_INVERSION,
                'operacion_exenta'       => null,
                'tipo_impositivo'        => $rate,
                'base_imponible'         => round($cuota / ($rate / 100), 2),
                'cuota_repercutida'      => $cuota,
            ];
        }

        if (empty($details)) {
            throw new \RuntimeException(
                'No se puede generar un registro de alta VERI*FACTU: la factura no tiene importe de IVA en '
                . 'ninguno de los tipos soportados (4%, 10%, 21%). Las facturas exentas o al 0% no están '
                . 'representadas todavía en Fakturalista.'
            );
        }

        $totalBase     = round(array_sum(array_column($details, 'base_imponible')), 2);
        $expectedBase  = round((float) $invoice->total - (float) $invoice->vta, 2);

        if (abs($totalBase - $expectedBase) > 0.02) {
            throw new \RuntimeException(
                'No se puede generar un registro de alta VERI*FACTU: la base imponible calculada a partir de '
                . "las cuotas de IVA ({$totalBase}) no coincide con Importe Total - Cuota Total de la factura "
                . "({$expectedBase}). Esto puede indicar una línea exenta o al 0% no representada todavía en "
                . 'Fakturalista.'
            );
        }

        return $details;
    }

    private function generatedAtTimestamp(CompanyProfile $company): string
    {
        return Carbon::now($company->timezone ?: 'Europe/Madrid')->format('Y-m-d\TH:i:sP');
    }

    /**
     * Lock (and, on first use for this NIF, create) the chain-state row for
     * the duration of the surrounding transaction, so two concurrent
     * callers for the same NIF can never read the same "previous hash" -
     * same primitive as InvoiceNumberingService::reserve().
     */
    private function lockChain(string $nif): VerifactuChainState
    {
        $chain = VerifactuChainState::where('nif_emisor', $nif)->lockForUpdate()->first();

        if (!$chain) {
            try {
                VerifactuChainState::create(['nif_emisor' => $nif]);
            } catch (QueryException $e) {
                // Another concurrent request created it first - fall
                // through to re-select (and lock) the winner's row.
            }
            $chain = VerifactuChainState::where('nif_emisor', $nif)->lockForUpdate()->first();
        }

        return $chain;
    }

    private function advanceChain(VerifactuChainState $chain, VerifactuRecord $record): void
    {
        $chain->last_huella              = $record->huella;
        $chain->last_verifactu_record_id = $record->id;
        $chain->save();
    }

    private function logAudit(Invoice $invoice, VerifactuRecord $record): void
    {
        $invoice->logHistory(InvoiceHistory::ACTION_VERIFACTU_RECORD_GENERATED, [
            'tipo_registro'       => $record->tipo_registro,
            'verifactu_record_id' => $record->id,
            'huella'              => $record->huella,
        ]);
    }
}
