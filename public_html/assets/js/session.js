/* Treino em andamento: marcar séries, descanso, fotos e finalizar. */
(() => {
    'use strict';

    const root = document.querySelector('[data-session]');
    if (!root) return;

    const cfg = JSON.parse(document.getElementById('session-data').textContent);
    const base = `/app/sessao/${cfg.id}`;
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];

    const toast = (text, type = 'info') => {
        let box = $('.toasts');
        if (!box) { box = document.createElement('div'); box.className = 'toasts'; document.body.appendChild(box); }
        const t = document.createElement('div');
        t.className = `toast toast-${type}`;
        t.textContent = text;
        box.appendChild(t);
        setTimeout(() => { t.classList.add('is-leaving'); setTimeout(() => t.remove(), 300); }, 3200);
    };

    /* ---------- Cronômetro do treino ---------- */
    const clock = $('[data-clock]');
    const startedAt = new Date(clock.dataset.start).getTime();
    const pad = (n) => String(n).padStart(2, '0');
    const tick = () => {
        const s = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
        const h = Math.floor(s / 3600);
        clock.textContent = (h ? h + ':' : '') + pad(Math.floor((s % 3600) / 60)) + ':' + pad(s % 60);
    };
    tick();
    setInterval(tick, 1000);

    // Mantém a tela acesa durante o treino (quando o navegador permite).
    let wakeLock = null;
    const keepAwake = async () => {
        try { wakeLock = await navigator.wakeLock?.request('screen'); } catch { /* sem suporte */ }
    };
    keepAwake();
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && (!wakeLock || wakeLock.released)) keepAwake();
    });

    /* ---------- Progresso ---------- */
    const progress = () => {
        const rows = $$('.set-row', root);
        const done = rows.filter((r) => r.classList.contains('is-done')).length;
        $('[data-done]').textContent = done;
        $('[data-total]').textContent = rows.length;
        $('[data-progress]').style.width = rows.length ? `${(done / rows.length) * 100}%` : '0%';
        $$('.ex-card', root).forEach((card) => {
            const all = $$('.set-row', card);
            card.classList.toggle('is-complete', all.length > 0 && all.every((r) => r.classList.contains('is-done')));
        });
        return { done, total: rows.length };
    };

    /* ---------- Salvar série (com fila se ficar sem internet) ---------- */
    const queue = new Map();

    const send = async (payload) => {
        const res = await fetch(`${base}/series`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': cfg.csrf },
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            const j = await res.json().catch(() => ({}));
            const err = new Error(j.error || j.message || 'Erro ao salvar');
            err.fatal = res.status < 500 && res.status !== 419;
            throw err;
        }
        return res.json();
    };

    const saveRow = async (row) => {
        const card = row.closest('.ex-card');
        const payload = {
            exercise_id: Number(card.dataset.exercise),
            set_number: Number(row.dataset.set),
            load_kg: $('[data-load]', row).value.trim(),
            reps: $('[data-reps]', row).value.trim(),
            completed: row.classList.contains('is-done'),
        };
        const key = `${payload.exercise_id}:${payload.set_number}`;
        row.classList.add('is-saving');
        try {
            await send(payload);
            queue.delete(key);
            row.classList.remove('is-pending');
        } catch (e) {
            if (e.fatal) {
                toast(e.message, 'error');
            } else {
                queue.set(key, row);
                row.classList.add('is-pending');
                toast('Sem conexão. A série fica salva aqui e sincroniza quando a internet voltar.', 'info');
            }
        } finally {
            row.classList.remove('is-saving');
        }
    };

    const flushQueue = () => [...queue.values()].forEach(saveRow);
    window.addEventListener('online', flushQueue);
    setInterval(() => queue.size && navigator.onLine && flushQueue(), 15000);

    /* ---------- Marcar série ---------- */
    root.addEventListener('click', (e) => {
        const check = e.target.closest('[data-check]');
        if (!check) return;
        const row = check.closest('.set-row');
        const done = !row.classList.contains('is-done');
        row.classList.toggle('is-done', done);
        check.setAttribute('aria-pressed', String(done));
        navigator.vibrate?.(done ? 25 : 10);
        progress();
        saveRow(row);

        if (done) {
            const card = row.closest('.ex-card');
            const isLastOfAll = progress().done === progress().total;
            if (!isLastOfAll) startRest(Number(card.dataset.rest) || 60);
            // Leva o foco para a próxima série pendente deste exercício
            const next = $$('.set-row:not(.is-done)', card)[0];
            if (!next) {
                const nextCard = card.nextElementSibling;
                if (nextCard?.classList.contains('ex-card')) setTimeout(() => nextCard.scrollIntoView({ behavior: 'smooth', block: 'start' }), 350);
            }
        } else {
            stopRest();
        }
    });

    // Editou carga/reps de uma série já marcada: salva de novo.
    root.addEventListener('change', (e) => {
        const row = e.target.closest('.set-row');
        if (row?.classList.contains('is-done')) saveRow(row);
        // Propaga a carga para as séries seguintes ainda não feitas (comodidade)
        if (e.target.matches('[data-load]')) {
            let next = row.nextElementSibling;
            while (next && !next.classList.contains('is-done')) {
                const input = $('[data-load]', next);
                if (!input.dataset.touched) input.value = e.target.value;
                next = next.nextElementSibling;
            }
        }
    });
    root.addEventListener('input', (e) => { if (e.target.matches('.set-input')) e.target.dataset.touched = '1'; });

    /* ---------- Série extra ---------- */
    root.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-add-set]');
        if (!btn) return;
        const sets = $('[data-sets]', btn.closest('.ex-card'));
        const last = sets.lastElementChild;
        const row = last.cloneNode(true);
        const n = Number(last.dataset.set) + 1;
        row.dataset.set = n;
        row.classList.remove('is-done', 'is-pending', 'is-saving');
        $('.set-n', row).textContent = n;
        $('[data-check]', row).setAttribute('aria-pressed', 'false');
        $$('input', row).forEach((i) => { delete i.dataset.touched; });
        row.classList.add('reveal');
        sets.appendChild(row);
        progress();
    });

    /* ---------- Descanso ---------- */
    const pill = $('[data-rest-pill]');
    const arc = $('[data-rest-arc]');
    const timeEl = $('[data-rest-time]');
    let restEnd = 0;
    let restTotal = 0;
    let restTimer = null;

    const beep = () => {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.frequency.value = 880;
            g.gain.setValueAtTime(0.15, ctx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            o.connect(g).connect(ctx.destination);
            o.start();
            o.stop(ctx.currentTime + 0.4);
        } catch { /* sem áudio */ }
    };

    const renderRest = () => {
        const left = Math.max(0, Math.ceil((restEnd - Date.now()) / 1000));
        timeEl.textContent = `${Math.floor(left / 60)}:${pad(left % 60)}`;
        arc.style.strokeDashoffset = String(100 - (left / restTotal) * 100);
        if (left <= 0) {
            stopRest();
            navigator.vibrate?.([200, 100, 200]);
            beep();
            toast('Descanso acabou. Próxima série!', 'success');
        }
    };

    function startRest(seconds) {
        restTotal = seconds;
        restEnd = Date.now() + seconds * 1000;
        pill.hidden = false;
        clearInterval(restTimer);
        renderRest();
        restTimer = setInterval(renderRest, 250);
    }

    function stopRest() {
        clearInterval(restTimer);
        pill.hidden = true;
    }

    $('[data-rest-plus]').addEventListener('click', () => { restEnd += 15000; restTotal += 15; renderRest(); });
    $('[data-rest-skip]').addEventListener('click', stopRest);

    /* ---------- Fotos ---------- */
    const photos = $('[data-photos]');
    const addTile = $('[data-photo-add]');
    const input = $('[data-photo-input]');

    // Reduz a foto no celular antes de enviar (máx. 1600px, JPEG) — upload rápido e leve.
    const compress = (file) => new Promise((resolve) => {
        const img = new Image();
        const url = URL.createObjectURL(file);
        img.onload = () => {
            const scale = Math.min(1, 1600 / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            canvas.toBlob((blob) => resolve(blob || file), 'image/jpeg', 0.82);
        };
        img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
        img.src = url;
    });

    input.addEventListener('change', async () => {
        const file = input.files[0];
        input.value = '';
        if (!file) return;

        const tile = document.createElement('div');
        tile.className = 'photo is-uploading';
        tile.innerHTML = '<div class="spinner"></div>';
        photos.insertBefore(tile, addTile);

        try {
            const blob = await compress(file);
            const body = new FormData();
            body.append('_token', cfg.csrf);
            body.append('photo', blob, 'foto.jpg');
            const res = await fetch(`${base}/fotos`, { method: 'POST', body, headers: { Accept: 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.error || 'Não foi possível enviar a foto.');
            tile.classList.remove('is-uploading');
            tile.dataset.photo = json.id;
            tile.innerHTML = `<img src="${json.url}" alt="Foto do treino"><button type="button" class="photo-del" aria-label="Remover foto" data-photo-del>✕</button>`;
            toast('Foto adicionada.', 'success');
        } catch (e) {
            tile.remove();
            toast(e.message, 'error');
        }
    });

    photos.addEventListener('click', async (e) => {
        const del = e.target.closest('[data-photo-del]');
        if (!del) return;
        const tile = del.closest('[data-photo]');
        if (!(await window.LiftEat.confirmDialog('Remover esta foto?', 'Remover'))) return;
        tile.style.opacity = '.4';
        const body = new FormData();
        body.append('_token', cfg.csrf);
        const res = await fetch(`/app/fotos/${tile.dataset.photo}/excluir`, { method: 'POST', body, headers: { Accept: 'application/json' } });
        if (res.ok) tile.remove(); else { tile.style.opacity = ''; toast('Não foi possível remover.', 'error'); }
    });

    /* ---------- Finalizar ---------- */
    const finishForm = $('[data-finish-form]');
    finishForm.addEventListener('submit', async (e) => {
        if (finishForm.dataset.ok) return;
        e.preventDefault();
        if (queue.size) {
            flushQueue();
            toast('Ainda sincronizando séries… tente de novo em instantes.', 'info');
            return;
        }
        const { done, total } = progress();
        const msg = done === 0
            ? 'Nenhuma série marcada. Finalizar mesmo assim?'
            : done < total ? `Você fez ${done} de ${total} séries. Finalizar mesmo assim?` : 'Mandou bem! Finalizar o treino?';
        const ok = await window.LiftEat.confirmDialog(msg, 'Finalizar', 'primary');
        if (!ok) {
            $('[data-finish]').classList.remove('is-loading');
            $('[data-finish]').removeAttribute('aria-disabled');
            return;
        }
        finishForm.dataset.ok = '1';
        finishForm.requestSubmit();
    });

    progress();
})();
