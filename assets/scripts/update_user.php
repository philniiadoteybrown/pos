<?php
include "dbconn.php";

$id = $_POST['id'];
$name = $_POST['name'];
$username = $_POST['username'];
$role = $_POST['role'];

if(!empty($_POST['password'])){
    $password = md5($_POST['password']);

    mysqli_query($conn,"
    UPDATE users SET
    name='$name',
    username='$username',
    password='$password',
    role='$role'
    WHERE id='$id'
    ");
}else{
    mysqli_query($conn,"
    UPDATE users SET
    name='$name',
    username='$username',
    role='$role'
    WHERE id='$id'
    ");
}

header("Location: ../../users.php");