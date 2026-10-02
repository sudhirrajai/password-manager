<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $team_id
 * @property int|null $vault_item_id
 * @property string $action
 * @property string|null $item_title
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Team|null $team
 * @property-read VaultItem|null $vaultItem
 */
#[Fillable([
    'user_id',
    'team_id',
    'vault_item_id',
    'action',
    'item_title',
    'ip_address',
    'user_agent',
])]
class VaultAuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<VaultItem, $this>
     */
    public function vaultItem(): BelongsTo
    {
        return $this->belongsTo(VaultItem::class);
    }
}
