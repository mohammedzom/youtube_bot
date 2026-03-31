<?php

namespace App\Jobs;

use App\Models\Download;
use App\Models\TelegramUser;
use App\Services\YoutubeDownloaderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use Throwable;

class ProcessVideoDownload implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Download $download,
        public TelegramUser $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(Nutgram $bot, YoutubeDownloaderService $downloader): void
    {
        $filePath = null;

        try {
            $this->download->update(['status' => 'downloading']);

            $filePath = $downloader->downloadVideo($this->download->video_url);

            $this->download->update([
                'status' => 'uploading',
                'file_path' => $filePath,
            ]);

            $bot->sendVideo(
                chat_id: $this->user->telegram_id,
                video: InputFile::make($filePath),
                caption: '✅ تم التحميل بنجاح!'
            );

            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            $this->download->update(['status' => 'completed']);

            $this->user->increment('used_today');

        } catch (Throwable $e) {
            $this->download->update(['status' => 'failed']);

            if ($filePath && File::exists($filePath)) {
                File::delete($filePath);
            }

            $bot->sendMessage(
                text: "❌ عذراً، فشل التحميل. يرجى المحاولة لاحقاً.\nالسبب: ".$e->getMessage(),
                chat_id: $this->user->telegram_id
            );
        }
    }
}
