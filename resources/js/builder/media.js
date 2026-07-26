/*
 * Shop Media Library — the client engine shared by the builder's image picker
 * (modal) and the standalone /shop/media manager. One reactive `media` namespace
 * holds the grid, search/sort, upload state, and the current detail/selection. It
 * never touches the rest of the builder document, so it stays fully isolated.
 *
 * Every image field in the builder writes a plain URL string (produced by this
 * picker) into the block props — so existing pages, templates and stored URLs
 * remain byte-compatible; the picker is just a nicer way to produce that URL.
 */
export function mediaCore(config) {
    const csrf = config.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const ep = config.endpoints || {};

    return {
        media: {
            open: false, // modal visibility (picker mode)
            picker: false, // true → show "Use" + selection; false → manager mode
            multiple: false,
            items: [],
            loading: false,
            nextPage: 1,
            total: 0,
            q: '',
            sort: 'recent', // recent | oldest
            trashed: false, // show soft-deleted (trash view)
            uploading: false,
            uploadPct: 0,
            dragOver: false,
            detail: null,
            selected: [],
            copied: null,
            confirmDelete: null,
            editName: '',
            editAlt: '',
            accept: (config.accept || ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']).map((e) => '.' + e).join(','),
        },
        _mediaOnSelect: null,
        _mediaDebounce: null,
        _mediaReplacing: null,

        mediaInit() {},

        // ── loading / querying ───────────────────────────────────────────────
        mediaReset() {
            this.media.items = [];
            this.media.nextPage = 1;
            this.media.total = 0;
        },
        async mediaLoad(reset = false) {
            if (reset) this.mediaReset();
            if (this.media.nextPage === null || this.media.loading) return;
            this.media.loading = true;
            const params = new URLSearchParams({
                page: this.media.nextPage, q: this.media.q, sort: this.media.sort,
                trashed: this.media.trashed ? 1 : 0,
            });
            try {
                const r = await fetch(`${ep.items}?${params}`, { headers: { Accept: 'application/json' } });
                if (r.ok) {
                    const d = await r.json();
                    this.media.items.push(...d.items);
                    this.media.nextPage = d.nextPage;
                    this.media.total = d.total;
                }
            } finally {
                this.media.loading = false;
            }
        },
        mediaSearch() {
            clearTimeout(this._mediaDebounce);
            this._mediaDebounce = setTimeout(() => this.mediaLoad(true), 300);
        },
        mediaToggleTrash() {
            this.media.trashed = !this.media.trashed;
            this.media.detail = null;
            this.media.selected = [];
            this.mediaLoad(true);
        },
        mediaScroll(e) {
            const el = e.target;
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 320) this.mediaLoad(false);
        },

        // ── upload (drag-drop + multi + progress) ────────────────────────────
        mediaBrowse() {
            if (this.$refs.mediaFile) this.$refs.mediaFile.click();
        },
        mediaOnFile(e) {
            this.mediaUpload(e.target.files);
            e.target.value = '';
        },
        mediaDrop(e) {
            this.media.dragOver = false;
            if (e.dataTransfer && e.dataTransfer.files) this.mediaUpload(e.dataTransfer.files);
        },
        mediaUpload(fileList) {
            const files = Array.from(fileList || []).filter((f) => f.type.startsWith('image/'));
            if (!files.length || this.media.trashed) return;
            const fd = new FormData();
            files.forEach((f) => fd.append('files[]', f));

            this.media.uploading = true;
            this.media.uploadPct = 0;
            const xhr = new XMLHttpRequest();
            xhr.open('POST', ep.upload);
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (ev) => {
                if (ev.lengthComputable) this.media.uploadPct = Math.round((ev.loaded / ev.total) * 100);
            };
            xhr.onload = () => {
                this.media.uploading = false;
                this.media.uploadPct = 0;
                if (xhr.status >= 200 && xhr.status < 300) {
                    const d = JSON.parse(xhr.responseText || '{}');
                    (d.items || []).slice().reverse().forEach((it) => {
                        const idx = this.media.items.findIndex((x) => x.id === it.id);
                        idx >= 0 ? (this.media.items[idx] = it) : this.media.items.unshift(it);
                    });
                    this.mediaPollProcessing(d.items || []);
                }
            };
            xhr.onerror = () => { this.media.uploading = false; };
            xhr.send(fd);
        },
        // Freshly uploaded rasters are "processing" until variants land — refresh
        // them a few times so thumbnails appear without a manual reload.
        mediaPollProcessing(items) {
            const ids = items.filter((i) => i.status === 'processing').map((i) => i.id);
            if (!ids.length) return;
            let tries = 0;
            const tick = async () => {
                tries++;
                try {
                    const r = await fetch(`${ep.items}?page=1&sort=recent`, { headers: { Accept: 'application/json' } });
                    if (r.ok) {
                        (await r.json()).items.forEach((fresh) => {
                            const idx = this.media.items.findIndex((x) => x.id === fresh.id);
                            if (idx >= 0) this.media.items[idx] = fresh;
                            if (this.media.detail && this.media.detail.id === fresh.id) this.media.detail = fresh;
                        });
                    }
                } catch (e) { /* keep trying */ }
                if (this.media.items.some((i) => ids.includes(i.id) && i.status === 'processing') && tries < 6) {
                    setTimeout(tick, 1500);
                }
            };
            setTimeout(tick, 1500);
        },

        // ── selection + detail ───────────────────────────────────────────────
        mediaSelect(item) {
            this.media.detail = item;
            this.media.editName = item.name;
            this.media.editAlt = item.alt || '';
            this.media.confirmDelete = null;
            if (!this.media.picker || item.trashed) return;
            if (this.media.multiple) {
                const i = this.media.selected.indexOf(item.id);
                i >= 0 ? this.media.selected.splice(i, 1) : this.media.selected.push(item.id);
            } else {
                this.media.selected = [item.id];
            }
        },
        mediaIsSelected(id) {
            return this.media.selected.includes(id);
        },
        mediaOrder(id) {
            const i = this.media.selected.indexOf(id);
            return i >= 0 ? i + 1 : null;
        },

        // ── item mutations (rename / replace / delete / restore / copy) ──────
        mediaCopyUrl(item) {
            if (navigator.clipboard) navigator.clipboard.writeText(item.url);
            this.media.copied = item.id;
            setTimeout(() => { if (this.media.copied === item.id) this.media.copied = null; }, 1500);
        },
        async mediaSaveMeta(item) {
            const r = await fetch(`${ep.upload}/${item.id}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ name: this.media.editName, alt: this.media.editAlt }),
            });
            if (r.ok) this.mediaReplaceItem((await r.json()).item);
        },
        mediaReplaceBrowse(item) {
            this._mediaReplacing = item.id;
            if (this.$refs.mediaReplaceFile) this.$refs.mediaReplaceFile.click();
        },
        async mediaOnReplaceFile(e) {
            const file = e.target.files[0];
            e.target.value = '';
            const id = this._mediaReplacing;
            if (!file || !id) return;
            const fd = new FormData();
            fd.append('file', file);
            this.media.uploading = true;
            const r = await fetch(`${ep.upload}/${id}/replace`, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: fd,
            });
            this.media.uploading = false;
            if (r.ok) {
                const item = (await r.json()).item;
                this.mediaReplaceItem(item);
                this.mediaPollProcessing([item]);
            }
        },
        // Trash view → permanent purge; normal view → soft delete (recoverable).
        async mediaDelete(item) {
            const url = this.media.trashed ? `${ep.upload}/${item.id}?force=1` : `${ep.upload}/${item.id}`;
            const r = await fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            if (r.ok) {
                this.media.items = this.media.items.filter((x) => x.id !== item.id);
                this.media.selected = this.media.selected.filter((id) => id !== item.id);
                if (this.media.detail && this.media.detail.id === item.id) this.media.detail = null;
                this.media.confirmDelete = null;
                this.media.total = Math.max(0, this.media.total - 1);
            }
        },
        async mediaRestore(item) {
            const r = await fetch(`${ep.upload}/${item.id}/restore`, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            });
            if (r.ok) {
                this.media.items = this.media.items.filter((x) => x.id !== item.id);
                if (this.media.detail && this.media.detail.id === item.id) this.media.detail = null;
                this.media.total = Math.max(0, this.media.total - 1);
            }
        },
        mediaReplaceItem(item) {
            const idx = this.media.items.findIndex((x) => x.id === item.id);
            if (idx >= 0) this.media.items[idx] = item;
            if (this.media.detail && this.media.detail.id === item.id) this.media.detail = item;
        },
    };
}

/**
 * Builder mixin — the media engine plus the picker glue that writes a chosen URL
 * back into the selected block's props / style. Merged into the pageBuilder Alpine
 * component so `this` also exposes selected/touched/setStyle/etc.
 */
export function mediaMixin(config) {
    return Object.assign(mediaCore(config), {
        openMediaPicker({ multiple = false, onSelect } = {}) {
            this.media.picker = true;
            this.media.multiple = multiple;
            this.media.trashed = false;
            this.media.selected = [];
            this.media.detail = null;
            this._mediaOnSelect = onSelect;
            this.media.open = true;
            this.mediaLoad(true);
        },
        mediaClose() {
            this.media.open = false;
            this._mediaOnSelect = null;
        },
        mediaUse() {
            const urls = this.media.selected
                .map((id) => this.media.items.find((x) => x.id === id))
                .filter(Boolean)
                .map((it) => it.url);
            if (this._mediaOnSelect) this._mediaOnSelect(urls);
            this.mediaClose();
        },
        openMediaManager() {
            this.media.picker = false;
            this.media.open = true;
            if (!this.media.items.length) this.mediaLoad(true);
        },

        // Field helpers (used by builder-field.blade.php + the background control).
        pickImage(key) {
            this.openMediaPicker({
                multiple: false,
                onSelect: (urls) => {
                    if (this.selected && urls[0] != null) {
                        this.snapshot();
                        this.selected.props[key] = urls[0];
                        this.commit({ immediate: true }); // repaint the canvas at once (no debounce)
                    }
                },
            });
        },
        clearImage(key) {
            if (this.selected) { this.snapshot(); this.selected.props[key] = ''; this.commit({ immediate: true }); }
        },
        pickRepeaterImage(fieldKey, i, subKey) {
            this.openMediaPicker({
                multiple: false,
                onSelect: (urls) => {
                    const rows = this.repeaterRows(fieldKey);
                    if (rows[i] && urls[0] != null) {
                        this.snapshot();
                        rows[i][subKey] = urls[0];
                        this.commit({ immediate: true });
                    }
                },
            });
        },
        // "Choose images" → append a row per selected image to an image repeater.
        pickImagesInto(field) {
            const imgField = (field.item || []).find((f) => f.type === 'image');
            if (!imgField) return;
            this.openMediaPicker({
                multiple: true,
                onSelect: (urls) => {
                    const n = this.selected;
                    if (!n || !urls.length) return;
                    this.snapshot();
                    const arr = (n.props[field.key] = n.props[field.key] || []);
                    urls.forEach((u) => {
                        const row = {};
                        (field.item || []).forEach((f) => (row[f.key] = f.default ?? ''));
                        row[imgField.key] = u;
                        arr.push(row);
                    });
                    this.commit({ immediate: true });
                },
            });
        },
        pickStyleImage(styleKey) {
            this.openMediaPicker({
                multiple: false,
                onSelect: (urls) => this.setStyle(styleKey, urls[0] || ''),
            });
        },
    });
}

/** Standalone /shop/media manager component. */
export function mediaManager(config) {
    return Object.assign(mediaCore(config), {
        init() {
            this.media.picker = false;
            this.media.open = true;
            this.mediaLoad(true);
        },
    });
}
