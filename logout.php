<?php
include(__DIR__ . '/config/session.php');

$_SESSION = [];

session_destroy();

header("Location: /index.php");
exit();
?>
