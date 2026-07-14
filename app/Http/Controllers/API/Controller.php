<?php

namespace App\Http\Controllers\API;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="pacadaworks-motomedic-ims-api",
 *     description="API documentation generated using Swagger in Laravel",
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Server"
 * )
 */
abstract class Controller
{
    /**
     * @OA\Get(
     *     path="/api/dummy",
     *     summary="Dummy endpoint",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    //
}
