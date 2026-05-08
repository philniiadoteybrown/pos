<?php

session_start();
include "dbconn.php";

if(isset($_POST['login'])){
$user=$_POST['username'];
$pass=md5($_POST['password']);

$res=mysqli_query($conn,"SELECT * FROM users WHERE username='$user' AND password='$pass'");

if(mysqli_num_rows($res)){
$_SESSION['user']=mysqli_fetch_assoc($res);
$msg="Successfully logged in. Welcome.";
header('refresh:2; url=../../index.php');
}else{
$errmsg="Invalid login credentials.";
header('refresh:2; url=../../login.php');
}
}