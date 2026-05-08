<?php
if(isset($_POST['closing'])){

    $data = $_POST['closing'];

    mysqli_begin_transaction($conn);

    try {

        foreach($data as $pid => $row){

            $pid = intval($pid);
            $physical = floatval($row['physical']);

            if($pid <= 0) continue;

            $res = mysqli_query($conn,"
                SELECT pname, totalstock 
                FROM products 
                WHERE productid='$pid'
            ");

            $prod = mysqli_fetch_assoc($res);
            if(!$prod) continue;

            $system = floatval($prod['totalstock']);
            $pname  = mysqli_real_escape_string($conn, $prod['pname']);

            $diff = $physical - $system;

            if($diff == 0) continue;

            // ✅ save WITH product name
            mysqli_query($conn,"
                INSERT INTO stock_adjustments
                (product_id, product_name, system_qty, physical_qty, difference, reason)
                VALUES
                ('$pid','$pname','$system','$physical','$diff','Smart closing')
            ");

            mysqli_query($conn,"
                UPDATE products 
                SET totalstock='$physical'
                WHERE productid='$pid'
            ");
        }

        mysqli_commit($conn);
        $msg = "✅ Closing saved successfully";

    } catch(Exception $e){

        mysqli_rollback($conn);
        $errmsg = "❌ Error: " . $e->getMessage();
    }
}
?>