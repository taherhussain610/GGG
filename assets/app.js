async function refreshBalance() {
    const target = document.querySelector('[data-balance]');
    if (!target || document.body.dataset.auth !== '1') return;
    try {
        const response = await fetch('/api/balance.php', { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        if (data.balance_formatted) target.textContent = data.balance_formatted;
    } catch (_) {}
}

async function refreshLivePanels() {
    const sportsTarget = document.querySelector('[data-live-sports]');
    if (sportsTarget) {
        try {
            const response = await fetch('/api/sports.php', { headers: { 'Accept': 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            sportsTarget.textContent = `${data.live_count ?? 0} live · ${data.upcoming_count ?? 0} upcoming`;
        } catch (_) {}
    }

    const resultsTarget = document.querySelector('[data-live-results]');
    if (resultsTarget) {
        try {
            const response = await fetch('/api/results.php', { headers: { 'Accept': 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            resultsTarget.textContent = `${(data.sports ?? []).length} sports results · ${(data.casino ?? []).length} casino logs`;
        } catch (_) {}
    }
}

async function refreshDashboardWidgets() {
    const players = document.querySelector('[data-dashboard-players]');
    const topBalance = document.querySelector('[data-dashboard-top-balance]');
    const openPicks = document.querySelector('[data-dashboard-open-picks]');
    const jackpotTotal = document.querySelector('[data-dashboard-jackpot-total]');
    if (!players && !topBalance && !openPicks && !jackpotTotal) return;

    try {
        const response = await fetch('/api/dashboard.php', { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        const snapshot = data.snapshot || {};
        if (players) players.textContent = Number(snapshot.players || 0).toLocaleString();
        if (topBalance) topBalance.textContent = `${Number(snapshot.top_balance || 0).toLocaleString()} CR`;
        if (openPicks) openPicks.textContent = Number(snapshot.open_picks || 0).toLocaleString();
        if (jackpotTotal) jackpotTotal.textContent = `${Number(snapshot.jackpot_total || 0).toLocaleString()} CR`;
    } catch (_) {}
}

function setupGameFilters() {
    const buttons = document.querySelectorAll('[data-filter]');
    const cards = document.querySelectorAll('[data-category]');
    const search = document.querySelector('[data-game-search]');

    const applyFilters = () => {
        const active = document.querySelector('[data-filter].active')?.dataset.filter || 'all';
        const term = (search?.value || '').trim().toLowerCase();
        cards.forEach((card) => {
            const matchesCategory = active === 'all' || card.dataset.category === active;
            const text = card.textContent.toLowerCase();
            const matchesSearch = !term || text.includes(term);
            card.hidden = !(matchesCategory && matchesSearch);
        });
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            applyFilters();
        });
    });

    if (search) search.addEventListener('input', applyFilters);
}

function setupPanelFilters() {
    const groups = new Map();
    document.querySelectorAll('[data-panel-filter]').forEach((button) => {
        const container = button.parentElement;
        if (!container) return;
        if (!groups.has(container)) groups.set(container, []);
        groups.get(container).push(button);
    });

    groups.forEach((buttons) => {
        const container = buttons[0]?.parentElement;
        if (!container) return;
        const cards = container.nextElementSibling?.querySelectorAll?.('[data-panel-category]') || [];
        const apply = (filter) => {
            cards.forEach((card) => {
                card.hidden = !(filter === 'all' || card.dataset.panelCategory === filter);
            });
        };
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                buttons.forEach((item) => item.classList.remove('active'));
                button.classList.add('active');
                apply(button.dataset.panelFilter || 'all');
            });
        });
        apply('all');
    });
}

setupGameFilters();
setupPanelFilters();
refreshBalance();
refreshLivePanels();
refreshDashboardWidgets();
setInterval(refreshBalance, 15000);
setInterval(refreshLivePanels, 20000);
setInterval(refreshDashboardWidgets, 20000);
