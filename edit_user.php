<?php
$pagetitle="Edit User";
include "assets/scripts/auth.php";
include "assets/scripts/dbconn.php";



// 🔍 GET ID
if(!isset($_GET['id'])){
    die("❌ No User selected");
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

// 🔍 FETCH DATA
$res = mysqli_query($conn,"SELECT * FROM users WHERE id='$id'");
$data = mysqli_fetch_assoc($res);

if(!$data){
    die("❌ User not found");
}

// ================= UPDATE =================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id       = mysqli_real_escape_string($conn, $_POST['id']);
    $name     = mysqli_real_escape_string($conn, $_POST['name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $role     = mysqli_real_escape_string($conn, $_POST['role']);

    if(empty($name) || empty($username)){
        $errmsg = "All fields are required";
    } else {

        if(!empty($_POST['password'])){
            $password = md5($_POST['password']);

            $query = "
                UPDATE users SET
                name='$name',
                username='$username',
                password='$password',
                role='$role'
                WHERE id='$id'
            ";
        } else {
            $query = "
                UPDATE users SET
                name='$name',
                username='$username',
                role='$role'
                WHERE id='$id'
            ";
        }

        if(mysqli_query($conn, $query)){
            $msg="Updated successfully";
            header("Location: users.php");
            exit;
        } else {
            $errmsg = "Update failed: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html>


<head>

    <?php include "assets/sections/headers/header_tag.php" ?>
</head>

<body class="fixed-left">
    <!-- Loader -->
    <div id="preloader">
        <div id="status">
            <div class="spinner"></div>
        </div>
    </div><!-- Begin page -->
    <div id="wrapper">
        <!-- ========== Left Sidebar Start ========== -->
        <?php include "assets/sections/leftside.php" ?>
        <!-- Left Sidebar End -->
        <!-- Start right Content here -->
        <div class="content-page">
            <!-- Start content -->
            <div class="content">
                <!-- Top Bar Start -->
                <?php include "assets/sections/topbar.php" ?>
                <!-- Top Bar End -->
                <div class="page-content-wrapper">
                    <div class="container-fluid">
                        <br>
                        <div class="row">
                            <div class="col-lg-12">

                                <div class="card m-b-30">
                                    <div class="card-body bootstrap-select-1">
                                        <h2>Edit User</h2>
                                        <?php if(isset($msg)){ ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <button type="button" class="close" data-dismiss="alert"
                                                aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                            <?php echo $msg ?>
                                        </div>
                                        <?php } ?>
                                        <?php if(isset($errmsg)){ ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <button type="button" class="close" data-dismiss="alert"
                                                aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                            <?php echo $msg ?>
                                        </div>
                                        <?php } ?>
                                        <form method="post" action="">

                                            <div class="card-body">
                                                <input type="hidden" name="id" value="<?= $data['id'] ?>">
                                                <div class="form-group">
                                                    <label> Name</label>
                                                    <input type="text" class="form-control" name="name"
                                                        value="<?php echo $data['name']; ?>" required>
                                                </div>

                                                <div class="form-group">
                                                    <label>Username</label>
                                                    <input type="text" class="form-control" name="username"
                                                        value="<?php echo $data['username']; ?>" required>
                                                </div>

                                                <div class="section-title">Role</div>

                                                <div class="form-group">
                                                    <label>Select Role</label>
                                                    <select class="form-control" name="role">
                                                        <option value="<?php echo $data['role']; ?>">
                                                            <?php echo $data['role']; ?></option>
                                                        <option value="admin">Admin</option>
                                                        <option value="cashier">Cashier</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Password</label>
                                                    <input type="password" class="form-control" name="password">
                                                </div>


                                            </div>

                                            <div class="card-footer">
                                                <button class="btn btn-primary" type="submit">Update</button>
                                            </div>

                                        </form>

                                    </div>
                                </div>


                            </div>


                        </div>
                    </div>

                </div><!-- end row -->
            </div><!-- container -->
        </div><!-- Page content Wrapper -->
    </div><!-- content -->
    <footer class="footer"><?php include "assets/sections/footers/footer.php" ?></footer>
    </div><!-- End Right content here -->
    </div><!-- END wrapper -->
    <!-- jQuery  -->
    <?php include "assets/sections/footers/jqueryscripts.php" ?>
</body>
<!-- Mirrored from mannatthemes.com/annex/vertical/form-advanced.html by HTTrack Website Copier/3.x [XR&CO'2014], Sat, 25 Apr 2026 11:14:09 GMT -->

</html>