<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Http\Resources;

use App\Modules\Contacts\Domain\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
final class ContactResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, emails: list<string>, phones: list<string>, created_at: string|null, updated_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'emails' => $this->emails->pluck('email')->values()->all(),
            'phones' => $this->phones->pluck('phone')->values()->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
