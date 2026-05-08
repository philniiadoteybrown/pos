<?php
include "assets/scripts/dbconn.php";

$id = $_GET['id'];

mysqli_query($conn,"DELETE FROM units WHERE id='$id'");

header("Location: product_units.php?deleted=1");