<?php

namespace App\Http\Middleware;

use App\Models\WidgetClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWidgetOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $widgetKey = $request->header('X-Widget-Key') ?? $request->input('widget_key');

        // 'page_origin' dikirim widget lewat postMessage dari halaman induk
        // (window.location.origin di halaman yang MEMASANG widget - nilai ini
        // ditentukan browser, tidak bisa dipalsukan situs itu sendiri).
        // Header 'Origin' bawaan browser TIDAK bisa dipakai di sini karena
        // fetch dari dalam iframe chat.html bersifat same-origin ke domain
        // widget kita sendiri, bukan ke domain situs yang memasangnya.
        $origin = $request->input('page_origin') ?? $request->header('Origin');

        $widgetClient = $widgetKey
            ? WidgetClient::where('widget_key', $widgetKey)->where('is_active', true)->first()
            : null;

        if (! $widgetClient) {
            return response()->json(['message' => 'Widget key tidak valid.'], 403);
        }

        $allowedOrigins = $widgetClient->allowed_origins ?? [];
        $originAllowed = $origin && in_array(rtrim($origin, '/'), array_map(
            fn (string $o): string => rtrim($o, '/'),
            $allowedOrigins
        ), true);

        // Preflight request (OPTIONS): langsung balas tanpa lanjut ke controller
        if ($request->isMethod('OPTIONS')) {
            return $this->withCorsHeaders(response('', 204), $origin, $originAllowed);
        }

        if (! $originAllowed) {
            return response()->json(['message' => 'Origin tidak diizinkan untuk widget ini.'], 403);
        }

        $request->attributes->set('widget_client', $widgetClient);

        $response = $next($request);

        return $this->withCorsHeaders($response, $origin, $originAllowed);
    }

    protected function withCorsHeaders(Response $response, ?string $origin, bool $originAllowed): Response
    {
        if ($originAllowed && $origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Widget-Key');
        $response->headers->set('Access-Control-Max-Age', '3600');

        return $response;
    }
}
