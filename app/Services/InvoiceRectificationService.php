<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\InvoiceTaxLine;
use App\Services\Tax\TaxTreatment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Routes a "this invoice needs correcting" request to the correct Spanish
 * invoicing mechanism - a rectificative invoice (factura rectificativa) or
 * an AEAT/VERI*FACTU annulment (registro de anulación) - and, for the
 * rectificativa path, creates the new document.
 *
 * ── Mechanism distinction (rectificativa vs. anulación) ────────────────
 *
 * - RD 1619/2012 art. 15.1 requires a rectificativa when the original
 *   invoice failed to meet the content requirements of arts. 6/7 (a
 *   "founded error", "error fundado en derecho").
 * - RD 1619/2012 art. 15.2 requires a rectificativa when the tax amounts
 *   were determined incorrectly, or when circumstances under LIVA
 *   (Ley 37/1992) art. 80 arise. All of these presuppose a REAL
 *   underlying operation that simply needs its figures corrected.
 * - The AEAT's own VERI*FACTU web-service specification (v1.0.3, §9.2.1)
 *   states the "registro de anulación" is used precisely for cases
 *   EXCLUDED from the rectificativa mechanism - its own example is "se
 *   haya expedido una factura por error cuando no ha habido una auténtica
 *   venta" (an invoice issued by mistake when there was no real sale at
 *   all). Where there is no real operation, there is nothing for a
 *   rectificativa to correct.
 *
 * ── R1-R5 codes (AEAT ClaveTipoFacturaType, Orden HAC/1177/2024, Anexo,
 *    lista L2) - quoted verbatim, not paraphrased ───────────────────────
 *
 *   R1  "Factura Rectificativa (Error fundado en derecho y art. 80 Uno
 *        Dos y Seis LIVA)."
 *   R2  "Factura Rectificativa (art. 80.3)."
 *   R3  "Factura Rectificativa (art. 80.4)."
 *   R4  "Factura Rectificativa (Resto)."
 *   R5  "Factura Rectificativa en facturas simplificadas."
 *
 * LIVA art. 80 (Ley 37/1992) sections referenced above:
 *   Uno   - base reduced for returned packaging/containers and for
 *           discounts/rebates granted AFTER the operation (documented).
 *   Dos   - operation declared wholly/partially void by firm judicial or
 *           administrative resolution, or per law/commercial usage, or
 *           its price altered after the operation took place.
 *   Tres  - base reduced for unpaid amounts once a "concurso de
 *           acreedores" (insolvency) declaration is issued against the
 *           recipient after the operation.
 *   Cuatro- credits declared wholly/partially uncollectible (bad debt),
 *           subject to its own separate procedure/deadlines.
 *   Seis  - named alongside Uno/Dos in R1's own definition.
 *
 * R1 therefore has TWO distinct legal bases that both resolve to the same
 * code - they are cited separately per reason below rather than lumped
 * together as "R1 for generic errors":
 *   - "error fundado en derecho" (a defect in the invoice itself - wrong
 *     data, wrong calculation) for REASON_ERROR_DATOS / REASON_IMPORTE_INCORRECTO.
 *   - LIVA art. 80.Uno (a real operation whose base is reduced after the
 *     fact) for REASON_DEVOLUCION / REASON_DESCUENTO_POSTERIOR.
 *
 * R2 and R3 are real, distinct AEAT codes for insolvency and bad-debt
 * procedures respectively - each has its own separate legal procedure
 * and deadlines under LIVA art. 80.3/80.4 that a simple business-reason
 * picker cannot safely infer. They remain fully representable via the
 * explicit `$invoiceTypeOverride` parameter on createRectification()
 * (see "advanced path" below), but are not offered as one of the six
 * guided REASON_* options - a reason picker cannot know whether a
 * bankruptcy/insolvency declaration has actually been issued, which is a
 * precondition for R2/R3, not a "business reason" a user selects.
 *
 * ── "Otro motivo" and other genuinely ambiguous input ───────────────────
 *
 * REASON_OTRO never silently resolves to a mechanism or a specific R-code.
 * determineMechanism() returns MECHANISM_REQUIRES_REVIEW for it unless the
 * caller supplies an explicit $explicitMode - it is better to ask one more
 * question than to guess a fiscal operation. Even when an explicit
 * 'rectificativa' mode is given for REASON_OTRO, the specific code
 * defaults to R4 ("Resto") only because that is *literally* what R4 means
 * officially - not a guess - but callers who know the real cause (e.g. an
 * insolvency or bad-debt case) should pass $invoiceTypeOverride explicitly
 * rather than rely on that default.
 */
class InvoiceRectificationService
{
    // Business-level reasons surfaced in the guided UI.
    public const REASON_ERROR_DATOS           = 'error_datos';
    public const REASON_IMPORTE_INCORRECTO    = 'importe_incorrecto';
    public const REASON_DEVOLUCION            = 'devolucion';
    public const REASON_DESCUENTO_POSTERIOR   = 'descuento_posterior';
    public const REASON_OPERACION_INEXISTENTE = 'operacion_inexistente';
    public const REASON_OTRO                  = 'otro';

    public const REASONS = [
        self::REASON_ERROR_DATOS,
        self::REASON_IMPORTE_INCORRECTO,
        self::REASON_DEVOLUCION,
        self::REASON_DESCUENTO_POSTERIOR,
        self::REASON_OPERACION_INEXISTENTE,
        self::REASON_OTRO,
    ];

    public const MECHANISM_RECTIFICATIVA  = 'rectificativa';
    public const MECHANISM_ANULACION      = 'anulacion';
    public const MECHANISM_REQUIRES_REVIEW = 'requires_review';

    /**
     * Precise legal basis for each reason, for display/audit/documentation
     * purposes - not just internal comments. Keyed by reason.
     */
    public const REASON_LEGAL_BASIS = [
        self::REASON_ERROR_DATOS           => 'RD 1619/2012 art. 15.1 (la factura original no cumple los requisitos de los arts. 6/7) - "error fundado en derecho", código R1.',
        self::REASON_IMPORTE_INCORRECTO    => 'RD 1619/2012 art. 15.2, primer inciso (cuotas impositivas determinadas incorrectamente) - "error fundado en derecho", código R1.',
        self::REASON_DEVOLUCION            => 'LIVA (Ley 37/1992) art. 80.Uno (envases/embalajes devueltos que reducen la base imponible), código R1.',
        self::REASON_DESCUENTO_POSTERIOR   => 'LIVA (Ley 37/1992) art. 80.Uno (descuentos y bonificaciones otorgados con posterioridad a la operación), código R1.',
        self::REASON_OPERACION_INEXISTENTE => 'AEAT, "Sistemas Informáticos de Facturación" v1.0.3 §9.2.1 - caso excluido de la factura rectificativa; procede registro de anulación.',
        self::REASON_OTRO                  => 'Motivo no clasificado - requiere confirmación explícita del mecanismo y, si es rectificativa, del código AEAT concreto.',
    ];

    /**
     * Reasons that unambiguously imply a real underlying operation exists
     * and simply needs correcting -> rectificativa. "operacion_inexistente"
     * unambiguously means the opposite -> anulación. "otro" is absent on
     * purpose - see class docblock.
     */
    private const REASON_MECHANISM_MAP = [
        self::REASON_ERROR_DATOS           => self::MECHANISM_RECTIFICATIVA,
        self::REASON_IMPORTE_INCORRECTO    => self::MECHANISM_RECTIFICATIVA,
        self::REASON_DEVOLUCION            => self::MECHANISM_RECTIFICATIVA,
        self::REASON_DESCUENTO_POSTERIOR   => self::MECHANISM_RECTIFICATIVA,
        self::REASON_OPERACION_INEXISTENTE => self::MECHANISM_ANULACION,
    ];

    public function __construct(private InvoiceNumberingService $numbering) {}

    /**
     * Resolve which mechanism applies. Returns MECHANISM_REQUIRES_REVIEW
     * (never throws) for a reason this service cannot safely auto-resolve
     * and for which the caller supplied no explicit mode - the caller
     * (controller) is expected to surface this to the user as "we need one
     * more answer from you" rather than silently pick one.
     *
     * @throws \InvalidArgumentException only for genuinely invalid input
     *         (an unrecognized reason or mode string) - never for merely
     *         ambiguous-but-valid input.
     */
    public function determineMechanism(string $reason, ?string $explicitMode = null): string
    {
        if (!in_array($reason, self::REASONS, true)) {
            throw new \InvalidArgumentException("Motivo de corrección desconocido: {$reason}");
        }

        if ($explicitMode !== null) {
            if (!in_array($explicitMode, [self::MECHANISM_RECTIFICATIVA, self::MECHANISM_ANULACION], true)) {
                throw new \InvalidArgumentException("Mecanismo de corrección desconocido: {$explicitMode}");
            }
            return $explicitMode;
        }

        return self::REASON_MECHANISM_MAP[$reason] ?? self::MECHANISM_REQUIRES_REVIEW;
    }

    /**
     * Create a new rectificative invoice (draft) against $original.
     *
     * The original invoice is left untouched - it remains issued/paid, and
     * simply gains a related rectification. Only the new document goes
     * through its own draft -> issue lifecycle to receive a definitive
     * number, from the separate rectification series.
     *
     * @param string|null $invoiceTypeOverride Advanced path: an explicit
     *        AEAT code (R1-R5) that takes precedence over the reason-based
     *        determination. This is how R2 (concurso de acreedores) and R3
     *        (créditos incobrables) are reached - a caller who has already
     *        confirmed the relevant insolvency/bad-debt procedure applies
     *        passes it explicitly; this service never infers R2/R3 from a
     *        business reason alone.
     *
     * @throws \RuntimeException if $original is not eligible for rectification.
     * @throws \InvalidArgumentException for invalid reason/mode/override values.
     */
    public function createRectification(
        Invoice $original,
        string $reason,
        string $rectificationMode,
        ?string $invoiceTypeOverride = null,
        ?array $lineOverrides = null
    ): Invoice {
        if (!in_array($reason, self::REASONS, true)) {
            throw new \InvalidArgumentException("Motivo de corrección desconocido: {$reason}");
        }

        if (!in_array($rectificationMode, [
            Invoice::RECTIFICATION_MODE_SUSTITUCION,
            Invoice::RECTIFICATION_MODE_DIFERENCIAS,
        ], true)) {
            throw new \InvalidArgumentException('Tipo de rectificación inválido (debe ser "S" o "I").');
        }

        if ($invoiceTypeOverride !== null && !in_array($invoiceTypeOverride, Invoice::RECTIFICATION_TYPES, true)) {
            throw new \InvalidArgumentException('Código de factura rectificativa inválido (debe ser R1-R5).');
        }

        if ($original->isDraft()) {
            throw new \RuntimeException('Solo se pueden rectificar facturas emitidas, no borradores.');
        }

        if ($original->isCancelled()) {
            throw new \RuntimeException('Esta factura ya ha sido anulada; no se puede rectificar una factura anulada.');
        }

        return DB::transaction(function () use ($original, $reason, $rectificationMode, $invoiceTypeOverride, $lineOverrides) {
            $original->loadMissing('carts', 'taxLines');

            $invoiceType = $invoiceTypeOverride ?? $this->determineInvoiceType($original, $reason);

            $rectification = Invoice::create([
                'uuid'                 => Str::uuid()->toString(),
                'reference'            => $this->numbering->nextDraftLabel(),
                'customer_id'          => $original->customer_id,
                'date'                 => now()->toDateString(),
                'status'               => Invoice::STATUS_DRAFT,
                'expiration_date'      => now()->addDays(30)->toDateString(),
                'sub_total'            => $original->sub_total,
                'discount_rate'        => $original->discount_rate,
                'discount_amount'      => $original->discount_amount,
                'vta'                  => $original->vta,
                'vta4'                 => $original->vta4,
                'vta10'                => $original->vta10,
                'vta21'                => $original->vta21,
                'total'                => $original->total,
                'note'                 => $original->note,
                'rectifies_invoice_id' => $original->id,
                'invoice_type'         => $invoiceType,
                'rectification_type'   => $rectificationMode,
                'rectification_reason' => $reason,
            ]);

            $lines = $lineOverrides ?? $original->carts->map(fn ($cart) => [
                'item_id'       => $cart->item_id,
                'description'   => $cart->description,
                'qty'           => $cart->qty,
                'price'         => $cart->price,
                'unite'         => $cart->unite,
                'discount'      => $cart->discount,
                'total'         => $cart->total,
                'vta'           => $cart->vta,
                // Morocco Phase 1C.3: without this, a rectified exempt line
                // (or any non-taxable treatment) silently reverted to the
                // Cart column's default ('taxable') on the new draft - the
                // same class of bug §7's invoice_tax_lines copy fixes, one
                // level down. Mirrors duplicate()'s existing line copy.
                'tax_treatment' => $cart->tax_treatment ?? TaxTreatment::TAXABLE,
            ])->all();

            foreach ($lines as $line) {
                Cart::create(array_merge($line, [
                    'cartable_type' => 'App\Models\Invoice',
                    'cartable_id'   => $rectification->id,
                ]));
            }

            // Morocco Phase 1C.3 (docs/morocco-phase-1c3-invoice-readiness.md):
            // mirror the original's own persisted, authoritative generic tax
            // breakdown onto the new rectification draft. Without this, a
            // freshly created rectification has zero `invoice_tax_lines` rows,
            // so its PDF falls back to reading `vta4`/`vta10`/`vta21` directly
            // (TemplateRendererService's else-branch) - fine for a Spanish
            // 4/10/21% invoice (those columns were already copied verbatim
            // above), but any rate that doesn't fit those three legacy
            // buckets (a Moroccan 20%/10%/exempt line, or any future rate)
            // would silently vanish from the rectification's tax summary
            // even though the original invoice correctly represented it.
            // Deliberately a verbatim copy, not a DocumentCalculationService
            // recompute: recomputing from $original->carts would silently
            // zero out the rectification's totals whenever the original's
            // cart lines aren't the authoritative source (e.g. an original
            // invoice issued before Phase 1C.1, or without persisted cart
            // rows) - the exact "risks VERI*FACTU behavior" case this phase
            // was told to avoid touching (see VerifactuXmlBuilderTest's
            // rectificativa tests, which build invoices with stored totals
            // but no cart rows). Copying the original's own already-computed
            // breakdown carries no such risk and never changes
            // vta4/vta10/vta21/sub_total/vta/total, which VERI*FACTU's
            // buildTaxBreakdown() still reads exactly as before.
            foreach ($original->taxLines as $sourceLine) {
                InvoiceTaxLine::create([
                    'invoice_id'   => $rectification->id,
                    'rate'         => $sourceLine->rate,
                    'treatment'    => $sourceLine->treatment,
                    'taxable_base' => $sourceLine->taxable_base,
                    'tax_amount'   => $sourceLine->tax_amount,
                ]);
            }

            $original->logHistory(InvoiceHistory::ACTION_RECTIFIED, [
                'rectification_uuid' => $rectification->uuid,
                'reason'             => $reason,
                'invoice_type'       => $invoiceType,
            ]);
            $rectification->logHistory(InvoiceHistory::ACTION_CREATED, [
                'source'          => 'rectification',
                'original_uuid'   => $original->uuid,
                'original_ref'    => $original->reference,
                'reason'          => $reason,
            ]);

            return $rectification;
        });
    }

    /**
     * AEAT ClaveTipoFacturaType for the new rectificativa, when no explicit
     * override is given. R5 always takes priority when the original was a
     * simplified invoice (F2), per its official definition ("Factura
     * Rectificativa en facturas simplificadas") - that classification is
     * about the ORIGINAL's format, not the reason for the correction.
     *
     * F3 (substitution invoice for a simplified one) is deliberately NOT
     * treated the same as F2 here: F3 is itself already a complete-format
     * document issued to replace a simplified one at the recipient's
     * request - a different mechanism from rectification entirely - so
     * rectifying an F3 follows the same R1/R4 logic as any other complete
     * invoice, not R5.
     *
     * error_datos/importe_incorrecto -> R1 via "error fundado en derecho"
     * (RD 1619/2012 art. 15.1/15.2). devolucion/descuento_posterior -> R1
     * via LIVA art. 80.Uno. Both pairs land on R1 but for different legal
     * reasons - see REASON_LEGAL_BASIS for the precise citation per reason.
     * "otro" (only reachable here when an explicit rectificativa mode was
     * given) defaults to R4 ("Resto") because that is literally R4's
     * official definition - not a guess.
     */
    private function determineInvoiceType(Invoice $original, string $reason): string
    {
        if ($original->invoice_type === Invoice::TYPE_F2) {
            return Invoice::TYPE_R5;
        }

        return match ($reason) {
            self::REASON_ERROR_DATOS,
            self::REASON_IMPORTE_INCORRECTO,
            self::REASON_DEVOLUCION,
            self::REASON_DESCUENTO_POSTERIOR => Invoice::TYPE_R1,
            default => Invoice::TYPE_R4,
        };
    }
}
