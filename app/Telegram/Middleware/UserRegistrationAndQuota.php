<?php

namespace App\Telegram\Middleware;

use App\Models\TelegramUser;
use Carbon\Carbon;
use SergiX44\Nutgram\Nutgram;

class UserRegistrationAndQuota
{
    /**
     * Run on every incoming update.
     *
     * Responsibilities:
     *  1. Find or create the TelegramUser record.
     *  2. Keep the stored username in sync.
     *  3. Reset used_today when the calendar day has changed.
     *  4. Update last_active_date and save.
     *  5. Share the model via $bot->set('user', $user).
     *  6. Halt the chain and notify the user if they have no quota left.
     */
    public function __invoke(Nutgram $bot, callable $next): void
    {
        $telegramUser = $bot->user();

        // Some updates (e.g. channel posts) carry no user — let them through.
        if ($telegramUser === null) {
            $next($bot);

            return;
        }

        // ------------------------------------------------------------------
        // 1. Find or create
        // ------------------------------------------------------------------
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

        // ------------------------------------------------------------------
        // 2. Sync username
        // ------------------------------------------------------------------
        if ($user->username !== $telegramUser->username) {
            $user->username = $telegramUser->username;
        }

        // ------------------------------------------------------------------
        // 3. Reset daily counter when the day has rolled over
        // ------------------------------------------------------------------
        $today = Carbon::today();

        if ($user->last_active_date === null || $user->last_active_date->lt($today)) {
            $user->used_today = 0;
        }

        // ------------------------------------------------------------------
        // 4. Update last active date and persist
        // ------------------------------------------------------------------
        $user->last_active_date = $today;
        $user->save();

        // ------------------------------------------------------------------
        // 5. Share the model with downstream handlers
        // ------------------------------------------------------------------
        $bot->set('user', $user);

        // ------------------------------------------------------------------
        // 6. Quota gate — halt and notify if the user is out of quota
        // ------------------------------------------------------------------
        if (! $user->hasAvailableQuota()) {
            $bot->sendMessage('⛔ انتهى رصيدك اليومي. راسل المطور لزيادته.');

            return; // do NOT call $next — chain is halted
        }

        $next($bot);
    }
}
