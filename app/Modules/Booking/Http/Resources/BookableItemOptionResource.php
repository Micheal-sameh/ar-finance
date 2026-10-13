<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\BookableItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookableItem
 */
class BookableItemOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_exclusive' => $this->is_exclusive,
            'rate_type' => $this->rate_type->value,
            'price' => $this->price,
            'deposit_percent' => $this->deposit_percent,
        ];
    }
}
