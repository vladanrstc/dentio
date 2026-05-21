<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponses
{
    /**
     * @param array<string, mixed> $data
     */
    protected function respond(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    protected function message(string $message, int $status = 200): JsonResponse
    {
        return $this->respond(['message' => $message], $status);
    }
}
