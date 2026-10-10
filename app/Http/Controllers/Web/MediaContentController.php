<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Fleet\MediaPreviewSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MediaContentController extends Controller
{
    public function __invoke(Request $request, Media $media, MediaPreviewSource $sources): Response
    {
        $this->authorize('view', $media);
        $source = $sources->resolve($media);
        abort_if($source === null, 404, 'Archivo no disponible.');
        $headers = ['Content-Type' => $source['mime_type'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($source['kind'] === 'local') {
            return response()->file($source['path'], $headers);
        }

        $range = $request->header('Range');
        abort_if($range !== null && ! preg_match('/^bytes=(?:\d+-\d*|-\d+)$/', $range), 416);
        $upstream = Http::baseUrl(rtrim((string) config('privatecloud.base_url'), '/'))
            ->connectTimeout((int) config('privatecloud.connect_timeout', 10))
            ->timeout((int) config('privatecloud.timeout', 60))
            ->withOptions(['stream' => true, 'allow_redirects' => false]);
        if (filled(config('privatecloud.token'))) {
            $upstream = $upstream->withToken((string) config('privatecloud.token'));
        }
        foreach (['Range', 'If-Range'] as $header) {
            if ($request->hasHeader($header)) {
                $upstream = $upstream->withHeaders([$header => $request->header($header)]);
            }
        }

        try {
            $video = $upstream->send($request->isMethod('HEAD') ? 'HEAD' : 'GET', $source['path']);
        } catch (Throwable) {
            abort(503, 'La biblioteca de videos no está disponible.');
        }
        abort_if($video->status() === 404, 404, 'Archivo de video no disponible.');
        abort_unless(in_array($video->status(), [200, 206, 416], true), 502, 'No fue posible leer el video.');
        foreach (['Content-Length', 'Content-Range', 'Accept-Ranges', 'ETag', 'Last-Modified'] as $header) {
            if ($video->header($header) !== '') {
                $headers[$header] = $video->header($header);
            }
        }
        $body = $video->toPsrResponse()->getBody();

        return response()->stream(function () use ($body, $request): void {
            try {
                if (! $request->isMethod('HEAD')) {
                    while (! $body->eof() && ! connection_aborted()) {
                        echo $body->read(65536);
                    }
                }
            } finally {
                $body->close();
            }
        }, $video->status(), $headers);
    }
}
