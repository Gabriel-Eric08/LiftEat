/* LiftEat — interações de interface (sem dependências) */
(() => {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

    /* ---------- Tema ---------- */
    const setTheme = (theme) => {
        document.documentElement.dataset.theme = theme;
        document.cookie = `theme=${theme}; path=/; max-age=31536000; samesite=lax`;
        const meta = $('meta[name="theme-color"]');
        if (meta) meta.content = theme === 'light' ? '#f6f7f6' : '#070807';
        document.dispatchEvent(new CustomEvent('themechange', { detail: theme }));
    };

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-theme-toggle]');
        if (!btn) return;
        const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
        if (document.startViewTransition) {
            const t = document.startViewTransition(() => setTheme(next));
            // Se outra transição (ex.: navegação) interromper esta, ignora silenciosamente.
            t.ready.catch(() => {});
            t.finished.catch(() => {});
            t.updateCallbackDone?.catch(() => {});
        } else {
            setTheme(next);
        }
    });

    /* ---------- Barra de progresso (navegação e envio de formulário) ---------- */
    const bar = document.createElement('div');
    bar.className = 'progress-bar';
    document.body.appendChild(bar);

    const startProgress = () => {
        bar.classList.remove('is-done');
        void bar.offsetWidth;
        bar.classList.add('is-active');
    };
    const endProgress = () => {
        bar.classList.add('is-done');
        bar.classList.remove('is-active');
    };

    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        const url = new URL(a.href, location.href);
        if (a.target === '_blank' || a.hasAttribute('download') || url.origin !== location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
        startProgress();
    });

    window.addEventListener('pageshow', endProgress);

    // Transição entre páginas (CSS @view-transition): se for interrompida, não gera erro.
    window.addEventListener('pagereveal', (e) => {
        const vt = e.viewTransition;
        if (!vt) return;
        vt.ready.catch(() => {});
        vt.finished.catch(() => {});
    });

    /* ---------- Formulários: loading no botão + confirmação ---------- */
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.dataset.confirm && !form.dataset.confirmed) {
            e.preventDefault();
            confirmDialog(form.dataset.confirm, form.dataset.confirmLabel, form.dataset.confirmTone).then((ok) => {
                if (!ok) return;
                form.dataset.confirmed = '1';
                form.requestSubmit(e.submitter || undefined);
            });
            return;
        }
        if (form.dataset.noLoading !== undefined) return;
        const btn = e.submitter || $('[type="submit"]', form);
        if (btn) {
            btn.classList.add('is-loading');
            // Evita duplo envio sem desabilitar (o valor do botão ainda é enviado)
            setTimeout(() => btn.setAttribute('aria-disabled', 'true'), 0);
        }
        startProgress();
    });

    /* ---------- Diálogo de confirmação ---------- */
    const confirmDialog = (message, label = 'Confirmar', tone = 'danger') => new Promise((resolve) => {
        const dlg = document.createElement('dialog');
        dlg.className = 'modal';
        dlg.innerHTML = `
            <div class="modal-body stack" style="--gap:8px">
                <h3>Tem certeza?</h3>
                <p class="muted"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-v="0">Cancelar</button>
                <button type="button" class="btn ${tone === 'primary' ? 'btn-primary' : 'btn-danger'}" data-v="1"></button>
            </div>`;
        $('p', dlg).textContent = message;
        $('[data-v="1"]', dlg).textContent = label || 'Confirmar';
        document.body.appendChild(dlg);
        dlg.showModal();
        const done = (v) => { dlg.close(); dlg.remove(); resolve(v); };
        dlg.addEventListener('click', (ev) => {
            const b = ev.target.closest('[data-v]');
            if (b) done(b.dataset.v === '1');
            else if (ev.target === dlg) done(false);
        });
        dlg.addEventListener('cancel', (ev) => { ev.preventDefault(); done(false); });
    });

    /* ---------- Modais declarativos: <button data-open="id"> / <button data-close> ---------- */
    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-open]');
        if (opener) {
            const dlg = document.getElementById(opener.dataset.open);
            if (dlg?.showModal) {
                dlg.showModal();
                $('input:not([type=hidden]), select, textarea', dlg)?.focus();
            }
            return;
        }
        const closer = e.target.closest('[data-close]');
        if (closer) closer.closest('dialog')?.close();
    });

    // Clique fora do conteúdo fecha o modal
    document.addEventListener('click', (e) => {
        if (e.target instanceof HTMLDialogElement && e.target.classList.contains('modal')) {
            const r = e.target.getBoundingClientRect();
            const inside = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
            if (!inside) e.target.close();
        }
    });

    /* ---------- Abas: [data-tabs] > [data-tab="x"] + [data-panel="x"] ---------- */
    $$('[data-tabs]').forEach((root) => {
        const key = root.dataset.tabs;
        const activate = (name, push = true) => {
            $$('[data-tab]', root).forEach((t) => {
                const on = t.dataset.tab === name;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on);
            });
            $$('[data-panel]', root).forEach((p) => p.classList.toggle('is-active', p.dataset.panel === name));
            if (push && key) {
                const url = new URL(location.href);
                url.searchParams.set(key, name);
                history.replaceState(null, '', url);
            }
            root.dispatchEvent(new CustomEvent('tabchange', { detail: name }));
        };
        root.addEventListener('click', (e) => {
            const t = e.target.closest('[data-tab]');
            if (t && root.contains(t)) { e.preventDefault(); activate(t.dataset.tab); }
        });
        const initial = (key && new URLSearchParams(location.search).get(key)) || $('[data-tab]', root)?.dataset.tab;
        if (initial) activate(initial, false);
    });

    /* ---------- Toasts (mensagens flash) ---------- */
    const toastRoot = $('.toasts');
    const dismiss = (t) => {
        t.classList.add('is-leaving');
        t.addEventListener('animationend', () => t.remove(), { once: true });
    };
    $$('.toast', toastRoot || document).forEach((t, i) => {
        setTimeout(() => dismiss(t), 4200 + i * 400);
        t.addEventListener('click', () => dismiss(t));
    });

    /* ---------- Mostrar/ocultar senha ---------- */
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-toggle-password]');
        if (!btn) return;
        const input = btn.parentElement.querySelector('input');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
        btn.classList.toggle('is-on', show);
    });

    /* ---------- Menu lateral (painel) no mobile ---------- */
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-sidebar-toggle]')) {
            document.body.classList.toggle('sidebar-open');
        } else if (e.target.closest('.sidebar-backdrop')) {
            document.body.classList.remove('sidebar-open');
        }
    });

    /* ---------- Rolagem lateral: centraliza o item ativo ---------- */
    $$('.h-scroll, .chips').forEach((el) => {
        const active = $('.is-active', el);
        if (active) el.scrollLeft = active.offsetLeft - el.clientWidth / 2 + active.clientWidth / 2;
    });

    /* ---------- Envio automático de filtros (selects) e chave de tema ---------- */
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-autosubmit]')) e.target.form?.requestSubmit();
        if (e.target.matches('[data-theme-switch]')) setTheme(e.target.checked ? 'dark' : 'light');
    });

    /* ---------- Modal que abre sozinho (ex.: erro de validação dentro dele) ---------- */
    $$('dialog[data-autoopen]').forEach((d) => d.showModal?.());

    window.LiftEat = { confirmDialog, startProgress, endProgress };
})();
