<?php

namespace App\Services\Z2;

use App\Models\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Z2VideoService
{
    private FanCloudService $client;

    public function __construct(FanCloudService $client)
    {
        $this->client = $client;
    }

    /**
     * Sync videos from cloud.
     */
    public function syncVideos(): array
    {
        $response = $this->client->request('POST', '/Effect/getUiListIsVersion', [
            'userName' => $this->client->username,
            'isVersion' => '',
            'effGroupID' => 0,
            'order' => 0,
            'iDisplayStart' => 0,
            'iDisplayLength' => 200,
        ]);

        if (! $response || ! isset($response['aaData'])) {
            Log::error('[Z2] Failed to fetch videos from cloud');
            return [];
        }

        $videosToUpsert = [];
        $uiCodes = [];

        foreach ($response['aaData'] as $videoData) {
            $uiCode = $videoData['uiCode'] ?? null;
            if (! $uiCode) {
                continue;
            }

            $uiCodes[] = $uiCode;

            $fileName = $videoData['resourcesName'] ?? $videoData['fileName'] ?? 'unknown.mp4';
            
            if (isset($videoData['videoSize'])) {
                $fileSize = $videoData['videoSize'] * 1024; // Convert KB to bytes
            } else {
                $fileSize = $videoData['fileSize'] ?? 0;
            }
            
            if (isset($videoData['videoTime'])) {
                $duration = round($videoData['videoTime'] / 1000); // Convert ms to seconds
            } else {
                $duration = $videoData['playTime'] ?? 0;
            }

            $advertisersCode = $videoData['advertisersCode'] ?? $this->client->getAdvertiserId() ?? '';

            // Check if imgsUrl contains thumbnail URL
            $thumbnail = null;
            if (! empty($videoData['imgsUrl'])) {
                if (is_array($videoData['imgsUrl'])) {
                    $thumbnail = $videoData['imgsUrl'][0] ?? null;
                } elseif (is_string($videoData['imgsUrl'])) {
                    $thumbnail = $videoData['imgsUrl'];
                }
            }

            if (! $thumbnail) {
                $thumbnail = $this->buildThumbnailUrl($advertisersCode, $uiCode, $fileName);
            }

            $videosToUpsert[] = [
                'file_path' => $uiCode,
                'name' => $fileName,
                'original_name' => $fileName,
                'mime_type' => 'video/mp4',
                'size' => (int) $fileSize,
                'duration' => (int) $duration,
                'thumbnail' => $thumbnail,
            ];
        }

        // Restore soft-deleted media that are back in the cloud
        if (! empty($uiCodes)) {
            $trashedMedia = Media::onlyTrashed()->whereIn('file_path', $uiCodes)->get();
            foreach ($trashedMedia as $trashed) {
                $trashed->restore();
                Log::info('[Z2] Restored soft-deleted media', ['file_path' => $trashed->file_path]);
            }
        }

        // Atomic upsert to avoid race conditions
        if (! empty($videosToUpsert)) {
            Media::upsert($videosToUpsert, ['file_path'], ['name', 'original_name', 'mime_type', 'size', 'duration', 'thumbnail']);
        }

        // Remove videos no longer in cloud
        Media::whereNotIn('file_path', $uiCodes)->delete();

        $syncedVideos = Media::whereIn('file_path', $uiCodes)->get()->all();

        Log::info('[Z2] Synced ' . count($syncedVideos) . ' videos from cloud');

        return $syncedVideos;
    }

    /**
     * Upload video to cloud (3-step process).
     */
    public function uploadVideo(string $filePath, string $fileName, int $duration = 0): ?Media
    {
        // Step 1: Get FTP credentials and slot
        $uploadInfo = $this->getUploadSlot($fileName);
        if (! $uploadInfo) {
            return null;
        }

        // Get actual file size from local file
        $actualFileSize = @filesize($filePath) ?: ($uploadInfo['fileSize'] ?? 0);

        // Get actual duration
        $actualDuration = $duration ?: ($uploadInfo['playTime'] ?? 10);
        if ($actualDuration <= 0) {
            $actualDuration = 10; // Default fallback to 10 seconds
        }

        // Ensure name has correct extension (important for Z2 cloud validation)
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if ($extension && !str_ends_with(strtolower($fileName), '.' . strtolower($extension))) {
            $fileName .= '.' . $extension;
        }

        // Step 2: Upload file via FTP
        $ftpUploaded = $this->uploadViaFtp(
            $filePath,
            $uploadInfo['ftpUrl'],
            $uploadInfo['ftpUsername'],
            $uploadInfo['ftpPassword'],
            $uploadInfo['ftpUploadUrl'],
            $fileName
        );

        if (! $ftpUploaded) {
            return null;
        }

        // Step 3: Confirm upload with correct file size and duration
        $confirmed = $this->confirmUpload(
            $uploadInfo['uiCode'],
            $fileName,
            $actualFileSize,
            $actualDuration
        );

        if (! $confirmed) {
            return null;
        }

        // Create local media record
        $media = Media::create([
            'name' => $fileName,
            'original_name' => $fileName,
            'file_path' => $uploadInfo['uiCode'],
            'mime_type' => 'video/mp4',
            'size' => $actualFileSize,
            'duration' => $actualDuration,
            'thumbnail' => $this->buildThumbnailUrl(
                $uploadInfo['advertisersCode'] ?? '',
                $uploadInfo['uiCode'],
                $fileName
            ),
        ]);

        return $media;
    }

    /**
     * Get upload slot from cloud.
     */
    private function getUploadSlot(string $fileName): ?array
    {
        $response = $this->client->request('POST', '/User/uploadMediaFile', [
            'userName' => $this->client->username,
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            return [
                'uiCode' => $response['uiCode'] ?? '',
                'ftpUrl' => $response['ftpUrl'] ?? '',
                'ftpUploadUrl' => $response['ftpUploadUrl'] ?? '',
                'ftpUsername' => $response['ftpUsername'] ?? '',
                'ftpPassword' => $response['ftpPassword'] ?? '',
                'advertisersCode' => $response['advertisersCode'] ?? '',
                'fileSize' => $response['fileSize'] ?? 0,
                'playTime' => $response['playTime'] ?? 0,
            ];
        }

        Log::error('[Z2] Get upload slot failed', ['response' => $response]);
        return null;
    }

    /**
     * Upload file via FTP.
     */
    private function uploadViaFtp(
        string $localPath,
        string $ftpHost,
        string $ftpUsername,
        string $ftpPassword,
        string $ftpRemotePath,
        string $fileName
    ): bool {
        try {
            $remotePath = $ftpRemotePath;
            if (str_ends_with($remotePath, '/')) {
                $remotePath .= rawurlencode($fileName);
            } else {
                $remotePath .= '/' . rawurlencode($fileName);
            }

            // Ensure the remote path starts with a slash
            $remotePath = '/' . ltrim($remotePath, '/');

            // Build full FTP URL
            $ftpUrl = "ftp://{$ftpHost}{$remotePath}";
            Log::info("[Z2] FTP uploading to {$ftpUrl} using Curl");

            $ch = curl_init();
            if ($ch === false) {
                Log::error('[Z2] Failed to initialize curl for FTP upload');
                return false;
            }

            $fp = fopen($localPath, 'r');
            if ($fp === false) {
                Log::error("[Z2] Failed to open local file for reading: {$localPath}");
                curl_close($ch);
                return false;
            }

            curl_setopt($ch, CURLOPT_URL, $ftpUrl);
            curl_setopt($ch, CURLOPT_USERPWD, "{$ftpUsername}:{$ftpPassword}");
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_INFILE, $fp);
            curl_setopt($ch, CURLOPT_INFILESIZE, @filesize($localPath) ?: 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, true);

            $response = curl_exec($ch);
            $errNo = curl_errno($ch);
            $errMsg = curl_error($ch);

            curl_close($ch);
            fclose($fp);

            if ($errNo !== 0) {
                Log::error("[Z2] Curl FTP upload failed: [{$errNo}] {$errMsg}", [
                    'ftpUrl' => $ftpUrl,
                    'username' => $ftpUsername
                ]);
                return false;
            }

            Log::info("[Z2] Curl FTP upload successful", ['remotePath' => $remotePath]);
            return true;
        } catch (\Throwable $e) {
            Log::error('[Z2] Curl FTP upload error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Confirm upload after FTP transfer.
     */
    private function confirmUpload(
        string $uiCode,
        string $fileName,
        int $fileSize,
        int $playTime
    ): bool {
        $response = $this->client->request('POST', '/User/uploadMediaSuccessIsVersion', [
            'userName' => $this->client->username,
            'uiCode' => $uiCode,
            'fileName' => $fileName,
            'resourcesName' => $fileName,
            'isVersion' => 0,
            'mediaGroup' => 0,
            'fileSize' => $fileSize,
            'playTime' => $playTime,
        ]);

        if ($response && ($response['result'] ?? -1) === 0) {
            return true;
        }

        Log::error('[Z2] Upload confirmation failed', [
            'uiCode' => $uiCode,
            'fileName' => $fileName,
            'response' => $response,
        ]);
        return false;
    }

    /**
     * Build thumbnail URL from advertisers code and uiCode.
     */
    private function buildThumbnailUrl(string $advertisersCode, string $uiCode, string $fileName): ?string
    {
        $baseUrl = config('z2.base_url', 'http://www.holographicdisplay.cn:8088');
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        return "{$baseUrl}/ui/{$advertisersCode}/{$uiCode}/{$name}.jpg";
    }

    /**
     * Resolve a playlist filename to its numeric Z2 uiCode.
     *
     * The device playList field stores filenames (e.g. "video_uva_2.mp4"),
     * but upgradeDeviceUi requires the numeric uiCode (e.g. "20260606230635773598").
     * This method fetches the full video list from Z2 and matches by resourcesName.
     *
     * Returns the uiCode if found, or null if not resolvable.
     */
    public function getUiCodeByFileName(string $fileName): ?string
    {
        // First check local DB (file_path = uiCode, name = resourcesName)
        $media = Media::whereRaw('LOWER(name) = LOWER(?)', [$fileName])
            ->whereRaw('file_path REGEXP ?', ['^[0-9]+$'])
            ->first();

        if ($media) {
            Log::info('[Z2] Resolved filename to uiCode via local DB', [
                'fileName' => $fileName,
                'uiCode'   => $media->file_path,
            ]);
            return $media->file_path;
        }

        // Fallback: fetch live list from Z2 cloud
        $response = $this->client->request('POST', '/Effect/getUiListIsVersion', [
            'userName'       => $this->client->username,
            'isVersion'      => '',
            'effGroupID'     => 0,
            'order'          => 0,
            'iDisplayStart'  => 0,
            'iDisplayLength' => 200,
        ]);

        if ($response && isset($response['aaData'])) {
            foreach ($response['aaData'] as $videoData) {
                $resourcesName = $videoData['resourcesName'] ?? $videoData['fileName'] ?? '';
                if (strcasecmp($resourcesName, $fileName) === 0) {
                    $uiCode = $videoData['uiCode'] ?? null;
                    if ($uiCode) {
                        Log::info('[Z2] Resolved filename to uiCode via cloud list', [
                            'fileName' => $fileName,
                            'uiCode'   => $uiCode,
                        ]);
                        return $uiCode;
                    }
                }
            }
        }

        Log::warning('[Z2] Could not resolve filename to uiCode', ['fileName' => $fileName]);
        return null;
    }

    /**
     * Delete video from cloud.
     */
    public function deleteVideo(string $uiCode): bool
    {
        // Note: No explicit delete endpoint documented
        // Delete locally only
        Media::where('file_path', $uiCode)->delete();
        return true;
    }

    /**
     * Get video URL for playback.
     */
    public function getVideoUrl(string $uiCode, string $fileName): string
    {
        $baseUrl = config('z2.base_url', 'http://www.holographicdisplay.cn:8088');
        return "{$baseUrl}/ui/{$uiCode}/{$fileName}";
    }
}
