<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();

            // Template identity
            $table->string('name', 120);                        // e.g. "Default", "Minimal", "Modern"
            $table->string('slug', 160);                        // unique within tenant
            $table->unsignedInteger('version')->default(1);     // versioning
            $table->boolean('is_default')->default(false);      // one default per tenant
            $table->boolean('is_active')->default(true);        // enable/disable

            // Target doc type (future-proof)
            $table->string('document_type', 30)->default('invoice'); // invoice|quote|credit_note

            // Rendering options
            $table->string('engine', 30)->default('dompdf');     // dompdf|wkhtmltopdf (if you add later)
            $table->string('paper_size', 10)->default('A4');     // A4|Letter...
            $table->string('orientation', 12)->default('portrait'); // portrait|landscape
            $table->string('locale', 10)->default('es');         // es|fr|en...
            $table->string('timezone', 64)->default('Europe/Madrid');

            // Visual defaults (aligned with UI configurator)
            $table->string('primary', 16)->default('#dad7d2');
            $table->string('text', 16)->default('#1f1c1a');
            $table->string('muted', 16)->default('#6b6764');
            $table->string('table_header_bg', 16)->default('#dedad5');
            $table->string('table_header_text', 16)->default('#4a4745');
            $table->string('table_border', 16)->default('#cfcac5');
            $table->string('font_family', 120)->default('Arial, sans-serif');
            $table->string('font_size', 20)->default('medium');
            $table->unsignedSmallInteger('logo_width_mm')->default(50);
            $table->string('logo_position', 10)->default('above');
            $table->boolean('show_payment_terms')->default(true);
            $table->boolean('show_customer_number')->default(false);
            $table->boolean('show_customer_phone')->default(false);
            $table->boolean('show_shipping_address')->default(true);
            $table->boolean('billing_address_right')->default(false);
            $table->boolean('show_discount')->default(false);
            $table->boolean('show_tax_column')->default(true);
            $table->boolean('show_subtotal')->default(true);
            $table->boolean('show_tax_breakdown')->default(true);
            $table->boolean('bold_total')->default(true);
            $table->text('payment_note')->nullable();
            $table->boolean('show_payment_note')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'version']);
            $table->index(['document_type', 'is_active']);
            $table->index(['is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
