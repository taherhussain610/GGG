(() => {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const appBase = (document.querySelector('meta[name="app-base"]')?.content ?? '').replace(/\/$/, '');
    const endpoint = (path) => `${appBase}/${path.replace(/^\//, '')}`;

    const postJson = async (path, payload) => {
        const response = await fetch(endpoint(path), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(payload),
        });
        let data;
        try {
            data = await response.json();
        } catch {
            throw new Error('The server returned an invalid response.');
        }
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'The request could not be completed.');
        }
        return data;
    };

    const setBalance = (label) => {
        if (!label) return;
        document.querySelectorAll('[data-balance]').forEach((element) => {
            element.textContent = label;
        });
    };

    const setBusy = (button, busy) => {
        if (!button) return;
        button.disabled = busy;
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
    };

    const renderResult = (container, result) => {
        if (!container || !result) return;
        const symbols = container.querySelector('[data-result-symbols]');
        if (symbols && Array.isArray(result.display)) {
            symbols.replaceChildren();
            result.display.forEach((value) => {
                const symbol = document.createElement('span');
                symbol.textContent = String(value);
                symbols.append(symbol);
            });
        }
        const title = container.querySelector('[data-result-title]');
        const message = container.querySelector('[data-result-message]');
        if (title) title.textContent = result.title || 'Round complete';
        if (message) message.textContent = result.message || '';
        container.classList.toggle('is-win', Boolean(result.won));
        container.classList.remove('result-pop');
        window.requestAnimationFrame(() => container.classList.add('result-pop'));
    };

    const setupGameFilters = () => {
        const search = document.querySelector('[data-game-search]');
        const buttons = [...document.querySelectorAll('[data-game-filter]')];
        const cards = [...document.querySelectorAll('[data-game-card]')];
        const empty = document.querySelector('[data-game-empty]');
        const count = document.querySelector('[data-game-count]');
        if (!search || cards.length === 0) return;

        let category = buttons.find((button) => button.classList.contains('is-active'))?.dataset.gameFilter || 'all';
        const apply = () => {
            const term = search.value.trim().toLowerCase();
            let visible = 0;
            cards.forEach((card) => {
                const matchesCategory = category === 'all' || card.dataset.category === category;
                const matchesSearch = !term || (card.dataset.title || '').includes(term);
                const show = matchesCategory && matchesSearch;
                card.hidden = !show;
                if (show) visible++;
            });
            if (empty) empty.hidden = visible !== 0;
            if (count) count.textContent = `${visible} ${visible === 1 ? 'game' : 'games'}`;
        };

        search.addEventListener('input', apply);
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                buttons.forEach((item) => item.classList.remove('is-active'));
                button.classList.add('is-active');
                category = button.dataset.gameFilter || 'all';
                apply();
            });
        });
        apply();
    };

    const setupGames = () => {
        document.querySelectorAll('[data-game-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const button = form.querySelector('button[type="submit"]');
                const feedback = form.closest('.game-stage')?.querySelector('[data-game-feedback]');
                const display = form.closest('.game-stage')?.querySelector('[data-game-display]');
                const values = Object.fromEntries(new FormData(form).entries());
                values.game = form.dataset.game;
                values.bet = Number(values.bet);
                setBusy(button, true);
                if (feedback) feedback.textContent = 'Generating a secure server result…';
                try {
                    const response = await postJson('/api/play.php', values);
                    renderResult(display, response.result);
                    setBalance(response.balance_label);
                    if (feedback) feedback.textContent = `Round #${response.round_id} · +${response.xp_awarded} XP`;
                } catch (error) {
                    if (feedback) feedback.textContent = error.message;
                } finally {
                    setBusy(button, false);
                }
            });
        });
    };

    const renderPokerHand = (container, hand, selectable) => {
        const oldHand = container.querySelector('[data-poker-hand]');
        const oldSymbols = container.querySelector('[data-result-symbols]');
        oldHand?.remove();
        oldSymbols?.remove();

        const handElement = document.createElement('div');
        handElement.className = 'poker-hand';
        handElement.dataset.pokerHand = '';
        hand.forEach((card, index) => {
            if (selectable) {
                const label = document.createElement('label');
                label.className = 'playing-card';
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.value = String(index);
                input.dataset.pokerHold = '';
                const face = document.createElement('span');
                face.textContent = String(card);
                const hint = document.createElement('small');
                hint.textContent = 'Hold';
                label.append(input, face, hint);
                handElement.append(label);
            } else {
                const cardElement = document.createElement('span');
                cardElement.className = 'playing-card final-card';
                cardElement.textContent = String(card);
                handElement.append(cardElement);
            }
        });
        container.prepend(handElement);
    };

    const setupPoker = () => {
        const form = document.querySelector('[data-poker-form]');
        if (!form) return;
        const button = form.querySelector('button[type="submit"]');
        const bet = form.querySelector('[name="bet"]');
        const feedback = form.closest('.game-stage')?.querySelector('[data-game-feedback]');
        const display = form.closest('.game-stage')?.querySelector('[data-game-display]');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const stage = form.dataset.stage || 'deal';
            const payload = {action: stage};
            if (stage === 'deal') {
                payload.bet = Number(bet.value);
            } else {
                payload.round_id = Number(form.dataset.roundId);
                payload.holds = [...document.querySelectorAll('[data-poker-hold]:checked')]
                    .map((input) => Number(input.value));
            }

            setBusy(button, true);
            if (feedback) feedback.textContent = stage === 'deal' ? 'Shuffling on the server…' : 'Drawing cards…';
            try {
                const response = await postJson('/api/poker.php', payload);
                setBalance(response.balance_label);
                if (response.stage === 'draw') {
                    renderPokerHand(display, response.hand, true);
                    display.querySelector('[data-result-title]').textContent = 'Choose your holds';
                    display.querySelector('[data-result-message]').textContent = response.message;
                    form.dataset.stage = 'draw';
                    form.dataset.roundId = String(response.round_id);
                    bet.disabled = true;
                    button.textContent = 'Draw cards';
                    if (feedback) feedback.textContent = `Round #${response.round_id} · choose up to five cards`;
                } else {
                    renderPokerHand(display, response.result.display || [], false);
                    const title = display.querySelector('[data-result-title]');
                    const message = display.querySelector('[data-result-message]');
                    if (title) title.textContent = response.result.title;
                    if (message) message.textContent = response.result.message;
                    display.classList.toggle('is-win', Boolean(response.result.won));
                    form.dataset.stage = 'deal';
                    form.dataset.roundId = '';
                    bet.disabled = false;
                    button.textContent = 'Deal five cards';
                    if (feedback) feedback.textContent = `Round #${response.round_id} complete`;
                }
            } catch (error) {
                if (feedback) feedback.textContent = error.message;
            } finally {
                setBusy(button, false);
            }
        });
    };

    const setupSports = () => {
        const filters = [...document.querySelectorAll('[data-sport-filter]')];
        const cards = [...document.querySelectorAll('[data-sport-card]')];
        filters.forEach((button) => {
            button.addEventListener('click', () => {
                filters.forEach((item) => item.classList.remove('is-active'));
                button.classList.add('is-active');
                const selected = button.dataset.sportFilter;
                cards.forEach((card) => {
                    card.hidden = selected !== 'all' && card.dataset.sportCard !== selected;
                });
            });
        });

        document.querySelectorAll('[data-pick-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const selected = form.querySelector('[name="selection"]:checked');
                const feedback = form.querySelector('[data-pick-feedback]');
                const button = form.querySelector('button[type="submit"]');
                if (!selected || !button) return;
                setBusy(button, true);
                if (feedback) feedback.textContent = 'Placing prediction…';
                try {
                    const response = await postJson('/api/place_pick.php', {
                        event_id: Number(form.querySelector('[name="event_id"]').value),
                        selection: selected.value,
                        stake: Number(form.querySelector('[name="stake"]').value),
                    });
                    setBalance(response.balance_label);
                    if (feedback) feedback.textContent = response.message;
                    form.reset();
                } catch (error) {
                    if (feedback) feedback.textContent = error.message;
                } finally {
                    setBusy(button, false);
                }
            });
        });

        const feedStatus = document.querySelector('[data-feed-status]');
        if (!feedStatus) return;
        const refreshFeed = async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(endpoint('/api/sports.php'), {
                    headers: {'Accept': 'application/json'},
                    credentials: 'same-origin',
                });
                if (!response.ok) return;
                const data = await response.json();
                const generated = new Date(data.generated_at);
                feedStatus.lastChild.textContent = ` Synced ${generated.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}`;
            } catch {
                feedStatus.lastChild.textContent = ' Reconnecting…';
            }
        };
        window.setInterval(refreshFeed, 20000);
    };

    const setupBonus = () => {
        const button = document.querySelector('[data-bonus-claim]');
        const feedback = document.querySelector('[data-bonus-feedback]');
        if (!button || !feedback) return;

        const nextClaim = feedback.dataset.nextClaim ? new Date(feedback.dataset.nextClaim) : null;
        if (nextClaim && !Number.isNaN(nextClaim.getTime())) {
            const updateCountdown = () => {
                const remaining = nextClaim.getTime() - Date.now();
                if (remaining <= 0) {
                    button.disabled = false;
                    button.textContent = 'Claim daily credits';
                    feedback.textContent = 'Ready now.';
                    return;
                }
                const hours = Math.floor(remaining / 3600000);
                const minutes = Math.floor((remaining % 3600000) / 60000);
                feedback.textContent = `Next reward in ${hours}h ${minutes}m.`;
            };
            updateCountdown();
            window.setInterval(updateCountdown, 60000);
        }

        button.addEventListener('click', async () => {
            setBusy(button, true);
            feedback.textContent = 'Opening your daily drop…';
            try {
                const response = await postJson('/api/bonus.php', {});
                setBalance(response.balance_label);
                button.textContent = 'Reward claimed';
                button.disabled = true;
                feedback.textContent = response.message;
            } catch (error) {
                feedback.textContent = error.message;
                button.disabled = false;
            }
        });
    };

    const setupTabs = () => {
        document.querySelectorAll('[data-tabs]').forEach((tabs) => {
            const buttons = [...tabs.querySelectorAll('[role="tab"]')];
            buttons.forEach((button, index) => {
                button.addEventListener('click', () => {
                    buttons.forEach((candidate) => {
                        const selected = candidate === button;
                        candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
                        const panel = document.getElementById(candidate.getAttribute('aria-controls'));
                        if (panel) panel.hidden = !selected;
                    });
                });
                button.addEventListener('keydown', (event) => {
                    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                    event.preventDefault();
                    const direction = event.key === 'ArrowRight' ? 1 : -1;
                    buttons[(index + direction + buttons.length) % buttons.length].focus();
                });
            });
        });
    };

    const pollBalance = () => {
        if (document.body.dataset.authenticated !== 'true') return;
        window.setInterval(async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(endpoint('/api/balance.php'), {
                    headers: {'Accept': 'application/json'},
                    credentials: 'same-origin',
                });
                if (!response.ok) return;
                const data = await response.json();
                setBalance(data.balance_label);
            } catch {
                // A transient poll failure should not interrupt play.
            }
        }, 30000);
    };

    setupGameFilters();
    setupGames();
    setupPoker();
    setupSports();
    setupBonus();
    setupTabs();
    pollBalance();
})();
