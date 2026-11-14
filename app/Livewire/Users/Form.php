<?php

namespace App\Livewire\Users;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Identity\Actions\AssignRoleAction;
use App\Domain\Identity\Actions\CreateUserAction;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.sidebar', ['heading' => 'Form Pengguna'])]
class Form extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = 'sales_agent';

    public bool $is_active = true;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(?User $user = null): void
    {
        if ($user !== null) {
            $this->authorize('update', $user);
            $this->user = $user;
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
            'email' => ['required', 'string', 'email', 'max:320', Rule::unique('users', 'email')->ignore($this->user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(Role::pluck('name')->all())],
            'is_active' => ['boolean'],
            'password' => [$this->user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($this->user === null) {
            $user = $createUser->handle([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'role' => $this->role,
                'phone' => $this->phone !== '' ? $this->phone : null,
                'is_active' => $this->is_active,
            ], auth()->id());

            session()->flash('status', 'Pengguna berhasil dibuat.');

            $this->redirectRoute('users.edit', ['user' => $user->id], navigate: false);

            return;
        }

        $before = $this->user->only(['name', 'email', 'phone', 'is_active']);

        $this->user->update([
            'name' => $this->name,
            'email' => mb_strtolower($this->email),
            'phone' => $this->phone !== '' ? $this->phone : null,
            'is_active' => $this->is_active,
        ]);

        if ($this->password !== '') {
            $this->user->update(['password' => $this->password]);
        }

        if ($this->role !== $this->user->getRoleNames()->first()) {
            $assignRole->handle($this->user, $this->role, auth()->id());
        }

        $audit->handle(
            user: auth()->id(),
            action: 'user.updated',
            subject: $this->user,
            before: $before,
            after: $this->user->only(['name', 'email', 'phone', 'is_active']),
        );

        session()->flash('status', 'Pengguna berhasil diperbarui.');
    }

    public function render()
    {
        return view('livewire.users.form', [
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }
}
