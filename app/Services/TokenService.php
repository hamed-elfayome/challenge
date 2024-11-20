<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TokenService
{
    public function generateUniqueToken(): string
    {
        $tokenTrackingKey = 'global:application:tokens:tracking';

        do {
            $token = $this->createRandomToken();
            $exists = Redis::sismember($tokenTrackingKey, $token);
        } while ($exists);

        Redis::sadd($tokenTrackingKey, $token);
        $this->trimTokenTrackingSet($tokenTrackingKey);

        return $token;
    }

    private function createRandomToken(): string
    {
        $timestamp = time();

        $counterKey = 'global:application:token:counter';
        $uniqueId = Redis::incr($counterKey);

        $randomBytes = bin2hex(random_bytes(8));

        $components = [
            (string)$timestamp,
            (string)$uniqueId,
            $randomBytes
        ];

        shuffle($components);

        return implode('', $components);
    }

    private function trimTokenTrackingSet(string $tokenTrackingKey)
    {
        if (Redis::scard($tokenTrackingKey) > 10000) {
            $excessTokens = Redis::srandmember($tokenTrackingKey, Redis::scard($tokenTrackingKey) - 5000);
            if (!empty($excessTokens)) {
                Redis::srem($tokenTrackingKey, ...$excessTokens);
            }
        }
    }
}
