<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Jobs\CreateApplicationJob;
use App\Services\TokenService;
use Illuminate\Http\Request;
use App\Models\Application;

class ApplicationController extends Controller
{
    /**
     * @OA\Post(
     *     path="/applications",
     *     tags={"Applications"},
     *     summary="Create a new application",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="My Application")
     *         )
     *     ),
     *     @OA\Response(
     *          response=201,
     *          description="Application created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Application creation request submitted"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="name", type="string", example="My Application"),
     *                  @OA\Property(property="token", type="string", example="abcd1234token"),
     *                  @OA\Property(property="created_at", type="string", example="2024-11-20T09:52:48.000000Z"),
     *              )
     *          )
     *      )
     * )
     */
    public function store(Request $request, TokenService $tokenService)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $token = $tokenService->generateUniqueToken();
        $name = $request->name;

        $application = [
            'name' => $name,
            'token' => $token,
        ];
        CreateApplicationJob::dispatch($token, $name);

        return response()->json([
            'message' => 'Application creation request submitted',
            'data' => $application,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/applications",
     *     tags={"Applications"},
     *     summary="List all applications",
     *     @OA\Response(
     *         response=200,
     *         description="List of applications",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="name", type="string", example="My Application"),
     *                 @OA\Property(property="token", type="string", example="abcd1234token"),
     *                 @OA\Property(property="chats_count", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", example="2024-11-20T09:52:48.000000Z"),
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json(Application::all());
    }
}
