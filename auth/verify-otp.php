
<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

$email = $_SESSION['pending_verification_email'] ?? '';
$message = '';
$messageType = 'error';
$success = false;

if ($email === '') {
    header('Location: register.php');
    exit;
}

// Gmail দিয়ে OTP পাঠানোর function
function sendVerificationOTP($email, $name, $otp)
{
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure =
        \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = MAIL_PORT;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress($email, $name);
    $mail->isHTML(true);

    $mail->Subject = 'Your New OTP - Handicraft by Sonia';

    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    $mail->Body = '
        <div style="font-family:Arial,sans-serif;max-width:500px;
                    margin:auto;padding:25px;border:1px solid #eee;
                    border-radius:12px;">
            <h2 style="color:#7c3aed;">Handicraft by Sonia</h2>
            <p>Hello ' . $safeName . ',</p>
            <p>Your email verification code is:</p>
            <h1 style="color:#7c3aed;letter-spacing:8px;">'
                . $otp .
            '</h1>
            <p>This OTP expires in 10 minutes.</p>
            <p>If you did not request this code, please ignore this email.</p>
        </div>
    ';

    $mail->AltBody =
        "Your verification OTP is $otp. It expires in 10 minutes.";

    $mail->send();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // নতুন OTP পাঠানোর অনুরোধ
    if (isset($_POST['resend_otp'])) {

        try {
            $stmt = $pdo->prepare(
                'SELECT id, name
                 FROM users
                 WHERE email = :email
                   AND email_verified = 0
                 LIMIT 1'
            );

            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $message = 'Account not found or email is already verified.';
            } else {
                $otp = (string) random_int(100000, 999999);

                $pdo->beginTransaction();

                // MySQL-এর নিজের সময় দিয়ে ১০ মিনিটের expiry
                $update = $pdo->prepare(
                    'UPDATE users
                     SET email_code = :otp,
                         email_code_expires_at =
                             DATE_ADD(NOW(), INTERVAL 10 MINUTE)
                     WHERE id = :id
                       AND email_verified = 0'
                );

                $update->execute([
                    ':otp' => $otp,
                    ':id'  => $user['id']
                ]);

                // নতুন OTP ইমেইলে পাঠানো
                sendVerificationOTP($email, $user['name'], $otp);

                $pdo->commit();

                $message = 'A new OTP has been sent to your email.';
                $messageType = 'success';
            }

        } catch (\Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Resend OTP error: ' . $e->getMessage());

            $message =
                'Could not send a new OTP. Please try again later.';
        }

    } else {

        // OTP যাচাই
        $otp = trim($_POST['otp'] ?? '');

        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            $message = 'Please enter a valid 6-digit OTP.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'SELECT id
                     FROM users
                     WHERE email = :email
                       AND email_code = :otp
                       AND email_verified = 0
                       AND email_code_expires_at >= NOW()
                     LIMIT 1'
                );

                $stmt->execute([
                    ':email' => $email,
                    ':otp'   => $otp
                ]);

                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $update = $pdo->prepare(
                        'UPDATE users
                         SET email_verified = 1,
                             email_code = NULL,
                             email_code_expires_at = NULL
                         WHERE id = :id
                           AND email_verified = 0'
                    );

                    $update->execute([':id' => $user['id']]);

                    if ($update->rowCount() === 1) {
                        unset($_SESSION['pending_verification_email']);
                        $success = true;
                    } else {
                        $message =
                            'Verification failed. Please try again.';
                    }
                } else {
                    $message =
                        'Invalid or expired OTP. Please request a new one.';
                }

            } catch (\PDOException $e) {
                error_log('OTP verification error: ' . $e->getMessage());
                $message = 'Something went wrong. Please try again later.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Email Verification | Handicraft by Sonia</title>

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
            padding: 20px;
            background: linear-gradient(135deg, #f5f0ff, #fce7f3);
            font-family: Arial, sans-serif;
            color: #30243a;
        }

        .verify-card {
            width: 100%;
            max-width: 440px;
            background: white;
            padding: 35px 28px;
            border-radius: 20px;
            box-shadow: 0 15px 45px rgba(76, 29, 149, .12);
            text-align: center;
        }

        h1 {
            color: #7c3aed;
            font-size: 27px;
        }

        p {
            line-height: 1.6;
            color: #655b70;
        }

        .email {
            font-weight: bold;
            color: #4c1d95;
            overflow-wrap: anywhere;
        }

        input {
            width: 100%;
            padding: 14px;
            margin: 18px 0;
            border: 1px solid #d8c9f5;
            border-radius: 10px;
            font-size: 24px;
            letter-spacing: 8px;
            text-align: center;
        }

        button, .login-link {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: #7c3aed;
            color: white;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        button:hover, .login-link:hover {
            background: #5b21b6;
        }

        .resend-button {
            margin-top: 12px;
            background: #f3e8ff;
            color: #5b21b6;
        }

        .resend-button:hover {
            background: #e9d5ff;
        }

        .message {
            padding: 12px;
            margin: 16px 0;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
        }

        .success-panel {
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            background: #dcfce7;
            color: #166534;
        }

        .small {
            font-size: 13px;
            margin-top: 18px;
        }
    </style>
</head>

<body>
    <div class="verify-card">

        <?php if ($success): ?>

            <h1>Email Verified!</h1>

            <div class="success-panel">
                Your email has been verified successfully.
            </div>

            <p>You can now log in to your account.</p>

            <a class="login-link" href="login.php">Go to Login</a>

        <?php else: ?>

            <h1>Verify Your Email</h1>

            <p>Verification code sent to:</p>

            <p class="email">
                <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
            </p>

            <?php if ($message !== ''): ?>
                <div class="message <?= $messageType === 'success' ? 'success' : '' ?>">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="verify-otp.php">
                <input
                    type="text"
                    name="otp"
                    placeholder="000000"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    autocomplete="one-time-code"
                    aria-label="6-digit OTP"
                    required
                >

                <button type="submit">Verify Email</button>
            </form>

            <p class="small">
                Didn't receive the email, or has your code expired?
            </p>

            <form method="post" action="verify-otp.php">
                <button
                    type="submit"
                    name="resend_otp"
                    value="1"
                    class="resend-button"
                >
                    Resend OTP
                </button>
            </form>

        <?php endif; ?>

    </div>
</body>
</html>
