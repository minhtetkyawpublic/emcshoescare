<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustedBrowserRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->get('Sec-Fetch-Site') === 'cross-site') {
            throw new ApiException('untrusted_origin', 'This request was blocked for your security.', 403);
        }

        $origin = rtrim((string) $request->headers->get('Origin'), '/');
        $allowed = config('emc.allowed_origins', []);
        if ($origin !== '' && ! in_array($origin, $allowed, true)) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            if (! $originHost || ! hash_equals(strtolower($request->getHost()), strtolower($originHost))) {
                throw new ApiException('untrusted_origin', 'This request was blocked for your security.', 403);
            }
        }

        return $next($request);
    }
}
