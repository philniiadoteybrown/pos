<?php
$pagetitle = "Welcome - Login";
session_start();
include "assets/scripts/dbconn.php";

if (isset($_POST['login'])) {

    $user = $_POST['username'];
    $pass = md5($_POST['password']);

    $res = mysqli_query(
        $conn,
        "SELECT * FROM users WHERE username='$user' AND password='$pass'"
    );

    if (mysqli_num_rows($res) > 0) {

        $userData = mysqli_fetch_assoc($res);

        // Store full user
        $_SESSION['user'] = $userData;

        // Store role separately
        $_SESSION['role'] = $userData['role'];
        $_SESSION['id'] = $userData['id'];
        $_SESSION['username'] = $userData['username'];

        // Normalize the role once so index.php can apply the correct menu.
        $_SESSION['role'] = strtolower(trim((string)$userData['role']));

        $msg = "Successfully logged in. Welcome.";

        /*
         * Both roles start at the dashboard.
         * index.php decides what the user is allowed to see:
         *   - cashier: Dashboard, Open Scanner, POS Terminal, People
         *   - admin:    the normal/current index.php menu
         */
        header("Location: index.php");
        exit();

    } else {

        // Do not reveal whether the username or password was incorrect.
        $errmsg = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php include "assets/sections/headers/header_tag.php" ?>
</head>

<body class="fixed-left">

    <div class="accountbg"></div>

    <div class="wrapper-page">

        <div class="card">

            <div class="card-body">

                <h3 class="text-center mt-0 m-b-15">
                    <a href="index.html" class="logo logo-admin">
                        <img
                            src="assets/images/phlogo.png"
                            height="70"
                            alt="logo"
                        >
                    </a>
                </h3>

                <div class="p-3">

                    <?php if (isset($msg)) { ?>

                        <div
                            class="alert alert-success alert-dismissible fade show"
                            role="alert"
                        >
                            <button
                                type="button"
                                class="close"
                                data-dismiss="alert"
                                aria-label="Close"
                            >
                                <span aria-hidden="true">&times;</span>
                            </button>

                            <?php echo $msg ?>
                        </div>

                    <?php } ?>

                    <form
                        class="form-horizontal m-t-20"
                        action=""
                        method="post"
                    >

                        <div class="form-group row">

                            <div class="col-12">

                                <input
                                    class="form-control"
                                    name="username"
                                    type="text"
                                    required
                                    placeholder="Username"
                                >

                            </div>

                        </div>


                        <div class="form-group row">

                            <div class="col-12">

                                <input
                                    class="form-control"
                                    name="password"
                                    type="password"
                                    required
                                    placeholder="Password"
                                >

                            </div>

                        </div>


                        <div class="form-group text-center row m-t-20">

                            <div class="col-12">

                                <button
                                    name="login"
                                    class="btn btn-danger btn-block waves-effect waves-light"
                                    type="submit"
                                >
                                    Log In
                                </button>

                            </div>

                        </div>


                        <div class="form-group m-t-10 mb-0 row">

                            <div class="col-sm-7 m-t-20">

                                <a
                                    href="pages-recoverpw.html"
                                    class="text-muted"
                                >
                                    <i class="mdi mdi-lock"></i>
                                    <small>Forgot your password ?</small>
                                </a>

                            </div>

                        </div>

                    </form>


                    <?php if (isset($errmsg)) { ?>

                        <div
                            class="alert alert-danger alert-dismissible fade show"
                            role="alert"
                        >

                            <button
                                type="button"
                                class="close"
                                data-dismiss="alert"
                                aria-label="Close"
                            >
                                <span aria-hidden="true">&times;</span>
                            </button>

                            <?php echo $errmsg ?>

                        </div>

                    <?php } ?>

                </div>

            </div>

        </div>

    </div>


    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/modernizr.min.js"></script>
    <script src="assets/js/detect.js"></script>
    <script src="assets/js/fastclick.js"></script>
    <script src="assets/js/jquery.slimscroll.js"></script>
    <script src="assets/js/jquery.blockUI.js"></script>
    <script src="assets/js/waves.js"></script>
    <script src="assets/js/jquery.nicescroll.js"></script>
    <script src="assets/js/jquery.scrollTo.min.js"></script>
    <script src="assets/js/app.js"></script>

</body>

</html>
