<?php
require __DIR__ . '/_game.php';
$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $choice = is_string($_POST['choice'] ?? null) ? $_POST['choice'] : 'red';
        $outcome = play_tx('roulette', (int) ($_POST['bet'] ?? 0), ['selection' => $choice]);
        $msg = $outcome['result'] . ' · payout ' . number_format((int) $outcome['payout']);
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
render_game_page('Roulette', 'roulette', $msg, $err, static function (): void {
    ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label><span>Pick</span>
            <select name="choice">
                <option value="red">Red</option>
                <option value="black">Black</option>
            </select>
        </label>
        <label><span>Bet amount</span><input type="number" min="10" name="bet" value="100" required></label>
        <button type="submit">Spin wheel</button>
    </form>
    <?php
});
