/* Scroll-reveal + number counters for the landing pages. Dependency-free.
   Encapsulated as a namespace object — no globals leak. Ported 1:1 from the
   legacy inline home scripts (`.reveal`→`.lp-reveal`). */
export const LandingAnimation = {
    init() {
        this.reveal();
        this.counters();
    },

    // Reveal `.lp-reveal` elements as they enter the viewport (immediate for
    // reduced-motion / no-IntersectionObserver so content is never hidden).
    reveal() {
        const els = document.querySelectorAll('.lp-reveal');
        if (!els.length) return;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce || !('IntersectionObserver' in window)) {
            els.forEach((el) => el.classList.add('in'));
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
        els.forEach((el) => io.observe(el));
    },

    // Animate `[data-count]` figures the first time they scroll into view.
    counters() {
        const nodes = document.querySelectorAll('[data-count]');
        if (!nodes.length) return;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const fmt = (n, dec) => n.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
        const run = (el) => {
            const target = parseFloat(el.getAttribute('data-count')) || 0;
            const dec = parseInt(el.getAttribute('data-dec') || '0', 10);
            const prefix = el.getAttribute('data-prefix') || '';
            const suffix = el.getAttribute('data-suffix') || '';
            if (reduce) { el.textContent = prefix + fmt(target, dec) + suffix; return; }
            const dur = 1300;
            const start = performance.now();
            const tick = (now) => {
                const t = Math.min((now - start) / dur, 1);
                const eased = 1 - Math.pow(1 - t, 3);
                el.textContent = prefix + fmt(target * eased, dec) + suffix;
                if (t < 1) requestAnimationFrame(tick);
                else el.textContent = prefix + fmt(target, dec) + suffix;
            };
            requestAnimationFrame(tick);
        };
        if (!('IntersectionObserver' in window)) { nodes.forEach(run); return; }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) { run(entry.target); io.unobserve(entry.target); }
            });
        }, { threshold: 0.6 });
        nodes.forEach((n) => io.observe(n));
    },
};
