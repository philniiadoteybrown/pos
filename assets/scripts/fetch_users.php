<?php
include "dbconn.php";

$search = $_GET['search'] ?? '';
$page   = $_GET['page'] ?? 1;
$limit  = $_GET['limit'] ?? 10;

$offset = ($page-1)*$limit;

$where = "WHERE 1";

if($search != ""){
    $where .= " AND (name LIKE '%$search%' OR username LIKE '%$search%')";
}

$res = mysqli_query($conn,"
SELECT * FROM users
$where
ORDER BY id DESC
LIMIT $offset,$limit
");
?>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>
        <?php while($u = mysqli_fetch_assoc($res)){ ?>
        <tr>
            <td><?= $u['name'] ?></td>
            <td><?= $u['username'] ?></td>
            <td><?= $u['role'] ?></td>
            <td>

                <a href="edit_user.php?id=<?php echo $u['id']; ?>" title="Edit"
                    class="btn btn-warning btn-animation btn-sm"> <span class="fa fa-edit"></span>
                </a>

                <a href="del_user.php?id=<?php echo $u['id']; ?>" title="Delete"
                    class="btn btn-danger btn-animation btn-sm"> <span class="fa fa-remove"></span>
                </a>

            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>