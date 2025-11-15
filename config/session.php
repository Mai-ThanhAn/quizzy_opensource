<?php
$timeout_duration = 10 * 60;

ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', $timeout_duration);

session_start();

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}

$_SESSION['LAST_ACTIVITY'] = time();
?>
