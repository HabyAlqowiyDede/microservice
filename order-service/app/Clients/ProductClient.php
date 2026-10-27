<?php

namespace App\Clients;

use App\Exceptions\ExternalServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.product.url'), '/');
        // Batas keras 5 detik sesuai ketentuan soal.
        $this->timeout = min((int) config('services.http_timeout', 5), 5);
    }

    /**
     * Mengambil satu produk dari product-service.
     *
     * @return array|null null jika produk tidak ada (404)
     * @throws ExternalServiceException jika service tidak dapat dihubungi atau error
     */
    public function find(int $productId): ?array
    {
        $url = "{$this->baseUrl}/products/{$productId}";

        try {
            $response = Http::connectTimeout(min(3, $this->timeout))
                ->timeout($this->timeout)
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException $e) {
            Log::error('Gagal menghubungi product-service', [
                'url' => $url,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            throw new ExternalServiceException(
                'product-service',
                'Product service tidak dapat dihubungi, coba lagi nanti',
                $e
            );
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            Log::error('Product-service mengembalikan error', [
                'url' => $url,
                'product_id' => $productId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new ExternalServiceException(
                'product-service',
                'Product service bermasalah, coba lagi nanti'
            );
        }

        $data = $response->json('data');

        if (! is_array($data) || ! isset($data['name'], $data['price'], $data['stock'])) {
            Log::error('Respons product-service tidak sesuai kontrak', [
                'url' => $url,
                'product_id' => $productId,
            ]);

            throw new ExternalServiceException(
                'product-service',
                'Respons product service tidak sesuai kontrak'
            );
        }

        return $data;
    }
}
