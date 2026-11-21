<?php
// Shared Login/Register UI for Handicraft by Sonia.

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$registerError = $_SESSION['register_error'] ?? '';
unset($_SESSION['register_error']);

$active = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'register.php'
    ? 'register'
    : 'login';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login & Register | Handicraft by Sonia</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --pink: #ec4899;
            --purple: #7c3aed;
            --deep: #4c1d95;
            --ink: #30243a;
            --muted: #8b8195;
        }

        body {
            font-family: Poppins, sans-serif;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px 16px;
            background:
                linear-gradient(120deg, rgba(38,13,52,.68), rgba(91,33,182,.48)),
                url('https://images.unsplash.com/photo-1617038220319-276d3cfab638?auto=format&fit=crop&w=2000&q=85')
                center / cover fixed no-repeat;
        }

        .auth-shell {
            width: min(1080px, 100%);
            min-height: 660px;
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 30px 90px rgba(20,5,35,.35);
        }

        .forms {
            width: 100%;
            min-height: 660px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            direction: rtl;
        }

        .form-side {
            direction: ltr;
            padding: 44px clamp(22px, 4vw, 58px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(255,255,255,.97);
            min-width: 0;
        }

        .form-side h2 {
            font-size: 29px;
            line-height: 1.3;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .sub {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 24px;
        }

        .brand {
            font-size: 19px;
            font-weight: 700;
            color: var(--deep);
            margin-bottom: 22px;
        }

        .brand span {
            color: var(--purple);
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 12px;
        }

        .field {
            margin-bottom: 13px;
            min-width: 0;
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #51445c;
            margin-bottom: 6px;
        }

        .field input {
            width: 100%;
            height: 43px;
            padding: 0 12px;
            border: 1px solid #e5dbea;
            border-radius: 8px;
            background: #fdfbff;
            font: 12px Poppins, sans-serif;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .field input:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px #7c3aed18;
            background: #fff;
        }

        .username-status {
            display: block;
            font-size: 10.5px;
            font-weight: 500;
            margin-top: 4px;
            min-height: 15px;
        }

        .username-status.available {
            color: #15803d;
        }

        .username-status.taken {
            color: #be123c;
        }

        .pw {
            position: relative;
        }

        .pw input {
            padding-right: 56px;
        }

        .show {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: none;
            color: var(--purple);
            font: 10px Poppins, sans-serif;
            cursor: pointer;
        }

        .terms {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            font-size: 10px;
            line-height: 1.7;
            color: var(--muted);
            margin: 3px 0 15px;
        }

        .terms input {
            margin-top: 4px;
            accent-color: var(--purple);
        }

        .btn {
            width: 100%;
            min-height: 46px;
            border: 0;
            border-radius: 9px;
            color: white;
            background: linear-gradient(110deg, #9333ea, #4c1d95);
            font: 600 12px Poppins, sans-serif;
            cursor: pointer;
            box-shadow: 0 8px 20px #7c3aed30;
            transition: transform .2s, box-shadow .2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px #7c3aed45;
        }

        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            font-size: 10px;
            color: var(--muted);
            margin: 4px 0 20px;
        }

        .options label {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            font-weight: 400;
        }

        .options input {
            accent-color: var(--purple);
        }

        a {
            color: var(--purple);
            text-decoration: none;
            font-weight: 600;
        }

        a:hover {
            color: var(--pink);
        }

        .switch-note {
            text-align: center;
            font-size: 11px;
            color: var(--muted);
            margin-top: 18px;
        }

        .error {
            font-size: 11px;
            color: #be123c;
            background: #fff1f2;
            border-radius: 8px;
            padding: 10px 12px;
            margin: 0 0 15px;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            color: #a89bb1;
            margin-top: 20px;
        }

        /* Sliding panel */
        .slider {
            position: absolute;
            inset: 0 auto 0 0;
            width: 50%;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 42px;
            color: white;
            background:
                linear-gradient(135deg, rgba(124,58,237,.78), rgba(236,72,153,.62)),
                url('https://images.unsplash.com/photo-1617038220319-276d3cfab638?auto=format&fit=crop&w=1200&q=85')
                center / cover no-repeat;
            transition: transform .8s cubic-bezier(.65,0,.35,1);
            will-change: transform;
        }

        .slider::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, #28103715, #28103770);
            pointer-events: none;
        }

        .slider-content {
            position: relative;
            z-index: 1;
            max-width: 340px;
        }

        .badge {
            display: inline-block;
            padding: 8px 13px;
            border: 1px solid #ffffff70;
            background: #ffffff20;
            border-radius: 30px;
            font-size: 10px;
            margin-bottom: 20px;
        }

        .slider h1 {
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1.2;
            margin-bottom: 15px;
        }

        .slider p {
            font-size: 12px;
            line-height: 1.9;
            color: #fff0fa;
            margin-bottom: 25px;
        }

        .switch-btn {
            border: 1px solid #ffffffc7;
            background: #ffffff12;
            color: white;
            border-radius: 30px;
            padding: 11px 25px;
            font: 600 11px Poppins, sans-serif;
            cursor: pointer;
            transition: background .25s, color .25s;
        }

        .switch-btn:hover {
            background: #fff;
            color: var(--deep);
        }

        .auth-shell.show-register .slider {
            transform: translateX(100%);
        }

        /* Form visibility */
        .form-side.login,
        .form-side.register {
            transition: opacity .35s ease-in-out;
        }

        .form-side.register {
            visibility: hidden;
            opacity: 0;
            pointer-events: none;
        }

        .form-side.login {
            visibility: visible;
            opacity: 1;
        }

        .auth-shell.show-register .form-side.login {
            visibility: hidden;
            opacity: 0;
            pointer-events: none;
        }

        .auth-shell.show-register .form-side.register {
            visibility: visible;
            opacity: 1;
            pointer-events: auto;
        }

        @media (max-width: 760px) {
            body {
                padding: 12px;
                background-attachment: scroll;
            }

            .auth-shell {
                max-width: 500px;
                min-height: 0;
            }

            .forms {
                display: block;
                min-height: 0;
                direction: ltr;
            }

            .form-side {
                padding: 30px 25px;
                min-height: 610px;
            }

            .form-side.register {
                display: none;
            }

            .auth-shell.show-register .form-side.login {
                display: none;
            }

            .auth-shell.show-register .form-side.register {
                display: flex;
            }

            .slider {
                position: relative;
                width: 100%;
                min-height: 245px;
                inset: auto;
                padding: 28px;
                transform: none !important;
                transition: none;
            }

            .slider h1 {
                font-size: 29px;
            }

            .slider p {
                margin-bottom: 16px;
            }

            .auth-shell.show-register .slider {
                display: none;
            }

            .form-side.login,
            .auth-shell.show-register .form-side.register {
                visibility: visible;
                opacity: 1;
                pointer-events: auto;
            }
        }

        @media (max-width: 420px) {
            .grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .full {
                grid-column: auto;
            }

            .form-side {
                padding: 27px 20px;
            }

            .form-side h2 {
                font-size: 25px;
            }

            .options {
                font-size: 9px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>
<main class="auth-shell" id="authShell">

    <div class="forms">

        <!-- LOGIN FORM -->
        <section class="form-side login" aria-label="Login form">
            <div class="brand"><a href="../index.php" style="color: inherit;">Handicraft <span>by Sonia</span></a></div>

            <h2>Welcome Back!</h2>
            <p class="sub">Log in to continue your handmade journey.</p>

            <?php if ($loginError): ?>
                <div class="error">
                    <?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="post">
                <div class="field">
                    <label for="identity">Email or Username</label>
                    <input
                        id="identity"
                        name="identity"
                        type="text"
                        placeholder="Enter your email or username"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="field">
                    <label for="login_password">Password</label>
                    <div class="pw">
                        <input
                            id="login_password"
                            name="password"
                            type="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <button class="show" type="button"
                            onclick="togglePassword('login_password', this)">Show</button>
                    </div>
                </div>

                <div class="options">
                    <label>
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                    <a href="../about.php#contact">Need help?</a>
                </div>

                <button class="btn" type="submit">
                    Login to Your Account →
                </button>
            </form>

            <p class="switch-note">
                New to our community?
                <a href="#register" onclick="setMode('register'); return false;">
                    Create an account
                </a>
            </p>

            <div class="footer">
                © 2026 Handicraft by Sonia. Made with love ♡
            </div>
        </section>

        <!-- REGISTER FORM -->
        <section class="form-side register" aria-label="Registration form">
            <div class="brand"><a href="../index.php" style="color: inherit;">Handicraft <span>by Sonia</span></a></div>

            <h2>Create Your Account</h2>
            <p class="sub">Join our community and find something beautiful.</p>

            <?php if ($registerError): ?>
                <div class="error">
                    <?= htmlspecialchars($registerError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="post" id="registerForm">
                <div class="grid">
                    <div class="field">
                        <label for="first_name">First Name</label>
                        <input id="first_name" name="first_name" type="text"
                            placeholder="First name" autocomplete="given-name" required>
                    </div>

                    <div class="field">
                        <label for="last_name">Last Name</label>
                        <input id="last_name" name="last_name" type="text"
                            placeholder="Last name" autocomplete="family-name" required>
                    </div>

                    <div class="field full">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text"
                            placeholder="Choose a unique username" autocomplete="username" required>
                        <small id="usernameStatus" class="username-status"></small>
                    </div>

                    <div class="field full">
                        <label for="email">Email Address</label>
                        <input id="email" name="email" type="email"
                            placeholder="Enter your email address" autocomplete="email" required>
                    </div>

                    <div class="field full">
                        <label for="phone">Phone Number</label>
                        <input id="phone" name="phone" type="tel"
                            placeholder="Enter your phone number" autocomplete="tel" required>
                    </div>

                    <div class="field">
                        <label for="register_password">Password</label>
                        <div class="pw">
                            <input id="register_password" name="password" type="password"
                                placeholder="Password" minlength="8"
                                autocomplete="new-password" required>
                            <button class="show" type="button"
                                onclick="togglePassword('register_password', this)">Show</button>
                        </div>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="pw">
                            <input id="confirm_password" name="confirm_password" type="password"
                                placeholder="Re-enter password" minlength="8"
                                autocomplete="new-password" required>
                            <button class="show" type="button"
                                onclick="togglePassword('confirm_password', this)">Show</button>
                        </div>
                    </div>
                </div>

                <label class="terms">
                    <input type="checkbox" name="terms" value="1" required>
                    <span>I agree to the Terms &amp; Conditions and Privacy Policy.</span>
                </label>

                <button class="btn" type="submit" id="registerSubmitBtn">Create Account →</button>
            </form>

            <p class="switch-note">
                Already have an account?
                <a href="#login" onclick="setMode('login'); return false;">
                    Login
                </a>
            </p>

            <div class="footer">
                © 2026 Handicraft by Sonia. Made with love ♡
            </div>
        </section>

    </div>

    <!-- SLIDING PANEL -->
    <section class="slider" id="sliderPanel">
        <div class="slider-content">
            <span class="badge">♡ Handmade With Love</span>

            <h1 id="sliderTitle">Beauty in Every Little Detail.</h1>

            <p id="sliderText">
                Discover jewellery and handmade creations made to express your unique style.
            </p>

            <button class="switch-btn" id="sliderButton" type="button"
                onclick="toggleMode()">
                New here? Create an account
            </button>
        </div>
    </section>

</main>

<script>
    const shell = document.getElementById('authShell');
    const isRegisterPage = <?php echo json_encode($active === 'register'); ?>;

    function setMode(mode) {
        const registerMode = mode === 'register';
        shell.classList.toggle('show-register', registerMode);
        updateSlider();
    }

    function toggleMode() {
        const isRegister = shell.classList.contains('show-register');
        setMode(isRegister ? 'login' : 'register');
    }

    function updateSlider() {
        const isRegister = shell.classList.contains('show-register');

        document.getElementById('sliderTitle').textContent =
            isRegister
                ? 'Welcome to Our Craft Family!'
                : 'Beauty in Every Little Detail.';

        document.getElementById('sliderText').textContent =
            isRegister
                ? 'Already have an account? Slide back to log in and discover your favourites.'
                : 'Discover jewellery and handmade creations made to express your unique style.';

        document.getElementById('sliderButton').textContent =
            isRegister
                ? 'Already a member? Login'
                : 'New here? Create an account';
    }

    function togglePassword(id, button) {
        const input = document.getElementById(id);

        input.type = input.type === 'password' ? 'text' : 'password';
        button.textContent = input.type === 'password' ? 'Show' : 'Hide';
    }

    // Initial state depends on the page being opened.
    let currentHoverMode = isRegisterPage ? 'register' : 'login';

    shell.classList.toggle('show-register', isRegisterPage);
    updateSlider();

    // Mouse hover sliding is enabled only on the Register page.
    if (isRegisterPage) {
        function updateModeFromMouse(e) {
            if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                return;
            }

            const rect = shell.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const nextMode = mouseX < rect.width / 2 ? 'login' : 'register';

            if (nextMode !== currentHoverMode) {
                currentHoverMode = nextMode;
                shell.classList.toggle('show-register', nextMode === 'register');
                updateSlider();
            }
        }

        shell.addEventListener('mousemove', updateModeFromMouse);
    }

    // Real-Time AJAX Username Availability Check (Without Page Reload)
    const usernameInput = document.getElementById('username');
    const usernameStatus = document.getElementById('usernameStatus');
    const registerSubmitBtn = document.getElementById('registerSubmitBtn');
    let checkTimer = null;

    if (usernameInput && usernameStatus) {
        usernameInput.addEventListener('input', function () {
            clearTimeout(checkTimer);
            const val = this.value.trim();

            if (val === '') {
                usernameStatus.textContent = '';
                usernameStatus.className = 'username-status';
                registerSubmitBtn.disabled = false;
                return;
            }

            checkTimer = setTimeout(async function () {
                try {
                    const res = await fetch('../api/username_check.php?username=' + encodeURIComponent(val));
                    const data = await res.json();

                    usernameStatus.textContent = data.message || '';
                    if (data.available) {
                        usernameStatus.className = 'username-status available';
                        registerSubmitBtn.disabled = false;
                    } else {
                        usernameStatus.className = 'username-status taken';
                        registerSubmitBtn.disabled = true;
                    }
                } catch (err) {
                    console.error('Username check error:', err);
                }
            }, 300);
        });
    }
</script>

</body>
</html>