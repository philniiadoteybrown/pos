<?php
include "dbconn.php";

$name = $_POST['name'];
$username = $_POST['username'];
$password = md5($_POST['password']); // 🔥 MD5
$role = $_POST['role'];

mysqli_query($conn,"
INSERT INTO users(name,username,password,role)
VALUES('$name','$username','$password','$role')
");

header("Location: ../../users.php");