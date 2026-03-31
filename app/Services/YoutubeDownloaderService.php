<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class YoutubeDownloaderService
{
    /**
     * The IPv6 /64 prefix for our AnyIP subnet.
     * All traffic will exit from a random address within this range.
     */
    private const IPV6_PREFIX = '2603:c024:4517:ec00';

    /**
     * Absolute path to the yt-dlp binary.
     */
    private const YTDLP_BIN = '/home/ubuntu/.local/bin/yt-dlp';

    /**
     * Maximum seconds to wait for a download to complete.
     */
    private const DOWNLOAD_TIMEOUT = 600;

    /**
     * yt-dlp format strings indexed by quality key.
     *
     * @var array<string, string>
     */
    private const FORMAT_MAP = [
        'audio' => 'bestaudio[ext=m4a]/bestaudio',
        '720' => 'bestvideo[height<=720][ext=mp4]+bestaudio[ext=m4a]/best[height<=720][ext=mp4]/best',
        'best' => 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
    ];

    /**
     * Download a YouTube video (or audio) and return the absolute path to the saved file.
     *
     * A random IPv6 source address from our /64 AnyIP subnet is chosen for
     * each download to rotate the outgoing IP and reduce YouTube throttling.
     *
     * @param  string  $quality  One of 'audio', '720', 'best'.
     *
     * @throws RuntimeException When yt-dlp exits with a non-zero status.
     */
    public function downloadVideo(string $url, string $quality = 'best'): string
    {
        $outputDirectory = storage_path('app/downloads');
        File::ensureDirectoryExists($outputDirectory);

        $extension = $quality === 'audio' ? 'm4a' : 'mp4';
        $filename = Str::uuid().'.'.$extension;
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.$filename;

        $format = self::FORMAT_MAP[$quality] ?? self::FORMAT_MAP['best'];
        $sourceAddress = $this->generateRandomIpv6();

        $result = Process::timeout(self::DOWNLOAD_TIMEOUT)->run([
            self::YTDLP_BIN,
            '--js-runtimes', 'node',
            '--remote-components', 'ejs:github',
            '--cookies', storage_path('app/youtube_cookies.txt'),
            '--extractor-args', 'youtube:player_client=android,web',
            '--source-address', $sourceAddress,
            '-f', $format,
            '-o', $outputPath,
            $url,
        ]);

        if ($result->failed()) {
            throw new RuntimeException(
                'yt-dlp failed: '.$result->errorOutput()
            );
        }

        return $outputPath;
    }

    /**
     * Generate a random IPv6 address within the 2603:c024:4517:ec00::/64 subnet.
     *
     * The upper 64 bits are fixed (our AnyIP prefix). The lower 64 bits are
     * filled with four cryptographically random 16-bit hex groups.
     *
     * Example: 2603:c024:4517:ec00:1a2b:3c4d:5e6f:7a8b
     */
    private function generateRandomIpv6(): string
    {
        $blocks = array_map(
            fn (): string => str_pad(dechex(random_int(0, 0xFFFF)), 4, '0', STR_PAD_LEFT),
            array_fill(0, 4, null)
        );

        return self::IPV6_PREFIX.':'.implode(':', $blocks);
    }
}
