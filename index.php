<?php
session_start();

if (isset($_SESSION['uid'])) {
    header("Location: directives/dashboard.php");
    exit();
} else {
    header("Location: auth/login.php");
    exit();
}
?>