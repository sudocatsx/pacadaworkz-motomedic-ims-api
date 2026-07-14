<?php

namespace App\Swagger\Controllers;

/**
 * @OA\Tag(
 *     name="Categories",
 *     description="API endpoints for Categories"
 * )
 */
class CategoryEndpoints
{
    /**
     * @OA\Get(
     *      path="/api/v1/categories",
     *      operationId="getCategoriesList",
     *      tags={"Categories"},
     *      summary="Get list of categories",
     *      description="Returns list of categories",
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
     *          @OA\JsonContent(ref="#/components/schemas/WrappedCategoryCollectionResponse")
     *       ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function index() {}

    /**
     * @OA\Get(
     *      path="/api/v1/categories/{id}",
     *      operationId="getCategoryById",
     *      tags={"Categories"},
     *      summary="Get category information",
     *      description="Returns category data",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Category id",
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
     *          @OA\JsonContent(ref="#/components/schemas/WrappedCategoryResponse")
     *       ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound")
     * )
     */
    public function show() {}

    /**
     * @OA\Post(
     *      path="/api/v1/categories",
     *      operationId="addCategory",
     *      tags={"Categories"},
     *      summary="Create a new category",
     *      description="Creates a new category",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="Category request body",
     *
     *          @OA\JsonContent(ref="#/components/schemas/CategoryRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedCategoryResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="422", ref="#/components/responses/UnprocessableEntity")
     * )
     */
    public function store() {}

    /**
     * @OA\Put(
     *      path="/api/v1/categories/{id}",
     *      operationId="updateCategory",
     *      tags={"Categories"},
     *      summary="Update existing category",
     *      description="Updates an existing category",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Category id",
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
     *          description="Category request body",
     *
     *          @OA\JsonContent(ref="#/components/schemas/CategoryRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedCategoryResponse")
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
     *      path="/api/v1/categories/{id}",
     *      operationId="deleteCategory",
     *      tags={"Categories"},
     *      summary="Delete existing category",
     *      description="Deletes a record and returns no content",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Category id",
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
