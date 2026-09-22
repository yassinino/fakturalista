<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * This started as Laravel's stock scaffold test, which hits `/` on the
 * default test host (`localhost`). That 404s here: the marketing site is
 * registered only under explicit `Route::domain(...)` groups in
 * routes/web.php (fakturalista.test / fakturalista.com / www.fakturalista.com),
 * and `localhost` carries no routes of its own even though it's a valid
 * tenancy central domain. Adapted to hit a real registered marketing
 * domain instead of removing it, since a basic "does the public home page
 * render" smoke test has value and none existed elsewhere.
 */
class ExampleTest extends TestCase
{
    public function test_the_public_home_page_returns_a_successful_response(): void
    {
        $response = $this->get('http://fakturalista.test/');

        $response->assertStatus(200);
        $response->assertSee('Fakturalista');
    }
}
