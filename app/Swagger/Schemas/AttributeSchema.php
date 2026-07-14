<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="AttributeValue",
 *     type="object",
 *     title="Attribute Value",
 *     description="Attribute Value model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Attribute Value ID"),
 *     @OA\Property(property="value", type="string", description="Attribute value")
 * )
 *
 * @OA\Schema(
 *     schema="Attribute",
 *     type="object",
 *     title="Attribute",
 *     description="Attribute model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Attribute ID"),
 *     @OA\Property(property="name", type="string", description="Attribute name"),
 *     @OA\Property(
 *         property="attribute_values",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/AttributeValue")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AttributeRequest",
 *     type="object",
 *     title="Attribute Request",
 *     required={"name"},
 *
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Attribute's name"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AttributeValueRequest",
 *     type="object",
 *     title="Attribute Value Request",
 *     required={"value"},
 *
 *     @OA\Property(
 *         property="value",
 *         type="string",
 *         description="Attribute value"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="WrappedAttributeResponse",
 *     type="object",
 *     title="Wrapped Attribute Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", ref="#/components/schemas/Attribute")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedAttributeCollectionResponse",
 *     type="object",
 *     title="Wrapped Attribute Collection Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *      @OA\Property(
 *         property="data",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/Attribute")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="WrappedAttributeValueResponse",
 *     type="object",
 *     title="Wrapped Attribute Value Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", ref="#/components/schemas/AttributeValue")
 * )
 */
class AttributeSchema {}
