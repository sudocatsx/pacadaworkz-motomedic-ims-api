<?php

use Illuminate\Support\Facades\DB;

test('health endpoint confirms the database and storage are available', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'checks' => [
                'database' => 'ok',
                'storage' => 'ok',
            ],
        ]);
});

test('health endpoint reports a database failure without exposing its cause', function () {
    DB::shouldReceive('selectOne')
        ->once()
        ->with('SELECT 1')
        ->andThrow(new RuntimeException('Sensitive database connection details'));

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertExactJson([
            'status' => 'degraded',
            'checks' => [
                'database' => 'unavailable',
                'storage' => 'ok',
            ],
        ])
        ->assertDontSee('Sensitive database connection details');
});

test('deployment debug endpoints are not publicly available', function () {
    $this->getJson('/api/v1/test-permissions')->assertNotFound();
    $this->getJson('/api/v1/test-activity-logs')->assertNotFound();
});
