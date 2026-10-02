<?php

namespace App\Models;

use Database\Factories\SecretLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string|null $title
 * @property string $encrypted_data
 * @property string $iv
 * @property int $max_views
 * @property int $view_count
 * @property Carbon $expires_at
 * @property string|null $passphrase_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'token',
    'title',
    'encrypted_data',
    'iv',
    'max_views',
    'view_count',
    'expires_at',
    'passphrase_hash',
])]
class SecretLink extends Model
{
    /** @use HasFactory<SecretLinkFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'max_views' => 'integer',
            'view_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast() || ($this->max_views > 0 && $this->view_count >= $this->max_views);
    }
}
