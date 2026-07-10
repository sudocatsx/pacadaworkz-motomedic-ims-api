<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     title="Product",
 *     description="Product model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Product ID"),
 *     @OA\Property(property="category_id", type="integer", description="Category ID"),
 *     @OA\Property(property="brand_id", type="integer", description="Brand ID"),
 *     @OA\Property(property="sku", type="string", description="SKU"),
 *     @OA\Property(property="name", type="string", description="Product name"),
 *     @OA\Property(property="description", type="string", description="Product description"),
 *     @OA\Property(property="unit_price", type="number", format="float", description="Unit Price"),
 *     @OA\Property(property="cost_price", type="number", format="float", description="Cost Price"),
 *     @OA\Property(property="reorder_level", type="integer", description="Reorder Level"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="ProductRequest",
 *     type="object",
 *     title="Product Request",
 *     required={"category_id", "brand_id", "sku", "name", "unit_price", "cost_price"},
 *
 *     @OA\Property(property="category_id", type="integer", description="Category ID"),
 *     @OA\Property(property="brand_id", type="integer", description="Brand ID"),
 *     @OA\Property(property="sku", type="string", description="SKU"),
 *     @OA\Property(property="name", type="string", description="Product name"),
 *     @OA\Property(property="description", type="string", description="Product description"),
 *     @OA\Property(property="unit_price", type="number", format="float", description="Unit Price"),
 *     @OA\Property(property="cost_price", type="number", format="float", description="Cost Price"),
 *     @OA\Property(property="reorder_level", type="integer", description="Reorder Level"),
 *     @OA\Property(property="image_url", type="string", description="Image URL")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedProductResponse",
 *     type="object",
 *     title="Wrapped Product Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", ref="#/components/schemas/Product")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedProductCollectionResponse",
 *     type="object",
 *     title="Wrapped Product Collection Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *      @OA\Property(
 *         property="data",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/Product")
 *     )
 * )
 */
class ProductSchema {}
