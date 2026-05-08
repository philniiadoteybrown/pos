<?php
include "dbconn.php";

// 🔎 search
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// 📄 pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if($page <= 0) $page = 1;
if($limit <= 0) $limit = 10;

$offset = ($page - 1) * $limit;
?>