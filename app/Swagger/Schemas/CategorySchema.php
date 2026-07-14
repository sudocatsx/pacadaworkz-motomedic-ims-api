<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     title="Category",
 *     description="Category model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Category ID"),
 *     @OA\Property(property="name", type="string", description="Category name"),
 *     @OA\Property(property="description", type="string", description="Category description"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="CategoryRequest",
 *     type="object",
 *     title="Category Request",
 *     required={"name"},
 *
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Category's name"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         description="Category's description"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="WrappedCategoryResponse",
 *     type="object",
 *     title="Wrapped Category Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", ref="#/components/schemas/Category")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedCategoryCollectionResponse",
 *     type="object",
 *     title="Wrapped Category Collection Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *      @OA\Property(
 *         property="data",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/Category")
 *     )
 * )
 */
class CategorySchema {}
