<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vault_item_id
 * @property int|null $user_id
 * @property string $encrypted_data
 * @property string $iv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VaultItem $vaultItem
 * @property-read User|null $user
 */
#[Fillable([
    'vault_item_id',
    'user_id',
    'encrypted_data',
    'iv',
])]
class VaultItemHistory extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<VaultItem, $this>
     */
    public function vaultItem(): BelongsTo
    {
        return $this->belongsTo(VaultItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
