/**
 * suggest.js — Busqueda predictiva REUTILIZABLE (todas las paginas)
 * ------------------------------------------------------------------
 * Uso: en cualquier <input> añade:
 *   autocomplete="off"
 *   data-suggest-url="/api/suggest?type=estudiante"
 *   data-suggest-target="#studentSuggest"   (opcional; por defecto crea/usa
 *                                            un div hermano .suggest-box)
 *
 * Respuesta esperada del endpoint: JSON
 *   [{ "label": "Juan Pérez", "sub": "6°A · 123456", "url": "/students/12" }]
 *
 * Comportamiento: debounce 250ms, min 2 caracteres, teclado (↑ ↓ Enter Esc),
 * click fuera cierra. Enter sin seleccion envia el form (busqueda normal).
 */
(function () {
    'use strict';

    function init(input) {
        var url = input.dataset.suggestUrl;
        if (!url) return;

        var box = input.dataset.suggestTarget
            ? document.querySelector(input.dataset.suggestTarget)
            : (input.parentElement && input.parentElement.querySelector('.suggest-box'));
        if (!box) {
            box = document.createElement('div');
            box.className = 'suggest-box';
            box.hidden = true;
            (input.parentElement || input).appendChild(box);
        }

        var timer = null, items = [], idx = -1, lastQ = '';

        function close() { box.hidden = true; items = []; idx = -1; }

        function render() {
            if (!items.length) { close(); return; }
            box.innerHTML = items.map(function (it, i) {
                return '<a class="suggest-item' + (i === idx ? ' is-active' : '') + '" href="' + it.url + '">' +
                    '<span class="suggest-label">' + esc(it.label) + '</span>' +
                    (it.sub ? '<span class="suggest-sub">' + esc(it.sub) + '</span>' : '') +
                    '</a>';
            }).join('');
            box.hidden = false;
        }

        function esc(s) {
            var d = document.createElement('div');
            d.textContent = String(s == null ? '' : s);
            return d.innerHTML;
        }

        function fetchSuggest(q) {
            var u = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
            fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (data) {
                    if (input.value.trim() !== q) return; // respuesta desfasada
                    items = Array.isArray(data) ? data : [];
                    idx = -1;
                    render();
                })
                .catch(function () { close(); });
        }

        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(timer);
            if (q.length < 2 || q === lastQ) { if (q.length < 2) close(); return; }
            lastQ = q;
            timer = setTimeout(function () { fetchSuggest(q); }, 250);
        });

        input.addEventListener('keydown', function (e) {
            if (box.hidden || !items.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(idx + 1, items.length - 1); render(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(idx - 1, 0); render(); }
            else if (e.key === 'Escape') { close(); }
            else if (e.key === 'Enter' && idx >= 0) { e.preventDefault(); window.location.href = items[idx].url; }
        });

        document.addEventListener('click', function (ev) {
            if (!box.contains(ev.target) && ev.target !== input) close();
        });

        input.addEventListener('focus', function () {
            if (items.length && input.value.trim().length >= 2) render();
        });
    }

    function boot() {
        document.querySelectorAll('input[data-suggest-url]').forEach(init);
    }
    if (document.readyState !== 'loading') boot();
    else document.addEventListener('DOMContentLoaded', boot);
})();
