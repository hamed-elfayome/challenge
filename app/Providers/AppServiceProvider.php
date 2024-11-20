<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            $connection = new AMQPStreamConnection(
                config('queue.connections.rabbitmq.host', 'rabbitmq'),
                config('queue.connections.rabbitmq.port', 5672),
                config('queue.connections.rabbitmq.login', 'admin'),
                config('queue.connections.rabbitmq.password', 'admin'),
                config('queue.connections.rabbitmq.vhost', '/')
            );

            $channel = $connection->channel();

            $queues = [
                'default',
            ];

            foreach ($queues as $queueName) {
                $channel->queue_declare(
                    $queueName,
                    false,
                    true,
                    false,
                    false
                );
            }

            $channel->close();
            $connection->close();

            Log::info('RabbitMQ queues created successfully');
        } catch (\Exception $e) {
            Log::error('RabbitMQ queue setup failed: ' . $e->getMessage());
        }
    }
}
