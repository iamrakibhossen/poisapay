import './bootstrap';
import './echo';
import './chart';

import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';

/*
 * Entry for the operator console (traditional server-rendered Blade). Livewire is
 * no longer used in admin — Alpine runs standalone for light UI (modals, tabs,
 * dropdowns, charts). All data is server-rendered and all mutations are form POSTs.
 */
Alpine.plugin(persist);
window.Alpine = Alpine;
Alpine.start();
