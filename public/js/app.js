(() => {
    'use strict';

    const API = {
        cleanest: '/api/cleanest-city',
        history: '/api/history',
        health: '/up',
    };

    const BANDS = [
        { max: 10, key: 'good', label: 'Good' },
        { max: 20, key: 'fair', label: 'Fair' },
        { max: 25, key: 'moderate', label: 'Moderate' },
        { max: 50, key: 'poor', label: 'Poor' },
        { max: 75, key: 'very-poor', label: 'Very poor' },
        { max: Infinity, key: 'extremely-poor', label: 'Extremely poor' },
    ];

    const $ = (id) => document.getElementById(id);

    const form = $('search-form');
    const fromInput = $('from');
    const toInput = $('to');
    const submitButton = $('submit');
    const formError = $('form-error');
    const historyHint = $('history-hint');
    const winnerCard = $('winner');
    const rankingList = $('ranking');
    const rankingCaption = $('ranking-caption');
    const mapCities = document.querySelectorAll('.map__city');
    const placeholderRanking = [...rankingList.children].map((li) => li.cloneNode(true));

    let history = null;
    const pickers = { from: null, to: null };

    function toIsoDate(date) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function parseIsoDate(value) {
        const [y, m, d] = value.split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    function formatDate(value) {
        return parseIsoDate(value).toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }

    function yesterday() {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        return d;
    }

    function latestDate() {
        return history ? parseIsoDate(history.to) : yesterday();
    }

    function setDate(name, value) {
        const picker = pickers[name];
        if (picker) {
            picker.setDate(value, false);
        } else {
            (name === 'from' ? fromInput : toInput).value = value;
        }
    }

    function setPeriod(from, to) {
        let start = from;
        let clamped = false;
        if (history && toIsoDate(start) < history.from) {
            start = parseIsoDate(history.from);
            clamped = true;
        }
        setDate('from', toIsoDate(start));
        applyLimits();
        setDate('to', toIsoDate(to));
        clearErrors();
        return clamped;
    }

    function visibleInput(name) {
        const picker = pickers[name];
        return picker?.altInput ?? (name === 'from' ? fromInput : toInput);
    }

    function onPickerChange() {
        applyLimits();
        clearErrors();
        setActiveChip(null);
        showHistoryHint(false);
    }

    function createPicker(input) {
        if (!window.flatpickr) {
            return null;
        }

        const plugins = window.confirmDatePlugin
            ? [new window.confirmDatePlugin({ confirmText: 'OK', showAlways: true, theme: 'light' })]
            : [];

        const picker = window.flatpickr(input, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altInputClass: 'date-input',
            altFormat: 'j M Y',
            disableMobile: true,
            closeOnSelect: false,
            monthSelectorType: 'static',
            locale: { firstDayOfWeek: 1 },
            plugins,
            onChange: onPickerChange,
        });

        picker.altInput.id = `${input.id}-display`;
        document.querySelector(`label[for="${input.id}"]`)?.setAttribute('for', picker.altInput.id);

        return picker;
    }

    function applyLimits() {
        const min = history?.from ?? null;
        const max = history?.to ?? toIsoDate(new Date());

        if (pickers.from) {
            pickers.from.set('minDate', min);
            pickers.from.set('maxDate', max);
        }

        if (pickers.to) {
            pickers.to.set('minDate', fromInput.value || min);
            pickers.to.set('maxDate', max);
        }
    }

    function setActiveChip(active) {
        document.querySelectorAll('.chip[data-days], .chip[data-months]').forEach((chip) => {
            chip.setAttribute('aria-pressed', String(chip === active));
        });
    }

    function showHistoryHint(clamped) {
        if (!history) return;
        const base = `Stored history: ${formatDate(history.from)} – ${formatDate(history.to)}.`;
        historyHint.textContent = clamped ? `${base} The period was shortened to the stored data.` : base;
    }

    function bandFor(value) {
        return BANDS.find((band) => value < band.max);
    }

    function pm25Of(item) {
        const value = item.avg_pm25 ?? item.average_pm25 ?? item.pm25 ?? null;
        return value === null || value === '' ? null : Number(value);
    }

    function formatPm(value) {
        return value.toFixed(1);
    }

    function clearErrors() {
        formError.hidden = true;
        formError.replaceChildren();
        visibleInput('from').removeAttribute('aria-invalid');
        visibleInput('to').removeAttribute('aria-invalid');
    }

    function showErrors(messages, fields = []) {
        formError.replaceChildren();

        if (messages.length === 1) {
            formError.textContent = messages[0];
        } else {
            const intro = document.createElement('strong');
            intro.textContent = 'Please fix the following:';
            const list = document.createElement('ul');
            for (const message of messages) {
                const li = document.createElement('li');
                li.textContent = message;
                list.append(li);
            }
            formError.append(intro, list);
        }

        for (const field of fields) {
            if (field === 'from' || field === 'to') {
                visibleInput(field).setAttribute('aria-invalid', 'true');
            }
        }

        formError.hidden = false;
        resetResults();
    }

    function resetResults() {
        winnerCard.hidden = true;
        rankingCaption.textContent = 'Pick a period to rank the cities';
        rankingList.replaceChildren(...placeholderRanking.map((li) => li.cloneNode(true)));
        mapCities.forEach((group) => {
            group.removeAttribute('data-band');
            group.classList.remove('is-winner');
            group.querySelector('.map__value').textContent = '';
        });
    }

    function updateMap(withData, winner) {
        const byCity = new Map(withData.map((item) => [item.city.toLowerCase(), item]));
        mapCities.forEach((group) => {
            const item = byCity.get(group.dataset.city.toLowerCase());
            const value = group.querySelector('.map__value');
            group.classList.toggle('is-winner', item === winner);
            if (item) {
                group.dataset.band = bandFor(item.pm25).key;
                value.textContent = `${formatPm(item.pm25)} µg/m³`;
            } else {
                group.removeAttribute('data-band');
                value.textContent = 'no data';
            }
        });
    }

    function validate(from, to) {
        const errors = [];
        const fields = new Set();

        if (!from) {
            errors.push('Choose a start date.');
            fields.add('from');
        }
        if (!to) {
            errors.push('Choose an end date.');
            fields.add('to');
        }
        if (errors.length) return { errors, fields: [...fields] };

        if (to < from) {
            errors.push('The end date must be on or after the start date.');
            fields.add('to');
        }

        const today = toIsoDate(new Date());
        if (to > today) {
            errors.push('The end date cannot be in the future.');
            fields.add('to');
        }

        if (history) {
            if (from < history.from || to > history.to) {
                errors.push(
                    `Data is only available from ${formatDate(history.from)} to ${formatDate(history.to)}.`
                );
                if (from < history.from) fields.add('from');
                if (to > history.to) fields.add('to');
            }
        }

        return { errors, fields: [...fields] };
    }

    async function getJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        let body = null;
        try {
            body = await response.json();
        } catch {}
        return { response, body };
    }

    function errorsFromResponse(response, body) {
        if (response.status === 422 && body) {
            const fieldErrors = body.errors ?? {};
            const messages = Object.values(fieldErrors).flat();
            return {
                messages: messages.length ? messages : [body.message ?? 'The request is not valid.'],
                fields: Object.keys(fieldErrors),
            };
        }
        if (response.status === 404) {
            return { messages: ['The air-quality service is not available yet. Please try again later.'], fields: [] };
        }
        if (response.status === 429) {
            return { messages: ['Too many requests. Please wait a moment and try again.'], fields: [] };
        }
        return {
            messages: [body?.message || `Something went wrong on the server (HTTP ${response.status}). Please try again.`],
            fields: [],
        };
    }

    async function loadHistory() {
        try {
            const { response, body } = await getJson(API.history);
            if (!response.ok || !body?.from || !body?.to) return;

            history = { from: body.from, to: body.to };
            showHistoryHint(false);
        } catch {}
    }

    async function checkHealth() {
        const status = $('api-status');
        const label = status.querySelector('.status__label');
        try {
            const response = await fetch(API.health, { cache: 'no-store' });
            status.dataset.state = response.ok ? 'up' : 'down';
            label.textContent = response.ok ? 'Service online' : 'Service unavailable';
        } catch {
            status.dataset.state = 'down';
            label.textContent = 'Service unreachable';
        }
    }

    function revealResults() {
        const top = winnerCard.getBoundingClientRect().top;
        if (top < 0 || top > window.innerHeight * 0.75) {
            winnerCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function renderResults(data, from, to, reveal) {
        const ranking = (data.ranking ?? data.cities ?? []).map((item) => ({
            ...item,
            pm25: pm25Of(item),
        }));

        const withData = ranking
            .filter((item) => item.pm25 !== null && !Number.isNaN(item.pm25))
            .sort((a, b) => a.pm25 - b.pm25);
        const withoutData = ranking.filter((item) => item.pm25 === null || Number.isNaN(item.pm25));

        if (!withData.length) {
            showErrors(['No measurements were found for any city in this period. Try a different range.']);
            return;
        }

        const winner = withData[0];
        const winnerBand = bandFor(winner.pm25);
        const period = `${formatDate(data.from ?? from)} – ${formatDate(data.to ?? to)}`;

        $('winner-city').textContent = winner.city;
        $('winner-period').textContent = period;
        $('winner-value').textContent = formatPm(winner.pm25);
        $('winner-band').textContent = winnerBand.label;
        $('winner-band').dataset.band = winnerBand.key;

        const max = Math.max(...withData.map((item) => item.pm25));
        rankingList.replaceChildren(
            ...withData.map((item, index) => rankingItem(item, index + 1, max)),
            ...withoutData.map((item) => rankingItem(item, '–', max))
        );
        rankingCaption.textContent = 'Average PM2.5, lowest first';
        updateMap(withData, winner);

        winnerCard.hidden = false;
        if (reveal) {
            revealResults();
        }
    }

    function rankingItem(item, rank, max) {
        const li = document.createElement('li');
        li.className = 'ranking__item';

        const rankEl = document.createElement('span');
        rankEl.className = 'ranking__rank';
        rankEl.textContent = rank;

        const cityEl = document.createElement('span');
        cityEl.className = 'ranking__city';
        cityEl.textContent = item.city;
        if (item.sensors !== undefined) {
            const meta = document.createElement('span');
            meta.className = 'ranking__meta';
            meta.textContent = `${item.sensors} sensor${item.sensors === 1 ? '' : 's'}`;
            cityEl.append(meta);
        }

        const track = document.createElement('span');
        track.className = 'ranking__track';
        const bar = document.createElement('span');
        bar.className = 'ranking__bar';
        track.append(bar);

        const valueEl = document.createElement('span');
        valueEl.className = 'ranking__value';

        if (item.pm25 === null || Number.isNaN(item.pm25)) {
            li.classList.add('ranking__item--nodata');
            bar.style.width = '0';
            valueEl.textContent = 'No data';
        } else {
            const band = bandFor(item.pm25);
            bar.style.setProperty('--bar', `var(--band-${band.key})`);
            bar.style.width = '0';
            requestAnimationFrame(() => {
                bar.style.width = `${Math.max(4, (item.pm25 / max) * 100)}%`;
            });
            valueEl.textContent = formatPm(item.pm25);
            const unit = document.createElement('small');
            unit.textContent = ' µg/m³';
            valueEl.append(unit);
            li.title = `${item.city}: ${formatPm(item.pm25)} µg/m³ (${band.label})`;
        }

        li.append(rankEl, cityEl, track, valueEl);
        return li;
    }

    function setLoading(loading) {
        submitButton.disabled = loading;
        submitButton.classList.toggle('is-loading', loading);
        submitButton.querySelector('.button__label').textContent = loading ? 'Searching…' : 'Find cleanest city';
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();

        const from = fromInput.value;
        const to = toInput.value;

        const { errors, fields } = validate(from, to);
        if (errors.length) {
            showErrors(errors, fields);
            return;
        }

        setLoading(true);
        try {
            const query = new URLSearchParams({ from, to });
            const { response, body } = await getJson(`${API.cleanest}?${query}`);

            if (!response.ok || !body) {
                const { messages, fields: badFields } = errorsFromResponse(response, body);
                showErrors(messages, badFields);
                return;
            }

            renderResults(body, from, to, event.submitter === submitButton);
        } catch {
            showErrors(['Could not reach the server. Check your connection and try again.']);
        } finally {
            setLoading(false);
        }
    });

    document.querySelectorAll('.chip[data-days], .chip[data-months]').forEach((chip) => {
        chip.addEventListener('click', () => {
            const end = latestDate();
            const start = new Date(end);
            if (chip.dataset.days) {
                start.setDate(start.getDate() - Number(chip.dataset.days) + 1);
            } else {
                start.setMonth(start.getMonth() - Number(chip.dataset.months));
                start.setDate(start.getDate() + 1);
            }
            showHistoryHint(setPeriod(start, end));
            setActiveChip(chip);
            form.requestSubmit();
        });
    });

    [fromInput, toInput].forEach((input) =>
        input.addEventListener('input', () => {
            clearErrors();
            setActiveChip(null);
            showHistoryHint(false);
        })
    );

    (async () => {
        pickers.from = createPicker(fromInput);
        pickers.to = createPicker(toInput);
        await loadHistory();
        applyLimits();
        const end = latestDate();
        const start = new Date(end);
        start.setDate(start.getDate() - 29);
        setPeriod(start, end);
    })();

    checkHealth();
})();
