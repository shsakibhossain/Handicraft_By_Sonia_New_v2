
<?php
session_start();

require_once "../config/database.php";

if (!empty($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === "" || $password === "") {
        $error = "Please enter username/email and password.";
    } else {

        $stmt = $pdo->prepare("
            SELECT id, name, username, email, password_hash, status
            FROM admins
            WHERE username = :login OR email = :login
            LIMIT 1
        ");

        $stmt->execute(['login' => $login]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $admin &&
            $admin['status'] === 'active' &&
            password_verify($password, $admin['password_hash'])
        ) {

            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_username'] = $admin['username'];

            $update = $pdo->prepare("
                UPDATE admins
                SET last_login = NOW()
                WHERE id = ?
            ");

            $update->execute([$admin['id']]);

            header("Location: dashboard.php");
            exit;

        } else {
            $error = "Invalid login information or inactive account.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | Handicraft by Sonia</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3e8ff;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.10);
        }

        h2 {
            text-align: center;
            color: #5b21b6;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #777;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #ddd;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 15px;
        }

        button {
            width: 100%;
            padding: 13px;
            background: #5b21b6;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #4c1d95;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
        }
    </style>
</head>

<body>

<div class="login-box">

    <h2>Admin Login</h2>

    <p class="subtitle">Handicraft by Sonia</p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Username or Email</label>

        <input
            type="text"
            name="login"
            placeholder="Enter username or email"
            required
        >

        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Enter your password"
            required
        >

        <button type="submit">Login to Dashboard</button>

    </form>

</div>

</body>
</html>
