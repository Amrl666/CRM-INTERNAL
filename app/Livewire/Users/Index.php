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

    public function toggleActive(int $userId, RecordAuditAction $audit): void
    {
        $this->authorize('update', User::class);

        $user = User::findOrFail($userId);

        if ($user->id === auth()->id()) {
            $this->addError('search', 'Anda tidak dapat menonaktifkan akun sendiri.');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);

        $audit->handle(
            user: auth()->id(),
            action: $user->is_active ? 'user.activated' : 'user.deactivated',
            subject: $user,
            after: ['is_active' => $user->is_active],
        );

        $this->dispatch('user-updated');
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
