<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="UserResource",
 *     type="object",
 *     title="User Resource",
 *     description="User resource representation",
 *     @OA\Property(property="id", type="integer", format="int64", description="User ID"),
 *     @OA\Property(property="role_id", type="integer", description="Role ID"),
 *     @OA\Property(property="name", type="string", description="Username"),
 *     @OA\Property(property="email", type="string", format="email", description="User's email address"),
 *     @OA\Property(property="first_name", type="string", description="First name"),
 *     @OA\Property(property="last_name", type="string", description="Last name"),
 *     @OA\Property(property="contact_number", type="string", nullable=true, description="Contact number"),
 *     @OA\Property(property="is_active", type="boolean", description="Active status")
 * )
 *
 * @OA\Schema(
 *     schema="StoreUserRequest",
 *     type="object",
 *     title="Store User Request",
 *     required={"role_id", "name", "email", "first_name", "last_name", "is_default_password"},
 *     @OA\Property(property="role_id", type="integer", description="Role ID"),
 *     @OA\Property(property="name", type="string", description="Username", minLength=1, maxLength=50),
 *     @OA\Property(property="email", type="string", format="email", description="Email address"),
 *     @OA\Property(property="password", type="string", format="password", nullable=true, description="Password required when is_default_password is false", minLength=6),
 *     @OA\Property(property="first_name", type="string", description="First name", maxLength=50),
 *     @OA\Property(property="last_name", type="string", description="Last name", maxLength=50),
 *     @OA\Property(property="contact_number", type="string", nullable=true, description="Digits-only contact number", maxLength=30),
 *     @OA\Property(property="is_default_password", type="boolean", description="Is default password")
 * )
 *
 * @OA\Schema(
 *     schema="UpdateUserRequest",
 *     type="object",
 *     title="Update User Request",
 *     @OA\Property(property="role_id", type="integer", description="Role ID"),
 *     @OA\Property(property="name", type="string", description="Username", minLength=1, maxLength=50),
 *     @OA\Property(property="email", type="string", format="email", description="Email address"),
 *     @OA\Property(property="first_name", type="string", description="First name", maxLength=50),
 *     @OA\Property(property="last_name", type="string", description="Last name", maxLength=50),
 *     @OA\Property(property="contact_number", type="string", nullable=true, description="Digits-only contact number", maxLength=30),
 *     @OA\Property(property="is_active", type="boolean", description="Is active")
 * )
 *
 * @OA\Schema(
 *     schema="ResetPasswordUserRequest",
 *     type="object",
 *     title="Reset Password User Request",
 *     required={"is_default_password"},
 *     @OA\Property(property="new_password", type="string", format="password", description="New password (required if is_default_password is false)", minLength=6),
 *     @OA\Property(property="is_default_password", type="boolean", description="Use default password")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedUserResourceResponse",
 *     type="object",
 *     title="Wrapped User Resource Response",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="data", ref="#/components/schemas/UserResource")
 * )
 *
 * @OA\Schema(
 *     schema="WrappedUserCollectionResponse",
 *     type="object",
 *     title="Wrapped User Collection Response",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/UserResource")
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="per_page", type="integer"),
 *         @OA\Property(property="current_page", type="integer"),
 *         @OA\Property(property="last_page", type="integer")
 *     )
 * )
 */
class UserSchemas
{
}
