<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Throwable;

trait ApiResponses
{
    protected function success(string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Detail teknis ($e) hanya disertakan saat APP_DEBUG=true.
     */
    protected function failure(string $message, int $status, ?Throwable $e = null): JsonResponse
    {
        $body = ['message' => $message];

        if ($e !== null && config('app.debug')) {
            $body['debug'] = [
                'exception' => get_class($e),
                'detail' => $e->getMessage(),
                'previous' => $e->getPrevious()?->getMessage(),
            ];
        }

        return response()->json($body, $status);
    }
}
