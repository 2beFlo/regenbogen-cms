<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePrivilegedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->requiresStrongAuthentication()) {
            return $next($request);
        }

        if ($user->hasStrongAuthenticationConfigured()) {
            return $next($request);
        }

        $profileUrl = filament()->getProfileUrl();

        if (! $profileUrl) {
            abort(503, 'Für dieses Konto ist Zwei-Faktor-Authentifizierung erforderlich, aber die Profilseite ist nicht verfügbar.');
        }

        // The profile page is the one place a privileged user must be able to
        // reach before MFA has been configured. Livewire update requests are
        // handled outside this panel route middleware.
        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($request->url() === $profileUrl || str_ends_with($routeName, '.auth.profile') || str_ends_with($routeName, '.auth.logout')) {
            return $next($request);
        }

        return redirect()->to($profileUrl)->with(
            'warning',
            'Für Redaktion und Administration ist Zwei-Faktor-Authentifizierung erforderlich. Bitte richte sie unter „Mein Konto & Sicherheit“ ein.',
        );
    }
}
