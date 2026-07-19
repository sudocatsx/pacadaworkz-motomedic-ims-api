<?php

namespace App\Services;

use App\Exceptions\DatabaseBackupException;
use Illuminate\Support\Facades\Http;
use Throwable;

class GitHubWorkflowDispatcher
{
    public function dispatch(string $workflow, array $inputs): void
    {
        $repository = (string) config('backup.github.repository');
        $token = (string) config('backup.github.token');
        $url = config('backup.github.api_url')."/repos/{$repository}/actions/workflows/{$workflow}/dispatches";

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(15)
                ->post($url, [
                    'ref' => config('backup.github.ref'),
                    'inputs' => $inputs,
                ]);
        } catch (Throwable $exception) {
            report($exception);
            throw new DatabaseBackupException('The database recovery service could not be reached.', 'workflow_dispatch_failed', 503);
        }

        if (! $response->successful()) {
            logger()->warning('Database workflow dispatch was rejected by GitHub.', ['status' => $response->status()]);
            throw new DatabaseBackupException('The database recovery service rejected the operation.', 'workflow_dispatch_failed', 503);
        }
    }
}
