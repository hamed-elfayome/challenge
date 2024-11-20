<?php

namespace App\Console\Commands;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Exception;

class SetupElasticsearchIndex extends Command
{
    protected $signature = 'elasticsearch:setup-index';
    protected $description = 'Set up the Elasticsearch index for messages';

    public function handle()
    {
        try {
            $client = ClientBuilder::create()->setHosts([config('services.elasticsearch.host')])->build();

            if (!$this->checkElasticsearchConnection($client)) {
                $this->error('Cannot connect to Elasticsearch. Please check if it is running.');
                return 1;
            }

            $params = [
                'index' => 'messages',
                'body' => [
                    'settings' => [
                        'analysis' => [
                            'analyzer' => [
                                'message_analyzer' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'standard',
                                    'filter' => ['lowercase', 'stop', 'snowball']
                                ]
                            ]
                        ]
                    ],
                    'mappings' => [
                        'properties' => [
                            'application_token' => [
                                'type' => 'keyword'
                            ],
                            'chat_number' => [
                                'type' => 'integer'
                            ],
                            'message_number' => [
                                'type' => 'integer'
                            ],
                            'body' => [
                                'type' => 'text',
                                'analyzer' => 'message_analyzer',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 256
                                    ]
                                ]
                            ],
                            'timestamp' => [
                                'type' => 'date'
                            ]
                        ]
                    ]
                ]
            ];

            if ($client->indices()->exists(['index' => 'messages'])) {
                $client->indices()->delete(['index' => 'messages']);
                $this->info('Existing index "messages" deleted.');
            }

            $this->info('Creating new index with mappings...');
            $response = $client->indices()->create($params);

            if ($response['acknowledged'] === true) {
                $this->info('Index "messages" created successfully with optimized mappings.');
                return 0;
            } else {
                $this->error('Failed to create index.');
                return 1;
            }

        } catch (Exception $e) {
            $this->handleError($e);
            return 1;
        }
    }

    private function checkElasticsearchConnection($client)
    {
        try {
            $response = $client->info();
            return isset($response['version']);
        } catch (Exception $e) {
            return false;
        }
    }

    private function handleError(Exception $e)
    {
        $message = $e->getMessage();

        $errorData = json_decode($message, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($errorData['error'])) {
            $errorType = $errorData['error']['type'] ?? 'unknown';
            $errorReason = $errorData['error']['reason'] ?? 'unknown reason';
            $this->error("Elasticsearch error ({$errorType}): {$errorReason}");
        } else {
            $this->error("Error setting up Elasticsearch index: {$message}");
        }
    }
}
