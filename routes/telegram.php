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
// YouTube URL Handler — show metadata + download buttons ONLY, no job dispatch
// ---------------------------------------------------------------------------
$bot->onText('(?i).*(youtube\.com|youtu\.be).*', function (Nutgram $bot): void {
    /** @var TelegramUser $user */
    $user = $bot->get('user');

    $url = $bot->message()->text;

    /** @var YoutubeMetadataService $metadata */
    $metadata = app(YoutubeMetadataService::class);

    $info = $metadata->fetchMetadata($url);

    if ($info === null) {
        $bot->sendMessage(
            text: '⚠️ لم أتمكن من جلب معلومات الفيديو. تأكد من صحة الرابط.',
            chat_id: $user->telegram_id,
        );

        return;
    }

    $videoId = $metadata->extractVideoId($url);

    $caption = "🎬 *{$info['title']}*\n\n"
        ."📺 القناة: {$info['channelTitle']}\n"
        ."⏱ المدة: {$info['duration']}\n\n"
        .'اختر جودة التحميل:';

    $keyboard = InlineKeyboardMarkup::make()
        ->addRow(
            InlineKeyboardButton::make('🎵 MP3 / صوت', callback_data: "dl:audio:{$videoId}"),
            InlineKeyboardButton::make('📹 720p', callback_data: "dl:720:{$videoId}"),
            InlineKeyboardButton::make('📹 أفضل جودة', callback_data: "dl:best:{$videoId}"),
        );

    $bot->sendPhoto(
        photo: $info['thumbnail_url'],
        caption: $caption,
        parse_mode: 'Markdown',
        reply_markup: $keyboard,
        chat_id: $user->telegram_id,
    );
});

// ---------------------------------------------------------------------------
// Callback Query Handler — activated when the user taps a download button
// ---------------------------------------------------------------------------
$bot->onCallbackQueryData('dl:{quality}:{vid}', function (Nutgram $bot, string $quality, string $vid): void {
    /** @var TelegramUser $user */
    $user = $bot->get('user');

    // Acknowledge the button tap immediately so Telegram removes the loading state
    $bot->answerCallbackQuery(text: '⏳ تمت الإضافة للطابور...');

    // Edit the caption in-place to show a queued status (keeps the photo)
    $bot->editMessageCaption(
        caption: '⏳ *جاري التحميل...*'."\n\nسيتم إرسال الملف لك فور الانتهاء.",
        parse_mode: 'Markdown',
    );

    // Reconstruct the full YouTube URL from the video ID
    $videoUrl = "https://www.youtube.com/watch?v={$vid}";

    // Create the download record and dispatch the background job
    $download = Download::create([
        'telegram_user_id' => $user->id,
        'video_url' => $videoUrl,
        'quality' => $quality,
        'status' => 'pending',
    ]);

    ProcessVideoDownload::dispatch($download, $user, $quality);
});
