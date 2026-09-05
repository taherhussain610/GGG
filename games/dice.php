<?php
require __DIR__ . '/_game.php';
$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $pick = is_string($_POST['pick'] ?? null) ? $_POST['pick'] : 'high';
        $outcome = play_tx('dice', (int) ($_POST['bet'] ?? 0), ['selection' => $pick]);
        $msg = $outcome['result'] . ' · payout ' . number_format((int) $outcome['payout']);
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
render_game_page('Dice', 'dice', $msg, $err, static function (): void {
    ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label><span>Pick</span>
            <select name="pick">
                <option value="high">High</option>
                <option value="low">Low</option>
            </select>
        </label>
        <label><span>Bet amount</span><input type="number" min="10" name="bet" value="100" required></label>
        <button type="submit">Roll dice</button>
    </form>
    <?php
});
