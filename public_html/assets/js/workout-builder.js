/* Montador de treino: estado em memória, salvo de uma vez no submit. */
(() => {
    'use strict';

    const form = document.querySelector('[data-builder]');
    if (!form) return;

    const data = JSON.parse(document.getElementById('builder-data').textContent);
    const rowsEl = form.querySelector('[data-rows]');
    const emptyEl = form.querySelector('[data-empty]');
    const addMore = form.querySelector('[data-add-more]');
    const tpl = document.getElementById('row-template');
    const dirtyEl = form.querySelector('[data-dirty]');

    let uid = 0;
    let dirty = false;
    const state = data.items.map((it) => ({ ...it, _key: ++uid }));
    const library = data.library;

    const markDirty = () => {
        dirty = true;
        dirtyEl.hidden = false;
    };

    /* ---------- Render das linhas ---------- */
    const render = () => {
        rowsEl.innerHTML = '';
        state.forEach((item, i) => {
            const node = tpl.content.firstElementChild.cloneNode(true);
            node.dataset.key = item._key;
            node.querySelector('.ex-num').textContent = String(i + 1).padStart(2, '0');
            node.querySelector('[data-f="name"]').textContent = item.name;
            node.querySelector('[data-f="muscle_group"]').textContent = item.muscle_group || '';
            node.querySelectorAll('[data-k]').forEach((input) => {
                const k = input.dataset.k;
                input.value = item[k] ?? '';
            });
            node.querySelector('[data-act="up"]').disabled = i === 0;
            node.querySelector('[data-act="down"]').disabled = i === state.length - 1;
            rowsEl.appendChild(node);
        });
        emptyEl.hidden = state.length > 0;
        addMore.hidden = state.length === 0;
        summary();
        refreshPicker();
    };

    const summary = () => {
        const sets = state.reduce((n, it) => n + (parseInt(it.sets, 10) || 0), 0);
        // ~45s por série + descanso prescrito (ou 60s)
        const secs = state.reduce((n, it) => n + (parseInt(it.sets, 10) || 0) * (45 + (parseInt(it.rest_seconds, 10) || 60)), 0);
        form.querySelector('[data-sum-ex]').textContent = state.length;
        form.querySelector('[data-sum-sets]').textContent = sets;
        form.querySelector('[data-sum-min]').textContent = Math.round(secs / 60);
    };

    const find = (el) => state.findIndex((it) => String(it._key) === el.closest('.ex-row')?.dataset.key);

    /* ---------- Edição dos campos ---------- */
    rowsEl.addEventListener('input', (e) => {
        const input = e.target.closest('[data-k]');
        if (!input) return;
        const i = find(input);
        if (i < 0) return;
        state[i][input.dataset.k] = input.value;
        markDirty();
        summary();
    });
    rowsEl.addEventListener('change', (e) => {
        if (e.target.matches('select[data-k]')) {
            const i = find(e.target);
            state[i][e.target.dataset.k] = e.target.value;
            markDirty();
            summary();
        }
    });

    /* ---------- Ações: subir, descer, remover ---------- */
    rowsEl.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-act]');
        if (!btn) return;
        const i = find(btn);
        const act = btn.dataset.act;
        if (act === 'up' && i > 0) [state[i - 1], state[i]] = [state[i], state[i - 1]];
        if (act === 'down' && i < state.length - 1) [state[i + 1], state[i]] = [state[i], state[i + 1]];
        if (act === 'remove') {
            const row = btn.closest('.ex-row');
            row.style.transition = 'opacity .2s, transform .2s';
            row.style.opacity = '0';
            row.style.transform = 'translateX(16px)';
            setTimeout(() => { state.splice(i, 1); render(); }, 180);
            markDirty();
            return;
        }
        markDirty();
        render();
    });

    /* ---------- Arrastar para reordenar (desktop) ---------- */
    let dragKey = null;
    rowsEl.addEventListener('dragstart', (e) => {
        const row = e.target.closest('.ex-row');
        if (!row) return;
        dragKey = row.dataset.key;
        row.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setDragImage(row, 20, 20);
    });
    rowsEl.addEventListener('dragover', (e) => {
        if (dragKey === null) return;
        e.preventDefault();
        const over = e.target.closest('.ex-row');
        const dragging = rowsEl.querySelector('.is-dragging');
        if (!over || over === dragging) return;
        const rect = over.getBoundingClientRect();
        const after = e.clientY > rect.top + rect.height / 2;
        over.parentNode.insertBefore(dragging, after ? over.nextSibling : over);
    });
    rowsEl.addEventListener('dragend', () => {
        if (dragKey === null) return;
        const order = [...rowsEl.querySelectorAll('.ex-row')].map((r) => r.dataset.key);
        state.sort((a, b) => order.indexOf(String(a._key)) - order.indexOf(String(b._key)));
        dragKey = null;
        markDirty();
        render();
    });

    /* ---------- Seletor de exercícios ---------- */
    const picker = document.getElementById('picker');
    const listEl = picker.querySelector('[data-picker-list]');
    const searchEl = picker.querySelector('[data-picker-search]');
    const groupsEl = picker.querySelector('[data-picker-groups]');
    const quick = picker.querySelector('[data-quick]');
    let group = '';

    const norm = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

    const renderGroups = () => {
        const groups = [...new Set(library.map((e) => e.muscle_group))].sort((a, b) => a.localeCompare(b, 'pt'));
        groupsEl.innerHTML = '';
        ['', ...groups].forEach((g) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'chip' + (g === group ? ' is-active' : '');
            b.textContent = g || 'Todos';
            b.addEventListener('click', () => { group = g; renderGroups(); refreshPicker(); });
            groupsEl.appendChild(b);
        });
    };

    const refreshPicker = () => {
        const q = norm(searchEl.value.trim());
        const inWorkout = new Set(state.map((it) => Number(it.exercise_id)));
        const items = library.filter((e) => (!group || e.muscle_group === group)
            && (!q || norm(e.name).includes(q) || norm(e.equipment).includes(q)));

        listEl.innerHTML = '';
        items.forEach((e) => {
            const added = inWorkout.has(e.id);
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'picker-item' + (added ? ' is-added' : '');
            b.innerHTML = `<span class="grow"><span class="bold"></span><span class="xsmall muted"></span></span><span class="picker-check"></span>`;
            b.querySelector('.bold').textContent = e.name;
            b.querySelector('.xsmall').textContent = [e.muscle_group, e.equipment].filter(Boolean).join(' · ');
            b.querySelector('.picker-check').textContent = added ? '✓' : '+';
            b.addEventListener('click', () => (added ? removeExercise(e.id) : addExercise(e)));
            listEl.appendChild(b);
        });

        if (!items.length && !q) {
            listEl.innerHTML = '<p class="small muted center" style="padding:16px">Biblioteca vazia neste grupo.</p>';
        }

        const exact = library.some((e) => norm(e.name) === q);
        quick.hidden = !q || exact;
        quick.querySelector('[data-quick-name]').textContent = `“${searchEl.value.trim()}”`;
        if (group) quick.querySelector('[data-quick-group]').value = group;
    };

    const addExercise = (e) => {
        state.push({
            _key: ++uid, id: 0, exercise_id: e.id, name: e.name, muscle_group: e.muscle_group,
            sets: 3, reps: '10', load_kg: '', rest_seconds: 60, technique: '', notes: '',
        });
        markDirty();
        render();
        rowsEl.lastElementChild?.classList.add('reveal');
    };

    const removeExercise = (exerciseId) => {
        const i = state.findIndex((it) => Number(it.exercise_id) === exerciseId);
        if (i >= 0) { state.splice(i, 1); markDirty(); render(); }
    };

    searchEl.addEventListener('input', refreshPicker);
    picker.addEventListener('close', () => { searchEl.value = ''; });

    // Criação rápida de exercício sem sair da tela
    picker.querySelector('[data-quick-create]').addEventListener('click', async (ev) => {
        const btn = ev.currentTarget;
        const name = searchEl.value.trim();
        const muscle = picker.querySelector('[data-quick-group]').value.trim();
        if (!name || !muscle) {
            picker.querySelector('[data-quick-group]').focus();
            return;
        }
        btn.classList.add('is-loading');
        try {
            const res = await fetch('/admin/exercicios', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': data.csrf },
                body: JSON.stringify({ name, muscle_group: muscle }),
            });
            const json = await res.json();
            if (!res.ok) throw new Error(json.error || json.message || 'Erro ao criar exercício.');
            const ex = { id: json.id, name: json.name, muscle_group: json.muscle_group, equipment: json.equipment };
            library.push(ex);
            searchEl.value = '';
            renderGroups();
            addExercise(ex);
        } catch (err) {
            alert(err.message);
        } finally {
            btn.classList.remove('is-loading');
        }
    });

    /* ---------- Nome e dias ---------- */
    const titlePreview = document.querySelector('[data-title-preview]');
    form.querySelector('[data-name-input]').addEventListener('input', (e) => {
        titlePreview.textContent = e.target.value || 'Treino sem nome';
        markDirty();
    });
    form.addEventListener('change', (e) => {
        if (e.target.matches('[data-day], textarea')) markDirty();
    });
    form.querySelector('textarea[name="notes"]').addEventListener('input', markDirty);

    /* ---------- Envio ---------- */
    form.addEventListener('submit', () => {
        form.querySelector('[data-items-input]').value = JSON.stringify(state.map(({ _key, ...it }) => it));
        dirty = false;
    });

    window.addEventListener('beforeunload', (e) => {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    renderGroups();
    render();
})();
