<?php

namespace App\Livewire\Users;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Identity\Actions\AssignRoleAction;
use App\Domain\Identity\Actions\CreateUserAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.sidebar', ['heading' => 'Form Pengguna'])]
class Form extends Component
{
    public ?string $userId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = 'sales_agent';

    public bool $is_active = true;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(?User $user = null): void
    {
        if ($user !== null && $user->exists) {
            $this->authorize('update', $user);
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->phone = (string) $user->phone;
            $this->is_active = $user->is_active;
            $this->role = $user->getRoleNames()->first() ?? 'viewer';
        } else {
            $this->authorize('create', User::class);
        }
    }

    public function save(
        CreateUserAction $createUser,
        AssignRoleAction $assignRole,
        RecordAuditAction $audit,
    ): void {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:320', Rule::unique('users', 'email')->ignore($this->userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(Role::pluck('name')->all())],
            'is_active' => ['boolean'],
            'password' => [$this->userId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->userId !== null ? User::findOrFail($this->userId) : null;

        if ($user !== null) {
            $this->authorize('update', $user);
        }

        if ($user !== null && $user->id === auth()->id() && ! $this->is_active) {
            $this->addError('is_active', 'Anda tidak dapat menonaktifkan akun sendiri.');

            return;
        }

        try {
            if ($user === null) {
                $user = $createUser->handle([
                    'name' => trim($this->name),
                    'email' => $this->email,
                    'password' => $this->password,
                    'role' => $this->role,
                    'phone' => $this->phone !== '' ? trim($this->phone) : null,
                    'is_active' => $this->is_active,
                ], auth()->id());

                session()->flash('status', 'Pengguna berhasil dibuat.');

                $this->redirectRoute('users.edit', ['user' => $user->id], navigate: false);

                return;
            }

            DB::transaction(function () use ($user, $assignRole, $audit): void {
                $before = $user->only(['name', 'email', 'phone', 'is_active']);

                $user->update([
                    'name' => trim($this->name),
                    'email' => mb_strtolower($this->email),
                    'phone' => $this->phone !== '' ? trim($this->phone) : null,
                    'is_active' => $this->is_active,
                ]);

                if ($this->password !== '') {
                    $user->update(['password' => $this->password]);
                }

                if ($this->role !== $user->getRoleNames()->first()) {
                    $assignRole->handle($user, $this->role, auth()->id());
                }

                $audit->handle(
                    user: auth()->id(),
                    action: 'user.updated',
                    subject: $user,
                    before: $before,
                    after: $user->only(['name', 'email', 'phone', 'is_active']),
                );
            });

            $this->dispatch('notify', message: 'Pengguna berhasil diperbarui.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('form', 'Pengguna tidak dapat disimpan. Silakan periksa kembali data dan coba lagi.');
        }
    }

    public function render()
    {
        return view('livewire.users.form', [
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }
}
