<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/home.php");
} else {
    header("Location: " . BASE_URL . "/home.php");
}
exit();
