import './bootstrap';
import './echo';
import './chart';

import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';

/*
 * Entry for the authenticated user frontend (traditional server-rendered Blade).
 * Livewire is NOT loaded here — Alpine runs standalone and is used only for light
 * UI (tabs, modals, show/hide, clipboard, countdowns). All data is server-rendered
 * from controllers and all mutations are plain form POSTs; there is no JSON API.
 */
Alpine.plugin(persist);

/*
 * P2P order chat (Phase 2). Live delivery over the private `p2p.order.{id}`
 * channel with typing whispers; falls back to the JSON history endpoint. The
 * Phase 5 order page mounts it with x-data="p2pChat(orderId, meId)".
 */
Alpine.data('p2pChat', (orderId, meId) => ({
    messages: [],
    typing: false,
    _typingTimer: null,

    init() {
        fetch(`/p2p/orders/${orderId}/messages`, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : { data: [] }))
            .then((d) => {
                this.messages = d.data || [];
                this.$nextTick(() => this.scroll());
            })
            .catch(() => {});

        if (window.Echo) {
            window.Echo.private(`p2p.order.${orderId}`)
                .listen('.p2p.message', (m) => {
                    this.messages.push(m);
                    this.$nextTick(() => this.scroll());
                })
                .listenForWhisper('typing', (e) => {
                    if (e && e.id !== meId) {
                        this.typing = true;
                        clearTimeout(this._typingTimer);
                        this._typingTimer = setTimeout(() => (this.typing = false), 2500);
                    }
                });
        }
    },

    whisperTyping() {
        if (window.Echo) {
            window.Echo.private(`p2p.order.${orderId}`).whisper('typing', { id: meId });
        }
    },

    scroll() {
        const el = this.$refs.thread;
        if (el) el.scrollTop = el.scrollHeight;
    },
}));

window.Alpine = Alpine;
Alpine.start();
