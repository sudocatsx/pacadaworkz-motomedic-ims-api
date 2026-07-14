<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Role",
 *     type="object",
 *     title="Role",
 *     description="Role model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Role ID"),
 *     @OA\Property(property="role_name", type="string", description="Name of the role"),
 *     @OA\Property(property="description", type="string", description="Description of the role"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="StoreRoleRequest",
 *     type="object",
 *     title="Store Role Request",
 *     required={"role_name", "description"},
 *
 *     @OA\Property(property="role_name", type="string", description="Name of the role"),
 *     @OA\Property(property="description", type="string", description="Description of the role")
 * )
 *
 * @OA\Schema(
 *     schema="UpdateRoleRequest",
 *     type="object",
 *     title="Update Role Request",
 *     required={"description"},
 *
 *     @OA\Property(property="description", type="string", description="Description of the role")
 * )
 *
 * @OA\Schema(
 *     schema="AssignPermissionsRequest",
 *     type="object",
 *     title="Assign Permissions Request",
 *     required={"permissions"},
 *
 *     @OA\Property(
 *         property="permissions",
 *         type="array",
 *         description="An array of permission IDs to assign to the role.",
 *
 *         @OA\Items(type="integer")
 *     )
 * )
 *
 * @OA\Schema(
 *      schema="RoleWithPermissions",
 *      allOf={
 *          @OA\Schema(ref="#/components/schemas/Role"),
 *          @OA\Schema(
 *
 *              @OA\Property(
 *                  property="permissions",
 *                  type="array",
 *
 *                  @OA\Items(ref="#/components/schemas/Permission")
 *              )
 *          )
 *      }
 * )
 */
class RoleSchemas {}
