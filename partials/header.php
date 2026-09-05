<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$activeNav = $activeNav ?? 'casino';
render_header($title ?? 'Neon Royale', $activeNav);
