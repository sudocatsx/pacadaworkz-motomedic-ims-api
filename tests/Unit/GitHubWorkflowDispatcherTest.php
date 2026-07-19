<?php

use App\Exceptions\DatabaseBackupException;
use App\Services\GitHubWorkflowDispatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('backup.github.api_url', 'https://api.github.com');
    config()->set('backup.github.repository', 'owner/repository');
    config()->set('backup.github.token', 'secret-token');
    config()->set('backup.github.ref', 'master');
});

test('dispatcher sends fixed workflow inputs to the configured default branch', function () {
    Http::fake([
        'api.github.com/*' => Http::response('', 204),
    ]);

    (new GitHubWorkflowDispatcher)->dispatch('database-backup.yml', [
        'operation_id' => 'operation-id',
        'operation_key' => 'database/operations/2026-07/operation-id.json',
        'period' => '2026-07',
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.github.com/repos/owner/repository/actions/workflows/database-backup.yml/dispatches'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request['ref'] === 'master'
        && $request['inputs']['period'] === '2026-07');
});

test('dispatcher converts GitHub rejection into a safe service error', function () {
    Http::fake([
        'api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401),
    ]);

    expect(fn () => (new GitHubWorkflowDispatcher)->dispatch('database-backup.yml', []))
        ->toThrow(DatabaseBackupException::class, 'The database recovery service rejected the operation.');
});
