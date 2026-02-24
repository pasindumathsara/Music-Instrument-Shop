<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

session_destroy();
header("Location: " . BASE_URL . "/login.php");
exit();