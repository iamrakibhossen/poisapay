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

/*
 * Card-detail reveal (card-manage page). The ONE async frontend flow in the app,
 * because showing a real PAN/CVV is inherently client-side + PCI-scoped:
 *   - stripe: generate a client nonce via Stripe.js, POST {password, nonce} to mint an
 *     ephemeral key server-side, then mount Stripe Issuing display Elements. The PAN/CVV
 *     render inside Stripe's iframes in the browser — our server never sees them.
 *   - mock:   the simulated provider returns demo PAN/CVV, rendered as text locally.
 * Auto-hides after a short window. Mounted with x-data="cardReveal({...})".
 */
let stripeJsPromise = null;
const loadStripeJs = () => {
    if (window.Stripe) return Promise.resolve();
    if (!stripeJsPromise) {
        stripeJsPromise = new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://js.stripe.com/v3/';
            s.async = true;
            s.onload = () => resolve();
            s.onerror = () => reject(new Error('Failed to load Stripe.js'));
            document.head.appendChild(s);
        });
    }
    return stripeJsPromise;
};

Alpine.data('cardReveal', (config) => ({
    driver: config.driver,
    revealed: false,
    loading: false,
    error: '',
    password: '',
    secondsLeft: 0,
    _stripe: null,
    _elements: [],
    _timer: null,

    async submit() {
        if (this.loading) return;
        this.loading = true;
        this.error = '';

        try {
            let nonce = '';
            if (this.driver === 'stripe') {
                if (!config.pk) throw new Error('Card reveal is not configured.');
                await loadStripeJs();
                this._stripe = this._stripe || window.Stripe(config.pk);
                const res = await this._stripe.createEphemeralKeyNonce({ issuingCard: config.card });
                if (res.error) throw new Error(res.error.message || 'Could not start a secure reveal.');
                nonce = res.nonce;
            }

            const resp = await fetch(config.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ password: this.password, nonce }),
            });

            const data = await resp.json().catch(() => ({}));
            if (!resp.ok) {
                this.error =
                    data?.errors?.password?.[0] ||
                    data?.errors?.card?.[0] ||
                    data?.message ||
                    'Unable to reveal card details.';
                return;
            }

            this.password = '';
            this.revealed = true;

            if (this.driver === 'stripe') {
                this.mountStripe(data.ephemeralKeySecret, nonce);
            } else {
                this.renderMock(data);
            }
            this.startCountdown();
        } catch (e) {
            this.error = e.message || 'Something went wrong. Please try again.';
        } finally {
            this.loading = false;
        }
    },

    mountStripe(ephemeralKeySecret, nonce) {
        const elements = this._stripe.elements();
        const style = { base: { fontSize: '18px', color: '#111827', fontFamily: 'monospace' } };
        const shared = { issuingCard: config.card, nonce, ephemeralKeySecret, style };
        const spec = [
            ['issuingCardNumberDisplay', this.$refs.pan],
            ['issuingCardExpiryDisplay', this.$refs.exp],
            ['issuingCardCvcDisplay', this.$refs.cvc],
        ];
        this._elements = spec.map(([type, el]) => {
            if (el) el.textContent = '';
            const element = elements.create(type, shared);
            element.mount(el);
            return element;
        });
    },

    renderMock(data) {
        if (this.$refs.pan) this.$refs.pan.textContent = (data.pan || '').replace(/(.{4})/g, '$1 ').trim();
        if (this.$refs.exp && data.expMonth) {
            this.$refs.exp.textContent =
                String(data.expMonth).padStart(2, '0') + '/' + String(data.expYear % 100).padStart(2, '0');
        }
        if (this.$refs.cvc) this.$refs.cvc.textContent = data.cvv || '•••';
    },

    startCountdown() {
        this.secondsLeft = 60;
        clearInterval(this._timer);
        this._timer = setInterval(() => {
            this.secondsLeft -= 1;
            if (this.secondsLeft <= 0) this.hide();
        }, 1000);
    },

    hide() {
        clearInterval(this._timer);
        this.secondsLeft = 0;
        this.revealed = false;
        this.error = '';
        this._elements.forEach((el) => {
            try {
                el.unmount();
            } catch (_) {
                /* already unmounted */
            }
        });
        this._elements = [];
        // Restore masked placeholders for the next reveal.
        if (this.$refs.pan) this.$refs.pan.textContent = '•••• •••• •••• ' + (config.last4 || '••••');
        if (this.$refs.cvc) this.$refs.cvc.textContent = '•••';
    },
}));

window.Alpine = Alpine;
Alpine.start();
