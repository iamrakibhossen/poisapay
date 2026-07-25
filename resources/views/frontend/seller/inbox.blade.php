<x-layouts.app :title="__('Inbox')">
    <div class="mt-6 space-y-4"
        x-data="{
            threads: @js($threads),
            active: 0,
            reply: '',
            send() {
                const t = this.reply.trim();
                if (! t) return;
                this.threads[this.active].messages.push({ from: 'seller', body: t, at: 'Just now' });
                this.threads[this.active].unread = false;
                this.reply = '';
                this.$nextTick(() => { const el = this.$refs.thread; if (el) el.scrollTop = el.scrollHeight; });
            },
            open(i) { this.active = i; this.threads[i].unread = false; },
        }">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Inbox') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ __('Messages from your buyers.') }}</p>
        </div>

        <div class="grid h-[72vh] overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-[var(--shadow-card)] lg:grid-cols-[320px_1fr]">
            {{-- Thread list --}}
            <div class="overflow-y-auto border-b border-neutral-100 lg:border-b-0 lg:border-r">
                <template x-for="(t, i) in threads" :key="t.id">
                    <button type="button" x-on:click="open(i)"
                        class="flex w-full items-start gap-3 border-b border-neutral-50 px-4 py-3.5 text-left transition"
                        :class="active === i ? 'bg-brand-50/60' : 'hover:bg-neutral-50'">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700" x-text="t.initials"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-neutral-900" x-text="t.buyer"></p>
                                <span class="shrink-0 text-[11px] text-neutral-400" x-text="t.time"></span>
                            </div>
                            <p class="truncate text-xs text-neutral-500" x-text="t.subject + ' · ' + t.product"></p>
                            <p class="truncate text-xs text-neutral-400" x-text="t.messages[t.messages.length - 1].body"></p>
                        </div>
                        <span x-show="t.unread" x-cloak class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span>
                    </button>
                </template>
            </div>

            {{-- Active thread --}}
            <div class="flex min-h-0 flex-col">
                {{-- Thread header --}}
                <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-3">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700" x-text="threads[active].initials"></span>
                        <div>
                            <p class="text-sm font-semibold text-neutral-900" x-text="threads[active].buyer"></p>
                            <p class="text-xs text-neutral-400"><span x-text="threads[active].subject"></span> · <span x-text="threads[active].product"></span></p>
                        </div>
                    </div>
                    <a href="{{ route('seller.orders') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">{{ __('View order') }}</a>
                </div>

                {{-- Messages --}}
                <div class="flex-1 space-y-3 overflow-y-auto bg-neutral-50/40 px-5 py-4" x-ref="thread">
                    <template x-for="(m, mi) in threads[active].messages" :key="mi">
                        <div class="flex" :class="m.from === 'seller' ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[75%]">
                                <div class="rounded-2xl px-3.5 py-2.5 text-sm"
                                    :class="m.from === 'seller' ? 'bg-brand-500 text-white rounded-br-sm' : 'border border-neutral-200 bg-white text-neutral-800 rounded-bl-sm'"
                                    x-text="m.body"></div>
                                <p class="mt-1 text-[11px] text-neutral-400" :class="m.from === 'seller' && 'text-right'" x-text="m.at"></p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Composer --}}
                <div class="border-t border-neutral-100 p-3">
                    <div class="flex items-end gap-2">
                        <textarea x-model="reply" x-on:keydown.enter.prevent="send()" rows="1" placeholder="{{ __('Write a reply…') }}"
                            class="max-h-32 min-h-[42px] flex-1 resize-none rounded-xl border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500"></textarea>
                        <x-ui.button x-on:click="send()" icon="paper-airplane">{{ __('Send') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
