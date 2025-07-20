<?php
require_once 'includes/auth.php';

// Fazer logout
$auth->logout();

// Redirecionar para login
redirect('login.php');
?>

