/* Live crypto→fiat converter widget + prices-grid refresh for the landing pages.
   Dependency-free; both consume the public rates feed (route('marketing.rates'))
   exposed via `data-rates-url`. Ported 1:1 from the legacy inline scripts
   (`.pp-converter`→`.lp-converter`, `.pp-price-flash`→`.lp-price-flash`). */
export const LandingConverter = {
    init() {
        this.initConverters();
        this.priceGrid();
        setInterval(() => this.refreshConverters(), 60000);
    },

    initConverters() {
        document.querySelectorAll('.lp-converter:not([data-ready])').forEach((root) => {
            root.setAttribute('data-ready', '1');
            const amt = root.querySelector('.cv-amount');
            const from = root.querySelector('.cv-from');
            const rateEl = root.querySelector('.cv-rate');
            const chargeEl = root.querySelector('.cv-charge');
            const out = root.querySelector('.cv-result');
            const spread = parseFloat(root.getAttribute('data-spread')) || 0;
            if (!amt || !from || !out) return;
            const fmt = (n, d) => n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
            const calc = () => {
                const rate = parseFloat(from.value) || 0;
                const raw = parseFloat((amt.value || '').replace(/[^0-9.]/g, '')) || 0;
                const sym = from.options[from.selectedIndex].text;
                const fiat = root.getAttribute('data-fiat-symbol') || '';
                const gross = raw * rate;
                const charge = gross * spread;
                out.textContent = fmt(gross - charge, 2);
                if (rateEl) rateEl.textContent = '1 ' + sym + ' = ' + fmt(rate, 2) + ' ' + fiat;
                if (chargeEl) chargeEl.textContent = fmt(charge, 2) + ' ' + fiat;
            };
            amt.addEventListener('input', calc);
            from.addEventListener('change', calc);
            amt.addEventListener('blur', () => {
                const r = parseFloat((amt.value || '').replace(/[^0-9.]/g, ''));
                if (!isNaN(r)) amt.value = r.toLocaleString('en-US');
            });
            root._lpRecalc = calc;
            calc();
        });
    },

    // Pull fresh rates and update each converter's <option> values in place.
    refreshConverters() {
        document.querySelectorAll('.lp-converter[data-rates-url]').forEach((root) => {
            fetch(root.getAttribute('data-rates-url'), { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => {
                    if (!data || !data.rates) return;
                    if (data.symbol) root.setAttribute('data-fiat-symbol', data.symbol);
                    const from = root.querySelector('.cv-from');
                    if (!from) return;
                    Array.prototype.forEach.call(from.options, (opt) => {
                        const sym = opt.getAttribute('data-sym') || opt.text;
                        if (data.rates[sym] != null) opt.value = data.rates[sym];
                    });
                    if (typeof root._lpRecalc === 'function') root._lpRecalc();
                })
                .catch(() => {});
        });
    },

    // Live-refresh the `[data-price-grid]` figures (home prices section + prices page).
    priceGrid() {
        const grid = document.querySelector('[data-price-grid]');
        if (!grid || !window.fetch) return;
        const url = grid.getAttribute('data-rates-url');
        let fiat = grid.getAttribute('data-fiat-symbol') || '';
        const fmt = (n) => fiat + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const refresh = () => {
            if (document.hidden) return;
            fetch(url, { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => {
                    if (!data || !data.rates) return;
                    if (data.symbol) fiat = data.symbol;
                    grid.querySelectorAll('[data-price][data-sym]').forEach((el) => {
                        const v = data.rates[el.getAttribute('data-sym')];
                        if (v == null) return;
                        const t = fmt(v);
                        if (el.textContent !== t) {
                            el.textContent = t;
                            el.classList.remove('lp-price-flash');
                            void el.offsetWidth;
                            el.classList.add('lp-price-flash');
                        }
                    });
                })
                .catch(() => {});
        };
        setInterval(refresh, 60000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    },
};
