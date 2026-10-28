<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ExternalServiceException;
use App\Exceptions\OrderNotFoundException;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\ListOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponses;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly OrderService $orders) {}

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $userId = (int) $request->validated('user_id');

        try {
            $order = $this->orders->createOrder($userId);

            return $this->success(
                'Pesanan berhasil dibuat',
                (new OrderResource($order))->resolve(),
                201
            );
        } catch (BusinessRuleException $e) {
            Log::warning('Checkout ditolak karena aturan bisnis', [
                'user_id' => $userId,
                'reason' => $e->getMessage(),
                'type' => class_basename($e),
            ]);

            return $this->failure($e->getMessage(), 409);
        } catch (ExternalServiceException $e) {
            Log::error('Checkout gagal karena service lain bermasalah', [
                'user_id' => $userId,
                'service' => $e->serviceName,
                'error' => $e->getMessage(),
            ]);

            return $this->failure($e->getMessage(), 503, $e);
        } catch (Throwable $e) {
            Log::error('Error tak terduga saat checkout', [
                'user_id' => $userId,
                'exception' => $e,
            ]);

            return $this->failure('Terjadi kesalahan pada server', 500, $e);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orders->findOrder($id);

            return $this->success(
                'Berhasil mengambil pesanan',
                (new OrderResource($order))->resolve()
            );
        } catch (OrderNotFoundException $e) {
            return $this->failure($e->getMessage(), 404);
        } catch (Throwable $e) {
            Log::error('Error tak terduga saat mengambil pesanan', [
                'order_id' => $id,
                'exception' => $e,
            ]);

            return $this->failure('Terjadi kesalahan pada server', 500, $e);
        }
    }

    public function byUser(ListOrdersRequest $request, int $userId): JsonResponse
    {
        try {
            $orders = $this->orders->listByUser($userId, $request->validated('status'));

            return $this->success(
                'Berhasil mengambil daftar pesanan',
                OrderResource::collection($orders)->resolve()
            );
        } catch (Throwable $e) {
            Log::error('Error tak terduga saat mengambil daftar pesanan', [
                'user_id' => $userId,
                'exception' => $e,
            ]);

            return $this->failure('Terjadi kesalahan pada server', 500, $e);
        }
    }

    public function pay(int $id): JsonResponse
    {
        try {
            $order = $this->orders->pay($id);

            return $this->success(
                'Pesanan berhasil dibayar',
                (new OrderResource($order))->resolve()
            );
        } catch (OrderNotFoundException $e) {
            return $this->failure($e->getMessage(), 404);
        } catch (BusinessRuleException $e) {
            Log::warning('Pembayaran ditolak', [
                'order_id' => $id,
                'reason' => $e->getMessage(),
            ]);

            return $this->failure($e->getMessage(), 409);
        } catch (Throwable $e) {
            Log::error('Error tak terduga saat pembayaran', [
                'order_id' => $id,
                'exception' => $e,
            ]);

            return $this->failure('Terjadi kesalahan pada server', 500, $e);
        }
    }
}
