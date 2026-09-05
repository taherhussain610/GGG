<?php
require __DIR__ . '/../includes/bootstrap.php';
$leaders = leaderboard_rows();
json_response(['ok' => true, 'leaders' => $leaders, 'rows' => $leaders]);
