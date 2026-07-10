<?php

namespace App\Swagger\Controllers;

/**
 * @OA\Tag(
 *     name="Products",
 *     description="API endpoints for Products"
 * )
 */
class ProductEndpoints
{
    /**
     * @OA\Get(
     *      path="/api/v1/products",
     *      operationId="getProductsList",
     *      tags={"Products"},
     *      summary="Get list of products",
     *      description="Returns list of products",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="search",
     *          in="query",
     *          description="Search by name, description or sku",
     *          required=false,
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="filter",
     *          in="query",
     *          description="Filter by category or brand",
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
     *          @OA\JsonContent(ref="#/components/schemas/WrappedProductCollectionResponse")
     *       ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function index() {}

    /**
     * @OA\Get(
     *      path="/api/v1/products/{id}",
     *      operationId="getProductById",
     *      tags={"Products"},
     *      summary="Get product information",
     *      description="Returns product data",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Product id",
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
     *          @OA\JsonContent(ref="#/components/schemas/WrappedProductResponse")
     *       ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound")
     * )
     */
    public function show() {}

    /**
     * @OA\Post(
     *      path="/api/v1/products",
     *      operationId="addProduct",
     *      tags={"Products"},
     *      summary="Create a new product",
     *      description="Creates a new product",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="Product request body",
     *
     *          @OA\JsonContent(ref="#/components/schemas/ProductRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedProductResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="422", ref="#/components/responses/UnprocessableEntity")
     * )
     */
    public function store() {}

    /**
     * @OA\Put(
     *      path="/api/v1/products/{id}",
     *      operationId="updateProduct",
     *      tags={"Products"},
     *      summary="Update existing product",
     *      description="Updates an existing product",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Product id",
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
     *          description="Product request body",
     *
     *          @OA\JsonContent(ref="#/components/schemas/ProductRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedProductResponse")
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
     *      path="/api/v1/products/{id}",
     *      operationId="deleteProduct",
     *      tags={"Products"},
     *      summary="Delete existing product",
     *      description="Deletes a record and returns no content",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Product id",
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
     * @OA\Delete(
     *      path="/api/v1/products/{id}/attributeValueId/{attributeValueId}",
     *      operationId="deleteProductAttribute",
     *      tags={"Products"},
     *      summary="Delete existing product attribute",
     *      description="Deletes a product attribute record and returns a success message",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Product id",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="attributeValueId",
     *          description="Attribute Value id",
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
    public function destroyAttributeProduct() {}

    /**
     * @OA\Get(
     *      path="/api/v1/products/export",
     *      operationId="exportProducts",
     *      tags={"Products"},
     *      summary="Export products to CSV file",
     *      description="Returns a CSV file with all products",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\MediaType(
     *              mediaType="text/csv",
     *          )
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function export() {}
}
