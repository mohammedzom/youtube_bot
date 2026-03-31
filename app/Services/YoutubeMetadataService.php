<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class YoutubeMetadataService
{
    private const API_URL = 'https://www.googleapis.com/youtube/v3/videos';

    /**
     * Extract a YouTube video ID from any standard YouTube URL.
     *
     * Supports:
     *  - https://www.youtube.com/watch?v=VIDEO_ID
     *  - https://youtu.be/VIDEO_ID
     *  - https://youtube.com/shorts/VIDEO_ID
     *  - https://www.youtube.com/embed/VIDEO_ID
     *
     * @return string|null The 11-character video ID, or null if not found.
     */
    public function extractVideoId(string $url): ?string
    {
        $patterns = [
            '/[?&]v=([a-zA-Z0-9_-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/\/(?:embed|shorts|v)\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Fetch video metadata from the YouTube Data API v3.
     *
     * @return array{title: string, channelTitle: string, duration: string, thumbnail_url: string}|null
     *                                                                                                  Null when the video ID cannot be found in the URL or the API returns no items.
     */
    public function fetchMetadata(string $url): ?array
    {
        $videoId = $this->extractVideoId($url);

        if ($videoId === null) {
            return null;
        }

        $response = Http::timeout(10)
            ->connectTimeout(5)
            ->retry(2, 300)
            ->get(self::API_URL, [
                'part' => 'snippet,contentDetails',
                'id' => $videoId,
                'key' => config('services.youtube.api_key'),
            ]);

        $response->throw();

        $items = $response->json('items');

        if (empty($items)) {
            return null;
        }

        $item = $items[0];

        return [
            'title' => $item['snippet']['title'],
            'channelTitle' => $item['snippet']['channelTitle'],
            'duration' => $this->formatIsoDuration($item['contentDetails']['duration']),
            'thumbnail_url' => $item['snippet']['thumbnails']['high']['url']
                ?? $item['snippet']['thumbnails']['default']['url'],
        ];
    }

    /**
     * Convert an ISO 8601 duration string into a human-readable HH:MM:SS or MM:SS format.
     *
     * Examples:
     *  "PT2M49S"    → "02:49"
     *  "PT1H3M20S"  → "01:03:20"
     *  "PT45S"      → "00:45"
     */
    public function formatIsoDuration(string $isoDuration): string
    {
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $isoDuration, $matches);

        $hours = isset($matches[1]) ? (int) $matches[1] : 0;
        $minutes = isset($matches[2]) ? (int) $matches[2] : 0;
        $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
