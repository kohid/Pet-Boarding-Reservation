/**
 * Emoji Picker (ported from kohid/Community-Business-Directory)
 *
 * Self-contained Fluent Emoji picker. Renders a Facebook-style tabbed UI,
 * fetches emojis from the kohid/icons jsDelivr CDN, and inserts the native
 * Unicode glyph into a target field on click.
 *
 * Markup contract — anywhere in the page:
 *   <button data-emoji-trigger data-emoji-target="#my-textarea">😀</button>
 *
 * Programmatic API:
 *   const picker = new PawEmojiPicker({ onSelect: ({ name, url, glyph }) => {} });
 *   picker.open({ trigger: btnEl, target: textareaEl });
 */
(function (window, document) {
    'use strict';

    // Pinned to one commit, so the list the dashboard loads cannot change underneath it.
    const CDN = 'https://cdn.jsdelivr.net/gh/kohid/icons@fb1b9ae254182d7dc7573114ac1231c78103e66d/fluent-emoji';
    // Skin-tone modifiers, longest names first so "medium-dark" is not read as "dark".
    const TONE_CODES = [
        ['medium-light', '\u{1F3FC}'],
        ['medium-dark', '\u{1F3FE}'],
        ['medium', '\u{1F3FD}'],
        ['light', '\u{1F3FB}'],
        ['dark', '\u{1F3FF}'],
    ];

    /** Text from the emoji list goes into HTML only through this. */
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
    }

    /** The base emoji with a skin-tone modifier after its first character ("👋" + medium → "👋🏽"). */
    function withTone(glyph, toneName) {
        const tone = TONE_CODES.find(t => toneName.endsWith('-' + t[0]));
        if (!tone || !glyph) return glyph;
        const chars = Array.from(glyph);
        const rest = chars.slice(1);
        if (rest[0] === '\uFE0F') rest.shift(); // The text/emoji selector is not kept before a modifier.
        return chars[0] + tone[1] + rest.join('');
    }
    const STORAGE_RECENT = 'paw-emoji-recents';
    const STORAGE_TONE = 'paw-emoji-tone';
    const RECENT_LIMIT = 24;

    const CATEGORIES = [
        { id: 'recent',     label: 'Recent',     icon: 'three-oclock' },
        { id: 'people',     label: 'People',     icon: 'grinning-face' },
        { id: 'nature',     label: 'Nature',     icon: 'dog-face' },
        { id: 'food',       label: 'Food',       icon: 'hamburger' },
        { id: 'activities', label: 'Activities', icon: 'soccer-ball' },
        { id: 'travel',     label: 'Travel',     icon: 'automobile' },
        { id: 'objects',    label: 'Objects',    icon: 'light-bulb' },
        { id: 'symbols',    label: 'Symbols',    icon: 'hundred-points' },
        { id: 'flags',      label: 'Flags',      icon: 'triangular-flag' },
    ];

    const TONES = ['default', 'light', 'medium-light', 'medium', 'medium-dark', 'dark'];
    const TONE_GLYPHS = {
        'default':       '👋',
        'light':         '👋🏻',
        'medium-light':  '👋🏼',
        'medium':        '👋🏽',
        'medium-dark':   '👋🏾',
        'dark':          '👋🏿',
    };

    let manifest = null;
    let manifestPromise = null;

    function loadManifest() {
        if (manifest) return Promise.resolve(manifest);
        if (manifestPromise) return manifestPromise;
        manifestPromise = fetch(CDN + '/manifest.json', { cache: 'no-cache' })
            .then(r => r.json())
            .then(m => (manifest = m));
        return manifestPromise;
    }

    function svgUrl(name) { return CDN + '/svg/' + encodeURIComponent(name) + '.svg'; }
    function nameToLabel(name) { return name.replace(/-/g, ' '); }

    function findGlyph(name) {
        if (!manifest || !manifest.categories) return '';
        for (const cat of Object.keys(manifest.categories)) {
            const list = manifest.categories[cat];
            for (const e of list) {
                if (e.name === name && e.unicode) return e.unicode;
                if (e.tones && e.tones.length) {
                    if (e.tones.indexOf(name) !== -1 && e.unicode) return withTone(e.unicode, name);
                }
            }
        }
        return '';
    }

    function getRecents() {
        try { return JSON.parse(localStorage.getItem(STORAGE_RECENT)) || []; }
        catch { return []; }
    }
    function addRecent(name) {
        const list = getRecents().filter(n => n !== name);
        list.unshift(name);
        localStorage.setItem(STORAGE_RECENT, JSON.stringify(list.slice(0, RECENT_LIMIT)));
    }

    function getTone() { return localStorage.getItem(STORAGE_TONE) || 'default'; }
    function setTone(t) { localStorage.setItem(STORAGE_TONE, t); }

    function applyTone(entry, tone) {
        if (tone === 'default' || !entry.tones || !entry.tones.length) return entry.name;
        return entry.tones.find(t => t.endsWith('-' + tone)) || entry.name;
    }

    class PawEmojiPicker {
        constructor(opts = {}) {
            this.opts = opts;
            this.el = null;
            this.trigger = null;
            this.target = null;
            this.activeTab = 'recent';
            this.searchQuery = '';
            this.tone = getTone();
            this._onDocClick = this._onDocClick.bind(this);
            this._onKey = this._onKey.bind(this);
            this._onScroll = this._onScroll.bind(this);
        }

        async open({ trigger, target } = {}) {
            this.trigger = trigger || null;
            this.target = target || null;
            try {
                await loadManifest();
            } catch (err) {
                console.error('EmojiPicker: failed to load manifest', err);
                return;
            }
            this._build();
            this._position();
            setTimeout(() => {
                document.addEventListener('mousedown', this._onDocClick, true);
                document.addEventListener('keydown', this._onKey);
                window.addEventListener('scroll', this._onScroll, true);
                window.addEventListener('resize', this._onScroll);
            }, 0);
        }

        close() {
            if (!this.el) return;
            this.el.remove();
            this.el = null;
            document.removeEventListener('mousedown', this._onDocClick, true);
            document.removeEventListener('keydown', this._onKey);
            window.removeEventListener('scroll', this._onScroll, true);
            window.removeEventListener('resize', this._onScroll);
            if (typeof this.opts.onClose === 'function') this.opts.onClose();
        }

        _onDocClick(e) {
            if (!this.el) return;
            if (this.el.contains(e.target)) return;
            if (this.trigger && this.trigger.contains(e.target)) return;
            this.close();
        }
        _onKey(e) {
            if (e.key === 'Escape') this.close();
        }
        _onScroll() {
            if (this.el && this.trigger) this._position();
        }

        _build() {
            const el = document.createElement('div');
            el.className = 'paw-emoji-picker';
            el.innerHTML = `
                <div class="paw-emoji-picker__head">
                    <label class="paw-emoji-search">
                        <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">
                            <path fill="currentColor" d="M11.7 10.3a6.5 6.5 0 1 0-1.4 1.4l3.9 3.9a1 1 0 0 0 1.4-1.4l-3.9-3.9zM6.5 12a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z"/>
                        </svg>
                        <input type="search" placeholder="Search" aria-label="Search emoji">
                    </label>
                    <button class="paw-emoji-tone-btn" type="button" aria-label="Skin tone">
                        <span class="paw-emoji-tone-glyph">${TONE_GLYPHS[this.tone]}</span>
                    </button>
                    <button class="paw-emoji-close" type="button" aria-label="Close">✕</button>
                </div>
                <div class="paw-emoji-tone-menu" hidden>
                    ${TONES.map(t => `
                        <button type="button" data-tone="${t}" class="paw-emoji-tone-opt${t === this.tone ? ' is-active' : ''}" aria-label="${t}">${TONE_GLYPHS[t]}</button>
                    `).join('')}
                </div>
                <div class="paw-emoji-tabs" role="tablist">
                    ${CATEGORIES.map(c => `
                        <button type="button" role="tab" class="paw-emoji-tab" data-cat="${c.id}" aria-label="${c.label}" title="${c.label}">
                            <img src="${svgUrl(c.icon)}" alt="" loading="lazy">
                        </button>
                    `).join('')}
                </div>
                <div class="paw-emoji-body" role="tabpanel"></div>
            `;
            this.el = el;
            document.body.appendChild(el);

            el.querySelector('.paw-emoji-close').addEventListener('click', () => this.close());

            el.querySelector('.paw-emoji-search input').addEventListener('input', (e) => {
                this.searchQuery = e.target.value.trim().toLowerCase();
                this._renderBody();
            });

            el.querySelectorAll('.paw-emoji-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    this.activeTab = tab.dataset.cat;
                    this.searchQuery = '';
                    el.querySelector('.paw-emoji-search input').value = '';
                    el.querySelectorAll('.paw-emoji-tab').forEach(t => t.classList.toggle('is-active', t === tab));
                    this._renderBody();
                });
            });

            const toneBtn = el.querySelector('.paw-emoji-tone-btn');
            const toneMenu = el.querySelector('.paw-emoji-tone-menu');
            toneBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toneMenu.hidden = !toneMenu.hidden;
            });
            toneMenu.querySelectorAll('[data-tone]').forEach(opt => {
                opt.addEventListener('click', () => {
                    this.tone = opt.dataset.tone;
                    setTone(this.tone);
                    el.querySelector('.paw-emoji-tone-glyph').textContent = TONE_GLYPHS[this.tone];
                    toneMenu.querySelectorAll('.paw-emoji-tone-opt').forEach(o => o.classList.toggle('is-active', o === opt));
                    toneMenu.hidden = true;
                    this._renderBody();
                });
            });

            const initial = el.querySelector('.paw-emoji-tab[data-cat="recent"]');
            if (initial) initial.classList.add('is-active');
            this._renderBody();
        }

        _renderBody() {
            const body = this.el.querySelector('.paw-emoji-body');

            if (this.searchQuery) {
                const q = this.searchQuery;
                const matches = [];
                for (const cat of CATEGORIES) {
                    if (cat.id === 'recent') continue;
                    const items = manifest.categories[cat.id] || [];
                    for (const e of items) {
                        if (e.name.includes(q)) matches.push(e);
                        if (matches.length >= 200) break;
                    }
                    if (matches.length >= 200) break;
                }
                body.innerHTML = matches.length
                    ? this._sectionHtml('Search results', matches)
                    : '<div class="paw-emoji-empty">No matches.</div>';
            } else if (this.activeTab === 'recent') {
                const recents = getRecents().map(n => ({ name: n }));
                let html = '';
                if (recents.length) html += this._sectionHtml('Frequently Used', recents, /*skipTone*/ true);
                else html += '<div class="paw-emoji-empty">Pick an emoji — it\'ll show up here.</div>';
                html += this._sectionHtml('People', manifest.categories.people || []);
                body.innerHTML = html;
            } else {
                const cat = CATEGORIES.find(c => c.id === this.activeTab);
                const items = manifest.categories[this.activeTab] || [];
                body.innerHTML = this._sectionHtml(cat.label, items);
            }

            body.querySelectorAll('.paw-emoji-item').forEach(btn => {
                btn.addEventListener('click', () => this._select(btn.dataset.name));
            });
        }

        _sectionHtml(label, entries, skipTone = false) {
            const isDefaultTone = skipTone || this.tone === 'default';
            const parts = entries.map(e => {
                const name = skipTone ? e.name : applyTone(e, this.tone);
                const lbl = nameToLabel(name);
                const inner = (isDefaultTone && e.unicode)
                    ? `<span class="paw-emoji-glyph">${esc(e.unicode)}</span>`
                    : `<img src="${esc(svgUrl(name))}" alt="${esc(lbl)}" loading="lazy">`;
                return `<button type="button" class="paw-emoji-item" data-name="${esc(name)}" title="${esc(lbl)}">${inner}</button>`;
            });
            return `<h4 class="paw-emoji-section">${esc(label)}</h4>
                    <div class="paw-emoji-grid">${parts.join('')}</div>`;
        }

        _position() {
            if (!this.trigger) {
                this.el.style.left = '50%';
                this.el.style.top = '50%';
                this.el.style.transform = 'translate(-50%, -50%)';
                return;
            }
            const r = this.trigger.getBoundingClientRect();
            const pr = this.el.getBoundingClientRect();
            const vh = window.innerHeight, vw = window.innerWidth;
            let top = r.bottom + 8;
            let left = r.left;
            if (top + pr.height > vh) top = Math.max(8, r.top - pr.height - 8);
            if (left + pr.width > vw) left = vw - pr.width - 8;
            if (left < 8) left = 8;
            this.el.style.top = top + 'px';
            this.el.style.left = left + 'px';
        }

        _select(name) {
            addRecent(name);
            const url = svgUrl(name);
            const glyph = findGlyph(name);

            if (typeof this.opts.onSelect === 'function') {
                this.opts.onSelect({ name, url, glyph });
            } else {
                this._defaultInsert(name, url, glyph);
            }
            this.close();
        }

        _defaultInsert(name, url, glyph) {
            const t = this.target;
            if (!t) return;
            // Insert the native Unicode glyph (falls back to the SVG image only
            // for content-editable targets when no glyph exists).
            const insertText = glyph || ('');

            if (t.tagName === 'TEXTAREA' || t.tagName === 'INPUT') {
                if (!insertText) return;
                const start = t.selectionStart ?? t.value.length;
                const end = t.selectionEnd ?? t.value.length;
                t.value = t.value.slice(0, start) + insertText + t.value.slice(end);
                t.selectionStart = t.selectionEnd = start + insertText.length;
                t.focus();
                t.dispatchEvent(new Event('input', { bubbles: true }));
            } else if (t.isContentEditable) {
                t.focus();
                if (glyph) {
                    document.execCommand('insertText', false, glyph);
                } else {
                    const html = `<img class="paw-emoji" alt=":${esc(name)}:" data-emoji="${esc(name)}" src="${esc(url)}">`;
                    document.execCommand('insertHTML', false, html);
                }
            }
        }
    }

    // ---- Auto-init: clicks on [data-emoji-trigger] ----
    let active = null;
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-emoji-trigger]');
        if (!trigger) return;
        e.preventDefault();
        if (active) { active.close(); active = null; return; }

        const targetSel = trigger.dataset.emojiTarget;
        const target = targetSel ? document.querySelector(targetSel) : null;
        active = new PawEmojiPicker({ onClose: () => { active = null; } });
        active.open({ trigger, target });
    });

    window.PawEmojiPicker = PawEmojiPicker;
    window.PawEmojiPicker.findGlyph = findGlyph;
})(window, document);
