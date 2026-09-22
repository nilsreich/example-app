<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Review-Befund (Required): `trustProxies(at: '*')` erlaubte IP-/Proto-Spoofing –
 * jede direkt erreichbare Instanz akzeptierte beliebige X-Forwarded-*.
 *
 * Erwartung: Nur Requests aus der konfigurierten Proxy-CIDR (Default: localhost)
 * dürfen X-Forwarded-For vertrauen; alle anderen behalten REMOTE_ADDR.
 */
final class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_untrusted_remote_address_keeps_remote_addr_as_ip(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        $request = Request::create('/up', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_X_FORWARDED_FOR' => '8.8.8.8',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $kernel->handle($request);

        // Nicht-vertrauter Absender → X-Forwarded-For darf nicht übernommen werden.
        $this->assertSame('203.0.113.7', $request->ip());
    }

    public function test_trusted_localhost_remote_address_may_forward_ip_and_scheme(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        $request = Request::create('/up', 'GET', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '10.0.0.42',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $kernel->handle($request);

        $this->assertSame('10.0.0.42', $request->ip());
        $this->assertSame('https', $request->getScheme());
    }
}
