<?php

test('configured frontend origins can call API routes', function () {
    $this->withHeaders([
        'Origin' => 'http://localhost:3001',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'content-type,authorization',
    ])->options('/api/v1/auth/login')
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3001');
});

test('unconfigured frontend origins are not reflected as allowed origins', function () {
    $this->withHeaders([
        'Origin' => 'https://untrusted.example',
        'Access-Control-Request-Method' => 'POST',
    ])->options('/api/v1/auth/login')
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3001');
});
