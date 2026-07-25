import Sortable from 'sortablejs';

/*
 * The visual page builder — a single Alpine component that owns the block-tree
 * document as its one reactive source of truth. Structural/style/prop edits mutate
 * the tree, push an undo snapshot, debounce-autosave to the server, and debounce-
 * refresh the iframe preview (which is the REAL server render, so editor === live).
 *
 * Registered as Alpine.data('pageBuilder', …) and mounted with x-data="pageBuilder(config)".
 */
export default function pageBuilder(config) {
    return {
        // ── server-provided ──────────────────────────────────────────────────
        schemas: config.schemas,
        palette: config.palette,
        endpoints: config.endpoints,
        csrf: config.csrf,
        products: config.products || {},

        // ── document + editor state ──────────────────────────────────────────
        doc: config.document,
        name: config.name,
        productId: config.productId,
        selectedId: null,
        device: 'desktop',
        leftTab: 'blocks', // blocks | layers | theme
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
        _touchTimer: null,
        _sortables: [],

        init() {
            this.refreshPreview();
            // Global keyboard shortcuts (undo/redo/duplicate/delete).
            window.addEventListener('keydown', (e) => this.onKey(e));
            this.$watch('selectedId', () => this.highlight());
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
        isContainer(type) {
            return !!(this.schemas[type] && this.schemas[type].isContainer);
        },
        newId() {
            return 'b_' + Math.random().toString(36).slice(2, 12);
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

        // ── history / commit ─────────────────────────────────────────────────
        snapshot() {
            this.past.push(this.clone(this.doc));
            if (this.past.length > 60) this.past.shift();
            this.future = [];
        },
        // `immediate` refreshes the canvas right away (structural edits: add/delete/
        // move) so they feel instant; text edits stay debounced + coalesced.
        commit({ preview = true, immediate = false } = {}) {
            this.dirty = true;
            this.scheduleSave();
            if (preview) immediate ? this.refreshPreview() : this.schedulePreview();
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
            if (this.selectedId && !this.find(this.selectedId)) this.selectedId = null;
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
            this.selectedId = node.id;
            this.leftTab = 'layers';
            this.commit({ immediate: true });
            this.$nextTick(() => this.mountSortables());
        },
        removeSelected() {
            if (!this.selectedId) return;
            this.snapshot();
            const id = this.selectedId;
            // Optimistically drop the node from the canvas so delete feels instant,
            // then reconcile with the authoritative server render.
            const el = this.$refs.canvas && this.$refs.canvas.querySelector('#' + CSS.escape(id));
            if (el) el.remove();
            this.walk(this.doc.root.children, (n, arr, i) => {
                if (n.id === id) { arr.splice(i, 1); return true; }
            });
            this.selectedId = null;
            this.commit({ immediate: true });
            this.$nextTick(() => this.mountSortables());
        },
        duplicateSelected() {
            if (!this.selectedId) return;
            this.snapshot();
            const id = this.selectedId;
            const reid = (n) => { n.id = this.newId(); (n.children || []).forEach(reid); };
            this.walk(this.doc.root.children, (n, arr, i) => {
                if (n.id === id) {
                    const copy = this.clone(n); reid(copy);
                    arr.splice(i + 1, 0, copy);
                    this.selectedId = copy.id;
                    return true;
                }
            });
            this.commit({ immediate: true });
            this.$nextTick(() => this.mountSortables());
        },
        toggleVisibility(device) {
            const n = this.selected; if (!n) return;
            this.snapshot();
            n.visibility[device] = !n.visibility[device];
            this.commit({ immediate: true });
        },

        // Property-panel bindings mutate props/style directly; call this after.
        touched({ preview = true } = {}) {
            // Coalesce rapid text edits into a single history entry.
            if (!this._touchTimer) {
                this.snapshot();
                this._touchTimer = setTimeout(() => (this._touchTimer = null), 600);
            }
            this.commit({ preview });
        },
        styleValue(bp, key) {
            const n = this.selected; if (!n) return '';
            return (n.style[bp] || {})[key] ?? '';
        },
        setStyle(key, value) {
            const n = this.selected; if (!n) return;
            (n.style[this.device] = n.style[this.device] || {})[key] = value;
            this.touched();
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
        schedulePreview() {
            clearTimeout(this._previewTimer);
            this._previewTimer = setTimeout(() => this.refreshPreview(), 200);
        },
        async refreshPreview() {
            clearTimeout(this._previewTimer);
            // Only the newest request may paint — a slow earlier response must never
            // overwrite a later state (e.g. re-adding a block you just deleted).
            const seq = ++this._previewSeq;
            const res = await fetch(this.endpoints.preview, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                body: JSON.stringify({ document: this.doc }),
            });
            if (!res.ok || seq !== this._previewSeq) return;
            const { html, css } = await res.json();
            if (seq !== this._previewSeq) return;
            const host = this.$refs.canvas;
            const scroller = this.$refs.scroller;
            if (!host) return;

            // Preserve the reader's place + selection across the in-place patch.
            const keepScroll = scroller ? scroller.scrollTop : 0;
            if (this.$refs.previewStyle) this.$refs.previewStyle.textContent = css;
            host.innerHTML = html;
            // Initialise any Alpine inside the rendered blocks (FAQ, countdown…).
            if (window.Alpine && window.Alpine.initTree) window.Alpine.initTree(host);
            if (scroller) scroller.scrollTop = keepScroll;
            this.highlight();
        },
        // Re-apply the selected/hover outline to the freshly-rendered nodes.
        highlight() {
            const host = this.$refs.canvas;
            if (!host) return;
            host.querySelectorAll('[data-pp-selected]').forEach((n) => n.removeAttribute('data-pp-selected'));
            if (this.selectedId) {
                const el = host.querySelector('#' + CSS.escape(this.selectedId));
                if (el) el.setAttribute('data-pp-selected', '');
            }
        },
        blockAt(el) {
            const host = this.$refs.canvas;
            while (el && el !== host) { if (el.id && /^b_/.test(el.id)) return el; el = el.parentElement; }
            return null;
        },
        onCanvasClick(e) {
            const b = this.blockAt(e.target);
            if (b) { e.preventDefault(); e.stopPropagation(); this.selectedId = b.id; this.leftTab = 'layers'; }
        },
        onCanvasHover(e) {
            const b = this.blockAt(e.target);
            this.clearHover();
            if (b && b.id !== this.selectedId) b.setAttribute('data-pp-hover', '');
        },
        clearHover() {
            const host = this.$refs.canvas;
            if (host) host.querySelectorAll('[data-pp-hover]').forEach((n) => n.removeAttribute('data-pp-hover'));
        },
        // Select from the layers tree → scroll the canvas to the block.
        select(id) {
            this.selectedId = id;
            const host = this.$refs.canvas;
            const el = host && host.querySelector('#' + CSS.escape(id));
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
            if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? this.redo() : this.undo(); return; }
            if (typing) return;
            if (mod && e.key.toLowerCase() === 'd') { e.preventDefault(); this.duplicateSelected(); }
            if ((e.key === 'Backspace' || e.key === 'Delete') && this.selectedId) { e.preventDefault(); this.removeSelected(); }
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
