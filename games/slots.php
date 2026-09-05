<?php
require __DIR__ . '/_game.php';
$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $outcome = play_tx('slots', (int) ($_POST['bet'] ?? 0));
        $msg = $outcome['result'] . ' · payout ' . number_format((int) $outcome['payout']);
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
render_game_page('Slots', 'slots', $msg, $err, static function (): void {
    ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label><span>Bet amount</span><input type="number" min="10" name="bet" value="100" required></label>
        <button type="submit">Spin reels</button>
    </form>
    <?php
});
