<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeSignedUrlEntities
{
    /**
     * Handle an incoming request and fix HTML entity escaped URL parameters (e.g. &amp; from mail logs).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->getRequestUri();

        if (str_contains($uri, '&amp;')) {
            $normalizedUri = str_replace('&amp;', '&', $uri);

            return redirect($normalizedUri, 302);
        }

        return $next($request);
    }
}
