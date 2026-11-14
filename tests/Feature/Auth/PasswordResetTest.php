<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sends a reset link for a known email', function () {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendResetLink')
        ->assertSet('sent', true)
        ->assertHasNoErrors();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'kata-sandi-baru')
        ->set('password_confirmation', 'kata-sandi-baru')
        ->call('resetPassword')
        ->assertHasNoErrors();

    expect(Hash::check('kata-sandi-baru', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create();

    Livewire::test(ResetPassword::class, ['token' => 'token-palsu'])
        ->set('email', $user->email)
        ->set('password', 'kata-sandi-baru')
        ->set('password_confirmation', 'kata-sandi-baru')
        ->call('resetPassword')
        ->assertHasErrors('email');
});
