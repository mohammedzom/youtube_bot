<?php

namespace App\Telegram\Middleware;

use App\Models\TelegramUser;
use Carbon\Carbon;
use SergiX44\Nutgram\Nutgram;

class UserRegistrationMiddleware
{
    /**
     * Register or retrieve the user, reset daily counters if needed,
     * and share the model on the bot instance for downstream handlers.
     */
    public function __invoke(Nutgram $bot, callable $next): void
    {
        $telegramUser = $bot->user();

        if ($telegramUser === null) {
            $next($bot);

            return;
        }

        /** @var TelegramUser $user */
        $user = TelegramUser::firstOrCreate(
            ['telegram_id' => $telegramUser->id],
            [
                'username' => $telegramUser->username,
                'daily_limit' => 5,
                'used_today' => 0,
                'premium_credits' => 0,
                'is_vip' => false,
                'is_admin' => false,
                'last_active_date' => Carbon::today(),
            ]
        );

        // Always keep the stored username in sync.
        if ($user->username !== $telegramUser->username) {
            $user->username = $telegramUser->username;
        }

        // Reset the daily counter if the user hasn't interacted yet today.
        $today = Carbon::today();
        if ($user->last_active_date === null || $user->last_active_date->lt($today)) {
            $user->used_today = 0;
        }

        $user->last_active_date = $today;
        $user->save();

        // Share the Eloquent model so handlers can call $bot->get('user').
        $bot->set('user', $user);

        $next($bot);
    }
}
