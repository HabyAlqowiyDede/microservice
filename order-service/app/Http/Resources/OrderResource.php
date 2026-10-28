<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'total_amount' => self::number($this->total_amount),
            'items' => $this->items->map(fn($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'unit_price' => self::number($item->unit_price),
                'quantity' => $item->quantity,
                'subtotal' => self::number($item->subtotal),
            ])->values()->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Kolom decimal dari MySQL berbentuk teks ("450000.00"). Ubah ke angka;
     * bilangan bulat ditampilkan tanpa desimal.
     */
    private static function number(mixed $value): int|float
    {
        $float = (float) $value;

        return floor($float) === $float ? (int) $float : $float;
    }
}
