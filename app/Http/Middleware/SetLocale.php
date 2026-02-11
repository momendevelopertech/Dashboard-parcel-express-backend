<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for X-Locale header first (custom header from frontend)
        $locale = $request->header('X-Locale');

        // If not found, check Accept-Language header
        if (!$locale) {
            $locale = $request->getPreferredLanguage(['en', 'ar']);
        }

        // Validate locale (only allow supported locales)
        $supportedLocales = ['en', 'ar'];
        if ($locale && in_array($locale, $supportedLocales)) {
            App::setLocale($locale);
        } else {
            // Fallback to default locale from config
            App::setLocale(config('app.locale', 'en'));
        }

        return $next($request);
    }
}
