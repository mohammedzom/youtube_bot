<?php

/** @var Nutgram $bot */

use App\Jobs\ProcessVideoDownload;
use App\Models\Download;
use App\Models\TelegramUser;
use App\Services\YoutubeMetadataService;
use App\Telegram\Middleware\UserRegistrationAndQuota;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

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

// ---------------------------------------------------------------------------
// YouTube URL Handler
// ---------------------------------------------------------------------------
$bot->onText('(?i).*(youtube\.com|youtu\.be).*', function (Nutgram $bot): void {
    /** @var TelegramUser $user */
    $user = $bot->get('user');

    $url = $bot->message()->text;

    $metadata = app(YoutubeMetadataService::class);

    // ------------------------------------------------------------------
    // Fetch and display video metadata immediately for instant UX
    // ------------------------------------------------------------------
    $info = $metadata->fetchMetadata($url);

    $download = Download::create([
        'telegram_user_id' => $user->id,
        'video_url' => $url,
        'status' => 'pending',
    ]);

    ProcessVideoDownload::dispatch($download, $user);

    if ($info !== null) {
        $caption = "🎬 *{$info['title']}*\n\n"
            ."📺 القناة: {$info['channelTitle']}\n"
            ."⏱ المدة: {$info['duration']}\n\n"
            .'⏳ جاري التحميل، سيتم إرسال الملف فور الانتهاء...';

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🎵 MP3', callback_data: "dl:mp3:{$download->id}"),
                InlineKeyboardButton::make('📹 720p', callback_data: "dl:720p:{$download->id}"),
                InlineKeyboardButton::make('📹 Best', callback_data: "dl:best:{$download->id}"),
            );

        $bot->sendPhoto(
            photo: $info['thumbnail_url'],
            caption: $caption,
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
            chat_id: $user->telegram_id,
        );
    } else {
        $bot->sendMessage(
            text: '⏳ تمت إضافة الفيديو لطابور التحميل. سيتم إرساله لك فور الانتهاء...',
            chat_id: $user->telegram_id,
        );
    }
});
