<?php

namespace Tests\Unit;

use App\Services\Verifactu\Aeat\AeatEndpointResolver;
use Tests\TestCase;

/**
 * Phase 2D item 1: production MUST NOT be reachable, even by
 * misconfiguration - the production URL string does not exist anywhere
 * else in the codebase (verified by grep, not just by this test).
 */
class AeatEndpointResolverTest extends TestCase
{
    /** @test */
    public function test_environment_resolves_to_the_official_pruebas_endpoint(): void
    {
        config(['verifactu.aeat.environment' => 'test']);

        $resolver = new AeatEndpointResolver();

        $this->assertEquals(
            'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
            $resolver->endpoint()
        );
        $this->assertEquals('test', $resolver->environment());
    }

    /** @test */
    public function production_environment_value_is_rejected(): void
    {
        config(['verifactu.aeat.environment' => 'production']);

        $this->expectException(\RuntimeException::class);

        (new AeatEndpointResolver())->endpoint();
    }

    /** @test */
    public function unknown_environment_value_is_rejected(): void
    {
        config(['verifactu.aeat.environment' => 'staging']);

        $this->expectException(\RuntimeException::class);

        (new AeatEndpointResolver())->endpoint();
    }

    /** @test */
    public function empty_environment_value_is_rejected(): void
    {
        config(['verifactu.aeat.environment' => '']);

        $this->expectException(\RuntimeException::class);

        (new AeatEndpointResolver())->endpoint();
    }

    /** @test */
    public function no_production_endpoint_string_exists_anywhere_in_the_codebase(): void
    {
        // The production host (www1.agenciatributaria.gob.es) is
        // documented in docs/ but must never appear as a configured,
        // reachable value in app/ or config/.
        $hits = [];
        foreach (['app', 'config'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    if (str_contains(file_get_contents($file->getPathname()), 'www1.agenciatributaria.gob.es')) {
                        $hits[] = $file->getPathname();
                    }
                }
            }
        }

        $this->assertEmpty($hits, 'The production AEAT endpoint must not appear anywhere in app/ or config/: ' . implode(', ', $hits));
    }
}
