<?php
session_start();
// Redirect to index page if already logged in
if (isset($_SESSION["admin"])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-5" style="max-width: 500px;">
        <?php
        if (isset($_POST["login"])) {
            $username = trim($_POST["username"]);
            $password = $_POST["password"];
            
            require_once "database.php";
            
            // Using prepared statement for security against SQL Injection
            $sql = "SELECT * FROM admin WHERE username = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_array($result, MYSQLI_ASSOC);

            if ($user) {
                // Verify hashed password
                if (password_verify($password, $user["password"])) {
                    $_SESSION["admin"] = $user["username"];
                    $_SESSION["admin_id"] = $user["admin_id"];
                    header("Location: index.php");
                    exit();
                } else {
                    echo "<div class='alert alert-danger'>Password does not match!</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>Username not found!</div>";
            }
        }
        ?>
      <form action="login.php" method="post">
        <div class="form-group mb-3">
            <input type="text" placeholder="Enter Username" name="username" class="form-control" required>
        </div>
        <div class="form-group mb-3">
            <input type="password" placeholder="Enter Password" name="password" class="form-control" required>
        </div>
        <div class="form-btn mb-3">
            <input type="submit" value="Login" name="login" class="btn btn-primary w-100">
        </div>
      </form>
      <div><p>Not registered yet? <a href="registration.php">Register Here</a></p></div>
    </div>
</body>
</html>