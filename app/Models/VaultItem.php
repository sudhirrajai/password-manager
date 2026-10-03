<?php

namespace App\Models;

use Database\Factories\VaultItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string $encrypted_data
 * @property string $iv
 * @property bool $is_favorite
 * @property string|null $folder
 * @property Carbon|null $last_used_at
 * @property Carbon|null $password_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User $user
 */
#[Fillable([
    'team_id',
    'user_id',
    'type',
    'title',
    'encrypted_data',
    'iv',
    'is_favorite',
    'folder',
    'last_used_at',
    'password_updated_at',
])]
class VaultItem extends Model
{
    /** @use HasFactory<VaultItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'last_used_at' => 'datetime',
            'password_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<VaultAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(VaultAuditLog::class);
    }

    /**
     * @return HasMany<VaultItemHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(VaultItemHistory::class)->latest('id');
    }
}
