/* =============================================================================
   PoishaPay Landing — isolated entry (ES module). Loaded ONLY by landing layouts,
   so it ships its own Alpine instance and never touches the app's frontend.js.
   All behaviour is encapsulated in the imported modules; only Alpine is exposed
   on window (its required convention), under an otherwise-clean `window.Landing`.
   ============================================================================= */
import Alpine from 'alpinejs';
import { LandingAnimation } from './landing-animation.js';
import { LandingConverter } from './landing-converter.js';

// 3D pointer-tilt for the hero card (Alpine component; was `ppTilt`).
Alpine.data('lpTilt', () => ({
    rx: 0,
    ry: 0,
    move(e) {
        const r = e.currentTarget.getBoundingClientRect();
        const px = (e.clientX - r.left) / r.width - 0.5;
        const py = (e.clientY - r.top) / r.height - 0.5;
        this.ry = px * 16;
        this.rx = -py * 16;
    },
    reset() {
        this.rx = 0;
        this.ry = 0;
    },
}));

window.Alpine = Alpine;
Alpine.start();

const boot = () => {
    LandingAnimation.init();
    LandingConverter.init();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

window.Landing = { version: 1 };
