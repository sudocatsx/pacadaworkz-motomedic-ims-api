<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => 'ok',
            'storage' => 'ok',
        ];

        try {
            DB::selectOne('SELECT 1');
        } catch (Throwable) {
            $checks['database'] = 'unavailable';
        }

        if (! is_dir(storage_path('framework')) || ! is_writable(storage_path('framework'))) {
            $checks['storage'] = 'unavailable';
        }

        $healthy = ! in_array('unavailable', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
