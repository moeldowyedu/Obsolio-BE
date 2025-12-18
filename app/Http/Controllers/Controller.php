<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Aasim API",
 *     version="1.0.0",
 *     description="API documentation for Aasim platform",
 *     @OA\Contact(
 *         email="support@aasim.com"
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
