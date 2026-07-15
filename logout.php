<?php
// logout.php
require_once 'config.php';
require_once 'includes/Database.php';  // Add this line before Auth
require_once 'includes/Auth.php';

$auth = new Auth();
$auth->logout();

header('Location: ' . BASE_URL . 'index.php');
exit;