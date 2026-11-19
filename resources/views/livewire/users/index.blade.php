<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="user-search" class="sr-only">Cari pengguna</label>
                <input id="user-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau email..."
                    class="w-64 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"/>
            </div>
            <div>
                <label for="user-role-filter" class="sr-only">Filter role pengguna</label>
                <select id="user-role-filter" wire:model.live="role"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Semua role</option>
                    @foreach ($roles as $roleName)
                        <option value="{{ $roleName }}">{{ $roleName }}</option>
                    @endforeach
                </select>
            </div>

            @if ($search !== '' || $role !== '')
                <button type="button" wire:click="clearFilters"
                    class="text-sm font-medium text-blue-700 hover:text-blue-800">
                    Reset filter
                </button>
            @endif
        </div>

        @can('users.manage')
            <a href="{{ route('users.create') }}" wire:navigate
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Tambah pengguna
            </a>
        @endcan
    </div>

    @error('search')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden" wire:loading.class="opacity-60" wire:target="search,role">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nama</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Login terakhir</th>
                        <th class="px-4 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $user->getRoleNames()->join(', ') ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs {{ $user->is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                @can('users.manage')
                                    @if ($user->id !== auth()->id())
                                        <button type="button" wire:click="toggleActive({{ $user->id }})"
                                            wire:confirm="{{ $user->is_active ? 'Nonaktifkan pengguna ini?' : 'Aktifkan pengguna ini?' }}"
                                            wire:loading.attr="disabled" wire:target="toggleActive({{ $user->id }})"
                                            class="text-sm {{ $user->is_active ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700' }} disabled:cursor-not-allowed disabled:opacity-60">
                                            {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    @endif
                                    <a href="{{ route('users.edit', $user) }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-700">Ubah</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center">
                                @if ($search !== '' || $role !== '')
                                    <p class="text-sm font-medium text-slate-700">Tidak ada pengguna yang cocok.</p>
                                    <p class="mt-1 text-sm text-slate-500">Coba ubah kata kunci atau reset filter.</p>
                                @else
                                    <p class="text-sm font-medium text-slate-700">Belum ada pengguna.</p>
                                    <p class="mt-1 text-sm text-slate-500">Tambahkan akun internal baru untuk mulai berkolaborasi.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $users->links() }}
</div>
