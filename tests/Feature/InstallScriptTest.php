<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\InstallScriptController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_install_script_as_plain_text(): void
    {
        $response = $this->get('/api/install.sh')->assertOk();

        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame(hash_file('sha256', InstallScriptController::path()), $response->headers->get('X-Install-Script-Sha256'));

        $body = $response->getContent();
        $this->assertStringStartsWith('#!/usr/bin/env bash', $body);
        $this->assertStringContainsString('--code', $body);
        $this->assertStringContainsString('api/config-template', $body);
    }

    public function test_script_info_reports_hash_and_time(): void
    {
        $info = InstallScriptController::info();

        $this->assertNotNull($info);
        $this->assertSame(hash_file('sha256', InstallScriptController::path()), $info['sha256']);
        $this->assertGreaterThan(0, $info['bytes']);
    }
}
