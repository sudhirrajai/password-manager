<?php

namespace App\Http\Resources;

use App\Models\VaultItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VaultItem
 */
class VaultItemResource extends JsonResource
{
    /**
     * Transform the resource into an array with only required fields for client-side decryption and UI.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'encrypted_data' => $this->encrypted_data,
            'iv' => $this->iv,
            'is_favorite' => (bool) $this->is_favorite,
            'folder' => $this->folder,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'password_updated_at' => $this->password_updated_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'user' => $this->relationLoaded('user') && $this->user ? [
                'name' => $this->user->name,
            ] : null,
        ];
    }
}
