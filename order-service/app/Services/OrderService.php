<?php

namespace App\Services;

use App\Clients\CartClient;
use App\Clients\ProductClient;
use App\Exceptions\EmptyCartException;
use App\Exceptions\ExternalServiceException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\OrderNotPayableException;
use App\Exceptions\ProductNotFoundException;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly CartClient $cartClient,
        private readonly ProductClient $productClient,
    ) {}

    /**
     * Checkout: mengubah isi keranjang menjadi pesanan.
     *
     * @throws EmptyCartException
     * @throws ProductNotFoundException
     * @throws InsufficientStockException
     * @throws ExternalServiceException
     */
    public function createOrder(int $userId): Order
    {
        // 1. Baca keranjang
        $cartItems = $this->cartClient->getItems($userId);

        if (count($cartItems) === 0) {
            throw new EmptyCartException($userId);
        }

        // 2. Validasi tiap item ke product-service dan siapkan snapshot
        $lines = [];
        $total = 0.0;

        foreach ($cartItems as $item) {
            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];

            $product = $this->productClient->find($productId);

            if ($product === null) {
                throw new ProductNotFoundException($productId);
            }

            $stock = (int) $product['stock'];

            if ($stock < $quantity) {
                throw new InsufficientStockException($product['name'], $quantity, $stock);
            }

            // Harga datang sebagai teks, misalnya "450000.00". Ubah ke angka.
            $unitPrice = (float) $product['price'];
            $subtotal = round($unitPrice * $quantity, 2);
            $total += $subtotal;

            $lines[] = [
                'product_id' => $productId,
                'product_name' => $product['name'], // snapshot nama
                'unit_price' => $unitPrice,         // snapshot harga
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        }

        // 3. Simpan pesanan dan item dalam satu transaksi: semua atau tidak sama sekali
        $order = DB::transaction(function () use ($userId, $total, $lines) {
            $order = Order::create([
                'user_id' => $userId,
                'status' => Order::STATUS_PENDING,
                'total_amount' => round($total, 2),
            ]);

            $order->items()->createMany($lines);

            return $order;
        });

        Log::info('Pesanan berhasil dibuat', [
            'order_id' => $order->id,
            'user_id' => $userId,
            'total_amount' => $order->total_amount,
        ]);

        // 4. Kosongkan keranjang. Kegagalan di sini tidak boleh membatalkan pesanan.
        $this->clearCartSafely($userId, $order->id);

        return $order->load('items');
    }

    public function findOrder(int $id): Order
    {
        $order = Order::with('items')->find($id);

        if ($order === null) {
            throw new OrderNotFoundException($id);
        }

        return $order;
    }

    /**
     * Daftar pesanan satu user, terbaru di atas, filter status opsional.
     *
     * @return Collection<int, Order>
     */
    public function listByUser(int $userId, ?string $status = null): Collection
    {
        return Order::with('items')
            ->where('user_id', $userId)
            ->when($status, fn($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Pembayaran simulasi: PENDING menjadi PAID.
     *
     * @throws OrderNotFoundException
     * @throws OrderNotPayableException
     */
    public function pay(int $id): Order
    {
        return DB::transaction(function () use ($id) {
            // lockForUpdate mencegah dua request bayar bersamaan lolos berdua
            $order = Order::with('items')->lockForUpdate()->find($id);

            if ($order === null) {
                throw new OrderNotFoundException($id);
            }

            if ($order->status !== Order::STATUS_PENDING) {
                throw new OrderNotPayableException($order->status);
            }

            $order->update(['status' => Order::STATUS_PAID]);

            Log::info('Pesanan dibayar', ['order_id' => $order->id]);

            return $order->refresh()->load('items');
        });
    }

    private function clearCartSafely(int $userId, int $orderId): void
    {
        try {
            $this->cartClient->clear($userId);
        } catch (Throwable $e) {
            Log::warning('Pesanan tersimpan tetapi keranjang gagal dikosongkan', [
                'order_id' => $orderId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
