<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="RolePermissionRequest",
 *     type="object",
 *     title="RolePermission Request",
 *     required={"permissions"},
 *
 *     @OA\Property(
 *         property="permissions",
 *         type="array",
 *
 *         @OA\Items(type="integer")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="RolePermission",
 *     type="object",
 *     title="RolePermission",
 *     description="RolePermission model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Permission ID"),
 *     @OA\Property(property="name", type="string", description="Permission name"),
 * )
 *
 * @OA\Schema(
 *     schema="WrappedRolePermissionResponse",
 *     type="object",
 *     title="Wrapped RolePermission Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Permissions assigned successfully"),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(property="role_name", type="string"),
 *         @OA\Property(
 *              property="permissions",
 *              type="array",
 *
 *              @OA\Items(ref="#/components/schemas/RolePermission")
 *          )
 *     )
 * )
 */
class RolePermissionSchema {}
