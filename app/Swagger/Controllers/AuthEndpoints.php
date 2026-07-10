<?php

namespace App\Swagger\Controllers;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="MotoMedic IMS API Documentation",
 *      description="API documentation for the MotoMedic Inventory Management System, generated with a modular structure."
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="API endpoints for Authentication"
 * )
 *
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="API Server"
 * )
 */
class AuthEndpoints
{
    /**
     * @OA\Post(
     *      path="/api/v1/auth/login",
     *      operationId="loginUser",
     *      tags={"Auth"},
     *      summary="Logs in a user",
     *      description="Logs in a user and returns an access token and refresh token.",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          description="User credentials",
     *
     *          @OA\JsonContent(ref="#/components/schemas/LoginRequest")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful login",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedTokenResponse")
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Invalid credentials",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedErrorResponse")
     *      )
     * )
     */
    public function login() {}

    /**
     * @OA\Post(
     *      path="/api/v1/auth/refresh",
     *      operationId="refreshToken",
     *      tags={"Auth"},
     *      summary="Refreshes an access token",
     *      description="Refreshes an expired access token using a refresh token.",
     *      security={{"refreshToken":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Token refreshed successfully",
     *
     *          @OA\JsonContent(ref="#/components/schemas/RefreshTokenResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function refresh() {}

    /**
     * @OA\Post(
     *      path="/api/v1/auth/logout",
     *      operationId="logoutUser",
     *      tags={"Auth"},
     *      summary="Logs out the current user",
     *      description="Logs out the currently authenticated user by invalidating the token.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successfully logged out",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedMessageResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function logout() {}

    /**
     * @OA\Get(
     *      path="/api/v1/auth/me",
     *      operationId="getAuthenticatedUser",
     *      tags={"Auth"},
     *      summary="Get the authenticated user's data",
     *      description="Returns the data of the currently authenticated user.",
     *      security={{"bearerAuth":{}}},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *
     *          @OA\JsonContent(ref="#/components/schemas/WrappedUserResponse")
     *      ),
     *
     *      @OA\Response(response="401", ref="#/components/responses/Unauthorized")
     * )
     */
    public function me() {}
}
