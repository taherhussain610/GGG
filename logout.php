<?php
require __DIR__ . '/includes/bootstrap.php';
logout_user();
header('Location: /index.php');
exit;
