{{-- Shared Media Library UI — used by the builder picker modal and the standalone
     /shop/media manager. Drives the `media` Alpine namespace (resources/js/builder/media.js).
     `media.picker` toggles selection + the "Use" footer; otherwise it's a manager. --}}
<div class="flex h-full min-h-0 flex-col">
    <div class="flex min-h-0 flex-1">
        {{-- Main column: toolbar + dropzone grid --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <div class="flex flex-wrap items-center gap-2 border-b border-neutral-100 p-3">
                <div class="relative min-w-40 flex-1">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                    <input x-model="media.q" @input="mediaSearch()" placeholder="{{ __('Search by name…') }}" class="w-full rounded-lg border border-neutral-200 py-1.5 pl-8 pr-3 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                </div>
                <select x-model="media.sort" @change="mediaLoad(true)" class="rounded-lg border border-neutral-200 px-2 py-1.5 text-xs text-neutral-600 focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                    <option value="recent">{{ __('Newest first') }}</option>
                    <option value="oldest">{{ __('Oldest first') }}</option>
                </select>
                <button type="button" @click="mediaToggleTrash()" :class="media.trashed ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50'" class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold">
                    <x-heroicon-o-trash class="h-4 w-4" /> <span x-text="media.trashed ? '{{ __('Exit trash') }}' : '{{ __('Trash') }}'"></span>
                </button>
                <button type="button" @click="mediaBrowse()" x-show="!media.trashed" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-600">
                    <x-heroicon-o-arrow-up-tray class="h-4 w-4" /> {{ __('Upload') }}
                </button>
            </div>

            {{-- Upload progress --}}
            <div x-show="media.uploading" x-cloak class="border-b border-neutral-100 px-3 py-2">
                <div class="flex items-center gap-2 text-xs text-neutral-500">
                    <span class="whitespace-nowrap">{{ __('Uploading…') }}</span>
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-neutral-100">
                        <div class="h-full rounded-full bg-brand-500 transition-all" :style="`width:${media.uploadPct}%`"></div>
                    </div>
                    <span x-text="media.uploadPct + '%'"></span>
                </div>
            </div>

            {{-- Grid (drop target + infinite scroll) --}}
            <div class="relative min-h-0 flex-1 overflow-y-auto p-3"
                @scroll="mediaScroll($event)"
                @dragover.prevent="!media.trashed && (media.dragOver = true)" @dragleave.prevent="media.dragOver = false" @drop.prevent="mediaDrop($event)">

                <div x-show="media.dragOver" x-cloak class="pointer-events-none absolute inset-2 z-10 grid place-items-center rounded-xl border-2 border-dashed border-brand-400 bg-brand-50/80 text-sm font-semibold text-brand-600">
                    {{ __('Drop images to upload') }}
                </div>

                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5">
                    <template x-for="item in media.items" :key="item.id">
                        <button type="button" @click="mediaSelect(item)"
                            :class="mediaIsSelected(item.id) ? 'border-brand-500 ring-2 ring-brand-500/30' : (media.detail && media.detail.id === item.id ? 'border-brand-300' : 'border-neutral-200 hover:border-neutral-300')"
                            class="group relative aspect-square overflow-hidden rounded-lg border bg-neutral-50 text-left transition">
                            <img :src="item.thumb" :alt="item.alt || item.name" loading="lazy" class="h-full w-full object-cover" />
                            {{-- Original previews instantly; a small badge shows variants are still optimising. --}}
                            <span x-show="item.status === 'processing'" x-cloak class="absolute left-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-white/90 shadow-sm" title="{{ __('Optimising…') }}">
                                <span class="h-3 w-3 animate-spin rounded-full border-2 border-neutral-300 border-t-brand-500"></span>
                            </span>
                            <span x-show="media.picker && media.multiple && mediaOrder(item.id)" x-cloak class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-brand-500 text-[10px] font-bold text-white" x-text="mediaOrder(item.id)"></span>
                            <span x-show="media.picker && !media.multiple && mediaIsSelected(item.id)" x-cloak class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full bg-brand-500 text-white"><x-heroicon-s-check class="h-3 w-3" /></span>
                            <span class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/60 to-transparent px-1.5 py-1 text-[10px] font-medium text-white opacity-0 transition group-hover:opacity-100" x-text="item.name"></span>
                        </button>
                    </template>
                </div>

                <div x-show="media.loading" class="grid place-items-center py-6"><span class="h-5 w-5 animate-spin rounded-full border-2 border-neutral-200 border-t-brand-500"></span></div>
                <div x-show="!media.loading && !media.items.length" x-cloak class="grid place-items-center gap-2 py-16 text-center text-neutral-400">
                    <x-heroicon-o-photo class="h-10 w-10" />
                    <p class="text-sm" x-text="media.trashed ? '{{ __('Trash is empty') }}' : '{{ __('No media yet') }}'"></p>
                    <button type="button" x-show="!media.trashed" @click="mediaBrowse()" class="text-xs font-semibold text-brand-600 hover:text-brand-700">{{ __('Upload your first image') }}</button>
                </div>
            </div>
        </div>

        {{-- Detail panel --}}
        <aside x-show="media.detail" x-cloak class="hidden w-64 shrink-0 flex-col border-l border-neutral-200 bg-white md:flex">
            <template x-if="media.detail">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="border-b border-neutral-100 p-3">
                        <div class="grid aspect-video place-items-center overflow-hidden rounded-lg border border-neutral-100 bg-neutral-50">
                            <img :src="media.detail.thumb" :alt="media.detail.name" class="max-h-full max-w-full object-contain" />
                        </div>
                    </div>
                    <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3 text-xs">
                        {{-- Trash view: restore / purge only --}}
                        <template x-if="media.detail.trashed">
                            <div class="space-y-2">
                                <p class="truncate font-medium text-neutral-700" x-text="media.detail.name"></p>
                                <button type="button" @click="mediaRestore(media.detail)" class="flex w-full items-center justify-center gap-1.5 rounded-md bg-brand-500 py-1.5 font-medium text-white hover:bg-brand-600">
                                    <x-heroicon-o-arrow-uturn-left class="h-3.5 w-3.5" /> {{ __('Restore') }}
                                </button>
                                <template x-if="media.confirmDelete === media.detail.id">
                                    <div class="rounded-md border border-rose-200 bg-rose-50 p-2 text-center">
                                        <p class="mb-2 text-rose-700">{{ __('Delete permanently?') }}</p>
                                        <div class="flex gap-1.5">
                                            <button type="button" @click="media.confirmDelete = null" class="flex-1 rounded-md border border-neutral-200 bg-white py-1 font-medium">{{ __('Cancel') }}</button>
                                            <button type="button" @click="mediaDelete(media.detail)" class="flex-1 rounded-md bg-rose-500 py-1 font-medium text-white hover:bg-rose-600">{{ __('Delete') }}</button>
                                        </div>
                                    </div>
                                </template>
                                <button type="button" x-show="media.confirmDelete !== media.detail.id" @click="media.confirmDelete = media.detail.id" class="flex w-full items-center justify-center gap-1.5 rounded-md border border-rose-200 py-1.5 font-medium text-rose-600 hover:bg-rose-50">
                                    <x-heroicon-o-trash class="h-3.5 w-3.5" /> {{ __('Delete forever') }}
                                </button>
                            </div>
                        </template>

                        {{-- Normal view: edit + copy + replace + delete --}}
                        <template x-if="!media.detail.trashed">
                            <div class="space-y-3">
                                <div>
                                    <label class="mb-1 block font-medium text-neutral-500">{{ __('Name') }}</label>
                                    <input x-model="media.editName" @change="mediaSaveMeta(media.detail)" class="w-full rounded-md border border-neutral-200 px-2 py-1.5 focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                </div>
                                <div>
                                    <label class="mb-1 block font-medium text-neutral-500">{{ __('Alt text') }}</label>
                                    <input x-model="media.editAlt" @change="mediaSaveMeta(media.detail)" placeholder="{{ __('Describe the image') }}" class="w-full rounded-md border border-neutral-200 px-2 py-1.5 focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                </div>
                                <dl class="space-y-1 rounded-lg bg-neutral-50 p-2.5 text-neutral-500">
                                    <div class="flex justify-between"><dt>{{ __('Dimensions') }}</dt><dd class="font-medium text-neutral-700"><span x-text="media.detail.width ? media.detail.width + '×' + media.detail.height : '—'"></span></dd></div>
                                    <div class="flex justify-between"><dt>{{ __('Size') }}</dt><dd class="font-medium text-neutral-700" x-text="media.detail.sizeHuman"></dd></div>
                                    <div class="flex justify-between"><dt>{{ __('Type') }}</dt><dd class="font-medium uppercase text-neutral-700" x-text="media.detail.ext"></dd></div>
                                    <div class="flex justify-between"><dt>{{ __('Uploaded') }}</dt><dd class="font-medium text-neutral-700" x-text="media.detail.createdHuman"></dd></div>
                                </dl>
                                <button type="button" @click="mediaCopyUrl(media.detail)" class="flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 py-1.5 font-medium text-neutral-600 hover:bg-neutral-50">
                                    <x-heroicon-o-link class="h-3.5 w-3.5" /> <span x-text="media.copied === media.detail.id ? '{{ __('Copied!') }}' : '{{ __('Copy URL') }}'"></span>
                                </button>
                                <button type="button" @click="mediaReplaceBrowse(media.detail)" class="flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 py-1.5 font-medium text-neutral-600 hover:bg-neutral-50">
                                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5" /> {{ __('Replace') }}
                                </button>
                                <template x-if="media.confirmDelete === media.detail.id">
                                    <div class="rounded-md border border-rose-200 bg-rose-50 p-2 text-center">
                                        <p class="mb-2 text-rose-700">{{ __('Move to trash?') }}</p>
                                        <div class="flex gap-1.5">
                                            <button type="button" @click="media.confirmDelete = null" class="flex-1 rounded-md border border-neutral-200 bg-white py-1 font-medium">{{ __('Cancel') }}</button>
                                            <button type="button" @click="mediaDelete(media.detail)" class="flex-1 rounded-md bg-rose-500 py-1 font-medium text-white hover:bg-rose-600">{{ __('Delete') }}</button>
                                        </div>
                                    </div>
                                </template>
                                <button type="button" x-show="media.confirmDelete !== media.detail.id" @click="media.confirmDelete = media.detail.id" class="flex w-full items-center justify-center gap-1.5 rounded-md border border-rose-200 py-1.5 font-medium text-rose-600 hover:bg-rose-50">
                                    <x-heroicon-o-trash class="h-3.5 w-3.5" /> {{ __('Delete') }}
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </aside>
    </div>

    {{-- Picker footer --}}
    <div x-show="media.picker" x-cloak class="flex items-center justify-between border-t border-neutral-200 bg-white px-4 py-3">
        <p class="text-xs text-neutral-500"><span x-text="media.selected.length"></span> {{ __('selected') }}</p>
        <div class="flex gap-2">
            <button type="button" @click="mediaClose()" class="rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-600 hover:bg-neutral-50">{{ __('Cancel') }}</button>
            <button type="button" @click="mediaUse()" :disabled="!media.selected.length" class="rounded-lg bg-brand-500 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-600 disabled:opacity-40">{{ __('Use image') }}<span x-show="media.multiple && media.selected.length > 1">s</span></button>
        </div>
    </div>

    {{-- Hidden inputs shared by upload + replace --}}
    <input type="file" x-ref="mediaFile" :accept="media.accept" multiple class="hidden" @change="mediaOnFile($event)" />
    <input type="file" x-ref="mediaReplaceFile" :accept="media.accept" class="hidden" @change="mediaOnReplaceFile($event)" />
</div>
