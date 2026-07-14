<?php

namespace App\Swagger\Controllers;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="API endpoints for managing roles"
 * )
 */
class RoleEndpoints
{
    /**
     * @OA\Get(
     *      path="/api/v1/roles",
     *      operationId="getRolesList",
     *      tags={"Roles"},
     *      summary="Get list of roles",
     *      description="Returns list of roles",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Role"))
     *          )
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function index() {}

    /**
     * @OA\Get(
     *      path="/api/v1/roles/{id}",
     *      operationId="getRoleById",
     *      tags={"Roles"},
     *      summary="Get role information",
     *      description="Returns role data",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Role id",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", ref="#/components/schemas/Role")
     *          )
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound")
     * )
     */
    public function show() {}

    /**
     * @OA\Post(
     *      path="/api/v1/roles",
     *      operationId="addRole",
     *      tags={"Roles"},
     *      summary="Create a new role",
     *      description="Creates a new role",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="Role data",
     *
     *          @OA\JsonContent(ref="#/components/schemas/StoreRoleRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", ref="#/components/schemas/Role")
     *          )
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function store() {}

    /**
     * @OA\Put(
     *      path="/api/v1/roles/{id}",
     *      operationId="updateRole",
     *      tags={"Roles"},
     *      summary="Update existing role",
     *      description="Updates an existing role",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Role id",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="Role data",
     *
     *          @OA\JsonContent(ref="#/components/schemas/UpdateRoleRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", ref="#/components/schemas/Role")
     *          )
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound")
     * )
     */
    public function update() {}

    /**
     * @OA\Delete(
     *      path="/api/v1/roles/{id}",
     *      operationId="deleteRole",
     *      tags={"Roles"},
     *      summary="Delete existing role",
     *      description="Deletes an existing role",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Role id",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedMessageResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound")
     * )
     */
    public function destroy() {}

    /**
     * @OA\Post(
     *      path="/api/v1/roles/{id}/permissions",
     *      operationId="assignPermissionsToRole",
     *      tags={"Roles"},
     *      summary="Assign permissions to a role",
     *      description="Assigns one or more permissions to a specific role.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="ID of the role",
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
     *          description="Permission IDs to assign",
     *
     *          @OA\JsonContent(ref="#/components/schemas/AssignPermissionsRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Permissions assigned successfully",
     *
     *          @OA\JsonContent(
     *
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", ref="#/components/schemas/RoleWithPermissions")
     *          )
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound")
     * )
     */
    public function assignPermissions() {}
}
