<?php

namespace App\Swagger\Controllers;

/**
 * @OA\Tag(
 *     name="Brands",
 *     description="API endpoints for Brands"
 * )
 */
class BrandEndpoints
{
    /**
     * @OA\Get(
     *      path="/api/v1/brands",
     *      operationId="getBrandsList",
     *      tags={"Brands"},
     *      summary="Get list of brands",
     *      description="Returns list of brands",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="search",
     *          in="query",
     *          description="Search by name or description",
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
     *          @OA\JsonContent(ref="#/components/schemas/WrappedBrandCollectionResponse")
     *       ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function index() {}

    /**
     * @OA\Get(
     *      path="/api/v1/brands/{id}",
     *      operationId="getBrandById",
     *      tags={"Brands"},
     *      summary="Get brand information",
     *      description="Returns brand data",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Brand id",
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
     *          @OA\JsonContent(ref="#/components/schemas/WrappedBrandResponse")
     *       ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound")
     * )
     */
    public function show() {}

    /**
     * @OA\Post(
     *      path="/api/v1/brands",
     *      operationId="addBrand",
     *      tags={"Brands"},
     *      summary="Create a new brand",
     *      description="Creates a new brand",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="Brand request body",
     *
     *          @OA\JsonContent(ref="#/components/schemas/BrandRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedBrandResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="422", ref="#/components/responses/UnprocessableEntity")
     * )
     */
    public function store() {}

    /**
     * @OA\Put(
     *      path="/api/v1/brands/{id}",
     *      operationId="updateBrand",
     *      tags={"Brands"},
     *      summary="Update existing brand",
     *      description="Updates an existing brand",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Brand id",
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
     *          description="Brand request body",
     *
     *          @OA\JsonContent(ref="#/components/schemas/BrandRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedBrandResponse")
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
     *      path="/api/v1/brands/{id}",
     *      operationId="deleteBrand",
     *      tags={"Brands"},
     *      summary="Delete existing brand",
     *      description="Deletes a record and returns no content",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Brand id",
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
}
