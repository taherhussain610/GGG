<?php

declare(strict_types=1);

if (!isset($legalKey) || !is_string($legalKey)) {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$pages = [
    'responsible' => [
        'title' => 'Healthy play',
        'intro' => 'Neon Royale is entertainment, not a way to earn money. Credits are free and cannot leave the platform.',
        'sections' => [
            ['Keep control', 'Decide how long you want to play before starting, use a timer, and take regular breaks. Stop when play no longer feels enjoyable.'],
            ['Credits are not money', 'Never treat a virtual balance, jackpot, payout, or sports return as money or property. There are no deposits, purchases, withdrawals, gifts, or prizes.'],
            ['Take a break', 'You can log out at any time. If you want an account pause, contact the site administrator and ask for the account to be suspended.'],
            ['For adults', 'This demonstration is intended for adults. Parents and guardians should use device-level controls to prevent unsupervised access by children.'],
        ],
    ],
    'terms' => [
        'title' => 'Terms of play',
        'intro' => 'These concise terms explain the intended use of this demonstration platform.',
        'sections' => [
            ['Play-money service', 'All balances, stakes, payouts, bonuses, jackpots, sports events, and results are simulated. They have no cash value and cannot be sold, transferred, purchased, or redeemed.'],
            ['Account responsibility', 'Provide accurate account information, protect your password, and do not share access. Automated play, abuse, exploitation, and attempts to manipulate balances are prohibited.'],
            ['Availability', 'The service may reset virtual balances, events, rankings, or accounts and may be changed or unavailable without notice.'],
            ['Fair use', 'Results use server-side randomness. Past results do not predict future results. Errors may be corrected using the transaction and audit records.'],
        ],
    ],
    'privacy' => [
        'title' => 'Privacy',
        'intro' => 'This installation stores only the account and game data needed to operate the play-money experience.',
        'sections' => [
            ['Data stored', 'The database stores usernames, email addresses, password hashes, virtual balances, XP, game rounds, sports predictions, and security audit records. Plain-text passwords are never stored.'],
            ['How data is used', 'Data authenticates accounts, maintains virtual balances, displays private history, prevents abuse, and gives administrators operational visibility.'],
            ['Sharing and payments', 'The application includes no advertising tracker, payment processor, or external casino provider by default. Hosting and database providers may process operational data under their own terms.'],
            ['Your choices', 'Ask the site administrator for account access, correction, suspension, or deletion. Legal retention requirements may apply to security records.'],
        ],
    ],
];

if (!isset($pages[$legalKey])) {
    http_response_code(404);
    exit;
}

$content = $pages[$legalKey];
$pageTitle = $content['title'];
require ROOT_PATH . '/partials/header.php';
?>
<article class="page-shell legal-page">
    <p class="eyebrow">Neon Royale</p>
    <h1><?= e($content['title']) ?></h1>
    <p class="legal-intro"><?= e($content['intro']) ?></p>
    <?php foreach ($content['sections'] as [$heading, $copy]): ?>
        <section>
            <h2><?= e($heading) ?></h2>
            <p><?= e($copy) ?></p>
        </section>
    <?php endforeach; ?>
</article>
<?php require ROOT_PATH . '/partials/footer.php'; ?>
