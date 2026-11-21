
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Login form submit হলে
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    // খালি ইনপুট পরীক্ষা
    if ($identity === '' || $password === '') {
        $_SESSION['login_error'] =
            'Please enter your username/email and password.';

        header('Location: login.php');
        exit;
    }

    try {
        // Username অথবা email দিয়ে account খোঁজা
        $stmt = $pdo->prepare(
            'SELECT id, name, username, email, password_hash, email_verified
             FROM users
             WHERE username = :username OR email = :email
             LIMIT 1'
        );

        $stmt->execute([
            ':username' => $identity,
            ':email'    => $identity
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Account এবং password যাচাই
        if (
            !$user ||
            !password_verify($password, $user['password_hash'])
        ) {
            $_SESSION['login_error'] =
                'Invalid username/email or password.';

            header('Location: login.php');
            exit;
        }

        // Email verified কি না পরীক্ষা
        if ((int) $user['email_verified'] !== 1) {
            $_SESSION['login_error'] =
                'Please verify your email before logging in.';

            header('Location: login.php');
            exit;
        }

        // নিরাপদ session তৈরি
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['user_email'] = $user['email'];

        // Login সফল হলে project-এর মূল Homepage-এ যাবে
        header('Location: ../index.php');
        exit;

    } catch (PDOException $e) {

        // বিস্তারিত error log-এ থাকবে
        error_log('Login error: ' . $e->getMessage());

        $_SESSION['login_error'] =
            'Something went wrong. Please try again.';

        header('Location: login.php');
        exit;
    }
}

// Login/Register UI দেখানো
require __DIR__ . '/auth-switch.php';
