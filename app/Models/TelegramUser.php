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
}
