<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

final class Cloudinary
{
    public function upload(UploadedFile $file, string $owner): array
    {
        $cfg = config('services.cloudinary');
        abort_unless($cfg['cloud'] && $cfg['key'] && $cfg['secret'], 503, 'Media storage is not configured');
        $params = ['folder' => 'nabaku/'.$owner, 'timestamp' => time()];
        ksort($params);
        $signature = hash('sha256', urldecode(http_build_query($params)).$cfg['secret']);
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            $res = Http::connectTimeout(5)->timeout(60)->attach('file', $stream, $file->getClientOriginalName())->post('https://api.cloudinary.com/v1_1/'.rawurlencode($cfg['cloud']).'/auto/upload', [...$params, 'api_key' => $cfg['key'], 'signature' => $signature]);
        } catch (ConnectionException $e) {
            abort(502, 'Media storage unavailable');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        abort_unless($res->successful() && $res->json('secure_url'), 502, 'Media upload failed');

        return ['url' => $res->json('secure_url'), 'type' => $res->json('resource_type')];
    }
}
