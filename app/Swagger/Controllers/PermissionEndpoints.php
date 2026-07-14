<?php

namespace App\Swagger\Controllers;

/**
 * @OA\Tag(
 *     name="Permissions",
 *     description="API endpoints for managing permissions"
 * )
 */
class PermissionEndpoints
{
    /**
     * @OA\Get(
     *      path="/api/v1/permissions",
     *      operationId="getPermissionsList",
     *      tags={"Permissions"},
     *      summary="Get list of permissions",
     *      description="Returns list of permissions",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Permission"))
     *          )
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function index() {}
}
