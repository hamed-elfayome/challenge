<?php

namespace App\Jobs;

use App\Models\Application;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateApplicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $token;
    private $name;

    /**
     * CreateApplicationJob constructor.
     *
     * @param string $token
     * @param string $name
     */
    public function __construct(string $token, string $name)
    {
        $this->token = $token;
        $this->name = $name;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            DB::beginTransaction();

            Application::create([
                'name' => $this->name,
                'token' => $this->token,
            ]);

            DB::commit();

            Log::info('Application created successfully', [
                'name' => $this->name,
                'token' => $this->token,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create application', [
                'error' => $e->getMessage(),
                'name' => $this->name,
                'token' => $this->token,
            ]);

            throw $e;
        }
    }
}
