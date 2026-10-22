// Teixit — © 2026 José Vicente Sanjuan · SPDX-License-Identifier: AGPL-3.0-or-later
document.addEventListener('DOMContentLoaded', () => {
    // Files clicables als llistats
    document.querySelectorAll('tr[data-href]').forEach(tr => {
        tr.addEventListener('click', e => {
            if (!e.target.closest('a, button, input, select, textarea')) location.href = tr.dataset.href;
        });
    });

    // Desplegables llargs: camp per a filtrar les opcions
    document.querySelectorAll('select[data-cerca]').forEach(sel => {
        const cerca = document.createElement('input');
        cerca.type = 'search';
        cerca.className = 'filtre-select';
        cerca.placeholder = '🔍';
        sel.parentNode.insertBefore(cerca, sel);
        const normal = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        cerca.addEventListener('input', () => {
            const q = normal(cerca.value);
            let primer = null;
            for (const o of sel.options) {
                const ok = !o.value || normal(o.text).includes(q);
                o.hidden = !ok;
                if (ok && o.value && !primer) primer = o;
            }
            if (q && primer && (sel.selectedIndex < 1 || sel.options[sel.selectedIndex].hidden)) sel.value = primer.value;
        });
    });

    // Camps de relació amb moltes opcions: s'escriu i el servidor proposa (societats amb milers de persones)
    document.querySelectorAll('input[data-cerca-ref]').forEach(inp => {
        const ocult = inp.form.querySelector('input[type="hidden"][name="' + inp.dataset.camp + '"]');
        const llista = inp.parentNode.querySelector('.resultats-ref');
        let temps = null, actiu = -1, triat = inp.value;
        const tanca = () => { llista.hidden = true; actiu = -1; };
        const tria = li => { ocult.value = li.dataset.id; inp.value = triat = li.textContent; tanca(); };
        const marca = i => {
            const els = llista.querySelectorAll('li');
            if (!els.length) return;
            actiu = (i + els.length) % els.length;
            els.forEach((li, j) => li.classList.toggle('actiu', j === actiu));
        };
        inp.addEventListener('input', () => {
            if (inp.value !== triat) ocult.value = '';
            clearTimeout(temps);
            const q = inp.value.trim();
            if (q.length < 2) { tanca(); return; }
            temps = setTimeout(async () => {
                const r = await fetch('?r=cerca_ref&e=' + encodeURIComponent(inp.dataset.cercaRef) + '&q=' + encodeURIComponent(q));
                const dades = r.ok ? await r.json() : [];
                llista.replaceChildren(...dades.map(d => {
                    const li = document.createElement('li');
                    li.textContent = d.text;
                    li.dataset.id = d.id;
                    return li;
                }));
                if (!dades.length) llista.append(Object.assign(document.createElement('li'), { textContent: '—', className: 'buit' }));
                llista.hidden = false;
                actiu = -1;
            }, 200);
        });
        llista.addEventListener('mousedown', e => { const li = e.target.closest('li[data-id]'); if (li) { e.preventDefault(); tria(li); } });
        inp.addEventListener('keydown', e => {
            if (llista.hidden) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); marca(actiu + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); marca(actiu - 1); }
            else if (e.key === 'Enter' && actiu >= 0) { e.preventDefault(); tria(llista.querySelectorAll('li')[actiu]); }
            else if (e.key === 'Escape') tanca();
        });
        inp.addEventListener('blur', () => setTimeout(() => { tanca(); if (!ocult.value) inp.value = triat = ''; }, 150));
    });

    // DNI i telèfon obligatoris només per a majors d'edat
    const naix = document.querySelector('input[name="data_naixement"]');
    const majors = document.querySelectorAll('input[data-major]');
    const nomesMajors = document.querySelectorAll('[data-nomes-major]');
    if (naix && (majors.length || nomesMajors.length)) {
        const actualitza = () => {
            let major = false;
            if (naix.value) {
                const d = new Date(naix.value + 'T00:00:00'), limit = new Date();
                limit.setFullYear(limit.getFullYear() - 18);
                major = d <= limit;
            }
            majors.forEach(i => {
                i.required = major;
                const marca = i.closest('.camp')?.querySelector('.obl-major');
                if (marca) marca.hidden = !major;
            });
            // L'IBAN només per als majors d'edat: amagat i sense enviar per als menors
            nomesMajors.forEach(c => {
                c.hidden = !major;
                c.querySelectorAll('input').forEach(i => { i.disabled = !major; });
            });
        };
        naix.addEventListener('input', actualitza);
        naix.addEventListener('change', actualitza);
        actualitza();
    }

    // Uniformes: la llista de talles segueix l'article triat
    document.querySelectorAll('input[data-talles]').forEach(inp => {
        const talles = JSON.parse(inp.dataset.talles || '{}');
        const llista = document.getElementById(inp.getAttribute('list'));
        const article = inp.form && inp.form.querySelector('select[name="uniforme_id"]');
        if (!llista || !article) return;
        const posa = () => {
            const t = talles[article.value];
            if (!t) return;
            llista.replaceChildren(...t.map(x => Object.assign(document.createElement('option'), { value: x })));
        };
        article.addEventListener('change', posa);
        posa();
        // En una venda nova, l'import es proposa amb el preu de l'article
        const preus = JSON.parse(inp.dataset.preus || '{}');
        const imp = inp.form.querySelector('input[name="import"]'), tipus = inp.form.querySelector('select[name="tipus"]'), qt = inp.form.querySelector('input[name="quantitat"]');
        if (imp && tipus && qt) {
            let proposat = imp.value === '';
            const preu = () => {
                if (!proposat || tipus.value !== 'venda' || preus[article.value] == null) return;
                imp.value = (preus[article.value] * (parseInt(qt.value, 10) || 1)).toFixed(2).replace('.', ',');
            };
            imp.addEventListener('input', () => { proposat = false; });
            [article, tipus, qt].forEach(x => x.addEventListener('change', preu));
        }
    });

    // Menú en pantalles menudes
    const boto = document.querySelector('.obre-menu');
    if (boto) boto.addEventListener('click', () => boto.closest('.lateral').classList.toggle('obert'));

    // Avís si s'abandona un formulari modificat
    document.querySelectorAll('form.formulari').forEach(f => {
        let canviat = false;
        f.addEventListener('input', () => canviat = true);
        f.addEventListener('submit', () => canviat = false);
        window.addEventListener('beforeunload', e => { if (canviat) { e.preventDefault(); e.returnValue = ''; } });
    });
});
