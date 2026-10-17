<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function test_assets_use_https_behind_a_tls_proxy(): void
    {
        $this->get('/', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'weather.milaski.xyz',
            'X-Forwarded-Port' => '443',
        ])
            ->assertOk()
            ->assertSee('https://weather.milaski.xyz/css/app.css', false)
            ->assertDontSee('http://weather.milaski.xyz/css/app.css', false);
    }

    /**
     * @return void
     */
    public function test_assets_use_http_without_a_proxy(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertMatchesRegularExpression('#href="http://[^"]+/css/app\.css#', $response->getContent());
    }
}
