<?php

namespace App\Swagger\Controllers;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API endpoints for managing users"
 * )
 */
class UserEndpoints
{
    /**
     * @OA\Get(
     *      path="/api/v1/users",
     *      operationId="getUsersList",
     *      tags={"Users"},
     *      summary="Get list of users",
     *      description="Returns a paginated list of users.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="search",
     *          in="query",
     *          description="Search by name",
     *          required=false,
     *
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="role_id",
     *          in="query",
     *          description="Filter by role_id",
     *          required=false,
     *
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_by",
     *          in="query",
     *          description="Sort by",
     *          required=false,
     *
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_order",
     *          in="query",
     *          description="Sort order",
     *          required=false,
     *
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Limit per page",
     *          required=false,
     *
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="limit",
     *          in="query",
     *          description="Page",
     *          required=false,
     *
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedUserCollectionResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function index() {}

    /**
     * @OA\Get(
     *      path="/api/v1/users/{id}",
     *      operationId="getUserById",
     *      tags={"Users"},
     *      summary="Get user information",
     *      description="Returns user data by ID.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="User ID",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedUserResourceResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound")
     * )
     */
    public function show() {}

    /**
     * @OA\Post(
     *      path="/api/v1/users",
     *      operationId="storeUser",
     *      tags={"Users"},
     *      summary="Create a new user",
     *      description="Creates a new user record.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="User data",
     *
     *          @OA\JsonContent(ref="#/components/schemas/StoreUserRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="User created successfully",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedUserResourceResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedErrorResponse")
     *      )
     * )
     */
    public function store() {}

    /**
     * @OA\Put(
     *      path="/api/v1/users/{id}",
     *      operationId="updateUser",
     *      tags={"Users"},
     *      summary="Update existing user",
     *      description="Updates an existing user record.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="User ID",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="User data to update",
     *
     *          @OA\JsonContent(ref="#/components/schemas/UpdateUserRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="User updated successfully",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedUserResourceResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound"),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedErrorResponse")
     *      )
     * )
     */
    public function update() {}

    /**
     * @OA\Delete(
     *      path="/api/v1/users/{id}",
     *      operationId="deleteUser",
     *      tags={"Users"},
     *      summary="Delete user",
     *      description="Deletes a user record.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="User ID",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="User deleted successfully",
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
     *      path="/api/v1/users/{id}/reset-password",
     *      operationId="resetUserPassword",
     *      tags={"Users"},
     *      summary="Reset user password",
     *      description="Resets the password for a user.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="User ID",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="Password reset data",
     *
     *          @OA\JsonContent(ref="#/components/schemas/ResetPasswordUserRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Password reset successfully",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedMessageResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized"),
     *      @OA\Response(response="404", ref="#/components/responses/NotFound"),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedErrorResponse")
     *      )
     * )
     */
    public function resetPassword() {}
}
