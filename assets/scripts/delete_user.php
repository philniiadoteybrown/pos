<?php
include "dbconn.php";

$id = $_GET['id'];

mysqli_query($conn,"DELETE FROM users WHERE id='$id'");

header("Location: ../../users.php");