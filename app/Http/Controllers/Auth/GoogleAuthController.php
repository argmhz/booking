<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $googleEmail = Str::lower(trim((string) $googleUser->getEmail()));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$googleEmail])
            ->orWhere('google_id', $googleUser->getId())
            ->first();

        if (! $user && $this->isSuperAdminEmail($googleEmail)) {
            Role::findOrCreate('admin', 'web');

            $user = User::create([
                'name' => $googleUser->getName() ?: 'Super Admin',
                'email' => $googleEmail,
                'password' => Hash::make(Str::random(40)),
                'locale' => 'da',
                'is_active' => true,
                'email_verified_at' => now(),
                'google_id' => $googleUser->getId(),
            ]);

            $user->assignRole('admin');
        }

        if (! $user) {
            return redirect()->route('login')->with('status', 'Din email er ikke inviteret endnu.');
        }

        if (! $user->is_active) {
            return redirect()->route('login')->with('status', 'Din bruger er deaktiveret.');
        }

        $user->forceFill([
            'name' => $googleUser->getName() ?? $user->name,
            'google_id' => $googleUser->getId(),
            'email' => $googleEmail,
            'email_verified_at' => now(),
        ])->save();

        Auth::login($user, true);

        request()->session()->regenerate();

        if (! $user->password) {
            return redirect()->route('password.setup.edit');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function isSuperAdminEmail(string $email): bool
    {
        $configured = Str::lower(trim((string) env('SUPER_ADMIN_EMAIL', '')));

        return $configured !== '' && $email === $configured;
    }
}
