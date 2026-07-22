{{-- Global toast host. Dispatch from Livewire: $this->dispatch('toast', type: 'success', message: '...') --}}
<div
    x-data="{
        toasts: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, ...detail });
            setTimeout(() => this.remove(id), detail.timeout || 4000);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
        style(type) {
            return {
                success: 'border-emerald-200',
                error:   'border-rose-200',
                warning: 'border-amber-200',
                info:    'border-sky-200',
            }[type] || 'border-neutral-200';
        }
    }"
    x-on:toast.window="add($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-2 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white p-4 shadow-[var(--shadow-pop)]"
            :class="style(toast.type)"
        >
            <div class="flex-1 text-sm text-neutral-800" x-text="toast.message"></div>
            <button type="button" x-on:click="remove(toast.id)" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
    </template>
</div>
