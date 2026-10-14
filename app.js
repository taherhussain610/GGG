const balance = document.querySelector("#balance");
const result = document.querySelector("#result");

/**
 * Builds an absolute URL for an API path.
 *
 * Every request in this file goes through here rather than calling fetch("/api/...") with a
 * relative path. A relative URL is resolved against the document's base, and when that base is
 * not a real http(s) origin the request fails with "Failed to parse URL from /api/..." before it
 * ever reaches the network - which is exactly what was happening, and why every API call appeared
 * to hang with no console error and no failed request to point at.
 */
const api = (path) => new URL(path, window.location.origin).toString();

/**
 * Promotes the deferred web-font stylesheet to `all` once it has downloaded.
 *
 * The stylesheet is linked with media="print" so it never sits on the critical render path, and
 * an inline onload= handler cannot promote it here because this app's CSP is script-src 'self'
 * with no 'unsafe-inline'. Doing it from this file (which is same-origin) is both CSP-compliant
 * and non-blocking: the page paints immediately with the system font stack, then upgrades itself
 * if and when the web fonts arrive. A failed font request is not an error - the fallback is fine.
 */
const fontStylesheet = document.querySelector("#font-stylesheet");
if (fontStylesheet) {
  const promote = () => {
    fontStylesheet.media = "all";
  };
  if (fontStylesheet.sheet) promote();
  else fontStylesheet.addEventListener("load", promote, { once: true });
}

const title = document.querySelector("#table-title");
const description = document.querySelector("#table-description");
const play = document.querySelector("#play");
const gamesContainer = document.querySelector(".games");
let games = document.querySelectorAll(".game");
let credits = 1000;
let played = 0;
let sessionResult = 0;
let selected = "Blackjack";
const catalog = {
  casino: [
    ["Blackjack", "♠", "Beat the dealer to 21", "Velvet Room"],
    ["Roulette", "◎", "Make a call, trust the wheel", "Velvet Room"],
    ["Lucky 7", "7", "Simple game, sharp feeling", "Velvet Room"],
    ["Andar Bahar", "◆", "Classic Indian card-room demo", "Ezugi"],
  ],
  favorites: [
    ["Blackjack", "♠", "Your saved table", "Velvet Room"],
    ["Aviator", "↗", "Your saved crash game", "Spribe"],
  ],
  top: [
    ["Aviator", "↗", "Popular multiplier demo", "Spribe"],
    ["Blackjack", "♠", "Popular classic table", "Velvet Room"],
    ["Red Baron Live", "◎", "Popular live-table demo", "Evolution"],
    ["Rummy", "◆", "Popular card-room demo", "Jili Games"],
  ],
  new: [
    ["Neon Cricket", "✦", "New cricket-themed demo", "Velvet Room"],
    ["Moon Market", "◌", "New reel-style demo", "Endorphina"],
    ["Orbit", "◉", "New multiplier demo", "Velvet Room"],
    ["Uncrossable Rush", "↗", "New instant multiplier demo", "Evoplay"],
  ],
  tournaments: [
    ["Velvet Championship", "♛", "Demo leaderboard tournament", "Velvet Room"],
    ["Weekend Sprint", "◇", "Compete for play-money points", "Velvet Room"],
    ["High Rollers Cup", "✦", "Invitation-style demo event", "Velvet Room"],
  ],
  live: [
    ["Red Baron Live", "◎", "Live-table style demo", "Evolution"],
    ["Midnight Blackjack", "♠", "Classic live-table demo", "Velvet Room"],
    ["Roulette No. 4", "◎", "European wheel demo", "Evolution"],
    ["Andar Bahar", "◆", "Live card-room demo", "Ezugi"],
  ],
  slots: [
    ["Neon Fortune", "✦", "Spin bright reels for a lucky line", "Velvet Room"],
    ["Moon Market", "◌", "Three reels, one midnight bonus", "Endorphina"],
    ["Golden Orchard", "✺", "Chase the fruit jackpot", "Wicked Games"],
    ["Hell Hot 100", "✺", "Fast slot-style demo", "Endorphina"],
    ["Transformers", "▣", "Themed slot-style demo", "Wicked Games"],
    ["Skydot", "◉", "Colorful reel-style demo", "Beon"],
    ["Ultimate Hot", "✦", "Classic hot-style slot demo", "Amusnet"],
    ["Duck Hunters", "◆", "Adventure slot-style demo", "Nolimit City"],
  ],
  sports: [
    ["Matchday Picks", "⚽", "Demo football fixtures and odds", "Kambi"],
    ["Court Vision", "◒", "Basketball lines for tonight", "Kambi"],
    ["Race Desk", "➤", "Pick a winner from the field", "Kambi"],
  ],
  instant: [
    ["Quick Draw", "✎", "Choose a number, reveal the card", "Jili Games"],
    ["Scratch House", "✧", "A fresh card in every round", "Jili Games"],
    ["Number Rush", "#", "Fast picks, instant results", "Velvet Room"],
    ["Mines", "◆", "Grid-based instant demo", "Velvet Room"],
  ],
  crash: [
    ["Aviator", "↗", "Cash out before the multiplier ends", "Spribe"],
    ["Rocket Run", "↗", "Cash out before the climb ends", "Velvet Room"],
    ["Orbit", "◉", "Watch the multiplier rise", "Velvet Room"],
    ["Skyline", "⌁", "A fast round with one decision", "Velvet Room"],
    ["Skydot", "◉", "Multiplier-style demo", "Beon"],
  ],
  batery: [
    ["Neon Cricket", "✦", "Exclusive Batery Games demo", "Velvet Room"],
    ["Rummy", "◆", "Classic card-room demo", "Jili Games"],
    ["Batery Blackjack", "♠", "House-branded blackjack demo", "Velvet Room"],
    ["Wheel Out", "◎", "Wheel-style Batery demo", "InOut Games"],
  ],
  volent: [
    ["Wheel Out", "◎", "VoltEnt wheel-style demo", "InOut Games"],
    ["Mystic Alaska", "✦", "VoltEnt reel-style demo", "Evoplay"],
    ["VoltEnt Classic", "◆", "Classic provider demo", "VoltEnt"],
  ],
  "buy-bonus": [
    ["Hell Hot 100", "✺", "Buy-bonus style slot demo", "Endorphina"],
    ["Golden Orchard Bonus", "✦", "Bonus-round demo", "Wicked Games"],
  ],
  roulette: [
    ["Roulette", "◎", "European wheel demo", "Evolution"],
    ["Red Baron Live", "◎", "Live roulette-style demo", "Evolution"],
  ],
  mines: [
    ["Mines", "◆", "Grid-based instant demo", "Velvet Room"],
    ["Deep Vault", "◇", "Choose a safe tile", "Velvet Room"],
  ],
  promo: [
    ["Welcome Boost", "✦", "Demo promotion with no cash value", "Velvet Room"],
    ["Tournament Ticket", "◇", "Demo entry reward", "Velvet Room"],
  ],
  featured: [
    ["Neon Cricket", "✦", "Exclusive cricket-themed demo", "Velvet Room"],
    ["Rummy", "◆", "Classic card-room demo", "Jili Games"],
    ["Red Baron Live", "◎", "Live-table style demo", "Evolution"],
    ["Hell Hot 100", "✺", "Fast slot-style demo", "Endorphina"],
    ["Transformers", "▣", "Themed slot-style demo", "Wicked Games"],
    ["Uncrossable Rush", "↗", "Instant multiplier demo", "Evoplay"],
    ["Skydot", "◉", "Multiplier-style demo", "Beon"],
    ["Ultimate Hot", "✦", "Hot-style slot demo", "Amusnet"],
    ["Mystic Alaska", "✧", "Adventure reel-style demo", "Evoplay"],
    ["Andar Bahar", "◆", "Live card-room demo", "Ezugi"],
    ["Duck Hunters", "◆", "Adventure slot-style demo", "Nolimit City"],
  ],
};
const copy = {
  Blackjack:
    "The cards are warm. Place a demo wager and see where the hand takes you.",
  Roulette: "Pick a color, number, or simply let the wheel decide.",
  "Lucky 7": "A small wager and one bright number. Sometimes that is enough.",
};
function renderBalance() {
  balance.textContent = credits.toLocaleString("en-US", {
    minimumFractionDigits: 2,
  });
  document.querySelector("#wallet-balance").textContent =
    `${credits.toLocaleString("en-US", { minimumFractionDigits: 2 })} VR`;
  document.querySelector("#played-today").textContent =
    `${played.toLocaleString("en-US", { minimumFractionDigits: 2 })} VR`;
  document.querySelector("#session-result").textContent =
    `${sessionResult >= 0 ? "+" : ""}${sessionResult.toLocaleString("en-US", { minimumFractionDigits: 2 })} VR`;
}

/**
 * Adds one row to the wallet activity table. `sub` carries the detail that makes a row worth
 * reading (the cards, the pocket, the reel line); the net is signed from the stake and the payout
 * so the table never disagrees with the balance above it.
 */
function pushActivity(gameName, bet, payout, sub) {
  const body = document.querySelector("#activity-body");
  if (!body) return;
  const net = Math.round((payout - bet) * 100) / 100;
  const sign = net >= 0 ? "+" : "";
  const detail = sub ? `<br><small>${sub}</small>` : "";
  body.insertAdjacentHTML(
    "afterbegin",
    `<tr><td>${new Date().toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit" })}</td>` +
      `<td>${payout > 0 ? "Win" : "Round"}${detail}</td>` +
      `<td>${gameName}</td>` +
      `<td>${sign}${net.toLocaleString("en-US", { minimumFractionDigits: 2 })} VR</td></tr>`,
  );
}

/** Renders the server's recorded spin history into the wallet table, newest first. */
function renderActivity(history) {
  const body = document.querySelector("#activity-body");
  if (!body || !Array.isArray(history) || history.length === 0) return;
  body.innerHTML = history
    .map((entry) => {
      const at = new Date(entry.at);
      const time = at.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit" });
      const net = Math.round((entry.payout - entry.bet) * 100) / 100;
      const sign = net >= 0 ? "+" : "";
      return (
        `<tr><td>${time}</td>` +
        `<td>${entry.win ? "Win" : "Round"}</td>` +
        `<td>${entry.game}</td>` +
        `<td>${sign}${net.toLocaleString("en-US", { minimumFractionDigits: 2 })} VR</td></tr>`
      );
    })
    .join("");
}
function showView(view) {
  document
    .querySelectorAll("[data-view-link]")
    .forEach((link) =>
      link.classList.toggle("active", link.dataset.viewLink === view),
    );
  document
    .querySelector(".view-panel.lobby")
    .classList.toggle("active", view === "lobby");
  document
    .querySelectorAll(".view-panel[data-view]")
    .forEach((panel) =>
      panel.classList.toggle("active", panel.dataset.view === view),
    );
  if (view === "wallet") renderBalance();
  // The Results panel is computed from live state, so refresh it on entry rather than only on load.
  if (view === "account") renderStats();
  window.location.hash = view;
}
document.querySelectorAll("[data-view-link]").forEach((link) =>
  link.addEventListener("click", (event) => {
    event.preventDefault();
    showView(link.dataset.viewLink);
  }),
);
document
  .querySelectorAll("[data-go]")
  .forEach((button) =>
    button.addEventListener("click", () => showView(button.dataset.go)),
  );
document.querySelectorAll("[data-table-game]").forEach((button) =>
  button.addEventListener("click", () => {
    const game = [...games].find(
      (item) => item.dataset.game === button.dataset.tableGame,
    );
    if (game) game.click();
    showView("lobby");
    document.querySelector("#table").scrollIntoView({ behavior: "smooth" });
  }),
);
function selectGame(game) {
  games.forEach((item) => item.classList.remove("active"));
  game.classList.add("active");
  selected = game.dataset.game;
  title.textContent = selected;
  description.textContent = copy[selected] || game.dataset.description;
  result.textContent = "Ready when you are.";
  result.dataset.state = "ready";

  // Route the card to a real, server-settled game. Every catalogue title now resolves: a slot,
  // a table game, or an instant game. There is no "not playable" branch left.
  const slotId = slotIdForName(selected);
  const tableSpec = TABLE_GAMES[selected];
  const instantEngine = INSTANT_TITLES[selected];

  if (slotId) {
    play.textContent = "Spin reels below";
    selectSlotGame(slotId);
    document.querySelector("#slots").scrollIntoView({ behavior: "smooth", block: "start" });
    return;
  }

  if (tableSpec) {
    play.textContent = `${tableSpec.action} · ${tableSpec.bet} VR`;
    document.querySelector("#table").scrollIntoView({ behavior: "smooth", block: "start" });
    return;
  }

  if (instantEngine) {
    play.textContent = `${INSTANT_ACTIONS[instantEngine] || "Play"} · 10 VR`;
    document.querySelector("#table").scrollIntoView({ behavior: "smooth", block: "start" });
    return;
  }

  // Only reachable if the server catalogue and this client's maps drift apart. Say so plainly
  // rather than pretending to run a game whose engine is missing.
  play.textContent = "Unavailable";
  result.textContent = "This title has no engine on this server.";
  result.dataset.state = "lose";
}
function renderCategory(category) {
  const query = document
    .querySelector("#game-search")
    .value.toLowerCase()
    .trim();
  const provider = document.querySelector("#provider-filter").value;
  const items = catalog[category].filter((entry) => {
    const name = Array.isArray(entry) ? entry[0] : entry.name;
    const blurb = Array.isArray(entry) ? entry[2] : entry.description;
    const source = Array.isArray(entry) ? entry[3] : entry.provider;
    return (
      (!query || `${name} ${blurb}`.toLowerCase().includes(query)) &&
      (provider === "all" || source === provider)
    );
  });
  gamesContainer.innerHTML = items.length
    ? items
        .map((entry, index) => {
          const [name, icon, blurb, source] = Array.isArray(entry)
            ? entry
            : [entry.name, entry.icon, entry.description, entry.provider];
          return `<button class="game${index === 0 ? " active" : ""}" data-game="${name}" data-description="${blurb}"><div class="game-icon">${icon}</div><div class="game-meta"><div><h3>${name}</h3><small>${blurb} · ${source}</small></div><span class="play-label">Play demo ↗</span></div></button>`;
        })
        .join("")
    : '<div class="catalog-empty">No demo games match these filters.</div>';
  games = document.querySelectorAll(".game");
  games.forEach((game) =>
    game.addEventListener("click", () => selectGame(game)),
  );
  if (games[0]) selectGame(games[0]);
  document.querySelector("#catalog-count").textContent =
    `${items.length.toString().padStart(2, "0")} available now`;
}
document.querySelectorAll("[data-category]").forEach((tab) =>
  tab.addEventListener("click", () => {
    document
      .querySelectorAll("[data-category]")
      .forEach((item) => item.classList.toggle("active", item === tab));
    renderCategory(tab.dataset.category);
  }),
);
document
  .querySelector("#game-search")
  .addEventListener("input", () =>
    renderCategory(
      document.querySelector("[data-category].active").dataset.category,
    ),
  );
document
  .querySelector("#provider-filter")
  .addEventListener("change", () =>
    renderCategory(
      document.querySelector("[data-category].active").dataset.category,
    ),
  );
/* ------------------------------------------------------------------ table games
 * Blackjack, Roulette and Lucky 7 are settled by POST /api/play, not in the browser. Each
 * one had a Math.random() coin flip here before; that meant the outcome was decided by the
 * client, which is both unfair and the exact thing this demo claims not to do. Now the client
 * sends the stake, the server draws from its own RNG, and the client renders the result.
 */
const TABLE_GAMES = {
  Blackjack: { id: "blackjack", bet: 25, action: "Deal hand" },
  Roulette: { id: "roulette", bet: 25, action: "Spin wheel" },
  "Lucky 7": { id: "lucky-seven", bet: 25, action: "Play round" },
};

/* ------------------------------------------------------------------ instant games
 * The rest of the catalogue - scratch cards, mines, wheels, crash and the promotions - is settled
 * by the same POST /api/play route, dispatched server-side to instant-games.js. These are the
 * branded lobby titles mapped to the engine that backs them; the mapping comes from the server
 * (GET /api/instant/games) so a title added there becomes playable here without a client change.
 * The fallback below keeps the app playable when opened from disk with no API.
 */
let INSTANT_TITLES = {
  "Quick Draw": "scratch",
  "Scratch House": "scratch",
  "Number Rush": "scratch",
  "Hell Hot 100": "scratch",
  Transformers: "scratch",
  Skydot: "scratch",
  "Ultimate Hot": "scratch",
  "Duck Hunters": "scratch",
  "Mystic Alaska": "scratch",
  "VoltEnt Classic": "scratch",
  "Golden Orchard Bonus": "scratch",
  "Neon Cricket": "scratch",
  "Andar Bahar": "scratch",
  Rummy: "scratch",
  Mines: "mines",
  "Deep Vault": "mines",
  "Wheel Out": "wheel",
  "Red Baron Live": "wheel",
  "Roulette No. 4": "wheel",
  "Midnight Blackjack": "wheel",
  "Batery Blackjack": "wheel",
  Aviator: "crash-round",
  "Rocket Run": "crash-round",
  "Uncrossable Rush": "crash-round",
  Orbit: "crash-round",
  Skyline: "crash-round",
  "Welcome Boost": "promo",
  "Tournament Ticket": "promo",
  "Velvet Championship": "promo",
  "Weekend Sprint": "promo",
  "High Rollers Cup": "promo",
};

/** Human labels for the instant engines, used on the play button and result line. */
const INSTANT_ACTIONS = {
  scratch: "Scratch card",
  mines: "Pick a tile",
  wheel: "Spin the wheel",
  "crash-round": "Launch round",
  promo: "Open the draw",
};

const SUIT_RED = { "♥": true, "♦": true };

/** Renders one settled table round in the result box. */
function describeTableResult(name, result) {
  const money = (value) =>
    value.toLocaleString("en-US", { minimumFractionDigits: 2 });
  const detail = result.detail || {};

  if (name === "Blackjack") {
    const hand = (cards) => cards.map((card) => card.rank + card.suit).join(" ");
    const line = `You ${detail.playerTotal} — dealer ${detail.dealerTotal}`;
    const cards = `${hand(detail.player)} vs ${hand(detail.dealer)}`;
    if (result.outcome === "push")
      return { text: `Push. Stake returned. ${line}`, sub: cards, state: "push" };
    if (result.outcome === "bust")
      return { text: `Bust at ${detail.playerTotal}. ${line}`, sub: cards, state: "lose" };
    if (result.outcome === "dealer-bust")
      return { text: `Dealer busts. You win ${money(result.payout)} VR. ${line}`, sub: cards, state: "win" };
    if (result.outcome === "win")
      return { text: `You win ${money(result.payout)} VR. ${line}`, sub: cards, state: "win" };
    return { text: `Dealer takes it. ${line}`, sub: cards, state: "lose" };
  }

  if (name === "Roulette") {
    const pocket = `${detail.landed} ${detail.color}`;
    if (result.win)
      return { text: `${pocket} — you hit. ${money(result.payout)} VR.`, sub: `Picked ${detail.picked}`, state: "win" };
    return { text: `${pocket}. You picked ${detail.picked}.`, sub: "One more spin?", state: "lose" };
  }

  if (result.win)
    return { text: `Seven! You win ${money(result.payout)} VR.`, sub: "Not this seven — it was this one.", state: "win" };
  return { text: `It landed on ${detail.landed}. Not this seven.`, sub: "A fresh draw next round.", state: "lose" };
}

/** Maps a lobby card name to a table game id, or null if it is not a table game. */
function tableIdForName(name) {
  const entry = TABLE_GAMES[name];
  return entry ? entry.id : null;
}

/** Renders one settled instant round (scratch, mines, wheel, crash, promo). */
function describeInstantResult(engine, result) {
  const money = (value) =>
    value.toLocaleString("en-US", { minimumFractionDigits: 2 });
  const detail = result.detail || {};

  if (engine === "scratch") {
    const shown = (detail.revealed || []).join(" ");
    if (result.outcome === "triple")
      return { text: `Three of a kind! You win ${money(result.payout)} VR.`, sub: shown, state: "win" };
    if (result.outcome === "pair")
      return { text: `A pair — ${money(result.payout)} VR back.`, sub: shown, state: "win" };
    return { text: "No matching symbols.", sub: shown, state: "lose" };
  }

  if (engine === "mines") {
    const board = (detail.tiles || []).map((t) => (t === "safe" ? "·" : "✱")).join(" ");
    if (result.win)
      return { text: `Safe tile. You win ${money(result.payout)} VR.`, sub: board, state: "win" };
    return { text: `That one was a mine.`, sub: board, state: "lose" };
  }

  if (engine === "wheel") {
    const color = detail.color || "red";
    if (result.win)
      return { text: `Landed on ${color} — ${money(result.payout)} VR.`, sub: `Multiplier ×${result.multiplier}`, state: "win" };
    return { text: `Landed on ${color}. No win this turn.`, sub: "Spin again", state: "lose" };
  }

  if (engine === "crash-round") {
    const point = detail.crashPoint;
    if (result.win)
      return { text: `Cashed out at ${detail.target}× — ${money(result.payout)} VR.`, sub: `Round reached ${point}×`, state: "win" };
    return { text: `Busted at ${point}× before the target.`, sub: `Target was ${detail.target}×`, state: "lose" };
  }

  if (engine === "promo") {
    // The promotion always returns the stake; a win is a bonus on top of it.
    if (result.win)
      return { text: `Bonus unlocked: ${detail.prize}. ${money(result.payout)} VR returned.`, sub: "Stake returned plus bonus", state: "win" };
    return { text: "No bonus this draw — your stake is returned in full.", sub: "Promotion draw", state: "ready" };
  }

  return { text: "Round settled.", sub: "", state: result.win ? "win" : "lose" };
}

/**
 * Settles one non-reel round: a table game or an instant game.
 *
 * Both live behind the same POST /api/play route and both are drawn on the server, so the only
 * difference here is which renderer describes the result. The stake is fixed per game (25 VR, or
 * 10 for the promotions) rather than free-entry, so the lobby button stays a single click.
 */
async function playTableGame(name) {
  const tableSpec = TABLE_GAMES[name];
  const instantEngine = INSTANT_TITLES[name];
  if (!tableSpec && !instantEngine) return;

  const bet = tableSpec ? tableSpec.bet : 10;
  if (bet > credits) {
    result.textContent = "Balance too low.";
    return;
  }

  play.disabled = true;
  result.textContent = "Dealing…";

  try {
    if (window.location.protocol === "file:") throw new Error("offline");
    const response = await fetch(api("/api/play"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ gameId: tableSpec ? tableSpec.id : instantEngine, bet }),
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.error || "Round failed.");
    credits = payload.balance;
    played += bet;
    sessionResult = payload.net;
    renderBalance();
    const described = tableSpec
      ? describeTableResult(name, payload.result)
      : describeInstantResult(instantEngine, payload.result);
    result.textContent = described.text;
    result.dataset.state = described.state;
    pushActivity(name, bet, payload.result.payout, described.sub);
    roundsPlayed += 1;
    rememberBankroll(credits);
    renderStats();
    if (payload.result.win) sound.win();
    else sound.lose();
  } catch (error) {
    result.textContent =
      window.location.protocol === "file:"
        ? "Open this page from the server to play."
        : error.message;
  } finally {
    play.disabled = false;
  }
}

play.addEventListener("click", () => {
  if (TABLE_GAMES[selected] || INSTANT_TITLES[selected]) return playTableGame(selected);
  result.textContent = "Choose a game to play.";
});
function restoreBalance() {
  credits = 1000;
  played = 0;
  sessionResult = 0;
  renderBalance();
  result.textContent = "Balance restored. Ready when you are.";
  result.dataset.state = "ready";

  // Reset the signed session too, otherwise the next spin would settle against the old ledger
  // the moment the page reloads and /api/session hands back the previous balance.
  if (window.location.protocol === "file:") return;
  fetch(api("/api/session/reset"), { method: "POST" })
    .then((response) => response.json())
    .then((session) => {
      credits = session.balance;
      played = 0;
      sessionResult = session.net || 0;
      renderBalance();
      renderActivity([]);
    })
    .catch(() => {});
}
/**
 * The wallet notice is driven by the server's capability flags, not by the
 * markup. If /api/wallet ever reports deposits:true the notice changes,
 * and until then the page cannot claim otherwise however it is edited.
 */
function renderWalletBoundary(wallet) {
  const flag = document.querySelector("#wallet-flag");
  const reason = document.querySelector("#wallet-boundary-reason");
  if (!flag || !reason) return;
  const deposits = Boolean(wallet && wallet.deposits);
  const withdrawals = Boolean(wallet && wallet.withdrawals);
  if (deposits || withdrawals) {
    flag.textContent = "PAYMENTS ON";
    flag.dataset.state = "on";
    reason.textContent = "Cashier access is enabled for this account.";
    return;
  }
  flag.textContent = "PAYMENTS OFF";
  flag.dataset.state = "off";
  if (wallet && wallet.note) reason.textContent = wallet.note;
}
document.querySelector("#reset").addEventListener("click", restoreBalance);
document
  .querySelector("#wallet-reset")
  .addEventListener("click", restoreBalance);
document.querySelector("#sign-out").addEventListener("click", () => {
  // A real sign-out: the server issues a fresh session, so the balance the player sees after
  // signing back in is the new one and not the ledger they left behind.
  result.textContent = "Signed out. A fresh guest session was issued.";
  result.dataset.state = "ready";
  showView("lobby");
  restoreBalance();
});
document.querySelector("#edit-profile").addEventListener("click", (event) => {
  // The profile is local to this prototype; the button confirms the write rather than pretending
  // to persist it to an account service that does not exist here.
  event.target.textContent = "Saved locally";
  event.target.disabled = true;
});

/* ------------------------------------------------------------------ dedicated slots
 * The three reel games below are real server-side games: their odds, paytables and
 * speed bands live in games.json and never reach this file. This client only renders
 * the grid the server returned and settles the balance the server reported. When the
 * page is opened straight from disk (file:), there is no API, so it falls back to a
 * local simulation that mirrors the same shapes so the games are still playable.
 */
const SLOT_GAMES = [
  {
    id: "golden-orchard",
    name: "Golden Orchard",
    icon: "✺",
    subtitle: "Fruit reels · single middle line",
  },
  {
    id: "neon-fortune",
    name: "Neon Fortune",
    icon: "✦",
    subtitle: "Looser reels · friendlier hit rate",
  },
  {
    id: "moon-market",
    name: "Moon Market",
    icon: "◌",
    subtitle: "Tighter reels · bigger top end",
  },
];
const SYMBOL_EMOJI = {
  cherry: "🍒",
  lemon: "🍋",
  grapes: "🍇",
  watermelon: "🍉",
  seven: "7️⃣",
};
let slotGame = SLOT_GAMES[0];
let slotCatalog = {};
let slotBusy = false;

/** Maps a catalogue card to a real game id, so clicking a slot card opens that machine. */
function slotIdForName(name) {
  const match = SLOT_GAMES.find(
    (game) => game.name.toLowerCase() === String(name).toLowerCase(),
  );
  return match ? match.id : null;
}

function slotMessage(text, state) {
  const message = document.querySelector("#slot-message");
  message.textContent = text;
  message.classList.remove("win", "lose");
  if (state) message.classList.add(state);
}

function renderSlotGrid(cells, winningLine) {
  const grid = document.querySelector("#reels-grid");
  const rows =
    cells && cells.length
      ? cells
      : [
          ["", "", ""],
          ["", "", ""],
          ["", "", ""],
        ];
  const middle = 1;
  grid.innerHTML = rows
    .map((row, r) =>
      row
        .map((cell) => {
          const isMid = r === middle;
          return `<div class="reel-cell spin${isMid ? " mid" : ""}">${cell}</div>`;
        })
        .join(""),
    )
    .join("");
  grid.classList.toggle("win", Boolean(winningLine));
}

function renderSlotPaytable(gameId) {
  const container = document.querySelector("#slot-paytable");
  const game = slotCatalog[gameId];
  const rows =
    game && game.paytable
      ? game.paytable
          .map(
            (tier) =>
              `<div class="paytable-row"><span class="sym">${tier.emoji}</span><span class="mult">×${tier.multiplier}</span></div>`,
          )
          .join("")
      : '<div class="slot-meta">Paytable loads from the server.</div>';
  container.innerHTML = `<h3>Paytable</h3>${rows}`;
}

function renderSlotMeta(meta) {
  document.querySelector("#slot-meta").innerHTML = meta;
}

function selectSlotGame(gameId) {
  const game = SLOT_GAMES.find((item) => item.id === gameId) || SLOT_GAMES[0];
  slotGame = game;
  document
    .querySelectorAll(".slot-tab")
    .forEach((tab) =>
      tab.classList.toggle("active", tab.dataset.slot === game.id),
    );
  renderSlotPaytable(game.id);
  slotMessage(`${game.name} · ${game.subtitle}`, null);
}

function renderSlotPicker() {
  const picker = document.querySelector("#slot-picker");
  picker.innerHTML = SLOT_GAMES.map(
    (game, index) =>
      `<button class="slot-tab${index === 0 ? " active" : ""}" data-slot="${game.id}" role="tab">${game.icon} ${game.name}</button>`,
  ).join("");
  picker
    .querySelectorAll(".slot-tab")
    .forEach((tab) =>
      tab.addEventListener("click", () => selectSlotGame(tab.dataset.slot)),
    );
}

/** Local mirror used only when there is no API (file:). Shapes match the server. */
const LOCAL_SLOTS = {
  "golden-orchard": {
    hitRate: 0.05,
    payouts: [
      ["seven", 100, 0.03],
      ["watermelon", 50, 0.12],
      ["grapes", 20, 0.2],
      ["lemon", 10, 0.3],
      ["cherry", 5, 0.35],
    ],
    filler: "cherry",
  },
  "neon-fortune": {
    hitRate: 0.18,
    payouts: [
      ["seven", 40, 0.03],
      ["watermelon", 18, 0.09],
      ["grapes", 8, 0.15],
      ["lemon", 3, 0.28],
      ["cherry", 1, 0.45],
    ],
    filler: "cherry",
  },
  "moon-market": {
    hitRate: 0.03,
    payouts: [
      ["seven", 250, 0.06],
      ["watermelon", 60, 0.12],
      ["grapes", 18, 0.22],
      ["lemon", 8, 0.3],
      ["cherry", 3, 0.3],
    ],
    filler: "cherry",
  },
};

function localSpin(gameId, bet) {
  const spec = LOCAL_SLOTS[gameId] || LOCAL_SLOTS["golden-orchard"];
  const keys = Object.keys(SYMBOL_EMOJI);
  const pick = () =>
    SYMBOL_EMOJI[keys[Math.floor(Math.random() * keys.length)]];
  const cells = Array.from({ length: 3 }, () =>
    Array.from({ length: 3 }, pick),
  );
  const won = Math.random() < spec.hitRate;
  if (!won) {
    const decoy = SYMBOL_EMOJI[spec.filler];
    cells[1] = [decoy, decoy, decoy];
    const breakAt = 1 + Math.floor(Math.random() * 2);
    let broken = decoy;
    while (broken === decoy) broken = pick();
    cells[1][breakAt] = broken;
    return {
      win: false,
      multiplier: 0,
      payout: 0,
      symbol: null,
      grid: cells,
      winningLine: false,
    };
  }
  const roll = Math.random();
  let acc = 0;
  let tier = spec.payouts[spec.payouts.length - 1];
  for (const candidate of spec.payouts) {
    acc += candidate[2];
    if (roll <= acc) {
      tier = candidate;
      break;
    }
  }
  const emoji = SYMBOL_EMOJI[tier[0]];
  cells[1] = [emoji, emoji, emoji];
  return {
    win: true,
    multiplier: tier[1],
    payout: Math.round(bet * tier[1] * 100) / 100,
    symbol: tier[0],
    grid: cells,
    winningLine: true,
  };
}

function applySlotResult(result, bet, receipt, fromServer) {
  // `win` is the authoritative flag on every path; `winningLine` is a hint the server also sends
  // now, but the highlight must not depend on it or an older server would render wins as losses.
  renderSlotGrid(result.grid, result.win);
  if (result.win) {
    slotMessage(
      `Line pays ×${result.multiplier} — ${result.payout.toLocaleString("en-US", { minimumFractionDigits: 2 })} VR`,
      "win",
    );
  } else {
    slotMessage("No line this spin. The reels keep turning.", "lose");
  }
  const seed = receipt
    ? `Commitment ${String(receipt.serverSeedHash || "").slice(0, 16)}…`
    : fromServer
      ? "Receipt unavailable."
      : "Local simulation (no server).";
  renderSlotMeta(
    [
      `Stake <b>${bet.toLocaleString("en-US", { minimumFractionDigits: 2 })} VR</b>`,
      `Multiplier <b>×${result.multiplier}</b>`,
      `Returned <b>${result.payout.toLocaleString("en-US", { minimumFractionDigits: 2 })} VR</b>`,
      seed,
    ].join("<br>"),
  );
  document
    .querySelector("#activity-body")
    .insertAdjacentHTML(
      "afterbegin",
      `<tr><td>Now</td><td>${result.win ? "Slot win" : "Slot spin"}</td><td>${slotGame.name}</td><td>${result.payout >= bet ? "+" : ""}${(result.payout - bet).toFixed(2)} VR</td></tr>`,
    );
}

async function spinSlot() {
  if (slotBusy) return;
  const betInput = document.querySelector("#slot-bet");
  const bet = Math.round(Number(betInput.value) * 100) / 100;
  if (!Number.isFinite(bet) || bet <= 0) {
    slotMessage("Enter a valid bet.", "lose");
    return;
  }
  if (bet < 1 || bet > 250) {
    slotMessage("Bet must be between 1 and 250 VR.", "lose");
    return;
  }
  if (bet > credits) {
    slotMessage("Balance too low for that bet.", "lose");
    return;
  }

  slotBusy = true;
    const button = document.querySelector("#slot-spin");
    button.disabled = true;
    slotMessage("Spinning…", null);
    sound.spin();
  // Re-trigger the reel animation on every spin.
  document.querySelectorAll(".reel-cell").forEach((cell, index) => {
    cell.classList.remove("spin");
    void cell.offsetWidth;
    cell.style.animationDelay = `${index * 25}ms`;
    cell.classList.add("spin");
  });

  try {
    if (window.location.protocol === "file:") throw new Error("offline");
    const response = await fetch(api("/api/spin"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ gameId: slotGame.id, bet }),
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok)
      throw new Error(payload.error || "Spin failed.");
    credits = payload.balance;
    played += bet;
    sessionResult = payload.net;
    renderBalance();
    applySlotResult(payload.result, bet, payload.result.fairness, true);
    roundsPlayed += 1;
    rememberBankroll(credits);
    renderStats();
    if (payload.result.win) sound.win();
    else sound.lose();
  } catch (error) {
    const sim = localSpin(slotGame.id, bet);
    credits = Math.round((credits - bet + sim.payout) * 100) / 100;
    played += bet;
    sessionResult = Math.round((sessionResult + sim.payout - bet) * 100) / 100;
    renderBalance();
    applySlotResult(sim, bet, null, false);
    roundsPlayed += 1;
    rememberBankroll(credits);
    renderStats();
    if (sim.win) sound.win();
    else sound.lose();
  } finally {
    slotBusy = false;
    button.disabled = false;
  }
}

renderSlotPicker();
renderSlotGrid(null, false);
selectSlotGame(SLOT_GAMES[0].id);
document.querySelector("#slot-spin").addEventListener("click", spinSlot);
document.querySelector("#slot-reset").addEventListener("click", restoreBalance);
document.querySelector("#slot-bet").addEventListener("keydown", (event) => {
  if (event.key === "Enter") spinSlot();
});

const initialView = window.location.hash.slice(1);
if (["tables", "wallet", "account"].includes(initialView))
  showView(initialView);
else renderBalance();
/*
 * Startup data, loaded SEQUENTIALLY rather than as eight parallel fetches.
 *
 * This matters more than it looks. HTTP/1.1 lets a browser open only six connections per host, so
 * firing the whole list at once fills every slot. Any request that then queues - the deferred
 * Google Fonts stylesheet holds a connection too - can leave a later fetch waiting indefinitely,
 * which is exactly what happened: the slot spin sat on "Spinning…" forever because its POST was
 * queued behind requests that never got a socket. Running them one at a time caps concurrency at
 * one, so the pool can never be starved no matter how slow a third party is.
 */
const startupSteps = [
  ["/api/session", (session) => {
    credits = session.balance;
    renderBalance();
  }],
  ["/api/wallet", (wallet) => renderWalletBoundary(wallet)],
  ["/api/session/history", (payload) => {
    renderActivity(payload.history);
    if (Array.isArray(payload.history)) roundsPlayed = payload.history.length;
    renderStats();
  }],
  ["/api/games", (slots) => {
    slots.games.forEach((game) => {
      slotCatalog[game.id] = game;
    });
    renderSlotPaytable(slotGame.id);
  }],
  ["/api/limits", (payload) => renderLimits(payload.limits)],
  ["/api/instant/games", (payload) => {
    if (payload && payload.titles && Object.keys(payload.titles).length) {
      INSTANT_TITLES = payload.titles;
      const active = document.querySelector("[data-category].active");
      if (active) renderCategory(active.dataset.category);
    }
  }],
  ["/api/catalog", (payload) => {
    Object.entries(payload.categories).forEach(([category, remoteGames]) => {
      const existing = new Set(
        (catalog[category] || []).map((entry) =>
          Array.isArray(entry) ? entry[0] : entry.name,
        ),
      );
      catalog[category] = [
        ...(catalog[category] || []),
        ...remoteGames.filter((entry) => !existing.has(entry.name || entry[0])),
      ];
    });
    const activeCategory = document.querySelector(".category-tab.active");
    if (activeCategory) renderCategory(activeCategory.dataset.category);
  }],
];

/**
 * Loads the startup data one request at a time. Each step is independently guarded, so a single
 * failing endpoint degrades that one feature and does not stop the rest of the page from working.
 */
async function loadStartupData() {
  for (const [path, apply] of startupSteps) {
    try {
      const response = await fetch(api(path));
      if (!response.ok) continue;
      apply(await response.json());
    } catch {
      // Offline or a slow endpoint: skip this step and carry on.
    }
  }
}

/* ================================================================== sound effects
 * Small synthesised tones via the Web Audio API. No audio files, no dependencies, and nothing
 * is created until the user first enables sound - an AudioContext is a real resource and starting
 * one on page load would be both wasteful and blocked by autoplay policy anyway.
 */
const sound = (() => {
  let context = null;
  let enabled = false;

  /** Tone helper. `type` is any OscillatorNode waveform. */
  const tone = (frequency, durationMs, type = "sine", gain = 0.05, delayMs = 0) => {
    if (!enabled || !context) return;
    const start = context.currentTime + delayMs / 1000;
    const oscillator = context.createOscillator();
    const volume = context.createGain();
    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, start);
    // A short ramp down avoids the click a hard stop produces.
    volume.gain.setValueAtTime(gain, start);
    volume.gain.exponentialRampToValueAtTime(0.0001, start + durationMs / 1000);
    oscillator.connect(volume).connect(context.destination);
    oscillator.start(start);
    oscillator.stop(start + durationMs / 1000);
  };

  const api = {
    /** Enables sound and lazily creates the context. Called from a user gesture. */
    enable() {
      enabled = true;
      try {
        // The webkit prefix is only present on older Safari; reading it via the window object keeps
        // this working there without tripping a type error about a non-standard property.
        const Ctor = window.AudioContext || window["webkitAudioContext"];
        if (Ctor && !context) context = new Ctor();
        if (context && context.state === "suspended") context.resume();
      } catch {
        // A browser without Web Audio simply plays nothing; that is not an error worth surfacing.
        enabled = false;
      }
      return enabled;
    },
    disable() {
      enabled = false;
    },
    get enabled() {
      return enabled;
    },
    spin() {
      tone(360, 90, "square", 0.03);
      tone(280, 90, "square", 0.03, 70);
    },
    win() {
      tone(523, 120, "sine", 0.05);
      tone(659, 120, "sine", 0.05, 110);
      tone(784, 180, "sine", 0.05, 220);
    },
    lose() {
      tone(180, 160, "sine", 0.04);
    },
    click() {
      tone(520, 40, "square", 0.02);
    },
  };
  return api;
})();

const soundToggle = document.querySelector("#sound-toggle");
if (soundToggle) {
  soundToggle.addEventListener("click", () => {
    const on = soundToggle.getAttribute("aria-pressed") !== "true";
    soundToggle.setAttribute("aria-pressed", String(on));
    if (on) {
      sound.enable();
      sound.click();
    } else {
      sound.disable();
    }
  });
}

/* ================================================================== theme
 * The toggle writes data-theme on <html>; every colour is a CSS custom property, so no component
 * needs a light-mode twin. The choice is remembered, and an absent choice follows the OS setting.
 */
const themeToggle = document.querySelector("#theme-toggle");
const THEME_KEY = "velvet-theme";

function applyTheme(theme) {
  if (theme === "light") {
    document.documentElement.setAttribute("data-theme", "light");
  } else {
    document.documentElement.removeAttribute("data-theme");
  }
  if (themeToggle) {
    themeToggle.setAttribute("aria-pressed", String(theme === "light"));
  }
}

function preferredTheme() {
  try {
    const saved = localStorage.getItem(THEME_KEY);
    if (saved === "light" || saved === "dark") return saved;
  } catch {
    // Private mode can throw on localStorage access; fall through to the OS preference.
  }
  return window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches
    ? "light"
    : "dark";
}

applyTheme(preferredTheme());

if (themeToggle) {
  themeToggle.addEventListener("click", () => {
    const next = document.documentElement.getAttribute("data-theme") === "light" ? "dark" : "light";
    applyTheme(next);
    try {
      localStorage.setItem(THEME_KEY, next);
    } catch {
      // Not being able to remember the choice is not a reason to refuse to apply it.
    }
    sound.click();
  });
}

/* ================================================================== statistics
 * Everything here is derived from the rounds this session actually settled, so the observed return
 * is a real measurement rather than a restatement of the configured odds. With small samples it is
 * noisy by design, and the caption says so.
 */
const STATS_KEY = "velvet-bankroll";

/** Bankroll after each round, newest last, capped so localStorage cannot grow without bound. */
const bankrollSeries = (() => {
  try {
    const raw = localStorage.getItem(STATS_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed.slice(-120) : [];
  } catch {
    return [];
  }
})();

function rememberBankroll(value) {
  bankrollSeries.push(Math.round(value * 100) / 100);
  if (bankrollSeries.length > 120) bankrollSeries.shift();
  try {
    localStorage.setItem(STATS_KEY, JSON.stringify(bankrollSeries));
  } catch {
    // Storage may be unavailable; the chart just will not persist across reloads.
  }
}

const money = (value) =>
  Number(value).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** How many rounds this session has settled. Incremented by the round handlers. */
let roundsPlayed = 0;

/** Redraws the Results panel from live session figures. */
function renderStats() {
  const spins = document.querySelector("#stat-spins");
  if (!spins) return;

  const staked = Math.round(played * 100) / 100;
  const returned = Math.round((sessionResult + played) * 100) / 100;
  const observed = staked > 0 ? (returned / staked) * 100 : null;

  spins.textContent = String(roundsPlayed);
  document.querySelector("#stat-staked").textContent = `${money(staked)} VR`;
  document.querySelector("#stat-returned").textContent = `${money(returned)} VR`;
  document.querySelector("#stat-rtp").textContent =
    observed === null ? "n/a" : `${observed.toFixed(1)}%`;

  renderBankrollChart();
}

/** Draws the bankroll sparkline. A flat or single-point series still renders a valid line. */
function renderBankrollChart() {
  const line = document.querySelector("#stats-spark-line");
  const note = document.querySelector("#stats-chart-note");
  if (!line || !note) return;

  if (bankrollSeries.length === 0) {
    line.setAttribute("points", "");
    note.textContent = "No rounds yet";
    return;
  }
  if (bankrollSeries.length === 1) {
    // A single sample has no shape, so draw a flat line at its own level rather than nothing.
    line.setAttribute("points", `0,60 600,60`);
    note.textContent = `1 round · latest ${money(bankrollSeries[0])} VR`;
    return;
  }

  const min = Math.min(...bankrollSeries);
  const max = Math.max(...bankrollSeries);
  const span = max - min || 1;
  const stepX = 600 / (bankrollSeries.length - 1);
  const points = bankrollSeries
    .map((value, index) => {
      const x = Math.round(index * stepX);
      // Invert Y: SVG grows downward, a bankroll grows upward.
      const y = Math.round(110 - ((value - min) / span) * 100);
      return `${x},${y}`;
    })
    .join(" ");
  line.setAttribute("points", points);
  note.textContent = `${bankrollSeries.length} rounds · low ${money(min)} · high ${money(max)} VR`;
}

/* ================================================================== limits
 * The guard itself lives on the server (lib/sessions.js). This only reads and writes it, and then
 * reports honestly whether a round is currently allowed.
 */
function renderLimits(limits) {
  const loss = document.querySelector("#limit-loss");
  if (!loss || !limits) return;
  loss.value = String(limits.sessionLossLimit || 0);
  document.querySelector("#limit-stake").value = String(limits.sessionStakeLimit || 0);
  document.querySelector("#limit-minutes").value = String(limits.sessionMinutes || 0);
  document.querySelector("#limit-exclude").checked = Boolean(limits.selfExcluded);

  const status = document.querySelector("#limits-status");
  status.classList.toggle("blocked", Boolean(limits.selfExcluded));
  status.textContent = limits.selfExcluded ? "Play is paused" : "No limits breached";
}

const limitsSave = document.querySelector("#limits-save");
if (limitsSave) {
  limitsSave.addEventListener("click", async () => {
    const payload = {
      sessionLossLimit: Number(document.querySelector("#limit-loss").value) || 0,
      sessionStakeLimit: Number(document.querySelector("#limit-stake").value) || 0,
      sessionMinutes: Number(document.querySelector("#limit-minutes").value) || 0,
      selfExcluded: document.querySelector("#limit-exclude").checked,
    };
    const status = document.querySelector("#limits-status");
    if (window.location.protocol === "file:") {
      status.textContent = "Limits need the server";
      return;
    }
    try {
      const response = await fetch(api("/api/limits"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const body = await response.json();
      if (!response.ok || !body.ok) throw new Error(body.error || "Could not save limits");
      renderLimits(body.limits);
      status.classList.toggle("blocked", false);
      status.textContent = "Limits saved";
      sound.click();
    } catch (error) {
      status.classList.add("blocked");
      status.textContent = error.message;
    }
  });
}

/* ================================================================== keyboard shortcuts
 * Space spins or plays, arrows nudge the bet. Ignored while an input or the search box has focus,
 * otherwise typing a number in the bet field would trigger a spin on every space.
 */
document.addEventListener("keydown", (event) => {
  const tag = (event.target && event.target.tagName) || "";
  const typing = tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT";
  if (typing) return;

  const activeView = document.querySelector(".view-panel.active");
  const inLobby = !activeView || activeView.classList.contains("lobby");

  if (event.code === "Space" || event.key === " ") {
    event.preventDefault();
    if (!inLobby) return;
    const slotPanel = document.querySelector("#slots");
    const slotVisible = slotPanel && slotPanel.getBoundingClientRect().top < window.innerHeight / 2;
    if (slotVisible) document.querySelector("#slot-spin").click();
    else if (TABLE_GAMES[selected] || INSTANT_TITLES[selected]) play.click();
    return;
  }

  if (event.key === "ArrowUp" || event.key === "ArrowDown") {
    const betInput = document.querySelector("#slot-bet");
    if (!betInput || document.activeElement === betInput) return;
    const step = event.shiftKey ? 25 : 5;
    const next = Number(betInput.value) + (event.key === "ArrowUp" ? step : -step);
    betInput.value = String(Math.min(250, Math.max(1, next)));
    event.preventDefault();
  }
});

/* ================================================================== startup
 *
 * Runs LAST, deliberately. This used to sit higher in the file, above the stats and sound blocks,
 * and called renderStats() - which reads `bankrollSeries`, a const declared further down. Touching
 * a const before its declaration throws a ReferenceError (the "temporal dead zone"), and because
 * that throw happened inside this block, loadStartupData() was never reached: no API request was
 * ever made, so every round hung on "Spinning..." while the console showed nothing. Keeping the
 * bootstrap at the very end means every declaration it depends on has already been evaluated.
 */
if (window.location.protocol !== "file:") {
  // Draw the chart from the persisted series immediately; the history call refreshes it when it lands.
  renderStats();
  loadStartupData();
}
