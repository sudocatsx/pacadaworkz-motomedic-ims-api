<?php

namespace App\Swagger;

/**
 * @OA\Components(
 *
 *     @OA\SecurityScheme(
 *         securityScheme="bearerAuth",
 *         type="http",
 *         scheme="bearer",
 *         bearerFormat="JWT",
 *         description="Enter 'Bearer' [space] and then your ACCESS TOKEN."
 *     ),
 *     @OA\SecurityScheme(
 *         securityScheme="refreshToken",
 *         type="http",
 *         scheme="bearer",
 *         bearerFormat="JWT",
 *         description="Enter 'Bearer' [space] and then your REFRESH TOKEN."
 *     ),
 *
 *     @OA\Response(
 *         response="Unauthorized",
 *         description="Unauthorized. The token is invalid or expired.",
 *
 *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 *     ),
 *
 *     @OA\Response(
 *         response="Forbidden",
 *         description="Forbidden. The user does not have permission to access this resource.",
 *
 *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 *     ),
 *
 *      @OA\Response(
 *         response="NotFound",
 *         description="The specified resource was not found.",
 *
 *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 *     )
 * )
 */
class Components {}
