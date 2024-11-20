<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Jobs\CreateChatJob;
use Illuminate\Http\Request;
use App\Models\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ChatController extends Controller
{
    /**
     * @OA\Post(
     *     path="/applications/{application_token}/chats",
     *     tags={"Chats"},
     *     summary="Create a new chat for an application",
     *     @OA\Parameter(
     *         name="application_token",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Token of the application"
     *     ),
     *     @OA\Response(
     *          response=201,
     *          description="Message created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Chat creation request submitted"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="chat_number", type="integer", example=1)
     *              )
     *          )
     *      )
     * )
     */
    public function store($applicationToken) {
        DB::beginTransaction();

        try {
            $application = Application::where('token', $applicationToken)->first();

            if (!$application) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Application not found',
                    'error' => 'Invalid application token'
                ], 404);
            }

            $chatNumber = Redis::incr("chat_counter:{$application->token}");

            $application->lockForUpdate();

            CreateChatJob::dispatch($application, $chatNumber);

            return response()->json([
                'message' => 'Chat creation request submitted',
                'data' => [
                    'chat_number' => $chatNumber,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($chatNumber)) {
                Redis::decr("app:{$applicationToken}:chat_counter");
            }

            return response()->json([
                'message' => 'Chat creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/applications/{application_token}/chats",
     *     tags={"Chats"},
     *     summary="List all chats for an application",
     *     @OA\Parameter(
     *         name="application_token",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Token of the application"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of chats",
     *         @OA\JsonContent(
     *              @OA\Property(property="number", type="integer", example=1),
     *              @OA\Property(property="messages_count", type="integer", example=1),
     *              @OA\Property(property="created_at", type="string", example="2024-11-19T12:34:56Z")
     *         )
     *     )
     * )
     */
    public function index($applicationToken)
    {

        $application = Application::where('token', $applicationToken)->first();
        if (!$application) {
            return response()->json([
                'message' => 'Application not found',
            ], 404);
        }

        $chats = $application->chats()->get();
        if ($chats->isEmpty()) {
            return response()->json([
                'message' => 'No chats found',
            ], 200);
        }

        return response()->json($chats);
    }
}
