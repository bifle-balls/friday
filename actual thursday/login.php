<?php
session_start();

// Predefined users
$users = [
    'doctor' => [
        'password' => 'doctor123',
        'role' => 'Doctor',
    ],
    'receptionist' => [
        'password' => 'receptionist123',
        'role' => 'Receptionist',
    ],
];

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (isset($users[$username]) && $users[$username]['password'] === $password) {
        // Set session variables
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $users[$username]['role'];

        // Redirect to the dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f3df;
        background-repeat: no-repeat;
        background-position: center center;
        background-size: cover;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
        
    }
    .login-container {
        width: 30%;
        height: 30%;
        padding: 20px;
        position: relative;
        background-position: center center;
        background-repeat: no-repeat;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        opacity: 90%;
    }
    .login-container h2 {
        text-align: center;
        margin-bottom: 20px;
        color: #2c3e50;
    }
    .login-container form {
        align-content: center;
        display: flex;
        flex-direction: column;
        opacity: 90%;
        width: 45%;
        float: center center;
        margin-left: auto;
        margin-right: auto;
    }
    .login-container input {
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .login-container button {
        padding: 10px;
        background-color: #5d89bf;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        width: 50%;
        margin-left: auto;
        margin-right: auto;

    }
    .login-container button:hover {
        background-color: #334e76;
    }
    .login-container .error {
        color: red;
        font-size: 14px;
        margin: 10px 0;
        text-align: center;
    }
    .logo{
position: absolute;
padding-bottom: 30%;
    }
</style>

</head>
<body>
    <div class="logo">
    <img src="orologo.png" alt="oro-va dental clinic logo">
    </div>
    <div class="login-container">
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
