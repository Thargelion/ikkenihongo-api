<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $google = $this->googleUser();
        if (! $google) {
            return redirect($this->frontend('/sign-in?error=google'));
        }

        $guest = Auth::user()?->is_guest ? Auth::user() : null;
        $user = $this->linkExisting($google) ?? $this->upgradeOrCreate($google, $guest);
        if ($guest && $guest->isNot($user)) {
            $guest->delete();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($this->frontend('/home'));
    }

    private function googleUser(): ?GoogleUser
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return null;
        }

        return $google->getEmail() && ($google->user['email_verified'] ?? false) ? $google : null;
    }

    private function linkExisting(GoogleUser $google): ?User
    {
        $user = User::firstWhere('google_id', $google->getId())
            ?? User::firstWhere('email', $google->getEmail());
        if (! $user) {
            return null;
        }

        // A password set at registration was never proven to belong to the email owner.
        $user->forceFill([
            'google_id' => $google->getId(),
            'password' => $user->email_verified_at ? $user->password : Str::random(40),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    private function upgradeOrCreate(GoogleUser $google, ?User $guest): User
    {
        $attributes = [
            'name' => $google->getName() ?: $google->getEmail(),
            'nickname' => $google->getNickname() ?: $google->getName() ?: Str::before($google->getEmail(), '@'),
            'email' => $google->getEmail(),
            'google_id' => $google->getId(),
            'password' => Str::random(40),
            'is_guest' => false,
        ];
        $user = $guest ?? new User;
        $user->forceFill($attributes + ['email_verified_at' => now()])->save();

        return $user;
    }

    private function frontend(string $path): string
    {
        return rtrim(config('app.frontend_url'), '/').$path;
    }
}
