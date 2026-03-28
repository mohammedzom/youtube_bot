<?php

namespace App\Models;

use Database\Factories\TelegramUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramUser extends Model
{
    /** @use HasFactory<TelegramUserFactory> */
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'daily_limit' => 'integer',
            'used_today' => 'integer',
            'premium_credits' => 'integer',
            'is_vip' => 'boolean',
            'is_admin' => 'boolean',
            'last_active_date' => 'date',
        ];
    }

    /**
     * Get the downloads for this Telegram user.
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    /**
     * Determine whether this user may request another download.
     *
     * A user has available quota when any of the following is true:
     *  - They have not yet hit their daily free limit (used_today < daily_limit)
     *  - They have premium credits remaining (premium_credits > 0)
     *  - They are a VIP user with unlimited access (is_vip == true)
     */
    public function hasAvailableQuota(): bool
    {
        return $this->is_vip
            || $this->premium_credits > 0
            || $this->used_today < $this->daily_limit;
    }
}
