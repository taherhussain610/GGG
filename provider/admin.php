<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim((string) ($_POST['action'] ?? ''));
    try {
        if ($action === 'toggle_provider') {
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $enabled = (string) ($_POST['enabled'] ?? '') === '1';
            provider_set_enabled($providerId, $enabled);
            provider_log_api($providerId, 'internal', '/provider/admin.php', 'POST', null, 200, ['action' => $action], ['enabled' => $enabled]);
            flash('Provider status updated.', 'success');
        } elseif ($action === 'toggle_game') {
            $gameId = (int) ($_POST['game_id'] ?? 0);
            $enabled = (string) ($_POST['enabled'] ?? '') === '1';
            provider_set_game_enabled($gameId, $enabled);
            provider_log_api(null, 'internal', '/provider/admin.php', 'POST', null, 200, ['action' => $action, 'game_id' => $gameId], ['enabled' => $enabled]);
            flash('Catalog game status updated.', 'success');
        } elseif ($action === 'health_check') {
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            $status = provider_check_health($providerId);
            flash('Provider health check: ' . $status . '.', 'success');
        } elseif ($action === 'update_settings') {
            $providerId = (int) ($_POST['provider_id'] ?? 0);
            provider_update_settings(
                $providerId,
                (string) ($_POST['api_base_url'] ?? ''),
                (string) ($_POST['credential_hint'] ?? '')
            );
            provider_log_api($providerId, 'internal', '/provider/admin.php', 'POST', null, 200, ['action' => $action], ['updated' => true]);
            flash('Provider placeholders updated. No credentials were stored.', 'success');
        } else {
            throw new InvalidArgumentException('Unsupported provider admin action.');
        }
    } catch (InvalidArgumentException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable) {
        flash('Unable to update provider configuration.', 'danger');
    }

    header('Location: /provider/admin.php');
    exit;
}

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
    'category' => (string) ($_GET['category'] ?? ''),
    'provider' => (string) ($_GET['provider'] ?? ''),
];
$data = provider_admin_snapshot($filters);
$stats = $data['stats'];
render_header('Provider management', 'admin');
?>
<section class="hero">
    <div class="panel">
        <span class="badge">Provider management</span>
        <h1>Sandbox provider control center</h1>
        <p class="muted">Manage demo feeds, catalog availability, launches, sessions, wallet records, health, callbacks and API logs. This build cannot connect to or settle real-money providers.</p>
        <div class="toolbar toolbar-tabs">
            <a class="mini-tab" href="/admin.php">Platform admin</a>
            <a class="mini-tab active" href="/provider/admin.php">Providers</a>
            <a class="mini-tab" href="/provider/catalog.php">Catalog JSON</a>
            <a class="mini-tab" href="/provider/config.php">API config</a>
        </div>
    </div>
    <div class="panel">
        <span class="badge <?= $data['ready'] ? 'live' : 'finished' ?>"><?= $data['ready'] ? 'Schema ready' : 'Setup required' ?></span>
        <h2>Safe integration boundary</h2>
        <p class="muted">Credential fields are status placeholders only. External network calls and production mode are intentionally absent.</p>
        <p><strong>Webhook secret:</strong> <?= trim((string) (app_config()['provider_webhook_secret'] ?? '')) !== '' ? 'Configured' : 'Not configured' ?></p>
    </div>
</section>

<?php if (!$data['ready']): ?>
    <section class="panel">
        <h2>Import required</h2>
        <p class="muted">Import the provider tables and seed records from <code>/database.sql</code>, then reload this page.</p>
    </section>
<?php else: ?>
    <section class="stats-grid compact-stats">
        <article class="stat-card"><span class="badge">Providers</span><h3><?= number_format((int) $stats['providers']) ?></h3><p class="muted">Configured adapters</p></article>
        <article class="stat-card"><span class="badge">Catalog</span><h3><?= number_format((int) $stats['enabled_games']) ?></h3><p class="muted">Enabled games</p></article>
        <article class="stat-card"><span class="badge">Sessions</span><h3><?= number_format((int) $stats['active_sessions']) ?></h3><p class="muted">Active launches</p></article>
        <article class="stat-card"><span class="badge">Rounds</span><h3><?= number_format((int) $stats['rounds']) ?></h3><p class="muted">Settled demo rounds</p></article>
    </section>

    <section class="section-head">
        <div>
            <span class="badge">Adapters</span>
            <h2>Providers and credentials</h2>
        </div>
    </section>
    <section class="provider-grid">
        <?php foreach ($data['providers'] as $provider): ?>
            <article class="panel provider-admin-card">
                <div class="section-head">
                    <div>
                        <span class="badge <?= $provider['health_status'] === 'healthy' ? 'live' : 'finished' ?>"><?= e(ucfirst($provider['health_status'])) ?></span>
                        <h3><?= e($provider['name']) ?></h3>
                    </div>
                    <strong><?= (int) $provider['is_enabled'] === 1 ? 'Enabled' : 'Disabled' ?></strong>
                </div>
                <dl class="detail-list">
                    <div><dt>Provider ID</dt><dd><?= e($provider['slug']) ?></dd></div>
                    <div><dt>Adapter</dt><dd><?= e($provider['adapter']) ?> / <?= e($provider['environment']) ?></dd></div>
                    <div><dt>Catalog</dt><dd><?= number_format((int) $provider['enabled_game_count']) ?> / <?= number_format((int) $provider['game_count']) ?> enabled</dd></div>
                    <div><dt>Sessions</dt><dd><?= number_format((int) $provider['active_session_count']) ?> active</dd></div>
                    <div><dt>Last health check</dt><dd><?= $provider['last_health_at'] ? e($provider['last_health_at']) : 'Never' ?></dd></div>
                </dl>
                <div class="inline-actions">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle_provider">
                        <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
                        <input type="hidden" name="enabled" value="<?= (int) $provider['is_enabled'] === 1 ? '0' : '1' ?>">
                        <button type="submit" class="secondary"><?= (int) $provider['is_enabled'] === 1 ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="health_check">
                        <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
                        <button type="submit" class="secondary">Check health</button>
                    </form>
                </div>
                <details>
                    <summary>Configuration placeholders</summary>
                    <form method="post" class="provider-config-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_settings">
                        <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
                        <label>
                            <span>Sandbox API base URL (not called)</span>
                            <input type="url" name="api_base_url" value="<?= e((string) ($provider['api_base_url'] ?? '')) ?>" placeholder="https://sandbox.example.invalid">
                        </label>
                        <label>
                            <span>Credential status / instructions</span>
                            <input type="text" name="credential_hint" maxlength="190" value="<?= e($provider['credential_hint']) ?>" required>
                        </label>
                        <button type="submit">Save placeholders</button>
                    </form>
                </details>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="panel">
        <div class="section-head">
            <div>
                <span class="badge">Catalog</span>
                <h2>Games, IDs and thumbnails</h2>
            </div>
        </div>
        <form method="get" class="catalog-filters">
            <label><span>Search</span><input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Title or provider game ID"></label>
            <label>
                <span>Category</span>
                <select name="category">
                    <option value="">All categories</option>
                    <?php foreach (['slots', 'live', 'instant', 'table', 'cards'] as $category): ?>
                        <option value="<?= e($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= e(ucfirst($category)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Provider</span>
                <select name="provider">
                    <option value="">All providers</option>
                    <?php foreach ($data['providers'] as $provider): ?>
                        <option value="<?= e($provider['slug']) ?>" <?= $filters['provider'] === $provider['slug'] ? 'selected' : '' ?>><?= e($provider['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Apply filters</button>
            <a class="button-link secondary-link" href="/provider/admin.php">Reset</a>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Game</th><th>Provider ID</th><th>Provider</th><th>Category</th><th>Limits</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (!$data['games']): ?>
                        <tr><td colspan="7" class="muted">No catalog games match these filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data['games'] as $game): ?>
                            <tr>
                                <td><strong><?= e($game['title']) ?></strong><br><span class="muted"><?= e($game['slug']) ?></span></td>
                                <td><code><?= e($game['provider_game_id']) ?></code></td>
                                <td><?= e($game['provider_name']) ?></td>
                                <td><?= e(ucfirst($game['category'])) ?></td>
                                <td><?= fmt_coins((int) $game['min_bet']) ?>–<?= fmt_coins((int) $game['max_bet']) ?></td>
                                <td><?= (int) $game['is_enabled'] === 1 ? 'Enabled' : 'Disabled' ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="toggle_game">
                                        <input type="hidden" name="game_id" value="<?= (int) $game['id'] ?>">
                                        <input type="hidden" name="enabled" value="<?= (int) $game['is_enabled'] === 1 ? '0' : '1' ?>">
                                        <button type="submit" class="secondary small"><?= (int) $game['is_enabled'] === 1 ? 'Disable' : 'Enable' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="results-grid">
        <article class="panel table-wrap">
            <h2>Recent sessions</h2>
            <table>
                <thead><tr><th>Session</th><th>Player / game</th><th>Status</th><th>Last activity</th></tr></thead>
                <tbody>
                    <?php if (!$data['sessions']): ?>
                        <tr><td colspan="4" class="muted">No provider sessions yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data['sessions'] as $session): ?>
                            <tr>
                                <td><code><?= e(substr($session['public_id'], 0, 10)) ?>…</code></td>
                                <td><?= e($session['username']) ?> · <?= e($session['game_title']) ?><br><span class="muted"><?= e($session['provider_name']) ?></span></td>
                                <td><?= e(ucfirst($session['status'])) ?></td>
                                <td><?= e($session['last_activity_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>
        <article class="panel table-wrap">
            <h2>Provider transactions</h2>
            <table>
                <thead><tr><th>Transaction</th><th>Player / game</th><th>Type</th><th>Amount</th></tr></thead>
                <tbody>
                    <?php if (!$data['transactions']): ?>
                        <tr><td colspan="4" class="muted">No provider transactions yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data['transactions'] as $transaction): ?>
                            <tr>
                                <td><code><?= e(substr($transaction['external_transaction_id'], 0, 16)) ?>…</code></td>
                                <td><?= e($transaction['username']) ?> · <?= e($transaction['game_title']) ?></td>
                                <td><?= e(ucfirst($transaction['transaction_type'])) ?></td>
                                <td><?= fmt_coins((int) $transaction['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>

    <section class="results-grid">
        <article class="panel table-wrap">
            <h2>Jackpots</h2>
            <table>
                <thead><tr><th>Name</th><th>Game</th><th>Amount</th><th>Updated</th></tr></thead>
                <tbody>
                    <?php if (!$data['jackpots']): ?>
                        <tr><td colspan="4" class="muted">No provider jackpots configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data['jackpots'] as $jackpot): ?>
                            <tr><td><?= e($jackpot['name']) ?></td><td><?= e((string) ($jackpot['game_title'] ?? 'Network')) ?></td><td><?= fmt_coins((int) $jackpot['amount']) ?></td><td><?= e($jackpot['updated_at']) ?></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>
        <article class="panel table-wrap">
            <h2>API logs</h2>
            <table>
                <thead><tr><th>Time</th><th>Direction</th><th>Endpoint</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (!$data['logs']): ?>
                        <tr><td colspan="4" class="muted">No provider API calls logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data['logs'] as $log): ?>
                            <tr><td><?= e($log['created_at']) ?></td><td><?= e(ucfirst($log['direction'])) ?></td><td><code><?= e($log['endpoint']) ?></code></td><td><?= (int) $log['status_code'] ?> · <?= (int) $log['duration_ms'] ?>ms</td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>
<?php endif; ?>
<?php render_footer('admin'); ?>
