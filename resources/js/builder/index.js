import Sortable from 'sortablejs';

/*
 * The visual page builder — a single Alpine component that owns the block-tree
 * document as its one reactive source of truth. Structural/style/prop edits mutate
 * the tree, push an undo snapshot, debounce-autosave to the server, and debounce-
 * refresh the inline preview (which is the REAL server render, so editor === live).
 *
 * Registered as Alpine.data('pageBuilder', …) and mounted with x-data="pageBuilder(config)".
 */
export default function pageBuilder(config) {
    return {
        // ── server-provided ──────────────────────────────────────────────────
        schemas: config.schemas,
        palette: config.palette,
        templates: config.templates || [],
        endpoints: config.endpoints,
        csrf: config.csrf,
        products: config.products || {},

        // ── document + editor state ──────────────────────────────────────────
        doc: config.document,
        name: config.name,
        productId: config.productId,
        selectedId: null, // the "primary" selection — the one the inspector edits
        selectedIds: [], // full multi-selection (includes the primary)
        editingId: null, // node currently inline-edited on the canvas
        showShortcuts: false,
        showTemplates: false,
        applyingTemplate: false,
        device: 'desktop',
        leftTab: 'blocks', // blocks | layers | theme | settings
        rightTab: 'content', // content | style | advanced
        addingUnder: null, // parent id the palette will insert into
        past: [],
        future: [],
        dirty: false,
        saving: false,
        savedAt: config.savedAt || null,
        _saveTimer: null,
        _previewTimer: null,
        _previewSeq: 0,
        _scrollTo: null, // id to bring into view after the next preview render (e.g. a just-added block)
        _touchTimer: null,
        _sortables: [],
        _clip: null, // in-memory clipboard (mirrors localStorage for cross-page paste)
        _editEl: null,
        _editKey: null,
        _editRich: false,
        _editCancel: false,

        init() {
            // Deep-link to a specific left panel (e.g. Funnels → "Edit offers" → ?tab=settings).
            const tab = new URLSearchParams(window.location.search).get('tab');
            if (['blocks', 'layers', 'theme', 'settings'].includes(tab)) this.leftTab = tab;
            this.refreshPreview();
            window.addEventListener('keydown', (e) => this.onKey(e));
            this.$watch('selectedIds', () => this.highlight());
            this.$nextTick(() => this.mountSortables());
        },

        // ── tree helpers ─────────────────────────────────────────────────────
        clone(v) {
            return JSON.parse(JSON.stringify(v));
        },
        walk(nodes, fn, parent = null) {
            for (let i = 0; i < nodes.length; i++) {
                if (fn(nodes[i], nodes, i, parent) === true) return true;
                if (nodes[i].children && this.walk(nodes[i].children, fn, nodes[i])) return true;
            }
            return false;
        },
        find(id) {
            let hit = null;
            this.walk(this.doc.root.children, (n) => {
                if (n.id === id) { hit = n; return true; }
            });
            return hit;
        },
        get selected() {
            return this.selectedId ? this.find(this.selectedId) : null;
        },
        get selectedSchema() {
            const n = this.selected;
            return n ? this.schemas[n.type] : null;
        },
        get selectionCount() {
            return this.selectedIds.length;
        },
        isContainer(type) {
            return !!(this.schemas[type] && this.schemas[type].isContainer);
        },
        newId() {
            return 'b_' + Math.random().toString(36).slice(2, 12);
        },
        reid(n) {
            n.id = this.newId();
            (n.children || []).forEach((c) => this.reid(c));
        },
        makeNode(type) {
            const s = this.schemas[type] || { defaults: {} };
            return {
                id: this.newId(),
                type,
                props: this.clone(s.defaults || {}),
                style: { base: {}, tablet: {}, mobile: {} },
                visibility: { desktop: true, tablet: true, mobile: true },
                children: [],
                meta: { name: (s.label || type) },
            };
        },

        // ── selection (single + multi) ───────────────────────────────────────
        selectOne(id) {
            this.selectedId = id || null;
            this.selectedIds = id ? [id] : [];
        },
        selectToggle(id) {
            if (!id) return;
            const i = this.selectedIds.indexOf(id);
            if (i >= 0) {
                this.selectedIds.splice(i, 1);
                this.selectedId = this.selectedIds[this.selectedIds.length - 1] || null;
            } else {
                this.selectedIds.push(id);
                this.selectedId = id;
            }
        },
        clearSelection() {
            this.selectedId = null;
            this.selectedIds = [];
        },
        isSelected(id) {
            return this.selectedIds.includes(id);
        },
        selectAll() {
            this.selectedIds = this.doc.root.children.map((n) => n.id);
            this.selectedId = this.selectedIds[this.selectedIds.length - 1] || null;
        },
        selectionOrPrimary() {
            return this.selectedIds.length ? this.selectedIds.slice() : (this.selectedId ? [this.selectedId] : []);
        },

        // ── history / commit ─────────────────────────────────────────────────
        snapshot() {
            this.past.push(this.clone(this.doc));
            if (this.past.length > 80) this.past.shift();
            this.future = [];
        },
        commit({ preview = true, immediate = false, styleOnly = false } = {}) {
            this.dirty = true;
            this.scheduleSave();
            if (preview) immediate ? this.refreshPreview({ styleOnly }) : this.schedulePreview({ styleOnly });
        },
        undo() {
            if (!this.past.length) return;
            this.future.push(this.clone(this.doc));
            this.doc = this.past.pop();
            this.afterStructureChange();
        },
        redo() {
            if (!this.future.length) return;
            this.past.push(this.clone(this.doc));
            this.doc = this.future.pop();
            this.afterStructureChange();
        },
        afterStructureChange() {
            this.selectedIds = this.selectedIds.filter((id) => this.find(id));
            this.selectedId = this.selectedIds[this.selectedIds.length - 1] || null;
            this.dirty = true;
            this.scheduleSave();
            this.refreshPreview();
            this.$nextTick(() => this.mountSortables());
        },

        // ── mutations ────────────────────────────────────────────────────────
        addBlock(type, parentId = null) {
            this.snapshot();
            const node = this.makeNode(type);
            if (parentId) {
                const p = this.find(parentId);
                if (p) (p.children = p.children || []).push(node);
            } else {
                this.doc.root.children.push(node);
            }
            this.addingUnder = null;
            this.selectOne(node.id);
            this.leftTab = 'layers';
            this._scrollTo = node.id; // reveal the new block once the canvas repaints
            this.commit({ immediate: true });
            this.$nextTick(() => this.mountSortables());
        },
        removeSelected() {
            const ids = this.selectionOrPrimary();
            if (!ids.length) return;
            this.snapshot();
            ids.forEach((id) => {
                const el = this.$refs.canvas && this.$refs.canvas.querySelector('#' + CSS.escape(id));
                if (el) el.remove();
                this.walk(this.doc.root.children, (n, arr, i) => {
                    if (n.id === id) { arr.splice(i, 1); return true; }
                });
            });
            this.clearSelection();
            this.commit({ immediate: true });
            this.$nextTick(() => this.mountSortables());
        },
        duplicateSelected() {
            const ids = this.selectionOrPrimary();
            if (!ids.length) return;
            this.snapshot();
            const copies = [];
            ids.forEach((id) => {
                this.walk(this.doc.root.children, (n, arr, i) => {
                    if (n.id === id) {
                        const copy = this.clone(n); this.reid(copy);
                        arr.splice(i + 1, 0, copy);
                        copies.push(copy.id);
                        return true;
                    }
                });
            });
            this.selectedIds = copies;
            this.selectedId = copies[copies.length - 1] || null;
            this.commit({ immediate: true });
            this.$nextTick(() => this.mountSortables());
        },
        toggleVisibility(device) {
            const n = this.selected; if (!n) return;
            this.snapshot();
            n.visibility[device] = !n.visibility[device];
            this.commit({ immediate: true });
        },

        // ── clipboard (cross-page via localStorage) ──────────────────────────
        copySelection() {
            const nodes = this.selectionOrPrimary().map((id) => this.find(id)).filter(Boolean).map((n) => this.clone(n));
            if (!nodes.length) return;
            this._clip = nodes;
            try { localStorage.setItem('pp:clipboard', JSON.stringify(nodes)); } catch (e) { /* private mode */ }
        },
        cut() {
            if (!this.selectionOrPrimary().length) return;
            this.copySelection();
            this.removeSelected();
        },
        readClip() {
            if (this._clip && this._clip.length) return this._clip;
            try {
                const raw = localStorage.getItem('pp:clipboard');
                const a = raw ? JSON.parse(raw) : null;
                return Array.isArray(a) ? a : [];
            } catch (e) { return []; }
        },
        paste() {
            const nodes = this.readClip();
            if (!nodes.length) return;
            this.snapshot();
            const copies = nodes.map((n) => { const c = this.clone(n); this.reid(c); return c; });
            // Drop after the primary selection within its parent, else append to root.
            let arr = this.doc.root.children;
            let at = arr.length;
            if (this.selectedId) {
                this.walk(this.doc.root.children, (n, a, i) => {
                    if (n.id === this.selectedId) { arr = a; at = i + 1; return true; }
                });
            }
            arr.splice(at, 0, ...copies);
            this.selectedIds = copies.map((c) => c.id);
            this.selectedId = this.selectedIds[this.selectedIds.length - 1];
            this.commit({ immediate: true });
            this.$nextTick(() => this.mountSortables());
        },

        // ── templates ────────────────────────────────────────────────────────
        // Replace the whole draft with a premium starter (server-built + sanitised).
        // Undoable: we snapshot the current doc before swapping.
        async applyTemplate(id) {
            if (this.applyingTemplate) return;
            this.applyingTemplate = true;
            try {
                const res = await fetch(this.endpoints.template, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                    body: JSON.stringify({ template: id }),
                });
                if (res.ok) {
                    const d = await res.json();
                    this.snapshot();
                    this.doc = d.document;
                    this.clearSelection();
                    this.savedAt = d.savedAt;
                    this.dirty = false;
                    this.showTemplates = false;
                    this.leftTab = 'layers';
                    this.refreshPreview();
                    this.$nextTick(() => this.mountSortables());
                }
            } finally {
                this.applyingTemplate = false;
            }
        },

        // Property-panel bindings mutate props/style directly; call this after.
        touched({ preview = true, styleOnly = false } = {}) {
            if (!this._touchTimer) {
                this.snapshot();
                this._touchTimer = setTimeout(() => (this._touchTimer = null), 600);
            }
            this.commit({ preview, styleOnly });
        },
        // The style buckets are base|tablet|mobile; the desktop device maps to `base`.
        get styleBucket() {
            return this.device === 'desktop' ? 'base' : this.device;
        },
        styleValue(bp, key) {
            const n = this.selected; if (!n) return '';
            const b = bp === 'desktop' ? 'base' : bp;
            return (n.style[b] || {})[key] ?? '';
        },
        setStyle(key, value) {
            const n = this.selected; if (!n) return;
            const bucket = (n.style[this.styleBucket] = n.style[this.styleBucket] || {});
            if (value === '' || value === null || value === undefined) delete bucket[key];
            else bucket[key] = value;
            // Style keys compile to scoped CSS only — no DOM change — so refresh the
            // stylesheet in place instead of repainting the canvas (no flicker/jump).
            this.touched({ styleOnly: true });
        },
        baseValue(key) {
            const n = this.selected; if (!n) return '';
            return (n.style.base || {})[key] ?? '';
        },
        setBase(key, value) {
            const n = this.selected; if (!n) return;
            const base = (n.style.base = n.style.base || {});
            if (value === '' || value === null || value === undefined) delete base[key];
            else base[key] = value;
            this.touched({ styleOnly: true });
        },
        metaValue(key) {
            const n = this.selected; if (!n || !n.meta) return '';
            return n.meta[key] ?? '';
        },
        setMeta(key, value) {
            const n = this.selected; if (!n) return;
            n.meta = n.meta || {};
            if (value === '' || value === null || value === undefined) delete n.meta[key];
            else n.meta[key] = value;
            this.touched();
        },
        resetDevice() {
            const n = this.selected; if (!n) return;
            this.snapshot();
            n.style[this.styleBucket] = {};
            this.commit({ immediate: true });
        },

        // ── repeater helpers (for property panel) ────────────────────────────
        repeaterRows(key) {
            const n = this.selected; if (!n) return [];
            return Array.isArray(n.props[key]) ? n.props[key] : [];
        },
        addRow(field) {
            const n = this.selected; if (!n) return;
            this.snapshot();
            const blank = {};
            (field.item || []).forEach((f) => (blank[f.key] = f.default ?? ''));
            (n.props[field.key] = n.props[field.key] || []).push(blank);
            this.commit({ immediate: true });
        },
        removeRow(key, i) {
            const n = this.selected; if (!n) return;
            this.snapshot();
            n.props[key].splice(i, 1);
            this.commit({ immediate: true });
        },

        // ── autosave ─────────────────────────────────────────────────────────
        scheduleSave() {
            clearTimeout(this._saveTimer);
            this._saveTimer = setTimeout(() => this.save(), 900);
        },
        async save() {
            this.saving = true;
            try {
                const res = await fetch(this.endpoints.save, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                    body: JSON.stringify({ document: this.doc, name: this.name, productId: this.productId }),
                });
                if (res.ok) {
                    const d = await res.json();
                    this.savedAt = d.savedAt;
                    this.dirty = false;
                }
            } finally {
                this.saving = false;
            }
        },

        // ── preview (inline SSR — patched in place, no reload) ───────────────
        schedulePreview({ styleOnly = false } = {}) {
            clearTimeout(this._previewTimer);
            // Style-only swaps are cheap + non-destructive → refresh sooner for a snappier feel.
            this._previewTimer = setTimeout(() => this.refreshPreview({ styleOnly }), styleOnly ? 120 : 200);
        },
        async refreshPreview({ styleOnly = false } = {}) {
            clearTimeout(this._previewTimer);
            // Never repaint while the user is typing directly on the canvas — it would
            // rip the contentEditable element out from under them.
            if (this.editingId) return;
            const seq = ++this._previewSeq;
            const res = await fetch(this.endpoints.preview, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                body: JSON.stringify({ document: this.doc }),
            });
            if (!res.ok || seq !== this._previewSeq) return;
            const { html, css } = await res.json();
            if (seq !== this._previewSeq) return;

            // Style-only edit: the DOM structure is unchanged, so swapping the compiled
            // stylesheet is enough — no innerHTML repaint means no flicker, and the
            // selection, scroll position, and any focused input are all left intact.
            if (this.$refs.previewStyle) this.$refs.previewStyle.textContent = css;
            if (styleOnly) return;

            const host = this.$refs.canvas;
            const scroller = this.$refs.scroller;
            if (!host) return;

            const keepScroll = scroller ? scroller.scrollTop : 0;
            host.innerHTML = html;
            if (window.Alpine && window.Alpine.initTree) window.Alpine.initTree(host);
            // Entrance animations are shown in their final state while editing (no
            // scroll-reveal distraction) — the public page runs the real reveal.
            host.querySelectorAll('[data-anim]').forEach((el) => el.classList.add('pp-in'));
            // Preserve scroll across edits, but reveal a freshly-added block (which lands
            // off-screen at the bottom of the page) so the seller sees what they inserted.
            const reveal = this._scrollTo && host.querySelector('#' + CSS.escape(this._scrollTo));
            this._scrollTo = null;
            if (reveal) reveal.scrollIntoView({ behavior: 'smooth', block: 'center' });
            else if (scroller) scroller.scrollTop = keepScroll;
            this.highlight();
        },
        // Re-apply the selection outline to the freshly-rendered nodes.
        highlight() {
            const host = this.$refs.canvas;
            if (!host) return;
            host.querySelectorAll('[data-pp-selected]').forEach((n) => n.removeAttribute('data-pp-selected'));
            this.selectedIds.forEach((id) => {
                const el = host.querySelector('#' + CSS.escape(id));
                if (el) el.setAttribute('data-pp-selected', '');
            });
        },
        blockAt(el) {
            const host = this.$refs.canvas;
            while (el && el !== host) { if (el.id && /^b_/.test(el.id)) return el; el = el.parentElement; }
            return null;
        },
        onCanvasClick(e) {
            if (this.editingId) return;
            const b = this.blockAt(e.target);
            if (b) {
                e.preventDefault(); e.stopPropagation();
                (e.shiftKey || e.metaKey || e.ctrlKey) ? this.selectToggle(b.id) : this.selectOne(b.id);
                this.leftTab = 'layers';
            }
        },
        onCanvasHover(e) {
            if (this.editingId) return;
            const b = this.blockAt(e.target);
            this.clearHover();
            if (b && !this.isSelected(b.id)) b.setAttribute('data-pp-hover', '');
        },
        clearHover() {
            const host = this.$refs.canvas;
            if (host) host.querySelectorAll('[data-pp-hover]').forEach((n) => n.removeAttribute('data-pp-hover'));
        },
        select(id) {
            this.selectOne(id);
            const host = this.$refs.canvas;
            const el = host && host.querySelector('#' + CSS.escape(id));
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
        onLayerClick(id, e) {
            (e.shiftKey || e.metaKey || e.ctrlKey) ? this.selectToggle(id) : this.select(id);
        },

        // ── inline editing (double-click text on the canvas) ─────────────────
        onCanvasDblClick(e) {
            const el = e.target.closest('[data-edit],[data-edit-rich]');
            const host = this.$refs.canvas;
            if (!el || !host || !host.contains(el)) return;
            e.preventDefault();
            this.beginEdit(el);
        },
        beginEdit(el) {
            const blockEl = this.blockAt(el);
            if (!blockEl) return;
            const rich = el.hasAttribute('data-edit-rich');
            this.selectOne(blockEl.id);
            this.editingId = blockEl.id;
            this._editEl = el;
            this._editRich = rich;
            this._editKey = el.getAttribute(rich ? 'data-edit-rich' : 'data-edit');
            this._editCancel = false;

            el.setAttribute('contenteditable', 'true');
            el.classList.add('pp-editing');
            el.focus();
            const range = document.createRange();
            range.selectNodeContents(el);
            const sel = window.getSelection();
            sel.removeAllRanges(); sel.addRange(range);

            el.addEventListener('blur', this._onEditBlur = () => this.endEdit(), { once: true });
            el.addEventListener('keydown', this._onEditKey = (ev) => {
                if (ev.key === 'Escape') { ev.preventDefault(); this._editCancel = true; el.blur(); }
                else if (ev.key === 'Enter' && !ev.shiftKey && !rich) { ev.preventDefault(); el.blur(); }
            });
        },
        endEdit() {
            const el = this._editEl;
            if (!el) return;
            el.removeEventListener('keydown', this._onEditKey);
            el.removeAttribute('contenteditable');
            el.classList.remove('pp-editing');
            const id = this.editingId;
            const key = this._editKey;
            const rich = this._editRich;
            this.editingId = null;
            this._editEl = null;

            const node = this.find(id);
            if (node && !this._editCancel) {
                const val = rich ? el.innerHTML : el.innerText.replace(/\s+$/, '');
                if ((node.props[key] ?? '') !== val) {
                    this.snapshot();
                    node.props[key] = val;
                    this.commit(); // debounced re-render reconciles with the server
                }
            }
            this._editCancel = false;
        },

        // ── drag & drop (SortableJS over the layers tree) ────────────────────
        mountSortables() {
            this._sortables.forEach((s) => s.destroy());
            this._sortables = [];
            this.$root.querySelectorAll('[data-sortable]').forEach((el) => {
                this._sortables.push(new Sortable(el, {
                    group: 'blocks',
                    animation: 150,
                    handle: '[data-drag]',
                    ghostClass: 'opacity-40',
                    onEnd: (evt) => this.onDragEnd(evt),
                }));
            });
        },
        onDragEnd(evt) {
            const fromId = evt.from.getAttribute('data-parent') || 'root';
            const toId = evt.to.getAttribute('data-parent') || 'root';
            if (fromId === toId && evt.oldIndex === evt.newIndex) return;
            this.snapshot();
            const from = fromId === 'root' ? this.doc.root.children : (this.find(fromId)?.children || []);
            const to = toId === 'root' ? this.doc.root.children : (this.find(toId)?.children || []);
            const [moved] = from.splice(evt.oldIndex, 1);
            if (moved) to.splice(evt.newIndex, 0, moved);
            this.commit({ immediate: true });
            this.$nextTick(() => this.mountSortables());
        },

        // ── keyboard ─────────────────────────────────────────────────────────
        onKey(e) {
            const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable;
            const mod = e.metaKey || e.ctrlKey;
            const key = e.key.toLowerCase();

            if (mod && key === 'z') { e.preventDefault(); e.shiftKey ? this.redo() : this.undo(); return; }
            if (typing) return; // inline-edit + panel inputs own their keys

            if (e.key === 'Escape') { this.clearSelection(); this.showShortcuts = false; return; }
            if (e.key === '?') { e.preventDefault(); this.showShortcuts = !this.showShortcuts; return; }
            if (mod && key === 'a') { e.preventDefault(); this.selectAll(); return; }
            if (mod && key === 'd') { e.preventDefault(); this.duplicateSelected(); return; }
            if (mod && key === 'c') { e.preventDefault(); this.copySelection(); return; }
            if (mod && key === 'x') { e.preventDefault(); this.cut(); return; }
            if (mod && key === 'v') { e.preventDefault(); this.paste(); return; }
            if ((e.key === 'Backspace' || e.key === 'Delete') && this.selectionOrPrimary().length) { e.preventDefault(); this.removeSelected(); }
        },

        // ── device switch ────────────────────────────────────────────────────
        setDevice(d) {
            this.device = d;
        },
        get frameWidth() {
            return { desktop: '100%', tablet: '834px', mobile: '390px' }[this.device];
        },
    };
}
