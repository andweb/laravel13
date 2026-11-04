<?php

namespace App\Http\Resources;

use App\Models\OrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => (string) $this->status,
            'total' => $this->total,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'lines' => $this->lines->map(fn (OrderLine $line) => [
                'id' => $line->id,
                'name' => $line->name,
                'quantity' => $line->quantity,
            ])->all(),
            'created_at' => $this->created_at,
        ];
    }
}
