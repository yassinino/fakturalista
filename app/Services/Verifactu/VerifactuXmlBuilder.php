<?php

namespace App\Services\Verifactu;

use App\Models\Verifactu\VerifactuRecord;

/**
 * Builds a standalone RegistroAlta / RegistroAnulacion XML document from an
 * already-persisted, immutable VerifactuRecord - and ONLY from it (plus
 * its taxDetails()/previousRecord() relations, themselves immutable
 * VerifactuRecord*rows, and static config/verifactu.php). Never reads
 * Invoice, Customer, or CompanyProfile - see docs/verifactu-phase-2c1-
 * decisions.md §8 for the completeness audit that confirmed this and
 * VerifactuChainService for where every one of these fields gets
 * snapshotted, once, at record-generation time.
 *
 * Structure, element order, namespaces and cardinality are all sourced
 * from the currently-vendored AEAT schema - see
 * docs/verifactu-xml-spec-freeze.md. Built entirely via DOMDocument
 * (never string concatenation), so escaping/encoding is handled by the
 * DOM API itself.
 *
 * ── Known, deliberate omissions (schema-optional; not guessed) ─────────
 *  - Destinatarios/IDDestinatario: IDOtro's IDType is restricted to the
 *    official lista L7 codes a Customer can actually record
 *    (Customer::hasForeignTaxId()); a customer with neither a Spanish NIF
 *    nor a foreign ID recorded has no destinatario emitted at all.
 *  - FacturasSustituidas, FechaOperacion, RefExterna, Subsanacion,
 *    RechazoPrevio, Tercero, Cupon, Macrodato, ds:Signature: no
 *    Fakturalista data source exists for any of these; all are optional.
 */
class VerifactuXmlBuilder
{
    private const NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    public function build(VerifactuRecord $record): string
    {
        return $record->isAnulacion() ? $this->buildAnulacion($record) : $this->buildAlta($record);
    }

    public function buildAlta(VerifactuRecord $record): string
    {
        if (!$record->isAlta()) {
            throw new \InvalidArgumentException('buildAlta() requires a VerifactuRecord of tipo_registro=alta.');
        }

        $dom  = $this->newDocument();
        $root = $this->el($dom, 'RegistroAlta');
        $dom->appendChild($root);

        $root->appendChild($this->el($dom, 'IDVersion', '1.0'));

        $idFactura = $this->el($dom, 'IDFactura');
        $idFactura->appendChild($this->el($dom, 'IDEmisorFactura', $record->nif_emisor));
        $idFactura->appendChild($this->el($dom, 'NumSerieFactura', $record->serie_numero));
        $idFactura->appendChild($this->el($dom, 'FechaExpedicionFactura', $this->formatFecha($record->fecha_expedicion)));
        $root->appendChild($idFactura);

        $root->appendChild($this->el($dom, 'NombreRazonEmisor', $record->nombre_razon_emisor));
        $root->appendChild($this->el($dom, 'TipoFactura', $record->tipo_factura));

        if (!empty($record->tipo_rectificativa)) {
            $root->appendChild($this->el($dom, 'TipoRectificativa', $record->tipo_rectificativa));
            $root->appendChild($this->buildFacturasRectificadas($dom, $record));

            $importeRectificacion = $this->buildImporteRectificacion($dom, $record);
            if ($importeRectificacion) {
                $root->appendChild($importeRectificacion);
            }
        }

        $root->appendChild($this->el($dom, 'DescripcionOperacion', $record->descripcion_operacion));

        $destinatarios = $this->buildDestinatarios($dom, $record);
        if ($destinatarios) {
            $root->appendChild($destinatarios);
        }

        $root->appendChild($this->buildDesglose($dom, $record));
        $root->appendChild($this->el($dom, 'CuotaTotal', $this->formatImporte($record->cuota_total)));
        $root->appendChild($this->el($dom, 'ImporteTotal', $this->formatImporte($record->importe_total)));
        $root->appendChild($this->buildEncadenamiento($dom, $record));
        $root->appendChild($this->buildSistemaInformatico($dom, $record));
        $root->appendChild($this->el($dom, 'FechaHoraHusoGenRegistro', $record->fecha_hora_huso_gen_registro));
        $root->appendChild($this->el($dom, 'TipoHuella', '01'));
        $root->appendChild($this->el($dom, 'Huella', $record->huella));

        return $dom->saveXML();
    }

    public function buildAnulacion(VerifactuRecord $record): string
    {
        if (!$record->isAnulacion()) {
            throw new \InvalidArgumentException('buildAnulacion() requires a VerifactuRecord of tipo_registro=anulacion.');
        }

        $dom  = $this->newDocument();
        $root = $this->el($dom, 'RegistroAnulacion');
        $dom->appendChild($root);

        $root->appendChild($this->el($dom, 'IDVersion', '1.0'));

        $idFactura = $this->el($dom, 'IDFactura');
        $idFactura->appendChild($this->el($dom, 'IDEmisorFacturaAnulada', $record->nif_emisor));
        $idFactura->appendChild($this->el($dom, 'NumSerieFacturaAnulada', $record->serie_numero));
        $idFactura->appendChild($this->el($dom, 'FechaExpedicionFacturaAnulada', $this->formatFecha($record->fecha_expedicion)));
        $root->appendChild($idFactura);

        $root->appendChild($this->buildEncadenamiento($dom, $record));
        $root->appendChild($this->buildSistemaInformatico($dom, $record));
        $root->appendChild($this->el($dom, 'FechaHoraHusoGenRegistro', $record->fecha_hora_huso_gen_registro));
        $root->appendChild($this->el($dom, 'TipoHuella', '01'));
        $root->appendChild($this->el($dom, 'Huella', $record->huella));

        return $dom->saveXML();
    }

    /**
     * FacturasRectificadas/IDFacturaRectificada, from the snapshot
     * VerifactuChainService::buildRectificationSnapshot() already copied
     * from the original invoice - IDEmisorFactura is this same record's
     * own nif_emisor (see that method's docblock for why: AEAT derives it
     * from the current record's IDFactura block regardless of what's sent
     * here, and Fakturalista never rectifies across a NIF change).
     */
    private function buildFacturasRectificadas(\DOMDocument $dom, VerifactuRecord $record): \DOMElement
    {
        if (empty($record->rectifica_num_serie)) {
            throw new \RuntimeException(
                'No se puede generar el XML de una factura rectificativa: falta el snapshot de la factura '
                . 'original (rectifica_num_serie/rectifica_fecha_expedicion).'
            );
        }

        $facturasRectificadas = $this->el($dom, 'FacturasRectificadas');
        $idFacturaRectificada = $this->el($dom, 'IDFacturaRectificada');
        $idFacturaRectificada->appendChild($this->el($dom, 'IDEmisorFactura', $record->nif_emisor));
        $idFacturaRectificada->appendChild($this->el($dom, 'NumSerieFactura', $record->rectifica_num_serie));
        $idFacturaRectificada->appendChild($this->el($dom, 'FechaExpedicionFactura', $this->formatFecha($record->rectifica_fecha_expedicion)));
        $facturasRectificadas->appendChild($idFacturaRectificada);

        return $facturasRectificadas;
    }

    /**
     * ImporteRectificacion (DesgloseRectificacionType) - only for
     * TipoRectificativa=S (sustitución); VerifactuChainService only
     * populates importe_rectificacion_base/_cuota in that case. Returns
     * null (nothing to append) for 'I' (por diferencias), matching the
     * schema's own minOccurs="0".
     */
    private function buildImporteRectificacion(\DOMDocument $dom, VerifactuRecord $record): ?\DOMElement
    {
        if (is_null($record->importe_rectificacion_base)) {
            return null;
        }

        $importeRectificacion = $this->el($dom, 'ImporteRectificacion');
        $importeRectificacion->appendChild($this->el($dom, 'BaseRectificada', $this->formatImporte($record->importe_rectificacion_base)));
        $importeRectificacion->appendChild($this->el($dom, 'CuotaRectificada', $this->formatImporte($record->importe_rectificacion_cuota)));
        // CuotaRecargoRectificado omitted: Fakturalista has no "recargo de
        // equivalencia" concept anywhere in its invoicing model.

        return $importeRectificacion;
    }

    /**
     * Destinatarios/IDDestinatario - NIF branch when the customer has a
     * Spanish NIF (destinatario_nif), IDOtro branch when identified as
     * foreign (destinatario_id_type/destinatario_id, CodigoPais optional),
     * or no element at all when neither was captured (correct for F2;
     * see VerifactuChainService::resolveDestinatario()).
     */
    private function buildDestinatarios(\DOMDocument $dom, VerifactuRecord $record): ?\DOMElement
    {
        if (empty($record->destinatario_nif) && empty($record->destinatario_id)) {
            return null;
        }

        $idDestinatario = $this->el($dom, 'IDDestinatario');
        $idDestinatario->appendChild($this->el($dom, 'NombreRazon', $record->destinatario_nombre_razon));

        if (!empty($record->destinatario_nif)) {
            $idDestinatario->appendChild($this->el($dom, 'NIF', $record->destinatario_nif));
        } else {
            $idOtro = $this->el($dom, 'IDOtro');
            if (!empty($record->destinatario_id_pais)) {
                $idOtro->appendChild($this->el($dom, 'CodigoPais', $record->destinatario_id_pais));
            }
            $idOtro->appendChild($this->el($dom, 'IDType', $record->destinatario_id_type));
            $idOtro->appendChild($this->el($dom, 'ID', $record->destinatario_id));
            $idDestinatario->appendChild($idOtro);
        }

        $destinatarios = $this->el($dom, 'Destinatarios');
        $destinatarios->appendChild($idDestinatario);

        return $destinatarios;
    }

    private function buildDesglose(\DOMDocument $dom, VerifactuRecord $record): \DOMElement
    {
        $details = $record->taxDetails;

        if ($details->isEmpty()) {
            throw new \RuntimeException(
                'No se puede generar el XML: el registro VERI*FACTU no tiene desglose de IVA asociado.'
            );
        }

        $desglose = $this->el($dom, 'Desglose');

        foreach ($details as $detail) {
            $detalle = $this->el($dom, 'DetalleDesglose');
            $detalle->appendChild($this->el($dom, 'ClaveRegimen', $detail->clave_regimen));

            if (!empty($detail->operacion_exenta)) {
                $detalle->appendChild($this->el($dom, 'OperacionExenta', $detail->operacion_exenta));
            } else {
                $detalle->appendChild($this->el($dom, 'CalificacionOperacion', $detail->calificacion_operacion));
            }

            $detalle->appendChild($this->el($dom, 'TipoImpositivo', $this->formatTipo($detail->tipo_impositivo)));
            $detalle->appendChild($this->el($dom, 'BaseImponibleOimporteNoSujeto', $this->formatImporte($detail->base_imponible)));

            if (!is_null($detail->cuota_repercutida)) {
                $detalle->appendChild($this->el($dom, 'CuotaRepercutida', $this->formatImporte($detail->cuota_repercutida)));
            }

            $desglose->appendChild($detalle);
        }

        return $desglose;
    }

    /**
     * Encadenamiento is a choice: PrimerRegistro="S" for the first record
     * ever generated for this NIF, or RegistroAnterior built from the
     * PREVIOUS record's own already-immutable identity fields (never from
     * Invoice/Customer/CompanyProfile - see VerifactuRecord::previousRecord()).
     */
    private function buildEncadenamiento(\DOMDocument $dom, VerifactuRecord $record): \DOMElement
    {
        $encadenamiento = $this->el($dom, 'Encadenamiento');

        if ($record->es_primer_registro) {
            $encadenamiento->appendChild($this->el($dom, 'PrimerRegistro', 'S'));

            return $encadenamiento;
        }

        $previous = $record->previousRecord;

        if (!$previous) {
            throw new \RuntimeException(
                'No se puede generar el XML: el registro indica que no es el primero de la cadena, pero no '
                . 'tiene un registro anterior asociado.'
            );
        }

        $registroAnterior = $this->el($dom, 'RegistroAnterior');
        $registroAnterior->appendChild($this->el($dom, 'IDEmisorFactura', $previous->nif_emisor));
        $registroAnterior->appendChild($this->el($dom, 'NumSerieFactura', $previous->serie_numero));
        $registroAnterior->appendChild($this->el($dom, 'FechaExpedicionFactura', $this->formatFecha($previous->fecha_expedicion)));
        $registroAnterior->appendChild($this->el($dom, 'Huella', $previous->huella));
        $encadenamiento->appendChild($registroAnterior);

        return $encadenamiento;
    }

    /**
     * Fakturalista's own identity as software producer - config-driven,
     * never invented (see config/verifactu.php). NumeroInstalacion comes
     * from the record's own snapshot (VerifactuChainService already
     * required and copied it from CompanyProfile at generation time - see
     * that class's requireInstallationNumber()), never re-read here.
     *
     * @throws \RuntimeException if the producer/system identity has not
     *         been configured.
     */
    private function buildSistemaInformatico(\DOMDocument $dom, VerifactuRecord $record): \DOMElement
    {
        $producer = config('verifactu.producer');
        $system   = config('verifactu.system');

        $hasNif    = !empty($producer['nif']);
        $hasIdOtro = !empty($producer['id_country']) && !empty($producer['id_type']) && !empty($producer['id']);

        if (empty($producer['name']) || (!$hasNif && !$hasIdOtro)) {
            throw new \RuntimeException(
                'No se puede generar el XML: falta configurar la identidad de Fakturalista como productor del '
                . 'sistema informático (config/verifactu.php - VERIFACTU_PRODUCER_NAME y, o bien '
                . 'VERIFACTU_PRODUCER_NIF, o bien VERIFACTU_PRODUCER_ID_COUNTRY/_ID_TYPE/_ID).'
            );
        }

        if (empty($system['name']) || empty($system['id']) || empty($system['version'])) {
            throw new \RuntimeException(
                'No se puede generar el XML: falta configurar el nombre, identificador o versión del sistema '
                . 'informático en config/verifactu.php.'
            );
        }

        if (empty($record->numero_instalacion)) {
            throw new \RuntimeException(
                'No se puede generar el XML: el registro no tiene número de instalación asociado.'
            );
        }

        $sistemaInformatico = $this->el($dom, 'SistemaInformatico');
        $sistemaInformatico->appendChild($this->el($dom, 'NombreRazon', $producer['name']));

        if ($hasNif) {
            $sistemaInformatico->appendChild($this->el($dom, 'NIF', $producer['nif']));
        } else {
            $idOtro = $this->el($dom, 'IDOtro');
            $idOtro->appendChild($this->el($dom, 'CodigoPais', $producer['id_country']));
            $idOtro->appendChild($this->el($dom, 'IDType', $producer['id_type']));
            $idOtro->appendChild($this->el($dom, 'ID', $producer['id']));
            $sistemaInformatico->appendChild($idOtro);
        }

        $sistemaInformatico->appendChild($this->el($dom, 'NombreSistemaInformatico', $system['name']));
        $sistemaInformatico->appendChild($this->el($dom, 'IdSistemaInformatico', $system['id']));
        $sistemaInformatico->appendChild($this->el($dom, 'Version', $system['version']));
        $sistemaInformatico->appendChild($this->el($dom, 'NumeroInstalacion', $record->numero_instalacion));
        $sistemaInformatico->appendChild($this->el($dom, 'TipoUsoPosibleSoloVerifactu', $this->siNo($system['only_verifactu'])));
        $sistemaInformatico->appendChild($this->el($dom, 'TipoUsoPosibleMultiOT', $this->siNo($system['multi_ot'])));
        $sistemaInformatico->appendChild($this->el($dom, 'IndicadorMultiplesOT', $this->siNo($system['multiple_ot_in_use'])));

        return $sistemaInformatico;
    }

    private function siNo(mixed $value): string
    {
        return $value ? 'S' : 'N';
    }

    private function newDocument(): \DOMDocument
    {
        $dom               = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        return $dom;
    }

    private function el(\DOMDocument $dom, string $name, ?string $value = null): \DOMElement
    {
        $element = $dom->createElementNS(self::NS, $name);

        if ($value !== null) {
            $element->appendChild($dom->createTextNode($value));
        }

        return $element;
    }

    private function formatFecha(mixed $date): string
    {
        return \Carbon\Carbon::parse($date)->format('d-m-Y');
    }

    private function formatImporte(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function formatTipo(mixed $rate): string
    {
        $rate = (float) $rate;

        return $rate == (int) $rate ? (string) (int) $rate : number_format($rate, 2, '.', '');
    }
}
