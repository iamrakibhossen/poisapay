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
window.Alpine = Alpine;
Alpine.start();
