<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Permission",
 *     type="object",
 *     title="Permission",
 *     description="Permission model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Permission ID"),
 *     @OA\Property(property="name", type="string", description="Name of the permission"),
 *     @OA\Property(property="description", type="string", description="Description of the permission"),
 *     @OA\Property(property="module", type="string", description="Module of the permission"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 */
class PermissionSchemas {}
