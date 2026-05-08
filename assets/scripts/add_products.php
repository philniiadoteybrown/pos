<?php include "dbconn.php";

if(isset($_POST['addproduct'])){

$pname=strtoupper($_POST['pname']);
$pdesc=strtoupper($_POST['pdesc']);
$unit=$_POST['unit'];
$qty=$_POST['qty'];
$unitprice=$_POST['unitprice'];
$selling=$_POST['sellingprice'];
$alert=$_POST['qtyalert'];

$res=mysqli_query($conn,"SELECT productid FROM products ORDER BY productid DESC LIMIT 1");

if(mysqli_num_rows($res)){
$row=mysqli_fetch_assoc($res);
$num=(int)substr($row['productid'],3)+1;
$productid="PRD".str_pad($num,4,"0",STR_PAD_LEFT);
}else{
$productid="PRD0001";
}

mysqli_query($conn,"
INSERT INTO products(productid,pname,pdesc,unit,qty,unitprice,sellingprice,qtyalert)
VALUES('$productid','$pname','$pdesc','$unit','$qty','$unitprice','$selling','$alert')
");

$msg="Successfully Saved.";
header('refresh:2; url=index.php');
}
?>