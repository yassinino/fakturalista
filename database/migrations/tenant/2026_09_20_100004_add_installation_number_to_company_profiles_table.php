<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AEAT SistemaInformatico/NumeroInstalacion (SuministroInformacion.xsd,
 * TextMax100Type) - mandatory per-installation identifier, distinct from
 * the software producer's own identity (config/verifactu.php). Nullable
 * and unused until a tenant is actually configured for VERI*FACTU -
 * VerifactuXmlBuilder throws rather than inventing a value when this is
 * empty (see its class docblock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('verifactu_installation_number', 100)->nullable()->after('rectification_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn('verifactu_installation_number');
        });
    }
};
