<?php

/** @var Nutgram $bot */

use App\Models\TelegramUser;
use App\Telegram\Middleware\UserRegistrationMiddleware;
use SergiX44\Nutgram\Nutgram;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Here is where you register telegram handlers for Nutgram. These handlers
| are loaded by the NutgramServiceProvider. The UserRegistrationMiddleware
| runs on every update and ensures the user exists in the database, resets
| daily counters when the day rolls over, and shares the TelegramUser model
| on the bot instance via $bot->get('user').
|
*/

// Run on every incoming update.
$bot->middleware(UserRegistrationMiddleware::class);

// /start command — greets the user and shows their current quota status.
$bot->onCommand('start', function (Nutgram $bot): void {
    /** @var TelegramUser $user */
    $user = $bot->get('user');

    $name = $bot->user()->first_name ?? 'there';

    if (! $user->hasAvailableQuota()) {
        $bot->sendMessage(
            text: "👋 Hi, {$name}!\n\n"
                ."⚠️ You've used all your downloads for today.\n"
                .'Your daily limit resets at midnight. Come back tomorrow!',
        );

        return;
    }

    $remaining = $user->daily_limit - $user->used_today;
    $creditsLine = $user->premium_credits > 0
        ? "\n💎 Premium credits: *{$user->premium_credits}*"
        : '';

    $vipBadge = $user->is_vip ? ' 👑' : '';

    $bot->sendMessage(
        text: "👋 Welcome{$vipBadge}, *{$name}*!\n\n"
            ."Send me any YouTube link and I'll download it for you.\n\n"
            ."📊 *Your quota today:* {$user->used_today} / {$user->daily_limit} used"
            ." ({$remaining} remaining){$creditsLine}",
        parse_mode: 'Markdown',
    );
})->description('Start the bot and check your download quota');
