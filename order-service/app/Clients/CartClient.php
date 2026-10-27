<?php

namespace App\Clients;

use App\Exceptions\ExternalServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CartClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.cart.url'), '/');
        $this->timeout = min((int) config('services.http_timeout', 5), 5);
    }

    /**
     * Mengambil daftar item keranjang milik user.
     *
     * @return array<int, array{product_id:int, quantity:int}>
     * @throws ExternalServiceException
     */
    public function getItems(int $userId): array
    {
        $url = "{$this->baseUrl}/carts/{$userId}";

        try {
            $response = $this->http()->get($url);
        } catch (ConnectionException $e) {
            $this->logConnectionFailure('ambil keranjang', $url, $userId, $e);

            throw new ExternalServiceException(
                'cart-service',
                'Cart service tidak dapat dihubungi, coba lagi nanti',
                $e
            );
        }

        // Keranjang belum pernah dibuat dianggap kosong.
        if ($response->status() === 404) {
            return [];
        }

        if ($response->failed()) {
            Log::error('Cart-service mengembalikan error saat ambil keranjang', [
                'url' => $url,
                'user_id' => $userId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new ExternalServiceException(
                'cart-service',
                'Cart service bermasalah, coba lagi nanti'
            );
        }

        $items = $response->json('data.items');

        return is_array($items) ? $items : [];
    }

    /**
     * Mengosongkan keranjang user.
     *
     * @throws ExternalServiceException
     */
    public function clear(int $userId): void
    {
        $url = "{$this->baseUrl}/carts/{$userId}";

        try {
            $response = $this->http()->delete($url);
        } catch (ConnectionException $e) {
            $this->logConnectionFailure('kosongkan keranjang', $url, $userId, $e);

            throw new ExternalServiceException(
                'cart-service',
                'Cart service tidak dapat dihubungi saat mengosongkan keranjang',
                $e
            );
        }

        if ($response->failed()) {
            throw new ExternalServiceException(
                'cart-service',
                "Cart service gagal mengosongkan keranjang (status {$response->status()})"
            );
        }
    }

    private function http(): PendingRequest
    {
        return Http::connectTimeout(min(3, $this->timeout))
            ->timeout($this->timeout)
            ->acceptJson();
    }

    private function logConnectionFailure(string $action, string $url, int $userId, ConnectionException $e): void
    {
        Log::error("Gagal menghubungi cart-service ({$action})", [
            'url' => $url,
            'user_id' => $userId,
            'error' => $e->getMessage(),
        ]);
    }
}
