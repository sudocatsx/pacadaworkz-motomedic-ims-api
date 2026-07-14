<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Brand",
 *     type="object",
 *     title="Brand",
 *     description="Brand model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Brand ID"),
 *     @OA\Property(property="name", type="string", description="Brand name"),
 *     @OA\Property(property="description", type="string", description="Brand description"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="BrandRequest",
 *     type="object",
 *     title="Brand Request",
 *     required={"name"},
 *
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Brand's name"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         description="Brand's description"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="WrappedBrandResponse",
 *     type="object",
 *     title="Wrapped Brand Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", ref="#/components/schemas/Brand")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedBrandCollectionResponse",
 *     type="object",
 *     title="Wrapped Brand Collection Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *      @OA\Property(
 *         property="data",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/Brand")
 *     )
 * )
 */
class BrandSchema {}
