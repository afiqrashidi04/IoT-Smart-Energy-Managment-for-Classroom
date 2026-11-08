<?php
session_start();
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
    <title>Admin Registration</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-5" style="max-width: 500px;">
        <?php
        if (isset($_POST["submit"])) {
            $username = trim($_POST["username"]);
            $password = $_POST["password"];
            $passwordRepeat = $_POST["repeat_password"];
            
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $errors = array();
            
            // Input Validation
            if (empty($username) OR empty($password) OR empty($passwordRepeat)) {
                array_push($errors, "All fields are required.");
            }
            if (strlen($username) > 50) {
                array_push($errors, "Username cannot exceed 50 characters.");
            }
            if (strlen($password) < 8) {
                array_push($errors, "Password must be at least 8 characters long.");
            }
            if ($password !== $passwordRepeat) {
                array_push($errors, "Passwords do not match.");
            }
            
            require_once "database.php";

            // Check if username already exists using prepared statements
            $sql = "SELECT * FROM admin WHERE username = ?";
            $stmt = mysqli_stmt_init($conn);
            if (mysqli_stmt_prepare($stmt, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if (mysqli_num_rows($result) > 0) {
                    array_push($errors, "Username already exists!");
                }
            }

            // Display errors if any
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    echo "<div class='alert alert-danger'>$error</div>";
                }
            } else {
                // Insert new admin record
                $sql = "INSERT INTO admin (username, password) VALUES (?, ?)";
                $stmt = mysqli_stmt_init($conn);
                $prepareStmt = mysqli_stmt_prepare($stmt, $sql);
                if ($prepareStmt) {
                    mysqli_stmt_bind_param($stmt, "ss", $username, $passwordHash);
                    mysqli_stmt_execute($stmt);
                    echo "<div class='alert alert-success'>Admin registered successfully! <a href='login.php'>Login Here</a></div>";
                } else {
                    die("Something went wrong on the server.");
                }
            }
        }
        ?>
        <form action="registration.php" method="post">
            <div class="form-group mb-3">
                <input type="text" class="form-control" name="username" placeholder="Username:" required>
            </div>
            <div class="form-group mb-3">
                <input type="password" class="form-control" name="password" placeholder="Password:" required>
            </div>
            <div class="form-group mb-3">
                <input type="password" class="form-control" name="repeat_password" placeholder="Repeat Password:" required>
            </div>
            <div class="form-btn mb-3">
                <input type="submit" class="btn btn-primary w-100" value="Register" name="submit">
            </div>
        </form>
        <div>
            <p>Already Registered? <a href="login.php">Login Here</a></p>
        </div>
    </div>
</body>
</html>