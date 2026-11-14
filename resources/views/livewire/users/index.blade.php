<div class="max-w-6xl mx-auto space-y-6">
    @if (session('status'))
        <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-4">
        <div class="flex gap-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau email..."
                class="w-64 rounded-md border border-slate-300 px-3 py-2 text-sm"/>
            <select wire:model.live="role" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua role</option>
                @foreach ($roles as $roleName)
                    <option value="{{ $roleName }}">{{ $roleName }}</option>
                @endforeach
            </select>
        </div>

        @can('users.manage')
            <a href="{{ route('users.create') }}" wire:navigate
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Tambah pengguna
            </a>
        @endcan
    </div>

    @error('search')
        <div class="text-sm text-red-600">{{ $message }}</div>
    @enderror

    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
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
                        <td class="px-4 py-3 text-slate-600">{{ $user->getRoleNames()->join(', ') }}</td>
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
                                    <button wire:click="toggleActive({{ $user->id }})" wire:loading.attr="disabled"
                                        class="text-sm {{ $user->is_active ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700' }}">
                                        {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                @endif
                                <a href="{{ route('users.edit', $user) }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-700">Ubah</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">Tidak ada pengguna.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
