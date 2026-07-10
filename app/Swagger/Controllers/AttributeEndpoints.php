<?php

namespace App\Swagger\Controllers;

/**
 * @OA\Tag(
 *     name="Attributes",
 *     description="API endpoints for Attributes"
 * )
 */
class AttributeEndpoints
{
    /**
     * @OA\Get(
     *      path="/api/v1/attributes",
     *      operationId="getAttributesList",
     *      tags={"Attributes"},
     *      summary="Get list of attributes",
     *      description="Returns list of attributes",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="search",
     *          in="query",
     *          description="Search by name",
     *          required=false,
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedAttributeCollectionResponse")
     *       ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function index() {}

    /**
     * @OA\Get(
     *      path="/api/v1/attributes/{id}",
     *      operationId="getAttributeById",
     *      tags={"Attributes"},
     *      summary="Get attribute information",
     *      description="Returns attribute data",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Attribute id",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedAttributeResponse")
     *       ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound")
     * )
     */
    public function show() {}

    /**
     * @OA\Post(
     *      path="/api/v1/attributes",
     *      operationId="addAttribute",
     *      tags={"Attributes"},
     *      summary="Create a new attribute",
     *      description="Creates a new attribute",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="Attribute request body",
     *
     *          @OA\JsonContent(ref="#/components/schemas/AttributeRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedAttributeResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="422", ref="#/components/responses/UnprocessableEntity")
     * )
     */
    public function store() {}

    /**
     * @OA\Put(
     *      path="/api/v1/attributes/{id}",
     *      operationId="updateAttribute",
     *      tags={"Attributes"},
     *      summary="Update existing attribute",
     *      description="Updates an existing attribute",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Attribute id",
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
     *          description="Attribute request body",
     *
     *          @OA\JsonContent(ref="#/components/schemas/AttributeRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedAttributeResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound"),
     *      @OA\Response(response="422", ref="#/components/responses/UnprocessableEntity")
     * )
     */
    public function update() {}

    /**
     * @OA\Delete(
     *      path="/api/v1/attributes/{id}",
     *      operationId="deleteAttribute",
     *      tags={"Attributes"},
     *      summary="Delete existing attribute",
     *      description="Deletes a record and returns no content",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Attribute id",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="integer"
     *          )
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
     *      path="/api/v1/attributes/{id}/values",
     *      operationId="addAttributeValue",
     *      tags={"Attributes"},
     *      summary="Create a new attribute value",
     *      description="Creates a new attribute value and associates it with an attribute",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Attribute id",
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
     *          description="Attribute value request body",
     *
     *          @OA\JsonContent(ref="#/components/schemas/AttributeValueRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedAttributeValueResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound"),
     *      @OA\Response(response="422", ref="#/components/responses/UnprocessableEntity")
     * )
     */
    public function storeAttributesValue() {}
}
