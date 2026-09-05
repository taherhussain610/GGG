<?php
require __DIR__ . '/../includes/bootstrap.php';
json_response(['leaders' => leaderboard_rows()]);
