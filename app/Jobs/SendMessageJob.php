<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\Chat;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $application;
    protected $chat;
    protected $messageNumber;
    protected $body;

    public function __construct(Application $application, Chat $chat, $messageNumber, $body)
    {
        $this->application = $application;
        $this->chat = $chat;
        $this->messageNumber = $messageNumber;
        $this->body = $body;
    }

    public function handle()
    {
        try {
            DB::beginTransaction();

            $message = $this->chat->messages()->create([
                'number' => $this->messageNumber,
                'body' => $this->body
            ]);

            $this->chat->increment('messages_count');

            Log::info('Message created', [
                'chat_id' => $this->chat->id,
                'message_number' => $this->messageNumber
            ]);

            $this->indexMessageInElasticsearch($message);

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();

            Redis::decr("chat:{$this->application->token}:{$this->chat->number}:message_counter");

            Log::error('Message creation failed', [
                'application_token' => $this->application->token,
                'chat_number' => $this->chat->number,
                'message_number' => $this->messageNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function indexMessageInElasticsearch($message)
    {
        try {
            $client = ClientBuilder::create()
                ->setHosts(['localhost:9200'])
                ->build();

            $params = [
                'index' => 'messages',
                'body' => [
                    'application_token' => $this->application->token,
                    'chat_number' => $this->chat->number,
                    'message_number' => $this->messageNumber,
                    'body' => $this->body,
                    'timestamp' => now()->toISOString(),
                    'message_id' => $message->id
                ],
            ];

            $response = $client->index($params);

            Log::info('Message indexed in Elasticsearch', [
                'message_id' => $message->id,
                'elasticsearch_id' => $response['_id'] ?? null
            ]);

        } catch (Exception $e) {
            Log::error('Failed to index message in Elasticsearch', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function failed(Throwable $exception)
    {
        Log::critical('SendMessageJob permanently failed', [
            'application_token' => $this->application->token,
            'chat_number' => $this->chat->number,
            'message_number' => $this->messageNumber,
            'error' => $exception->getMessage()
        ]);
    }
}
