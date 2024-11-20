<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\Chat;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateChatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $application;
    protected $chatNumber;

    public function __construct(Application $application, $chatNumber)
    {
        $this->application = $application;
        $this->chatNumber = $chatNumber;
    }

    public function handle()
    {
        try {
            DB::beginTransaction();

            $chat = $this->application->chats()->create([
                'number' => $this->chatNumber
            ]);

            $this->application->increment('chats_count');

            DB::commit();

            Log::info('Chat created', [
                'application_id' => $this->application->id,
                'chat_number' => $this->chatNumber
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Chat creation failed', [
                'application_id' => $this->application->id,
                'chat_number' => $this->chatNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Redis::decr("app:{$this->application->token}:chat_counter");

            throw $e;
        }
    }

    public function failed(Throwable  $exception)
    {
        Log::critical('CreateChatJob permanently failed', [
            'application_id' => $this->application->id,
            'chat_number' => $this->chatNumber,
            'error' => $exception->getMessage()
        ]);
    }
}
