<?php

namespace App\Swagger\Controllers;

/**
 * @OA\Tag(
 *     name="RolePermissions",
 *     description="API endpoints for RolePermissions"
 * )
 */
class RolePermissionEndpoints
{
    /**
     * @OA\Post(
     *      path="/api/v1/roles/{role}/permissions",
     *      operationId="assignPermissions",
     *      tags={"RolePermissions"},
     *      summary="Assign permissions to a role",
     *      description="Assigns permissions to a role",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="role",
     *          description="Role id",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="Permissions to assign",
     *
     *          @OA\JsonContent(ref="#/components/schemas/RolePermissionRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedRolePermissionResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound"),
     *      @OA\Response(response="422", ref="#/components/responses/UnprocessableEntity")
     * )
     */
    public function assignPermissions() {}
}
