<?php

namespace App\Modules\Donations\Http\Resources;

use App\Modules\Donations\Models\Donor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Donor
 */
class DonorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'notes' => $this->notes,
            'donations_count' => $this->donations_count ?? 0,
        ];
    }
}
