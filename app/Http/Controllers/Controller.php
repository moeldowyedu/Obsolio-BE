<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="OBSOLIO API Doc",
 *     version="1.0.0",
 *     description="API documentation for OBSOLIO platform",
 *     @OA\Contact(
 *         email="admin@obsolio.com"
 *     )
 * )
 * @OA\Server(
 *     url="/api/v1",
 *     description="API Server"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
abstract class Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    use \Illuminate\Foundation\Validation\ValidatesRequests;
}
