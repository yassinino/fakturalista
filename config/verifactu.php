<?php

/**
 * Fakturalista's own identity as VERI*FACTU software producer
 * ("SistemaInformatico" block, SuministroInformacion.xsd) - constant
 * across every tenant, distinct from each tenant's own fiscal identity
 * (CompanyProfile).
 *
 * IMPORTANT: every value below is null until explicitly configured via
 * env vars. VerifactuXmlBuilder throws rather than inventing any of
 * these - see docs/verifactu-xml-spec-freeze.md §"Software identification"
 * for the official requirements this maps to and why Fakturalista being
 * developed/operated from Morocco does NOT require a Spanish producer
 * identity (RD 1007/2023 art. 13 / Orden HAC/1177/2024 art. 15 and the
 * SistemaInformatico/IDOtro branch both explicitly accommodate a foreign
 * producer).
 *
 * Fill in exactly one identity branch:
 *  - VERIFACTU_PRODUCER_NIF, if Fakturalista ever holds a Spanish NIF, OR
 *  - VERIFACTU_PRODUCER_ID_COUNTRY / _ID_TYPE / _ID (IDOtroType: ISO
 *    3166-1 alpha-2 country code, PersonaFisicaJuridicaIDTypeType code,
 *    up to 20 chars) for a foreign producer identifier.
 */
return [
    'producer' => [
        'name' => env('VERIFACTU_PRODUCER_NAME'),

        'nif' => env('VERIFACTU_PRODUCER_NIF'),

        'id_country' => env('VERIFACTU_PRODUCER_ID_COUNTRY'),
        'id_type'    => env('VERIFACTU_PRODUCER_ID_TYPE'),
        'id'         => env('VERIFACTU_PRODUCER_ID'),
    ],

    'system' => [
        // NombreSistemaInformatico, TextMax30Type
        'name' => env('VERIFACTU_SYSTEM_NAME'),
        // IdSistemaInformatico, TextMax2Type - self-assigned by the
        // producer to distinguish its own products, not issued by AEAT.
        'id' => env('VERIFACTU_SYSTEM_ID'),
        // Version, TextMax50Type
        'version' => env('VERIFACTU_SYSTEM_VERSION'),
        // TipoUsoPosibleSoloVerifactu (SiNoType)
        'only_verifactu' => env('VERIFACTU_SYSTEM_ONLY_VERIFACTU'),
        // TipoUsoPosibleMultiOT (SiNoType) - Fakturalista serves many
        // independent tenants/obligados from one system.
        'multi_ot' => env('VERIFACTU_SYSTEM_MULTI_OT'),
        // IndicadorMultiplesOT (SiNoType) - whether THIS installation
        // (this tenant) is actually being used for more than one
        // obligado tributario. Defaults to "N"; Fakturalista has no
        // multi-obligado-per-tenant feature today (see
        // docs/verifactu-implementation-plan.md §18 Q5, still open).
        'multiple_ot_in_use' => env('VERIFACTU_SYSTEM_MULTIPLE_OT_IN_USE', false),
    ],

    /**
     * AEAT connectivity (Phase 2D) - TEST only. See
     * docs/verifactu-aeat-connectivity.md §1 for the exact primary
     * source (the live WSDL's own port comments) behind every URL below.
     *
     * 'environment' has exactly one legal value in this phase: 'test'.
     * AeatEndpointResolver throws for anything else, including
     * 'production' - the production endpoint string does not exist
     * anywhere else in this codebase, so there is no way to reach it
     * even by misconfiguration.
     */
    'aeat' => [
        'environment' => env('VERIFACTU_AEAT_ENV', 'test'),

        'endpoints' => [
            // WSDL port "SistemaVerifactuPruebas" - "Entorno de PRUEBAS".
            'test' => 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
        ],

        // Seconds. AEAT's own SOAP calls are synchronous request/response
        // (S5 §9.3) - no official guidance on client timeout values, so
        // this is an engineering judgment call, not a regulatory one.
        'connect_timeout' => env('VERIFACTU_AEAT_CONNECT_TIMEOUT', 10),
        'timeout'         => env('VERIFACTU_AEAT_TIMEOUT', 30),

        // Orden HAC/1177/2024 art. 16.2: the mandatory minimum wait
        // between submissions for the same NIF before any response has
        // ever told Fakturalista a different value (see
        // VerifactuChainState.next_submission_not_before).
        'default_flow_control_seconds' => 60,
    ],
];
