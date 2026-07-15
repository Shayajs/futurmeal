<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\BrightShieldAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class BrightShieldController extends Controller
{
    /**
     * Mode UX choisi par l’app cliente (config) — BrightShield en gère deux :
     * - redirect : redirection GET classique (choisi par Futurmeal)
     * - popup : fermeture popup + postMessage (disponible pour d’autres apps)
     */
    public function redirect(): RedirectResponse
    {
        $this->ensureConfigured();

        Session::put('brightshield.mode', $this->uxMode());

        return Socialite::driver('brightshield')->redirect();
    }

    public function callback(Request $request, BrightShieldAuthService $authService): RedirectResponse|View
    {
        $this->ensureConfigured();

        $mode = Session::pull('brightshield.mode', $this->uxMode());

        if ($request->query('error') !== null) {
            return $this->deny($mode, (string) $request->query('error_description', 'Connexion refusée.'));
        }

        try {
            $socialiteUser = Socialite::driver('brightshield')->user();
        } catch (InvalidStateException) {
            return $this->deny($mode, 'Session BrightShield expirée. Recommencez la connexion.');
        } catch (Throwable $e) {
            Log::warning('BrightShield callback failed', [
                'message' => $e->getMessage(),
            ]);

            $message = 'Connexion BrightShell impossible.';
            if (str_contains($e->getMessage(), 'Failed to connect') || str_contains($e->getMessage(), 'Could not connect')) {
                $message = 'Futurmeal n’atteint pas BrightShield (réseau Docker). Vérifiez BRIGHTSHIELD_API_BASE_URL.';
            } elseif (str_contains($e->getMessage(), 'invalid_client')) {
                $message = 'Identifiants BrightShield invalides (client_id / client_secret). Vérifiez le .env Futurmeal.';
            } elseif (str_contains($e->getMessage(), 'invalid_grant') || str_contains($e->getMessage(), 'redirect_uri')) {
                $message = 'URI de callback BrightShield invalide. Vérifiez BRIGHTSHIELD_REDIRECT_URI.';
            }

            return $this->deny($mode, $message);
        }

        $user = $authService->resolveUser($socialiteUser);

        $authService->login($user);
        Session::regenerate();

        $target = $authService->redirectPath($user);

        if ($mode === 'popup') {
            return view('auth.brightshield-popup-callback', [
                'status' => 'success',
                'redirect' => url($target),
                'message' => null,
            ]);
        }

        return redirect()->intended($target);
    }

    private function deny(string $mode, string $message): RedirectResponse|View
    {
        if ($mode === 'popup') {
            return view('auth.brightshield-popup-callback', [
                'status' => 'error',
                'redirect' => null,
                'message' => $message,
            ]);
        }

        return redirect()->route('login')->withErrors(['brightshield' => $message]);
    }

    private function uxMode(): string
    {
        $mode = strtolower((string) config('services.brightshield.ux_mode', 'redirect'));

        return in_array($mode, ['redirect', 'popup'], true) ? $mode : 'redirect';
    }

    private function ensureConfigured(): void
    {
        if (
            blank(config('services.brightshield.client_id'))
            || blank(config('services.brightshield.client_secret'))
            || blank(config('services.brightshield.redirect'))
        ) {
            abort(503, 'BrightShield n’est pas configuré sur cette instance.');
        }
    }
}
