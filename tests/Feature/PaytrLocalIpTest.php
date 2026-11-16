<?php

namespace Tests\Feature;

use App\Support\Paytr\ClientIp;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaytrLocalIpTest extends TestCase
{
    public function test_local_test_uses_configured_public_ip(): void
    {
        app()->instance('env', 'local');
        config(['paytr.test_mode' => true, 'paytr.local_test_ip' => '78.177.167.115']);
        $this->assertSame('78.177.167.115', app(ClientIp::class)->resolve('127.0.0.1', true));
    }

    public function test_override_never_changes_production_or_live_attempt_ip(): void
    {
        config(['paytr.test_mode' => true, 'paytr.local_test_ip' => '78.177.167.115']);
        foreach (['production', 'staging', 'testing'] as $environment) {
            app()->instance('env', $environment);
            $this->assertSame('8.8.8.8', app(ClientIp::class)->resolve('8.8.8.8', true));
        }
        app()->instance('env', 'local');
        $this->assertSame('8.8.8.8', app(ClientIp::class)->resolve('8.8.8.8', false));
        config(['paytr.test_mode' => false]);
        $this->assertSame('8.8.8.8', app(ClientIp::class)->resolve('8.8.8.8', true));
    }

    public function test_invalid_override_is_rejected_instead_of_sent_to_paytr(): void
    {
        app()->instance('env', 'local');
        config(['paytr.test_mode' => true, 'paytr.local_test_ip' => '192.168.1.10']);
        $this->expectException(ValidationException::class);
        app(ClientIp::class)->resolve('127.0.0.1', true);
    }
}
