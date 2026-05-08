<?php
session_start();

if($_SESSION['user']['role'] == 'cashier'){
    session_destroy();
    header("Location: login.php");
    exit;
}

// non-cashier: allow logout only if explicitly forced
if(isset($_GET['force']) && $_GET['force'] == 1){
    session_destroy();
    header("Location: login.php");
    exit;
}

// otherwise block
header("Location: index.php");
exit;
?>