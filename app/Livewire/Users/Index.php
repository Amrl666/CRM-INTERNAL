<?php

namespace App\Livewire\Users;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.sidebar', ['heading' => 'Pengguna'])]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'role');
        $this->resetPage();
    }

    public function toggleActive(int $userId, RecordAuditAction $audit): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);

        if ($user->id === auth()->id()) {
            $this->addError('search', 'Anda tidak dapat menonaktifkan akun sendiri.');

            return;
        }

        if ($user->is_active && $user->hasRole('super_admin')) {
            $activeSuperAdmins = User::query()
                ->where('is_active', true)
                ->role('super_admin')
                ->count();

            if ($activeSuperAdmins <= 1) {
                $this->addError('search', 'Super admin aktif terakhir tidak dapat dinonaktifkan.');

                return;
            }
        }

        try {
            $user->update(['is_active' => ! $user->is_active]);

            $audit->handle(
                user: auth()->id(),
                action: $user->is_active ? 'user.activated' : 'user.deactivated',
                subject: $user,
                after: ['is_active' => $user->is_active],
            );

            $this->dispatch('notify', message: $user->is_active ? 'Pengguna berhasil diaktifkan.' : 'Pengguna berhasil dinonaktifkan.', type: 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('search', 'Status pengguna tidak dapat diubah. Silakan coba lagi.');
        }
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query) {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                });
            })
            ->when($this->role !== '', fn ($query) => $query->role($this->role))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }
}
