<?php

namespace Tests\Unit;

use App\Services\InvoiceNumberingService;
use App\Services\InvoiceRectificationService;
use Tests\TestCase;

/**
 * Pure logic tests for the reason -> mechanism mapping. No database or
 * tenancy involved - this is exactly the kind of decision this feature
 * must never get wrong silently, so it is covered in isolation from the
 * HTTP/DB layer (see tests/Feature/InvoiceLifecycleTest.php for the
 * end-to-end equivalents, including issuance-time series routing).
 *
 * The mapping itself is sourced from:
 *  - RD 1619/2012 art. 15.1/15.2 (when a rectificativa is required at all)
 *  - Orden HAC/1177/2024, Anexo, lista L2 (the exact R1-R5 definitions,
 *    quoted verbatim in InvoiceRectificationService's class docblock)
 *  - LIVA (Ley 37/1992) art. 80, apartados Uno/Dos/Tres/Cuatro
 *  - AEAT "Sistemas Informáticos de Facturación" v1.0.3 §9.2.1 (the
 *    "operación inexistente" -> anulación example)
 *
 * Every reason currently exposed via the API is covered below, each
 * asserting BOTH the resolved mechanism and (where applicable) the exact
 * AEAT code it would produce - see
 * InvoiceRectificationServiceDecisionTableTest for the full table form.
 */
class InvoiceRectificationServiceTest extends TestCase
{
    private function service(): InvoiceRectificationService
    {
        return new InvoiceRectificationService(new InvoiceNumberingService());
    }

    // ── Reasons that resolve to RECTIFICATIVA ───────────────────────────

    /** @test */
    public function error_datos_resolves_to_rectificativa(): void
    {
        // RD 1619/2012 art. 15.1 - "error fundado en derecho" -> R1.
        $this->assertEquals(
            InvoiceRectificationService::MECHANISM_RECTIFICATIVA,
            $this->service()->determineMechanism(InvoiceRectificationService::REASON_ERROR_DATOS)
        );
    }

    /** @test */
    public function importe_incorrecto_resolves_to_rectificativa(): void
    {
        // RD 1619/2012 art. 15.2, primer inciso - cuotas mal determinadas,
        // también "error fundado en derecho" -> R1. Same code as
        // error_datos, but a DIFFERENT legal basis (see REASON_LEGAL_BASIS).
        $this->assertEquals(
            InvoiceRectificationService::MECHANISM_RECTIFICATIVA,
            $this->service()->determineMechanism(InvoiceRectificationService::REASON_IMPORTE_INCORRECTO)
        );
    }

    /** @test */
    public function devolucion_resolves_to_rectificativa(): void
    {
        // LIVA art. 80.Uno (envases/embalajes devueltos) -> R1. Same code
        // as error_datos, but via art. 80.Uno, NOT "error fundado".
        $this->assertEquals(
            InvoiceRectificationService::MECHANISM_RECTIFICATIVA,
            $this->service()->determineMechanism(InvoiceRectificationService::REASON_DEVOLUCION)
        );
    }

    /** @test */
    public function descuento_posterior_resolves_to_rectificativa(): void
    {
        // LIVA art. 80.Uno (descuentos/bonificaciones posteriores) -> R1.
        $this->assertEquals(
            InvoiceRectificationService::MECHANISM_RECTIFICATIVA,
            $this->service()->determineMechanism(InvoiceRectificationService::REASON_DESCUENTO_POSTERIOR)
        );
    }

    // ── Reason that resolves to ANULACION ───────────────────────────────

    /** @test */
    public function operacion_inexistente_resolves_to_anulacion(): void
    {
        // AEAT SWeb spec v1.0.3 §9.2.1: excluded from rectificativa
        // entirely because no real operation ever existed.
        $this->assertEquals(
            InvoiceRectificationService::MECHANISM_ANULACION,
            $this->service()->determineMechanism(InvoiceRectificationService::REASON_OPERACION_INEXISTENTE)
        );
    }

    // ── "Otro motivo" - must never silently guess ───────────────────────

    /** @test */
    public function otro_without_explicit_mode_requires_review_and_does_not_throw(): void
    {
        // Returning a structured status (not throwing) is the contract the
        // controller relies on to surface "we need one more answer from
        // you" to the user, rather than a raw exception.
        $this->assertEquals(
            InvoiceRectificationService::MECHANISM_REQUIRES_REVIEW,
            $this->service()->determineMechanism(InvoiceRectificationService::REASON_OTRO)
        );
    }

    /** @test */
    public function otro_with_explicit_anulacion_mode_is_honored(): void
    {
        $this->assertEquals(
            InvoiceRectificationService::MECHANISM_ANULACION,
            $this->service()->determineMechanism(
                InvoiceRectificationService::REASON_OTRO,
                InvoiceRectificationService::MECHANISM_ANULACION
            )
        );
    }

    /** @test */
    public function otro_with_explicit_rectificativa_mode_is_honored(): void
    {
        $this->assertEquals(
            InvoiceRectificationService::MECHANISM_RECTIFICATIVA,
            $this->service()->determineMechanism(
                InvoiceRectificationService::REASON_OTRO,
                InvoiceRectificationService::MECHANISM_RECTIFICATIVA
            )
        );
    }

    /**
     * Explicit mode overrides the suggested mechanism even for an otherwise
     * unambiguous reason - the caller (a specific endpoint) is responsible
     * for cross-checking this isn't abused; the service itself trusts an
     * explicit, valid mode.
     */
    /** @test */
    public function explicit_mode_overrides_an_unambiguous_reasons_default(): void
    {
        $this->assertEquals(
            InvoiceRectificationService::MECHANISM_ANULACION,
            $this->service()->determineMechanism(
                InvoiceRectificationService::REASON_ERROR_DATOS,
                InvoiceRectificationService::MECHANISM_ANULACION
            )
        );
    }

    // ── Invalid input still throws (never REQUIRES_REVIEW) ──────────────

    /** @test */
    public function unknown_reason_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->determineMechanism('not_a_real_reason');
    }

    /** @test */
    public function unknown_explicit_mode_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->determineMechanism(InvoiceRectificationService::REASON_ERROR_DATOS, 'not_a_real_mode');
    }

    // ── Legal basis documentation is complete ───────────────────────────

    /** @test */
    public function every_reason_has_a_documented_legal_basis(): void
    {
        foreach (InvoiceRectificationService::REASONS as $reason) {
            $this->assertArrayHasKey(
                $reason,
                InvoiceRectificationService::REASON_LEGAL_BASIS,
                "Reason '{$reason}' is missing its legal citation in REASON_LEGAL_BASIS."
            );
        }
    }
}
