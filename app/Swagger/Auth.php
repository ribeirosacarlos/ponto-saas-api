<?php

namespace App\Swagger;

/**
 * @OA\Tag(
 *     name="Auth",
 *     description="User authentication"
 * )
 */
class Auth {}


/**
 * ==========================================
 * LOGIN
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/auth/login",
 *     summary="Performs user login",
 *     description="Returns the token and authenticated user data",
 *     tags={"Auth"},

 *     @OA\RequestBody(
 *         required=true,
 *         description="Login credentials",
 *         @OA\JsonContent(
 *             required={"email","password"},
 *             @OA\Property(property="email", type="string", format="email", example="admin@teste.com"),
 *             @OA\Property(property="password", type="string", example="123456")
 *         )
 *     ),

 *     @OA\Response(
 *         response=200,
 *         description="Login successful",
 *         @OA\JsonContent(
 *             @OA\Property(property="user", type="object",
 *                 description="Authenticated user object"
 *             ),
 *             @OA\Property(property="roles", type="array",
 *                 @OA\Items(type="string"),
 *                 example={"employee","admin"}
 *             ),
 *             @OA\Property(property="token", type="string", example="2|xjfi3290jf0239jf0asd0f9as...")
 *         )
 *     ),

 *     @OA\Response(
 *         response=401,
 *         description="Invalid credentials",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Invalid credentials")
 *         )
 *     )
 * )
 */
class AuthLogin {}



/**
 * ==========================================
 * LOGOUT
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/auth/logout",
 *     summary="Performs logout for authenticated user",
 *     description="Revokes the current user token",
 *     tags={"Auth"},
 *     security={{"bearerAuth":{}}},

 *     @OA\Response(
 *         response=200,
 *         description="Logout successful",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Logged out successfully")
 *         )
 *     )
 * )
 */
class AuthLogout {}


/**
 * ==========================================
 * FORGOT PASSWORD
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/forgot-password",
 *     summary="Request password reset",
 *     description="Sends a password reset code to the user's email",
 *     tags={"Auth"},

 *     @OA\RequestBody(
 *         required=true,
 *         description="Email for password reset",
 *         @OA\JsonContent(
 *             required={"email"},
 *             @OA\Property(property="email", type="string", format="email", example="user@company.com")
 *         )
 *     ),

 *     @OA\Response(
 *         response=200,
 *         description="Reset code sent successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="If the email exists, a recovery link has been sent.")
 *         )
 *     ),

 *     @OA\Response(
 *         response=422,
 *         description="Invalid input data",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="The email field is required.")
 *         )
 *     )
 * )
 */
class ForgotPassword {}


/**
 * ==========================================
 * RESET PASSWORD
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/reset-password",
 *     summary="Reset user password",
 *     description="Reset user password with the provided code",
 *     tags={"Auth"},

 *     @OA\RequestBody(
 *         required=true,
 *         description="Data for password reset",
 *         @OA\JsonContent(
 *             required={"token", "email", "password", "password_confirmation"},
 *             @OA\Property(property="token", type="string", example="ABCDEFGH"),
 *             @OA\Property(property="email", type="string", format="email", example="user@company.com"),
 *             @OA\Property(property="password", type="string", example="NewPassword123!"),
 *             @OA\Property(property="password_confirmation", type="string", example="NewPassword123!")
 *         )
 *     ),

 *     @OA\Response(
 *         response=200,
 *         description="Password reset successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Password reset successfully.")
 *         )
 *     ),

 *     @OA\Response(
 *         response=422,
 *         description="Invalid, expired token or invalid data",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Invalid or expired token.")
 *         )
 *     )
 * )
 */
class ResetPassword {}