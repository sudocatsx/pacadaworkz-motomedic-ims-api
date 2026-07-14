<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="LoginRequest",
 *     type="object",
 *     title="Login Request",
 *     required={"email", "password"},
 *
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         format="email",
 *         description="User's email address",
 *         example="user@example.com"
 *     ),
 *     @OA\Property(
 *         property="password",
 *         type="string",
 *         format="password",
 *         description="User's password",
 *         example="password"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="TokenResponse",
 *     type="object",
 *     title="Authentication Token Response",
 *
 *     @OA\Property(property="access_token", type="string", description="The access token for authentication."),
 *     @OA\Property(property="expires_in", type="integer", description="The token expiration time in seconds."),
 *     @OA\Property(property="token_type", type="string", example="bearer", description="Type of the token."),
 *     @OA\Property(property="refresh_token", type="string", description="The refresh token to get a new access token.")
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="User ID"),
 *     @OA\Property(property="name", type="string", description="User's name"),
 *     @OA\Property(property="email", type="string", format="email", description="User's email address"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     title="Error Response",
 *
 *     @OA\Property(property="error", type="string", description="Error message.")
 * )
 *
 * @OA\Schema(
 *     schema="MessageResponse",
 *     type="object",
 *     title="Message Response",
 *
 *     @OA\Property(property="message", type="string", description="A success or informational message.")
 * )
 *
 * @OA\Schema(
 *     schema="RefreshTokenResponse",
 *     type="object",
 *     title="Refresh Token Response",
 *
 *     @OA\Property(property="new_access_token", type="string", description="The new access token for authentication."),
 *     @OA\Property(property="token_type", type="string", example="bearer", description="Type of the token."),
 *     @OA\Property(property="expires_in", type="integer", description="The token expiration time in seconds.")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedTokenResponse",
 *     type="object",
 *     title="Wrapped Token Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", ref="#/components/schemas/TokenResponse")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedErrorResponse",
 *     type="object",
 *     title="Wrapped Error Response",
 *
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="data", ref="#/components/schemas/ErrorResponse")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedMessageResponse",
 *     type="object",
 *     title="Wrapped Message Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", ref="#/components/schemas/MessageResponse")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedUserResponse",
 *     type="object",
 *     title="Wrapped User Response",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", ref="#/components/schemas/User")
 * )
 *
 * @OA\Response(
 *      response="UnprocessableEntity",
 *      description="Unprocessable Entity",
 *
 *      @OA\JsonContent(ref="#/components/schemas/WrappedErrorResponse")
 * )
 */
class AuthSchemas {}
