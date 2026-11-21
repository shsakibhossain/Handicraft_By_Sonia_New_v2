
<?php
session_start();
date_default_timezone_set('Asia/Dhaka');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Registration form থেকে তথ্য নেওয়া
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $name      = trim($firstName . ' ' . $lastName);
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $terms     = $_POST['terms'] ?? '';

    // 2. ভুল তথ্য যাচাই
    if (
        $firstName === '' ||
        $lastName === '' ||
        $username === '' ||
        $email === '' ||
        $phone === '' ||
        $password === ''
    ) {
        $_SESSION['register_error'] = 'Please fill in all required fields.';
        header('Location: register.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = 'Please enter a valid email address.';
        header('Location: register.php');
        exit;
    }

    if (strlen($password) < 8) {
        $_SESSION['register_error'] = 'Password must be at least 8 characters.';
        header('Location: register.php');
        exit;
    }

    if ($password !== $confirmPassword) {
        $_SESSION['register_error'] = 'Passwords do not match.';
        header('Location: register.php');
        exit;
    }

    if ($terms !== '1') {
        $_SESSION['register_error'] = 'Please accept the terms and conditions.';
        header('Location: register.php');
        exit;
    }

    try {
        // 3. Username, email অথবা phone আগে ব্যবহার করা হয়েছে কি না
        $check = $pdo->prepare(
            'SELECT id FROM users
             WHERE username = :username
                OR email = :email
                OR phone = :phone
             LIMIT 1'
        );

        $check->execute([
            ':username' => $username,
            ':email'    => $email,
            ':phone'    => $phone
        ]);

        if ($check->fetch(PDO::FETCH_ASSOC)) {
            $_SESSION['register_error'] =
                'Username, email or phone number is already registered.';
            header('Location: register.php');
            exit;
        }

        // 4. ছয় সংখ্যার OTP তৈরি; মেয়াদ ১০ মিনিট
        $otp = (string) random_int(100000, 999999);
        $otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // 5. Password hash তৈরি
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // 6. Database-এ account তৈরি এবং OTP email পাঠানো
        $pdo->beginTransaction();

        $insert = $pdo->prepare(
            'INSERT INTO users
                (name, username, email, phone, password_hash,
                 email_verified, phone_verified, email_code,
                 email_code_expires_at, created_at)
             VALUES
                (:name, :username, :email, :phone, :password_hash,
                 0, 0, :email_code, :expiry, NOW())'
        );

        $insert->execute([
            ':name'          => $name,
            ':username'      => $username,
            ':email'         => $email,
            ':phone'         => $phone,
            ':password_hash' => $passwordHash,
            ':email_code'    => $otp,
            ':expiry'        => $otpExpiry
        ]);

        // 7. Gmail SMTP দিয়ে OTP পাঠানো
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure =
            \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email - Handicraft by Sonia';

        $mail->Body = '
            <div style="font-family:Arial,sans-serif;max-width:500px;
                        margin:auto;padding:25px;border:1px solid #eee;
                        border-radius:12px;">
                <h2 style="color:#7c3aed;">
                    Handicraft by Sonia
                </h2>
                <p>Hello ' .
                    htmlspecialchars($name, ENT_QUOTES, 'UTF-8') .
                ',</p>
                <p>Thank you for registering. Use the OTP below
                   to verify your email address.</p>
                <div style="font-size:32px;font-weight:bold;
                            letter-spacing:8px;color:#7c3aed;
                            padding:18px;text-align:center;">
                    ' . $otp . '
                </div>
                <p>This OTP expires in <strong>10 minutes</strong>.</p>
                <p>If you did not request this, you can ignore this email.</p>
            </div>
        ';

        $mail->AltBody =
            "Your Handicraft by Sonia email verification OTP is: " .
            $otp . ". It expires in 10 minutes.";

        $mail->send();

        $pdo->commit();

        // 8. OTP verification page-এ পাঠানো
        $_SESSION['pending_verification_email'] = $email;

        header('Location: verify-otp.php');
        exit;

    } catch (\Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // আসল error server log-এ থাকবে, ব্যবহারকারীকে দেখানো হবে না
        error_log('Registration error: ' . $e->getMessage());

        $_SESSION['register_error'] =
            'Registration could not be completed. Please check your email settings and try again.';

        header('Location: register.php');
        exit;
    }
}

// GET request হলে আগের ডিজাইন দেখাবে
require __DIR__ . '/auth-switch.php';
