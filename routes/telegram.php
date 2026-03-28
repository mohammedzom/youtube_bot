<?php

/** @var Nutgram $bot */

use App\Models\TelegramUser;
use App\Telegram\Middleware\UserRegistrationAndQuota;
use SergiX44\Nutgram\Nutgram;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| The UserRegistrationAndQuota middleware runs globally on every incoming
| update. It finds or creates the TelegramUser, resets the daily counter
| when the day changes, and halts the chain with an Arabic quota-exceeded
| message when the user has no available downloads remaining.
|
*/

$bot->middleware(UserRegistrationAndQuota::class);

// ---------------------------------------------------------------------------
// /start — welcome message with Telegram ID and remaining daily quota
// ---------------------------------------------------------------------------
$bot->onCommand('start', function (Nutgram $bot): void {
    /** @var TelegramUser $user */
    $user = $bot->get('user');

    $remaining = $user->daily_limit - $user->used_today;

    $creditsLine = $user->premium_credits > 0
        ? "\n💎 رصيد premium: *{$user->premium_credits}* تنزيل"
        : '';

    $vipBadge = $user->is_vip ? ' 👑' : '';

    $bot->sendMessage(
        text: "👋 أهلاً بك{$vipBadge}!\n\n"
            ."🆔 معرّفك: `{$user->telegram_id}`\n"
            ."📊 حصتك اليوم: *{$user->used_today}* / *{$user->daily_limit}* مستخدم"
            ." _(متبقي: {$remaining})_"
            ."{$creditsLine}\n\n"
            .'أرسل لي أي رابط YouTube وسأقوم بتحميله لك. 🎬',
        parse_mode: 'Markdown',
    );
})->description('Start the bot');
