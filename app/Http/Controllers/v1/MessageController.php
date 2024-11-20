<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Jobs\SendMessageJob;
use App\Models\Application;
use App\Models\Chat;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    /**
     * @OA\Post(
     *     path="/applications/{application_token}/chats/{chat_number}/messages",
     *     tags={"Messages"},
     *     summary="Create a new message in a chat",
     *     @OA\Parameter(
     *         name="application_token",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Token of the application"
     *     ),
     *     @OA\Parameter(
     *         name="chat_number",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Number of the chat"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"body"},
     *             @OA\Property(property="body", type="string", example="Hello, how are you?")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Message created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Message creation request submitted"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="message_number", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request, $applicationToken, $chatNumber)
    {
        try {
            $validatedData = $request->validate([
                'body' => 'required|string|max:65535'
            ]);

            $application = Application::where('token', $applicationToken)
                ->lockForUpdate()
                ->firstOrFail();

            $chat = $application->chats()
                ->where('number', $chatNumber)
                ->lockForUpdate()
                ->firstOrFail();

            DB::beginTransaction();

            $messageNumber = Redis::incr("chat:{$application->id}:{$chat->id}:message_counter");

            SendMessageJob::dispatch(
                $application,
                $chat,
                $messageNumber,
                $validatedData['body'],
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Message creation request submitted',
                'data' => [
                    'message_number' => $messageNumber,
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application or Chat not found',
                'errors' => ['resource' => 'Application or Chat does not exist']
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Message creation failed', [
                'application_token' => $applicationToken,
                'chat_number' => $chatNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'errors' => ['server' => 'An unexpected error occurred']
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/applications/{application_token}/chats/{chat_number}/messages",
     *     tags={"Messages"},
     *     summary="List all messages in a chat",
     *     @OA\Parameter(
     *         name="application_token",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Token of the application"
     *     ),
     *     @OA\Parameter(
     *         name="chat_number",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Number of the chat"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of messages",
     *          @OA\JsonContent(
     *               @OA\Property(property="data", type="object",
     *                   @OA\Property(property="message_number", type="integer", example=1),
     *                   @OA\Property(property="body", type="integer", example="Hello, how are you?"),
     *                   @OA\Property(property="created_at", type="integer", example="2024-11-19T12:34:56Z"),
     *               )
     *           )
     *     )
     * )
     */

    public function index($applicationToken, $chatNumber)
    {
        try {
            $application = Application::where('token', $applicationToken)
                ->firstOrFail();

            $chat = $application->chats()
                ->where('number', $chatNumber)
                ->firstOrFail();

            $messages = $chat->messages()
                ->orderBy('number')
                ->paginate(20);

            return response()->json([
                'data' => $messages->items(),
                'meta' => [
                    'total' => $messages->total(),
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application or Chat not found',
                'errors' => ['resource' => 'Application or Chat does not exist']
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve messages', [
                'application_token' => $applicationToken,
                'chat_number' => $chatNumber,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'errors' => ['server' => 'An unexpected error occurred']
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/applications/{application_token}/chats/{chat_number}/messages/search",
     *     tags={"Messages"},
     *     summary="Search for any match",
     *     @OA\Parameter(
     *         name="application_token",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Token of the application"
     *     ),
     *     @OA\Parameter(
     *         name="chat_number",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Number of the chat"
     *     ),
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Search query (e.g., 'hi')"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of messages",
     *         @OA\JsonContent(
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="message_number", type="integer", example=1),
     *                  @OA\Property(property="body", type="string", example="Hello, how are you?"),
     *                  @OA\Property(property="timestamp", type="string", example="2024-11-19T12:34:56Z"),
     *              )
     *          )
     *     )
     * )
     */

    public function searchMessages(Request $request, $applicationToken, $chatNumber)
    {
        $validator = validator($request->all(), [
            'query' => 'required|string|max:65535'
        ], [
            'query.required' => 'Search query is required',
            'query.string' => 'Search query must be a string',
            'query.max' => 'Search query exceeds maximum length of 65535 characters'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $client = ClientBuilder::create()->setHosts([config('services.elasticsearch.host')])->build();
        $searchQuery = $request->input('query');

        $query = [
            'index' => 'messages',
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['application_token' => $applicationToken]],
                            ['term' => ['chat_number' => (int)$chatNumber]],
                            [
                                'multi_match' => [
                                    'query' => $searchQuery,
                                    'fields' => ['body^2'],
                                    'type' => 'phrase_prefix',
                                    'operator' => 'and'
                                ]
                            ]
                        ]
                    ]
                ],
                'sort' => [
                    ['_score' => ['order' => 'desc']],
                    ['timestamp' => ['order' => 'desc']]
                ]
            ]
        ];

        try {
            $results = $client->search($query);
            $messages = array_map(function ($hit) {
                return [
                    'message_number' => $hit['_source']['message_number'],
                    'body' => $hit['_source']['body'],
                    'timestamp' => $hit['_source']['timestamp'],
                ];
            }, $results['hits']['hits']);

            return response()->json($messages);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
