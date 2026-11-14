<?php

namespace App\Livewire\Auth;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate(RecordAuditAction $audit): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = mb_strtolower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('email', __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($throttleKey),
            ]));

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);
            event(new Failed(User::class, $this->email, 'email'));
            $audit->handle(action: 'auth.login_failed', after: ['email' => mb_strtolower($this->email)]);
            $this->addError('email', __('auth.failed'));

            return;
        }

        $user = auth()->user();

        if (! $user?->isActive()) {
            auth()->logout();
            $audit->handle(user: $user?->id, action: 'auth.login_rejected_inactive', after: ['email' => mb_strtolower($this->email)]);
            $this->addError('email', 'Akun Anda telah dinonaktifkan.');

            return;
        }

        RateLimiter::clear($throttleKey);

        $user->update(['last_login_at' => now(), 'last_login_ip' => request()->ip()]);

        $audit->handle(user: $user->id, action: 'auth.login_success', subject: $user);

        session()->regenerate();

        $this->redirectIntended(route('dashboard', absolute: false));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
