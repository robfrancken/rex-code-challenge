<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Http\Resources;

use App\Modules\Contacts\Application\DTOs\CallResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CallResult
 */
final class CallResource extends JsonResource
{
    /**
     * @return array{contact_id: string, phone: string, outcome: string, placed_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'contact_id' => $this->contactId,
            'phone' => $this->dialled->value,
            'outcome' => $this->outcome->value,
            'placed_at' => $this->placedAt->toIso8601String(),
        ];
    }
}
