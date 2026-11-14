@props(['name' => 'date', 'value' => '', 'placeholder' => 'Pilih tanggal'])

<div x-data="{
    open: false,
    selected: '{{ $value }}',
    month: new Date().getMonth(),
    year: new Date().getFullYear(),
    days: [],
    init() { this.generateDays(); },
    generateDays() {
        const firstDay = new Date(this.year, this.month, 1);
        const lastDay = new Date(this.year, this.month + 1, 0);
        const startDay = firstDay.getDay();
        const totalDays = lastDay.getDate();
        this.days = [];
        for (let i = 0; i < startDay; i++) this.days.push(null);
        for (let d = 1; d <= totalDays; d++) this.days.push(d);
    },
    prevMonth() {
        if (this.month === 0) { this.month = 11; this.year--; } else { this.month--; }
        this.generateDays();
    },
    nextMonth() {
        if (this.month === 11) { this.month = 0; this.year++; } else { this.month++; }
        this.generateDays();
    },
    selectDay(day) {
        if (!day) return;
        const m = String(this.month + 1).padStart(2, '0');
        const d = String(day).padStart(2, '0');
        this.selected = `${this.year}-${m}-${d}`;
        this.open = false;
    },
    monthName() {
        return new Date(this.year, this.month).toLocaleString('id-ID', { month: 'long', year: 'numeric' });
    }
}" class="relative">
    <input type="hidden" name="{{ $name }}" :value="selected" />

    <button
        @click="open = !open"
        @click.outside="open = false"
        type="button"
        class="flex w-full items-center justify-between rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
    >
        <span x-text="selected || '{{ $placeholder }}'" :class="selected ? '' : 'text-slate-400'"></span>
        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        x-cloak
        class="absolute z-50 mt-2 w-72 rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
        style="display: none;"
    >
        <!-- Month navigation -->
        <div class="mb-3 flex items-center justify-between">
            <button @click="prevMonth()" type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </button>
            <span class="text-sm font-medium text-slate-700" x-text="monthName()"></span>
            <button @click="nextMonth()" type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </button>
        </div>

        <!-- Day headers -->
        <div class="mb-1 grid grid-cols-7 gap-1 text-center text-xs font-medium text-slate-500">
            <span>Min</span><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span>
        </div>

        <!-- Days -->
        <div class="grid grid-cols-7 gap-1">
            <template x-for="(day, index) in days" :key="index">
                <button
                    @click="selectDay(day)"
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-md text-xs"
                    :class="day ? 'text-slate-700 hover:bg-blue-50 hover:text-blue-700' : 'cursor-default'"
                    :disabled="!day"
                    x-text="day || ''"
                ></button>
            </template>
        </div>
    </div>
</div>
