<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * @OA\Post (
     *      path="/oauth/token",
     *      operationId="authorization",
     *      tags={"Authorization"},
     *      summary="Authorization",
     *      description="return Access token",
     *
     *      @OA\RequestBody(
     *          required = true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="grant_type",
     *                  type="string",
     *                  example="password"
     *              ),
     *              @OA\Property(
     *                  property="client_id",
     *                  type="integer",
     *                  example=2
     *              ),
     *              @OA\Property(
     *                  property="username",
     *                  type="string",
     *                  example="support@goodwin.gw"
     *              ),
     *              @OA\Property(
     *                  property="password",
     *                  type="string",
     *                  example="Odg4&)(DH&*IU"
     *              ),
     *              @OA\Property(
     *                  property="client_secret",
     *                  type="string",
     *                  example="v0eWFKvPlqErZYVGsAiK2kKvPkhIN0s0QfL3PC7U"
     *              )
     *          ),
     *      ),
     *
     *     @OA\Response(response="default", description="Credit card response")
     * )
     *
     * @OA\SecurityScheme(
     *     type="http",
     *     description="Login with email and password to get the authentication token",
     *     name="Token based Based",
     *     in="header",
     *     scheme="bearer",
     *     bearerFormat="JWT",
     *     securityScheme="access_token",
     * )
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
