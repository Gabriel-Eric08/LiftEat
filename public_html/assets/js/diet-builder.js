/* Montador do plano alimentar: refeições → opções → alimentos, salvo de uma vez no submit. */
(() => {
    'use strict';

    const form = document.querySelector('[data-diet-builder]');
    if (!form) return;

    const data = JSON.parse(document.getElementById('diet-builder-data').textContent);
    const mealsEl = form.querySelector('[data-meals]');
    const emptyEl = form.querySelector('[data-empty]');
    const addMore = form.querySelector('[data-add-more]');
    const dirtyEl = form.querySelector('[data-dirty]');
    const tpl = (id) => document.getElementById(id).content.firstElementChild;
    const mealTpl = tpl('meal-template');
    const optionTpl = tpl('option-template');
    const itemTpl = tpl('item-template');

    const str = (v) => (v === null || v === undefined ? '' : String(v));
    const newItem = () => ({ food: '', quantity: '', notes: '' });
    const newOption = (n) => ({ name: `Opção ${n}`, notes: '', items: [newItem()] });
    const newMeal = () => ({ name: '', meal_time: '', notes: '', options: [newOption(1)] });

    // Normaliza o que veio do servidor (ou do rascunho) para o formato do estado.
    const state = (Array.isArray(data.meals) ? data.meals : []).map((m) => ({
        name: str(m?.name),
        meal_time: str(m?.meal_time).slice(0, 5),
        notes: str(m?.notes),
        options: (Array.isArray(m?.options) && m.options.length ? m.options : [{ name: 'Opção 1', items: [] }]).map((o) => ({
            name: str(o?.name),
            notes: str(o?.notes),
            items: (Array.isArray(o?.items) ? o.items : []).map((i) => ({
                food: str(i?.food), quantity: str(i?.quantity), notes: str(i?.notes),
            })),
        })),
    }));

    let dirty = false;
    const markDirty = () => { dirty = true; dirtyEl.hidden = false; };

    const fill = (node, obj) => {
        node.querySelectorAll(':scope [data-k]').forEach((input) => {
            // Só os campos deste nível (não os dos filhos).
            if (input.closest('[data-level]') === node) input.value = obj[input.dataset.k] ?? '';
        });
    };

    const swap = (list, i, j) => {
        if (j < 0 || j >= list.length) return false;
        [list[i], list[j]] = [list[j], list[i]];
        return true;
    };

    /* ---------- Render ---------- */
    const render = () => {
        mealsEl.innerHTML = '';
        state.forEach((meal, m) => {
            const mealNode = mealTpl.cloneNode(true);
            mealNode.dataset.level = 'meal';
            mealNode.dataset.m = m;
            mealNode.querySelector('.ex-num').textContent = String(m + 1).padStart(2, '0');
            fill(mealNode, meal);
            mealNode.querySelector('[data-act="meal-up"]').disabled = m === 0;
            mealNode.querySelector('[data-act="meal-down"]').disabled = m === state.length - 1;
            mealNode.querySelector('[data-act="option-add"]').hidden = meal.options.length >= data.maxOptions;

            const optionsEl = mealNode.querySelector('[data-options]');
            meal.options.forEach((option, o) => {
                const optNode = optionTpl.cloneNode(true);
                optNode.dataset.level = 'option';
                optNode.dataset.o = o;
                fill(optNode, option);
                optNode.querySelector('[data-k="name"]').placeholder = `Opção ${o + 1}`;
                // Uma refeição sempre tem pelo menos uma opção.
                optNode.querySelector('[data-act="option-remove"]').hidden = meal.options.length === 1;
                optNode.querySelector('[data-act="item-add"]').hidden = option.items.length >= data.maxItems;

                const itemsEl = optNode.querySelector('[data-items]');
                option.items.forEach((item, i) => {
                    const itemNode = itemTpl.cloneNode(true);
                    itemNode.dataset.level = 'item';
                    itemNode.dataset.i = i;
                    fill(itemNode, item);
                    itemNode.querySelector('[data-act="item-up"]').disabled = i === 0;
                    itemNode.querySelector('[data-act="item-down"]').disabled = i === option.items.length - 1;
                    itemsEl.appendChild(itemNode);
                });
                optionsEl.appendChild(optNode);
            });
            mealsEl.appendChild(mealNode);
        });

        emptyEl.hidden = state.length > 0;
        addMore.hidden = state.length === 0 || state.length >= data.maxMeals;
        form.querySelectorAll('[data-add-meal]').forEach((b) => { b.disabled = state.length >= data.maxMeals; });
        summary();
    };

    const summary = () => {
        const options = state.reduce((n, m) => n + m.options.length, 0);
        const items = state.reduce((n, m) => n + m.options.reduce((k, o) => k + o.items.filter((i) => i.food.trim()).length, 0), 0);
        form.querySelector('[data-sum-meals]').textContent = state.length;
        form.querySelector('[data-sum-options]').textContent = options;
        form.querySelector('[data-sum-items]').textContent = items;
    };

    /** Caminho [m, o, i] do elemento na árvore (o e i podem ser undefined). */
    const path = (el) => {
        const idx = (level, key) => {
            const node = el.closest(`[data-level="${level}"]`);
            return node ? Number(node.dataset[key]) : undefined;
        };
        return [idx('meal', 'm'), idx('option', 'o'), idx('item', 'i')];
    };

    /** Objeto do estado ao qual o campo pertence. */
    const target = (input) => {
        const [m, o, i] = path(input);
        const level = input.closest('[data-level]').dataset.level;
        if (level === 'meal') return state[m];
        if (level === 'option') return state[m].options[o];
        return state[m].options[o].items[i];
    };

    /* ---------- Edição dos campos ---------- */
    mealsEl.addEventListener('input', (e) => {
        const input = e.target.closest('[data-k]');
        if (!input) return;
        target(input)[input.dataset.k] = input.value;
        markDirty();
        summary();
    });

    /* ---------- Ações ---------- */
    mealsEl.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-act]');
        if (!btn) return;
        const [m, o, i] = path(btn);
        const meal = state[m];
        const act = btn.dataset.act;
        let focus = null;

        switch (act) {
            case 'meal-up': swap(state, m, m - 1); break;
            case 'meal-down': swap(state, m, m + 1); break;
            case 'meal-remove': {
                const filled = meal.name.trim() || meal.options.some((op) => op.items.some((it) => it.food.trim()));
                if (filled && !window.confirm(`Remover ${meal.name.trim() || 'esta refeição'} e todos os alimentos dela?`)) return;
                state.splice(m, 1);
                break;
            }
            case 'option-add':
                if (meal.options.length >= data.maxOptions) return;
                meal.options.push(newOption(meal.options.length + 1));
                focus = [m, meal.options.length - 1, 0, 'food'];
                break;
            case 'option-remove':
                if (meal.options.length > 1) meal.options.splice(o, 1);
                break;
            case 'item-add': {
                const items = meal.options[o].items;
                if (items.length >= data.maxItems) return;
                items.push(newItem());
                focus = [m, o, items.length - 1, 'food'];
                break;
            }
            case 'item-up': swap(meal.options[o].items, i, i - 1); break;
            case 'item-down': swap(meal.options[o].items, i, i + 1); break;
            case 'item-remove': meal.options[o].items.splice(i, 1); break;
            default: return;
        }

        markDirty();
        render();
        if (focus) focusField(...focus);
    });

    const focusField = (m, o, i, key) => {
        const meal = mealsEl.querySelector(`[data-level="meal"][data-m="${m}"]`);
        const scope = o === undefined ? meal
            : i === undefined ? meal?.querySelector(`[data-level="option"][data-o="${o}"]`)
            : meal?.querySelector(`[data-level="option"][data-o="${o}"] [data-level="item"][data-i="${i}"]`);
        scope?.querySelector(`[data-k="${key}"]`)?.focus();
    };

    form.querySelectorAll('[data-add-meal]').forEach((btn) => btn.addEventListener('click', () => {
        if (state.length >= data.maxMeals) return;
        state.push(newMeal());
        markDirty();
        render();
        focusField(state.length - 1, undefined, undefined, 'name');
    }));

    // Refeições sem horário vão para o fim, mantendo a ordem entre si.
    form.querySelector('[data-sort-time]').addEventListener('click', () => {
        const keyed = state.map((meal, idx) => ({ meal, idx }));
        keyed.sort((a, b) => {
            const ta = a.meal.meal_time || '99:99';
            const tb = b.meal.meal_time || '99:99';
            return ta === tb ? a.idx - b.idx : ta.localeCompare(tb);
        });
        state.splice(0, state.length, ...keyed.map((k) => k.meal));
        markDirty();
        render();
    });

    /* ---------- Envio ---------- */
    form.addEventListener('submit', () => {
        form.querySelector('[data-meals-input]').value = JSON.stringify(state);
        dirty = false;
    });

    window.addEventListener('beforeunload', (e) => {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    render();
})();
