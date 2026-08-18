<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="theme-color" content="#080a0f">

    <title>Login - MbunieEduHub</title>

    <style>
        /* =========================================================
           ROOT
        ========================================================= */

        :root {
            --primary: #4f46e5;
            --primary-hover: #6366f1;
            --secondary: #8b5cf6;

            --background: #07090f;
            --background-soft: #0b0e15;

            --card: rgba(16, 19, 28, 0.96);
            --card-light: rgba(255, 255, 255, 0.04);

            --border: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(255, 255, 255, 0.15);

            --input: #0d1119;
            --input-border: #252b37;

            --text: #ffffff;
            --text-secondary: #d1d5db;
            --text-muted: #8b93a1;

            --danger: #ff5c5c;
            --success: #22c55e;

            --radius: 20px;
            --input-radius: 11px;

            --transition: 0.25s ease;
        }


        /* =========================================================
           RESET
        ========================================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        html {
            width: 100%;
            min-height: 100%;
            scroll-behavior: smooth;
        }


        body {
            width: 100%;
            min-height: 100vh;

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Helvetica,
                Arial,
                sans-serif;

            color: var(--text);

            background:
                radial-gradient(
                    circle at 10% 10%,
                    rgba(99, 102, 241, 0.10),
                    transparent 32%
                ),
                radial-gradient(
                    circle at 90% 90%,
                    rgba(139, 92, 246, 0.08),
                    transparent 32%
                ),
                var(--background);
        }


        button,
        input {
            font-family: inherit;
        }


        a {
            color: inherit;
        }


        /* =========================================================
           PAGE
        ========================================================= */

        .page {
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 40px 20px;

            position: relative;
            overflow: hidden;
        }


        /* Decorative background */

        .background-circle {
            position: fixed;

            border-radius: 50%;

            pointer-events: none;

            filter: blur(2px);

            z-index: 0;
        }


        .background-circle.one {
            width: 400px;
            height: 400px;

            top: -200px;
            left: -180px;

            background: rgba(99, 102, 241, 0.06);
        }


        .background-circle.two {
            width: 350px;
            height: 350px;

            bottom: -180px;
            right: -150px;

            background: rgba(139, 92, 246, 0.06);
        }


        /* =========================================================
           MAIN CONTAINER
        ========================================================= */

        .container {
            position: relative;
            z-index: 2;

            width: 100%;
            max-width: 1050px;

            min-height: 620px;

            display: grid;

            grid-template-columns: 1fr 1fr;

            background: var(--card);

            border: 1px solid var(--border);

            border-radius: var(--radius);

            overflow: hidden;

            box-shadow:
                0 30px 80px rgba(0, 0, 0, 0.45),
                0 10px 30px rgba(0, 0, 0, 0.20);

            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }


        /* =========================================================
           LEFT PANEL
        ========================================================= */

        .left {
            position: relative;

            display: flex;
            flex-direction: column;
            justify-content: center;

            padding: 60px;

            overflow: hidden;

            background:
                linear-gradient(
                    145deg,
                    rgba(99, 102, 241, 0.14),
                    rgba(139, 92, 246, 0.04) 50%,
                    rgba(0, 0, 0, 0.12)
                );
        }


        .left::before {
            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            top: -120px;
            left: -120px;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(99, 102, 241, 0.16),
                    transparent 70%
                );
        }


        .left::after {
            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            bottom: -160px;
            right: -150px;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(139, 92, 246, 0.12),
                    transparent 70%
                );
        }


        /* =========================================================
           HOME LINK
        ========================================================= */

        .home-link {
            position: absolute;

            top: 28px;
            left: 32px;

            z-index: 5;

            display: inline-flex;

            align-items: center;

            gap: 8px;

            color: var(--text-muted);

            font-size: 14px;
            font-weight: 500;

            text-decoration: none;

            transition: var(--transition);
        }


        .home-link:hover {
            color: #ffffff;

            transform: translateX(-3px);
        }


        .home-icon {
            font-size: 18px;

            line-height: 1;
        }


        /* =========================================================
           BRAND
        ========================================================= */

        .brand {
            position: relative;

            z-index: 3;

            margin-bottom: 34px;
        }


        .brand-name {
            font-size: 30px;

            font-weight: 800;

            letter-spacing: -1px;

            background:
                linear-gradient(
                    90deg,
                    #ffffff,
                    #818cf8,
                    #8b5cf6
                );

            -webkit-background-clip: text;
            background-clip: text;

            -webkit-text-fill-color: transparent;
        }


        .brand-line {
            width: 48px;
            height: 4px;

            margin-top: 10px;

            border-radius: 20px;

            background:
                linear-gradient(
                    90deg,
                    var(--primary),
                    var(--secondary)
                );
        }


        /* =========================================================
           LEFT CONTENT
        ========================================================= */

        .login-title {
            position: relative;

            z-index: 3;

            margin-bottom: 18px;

            font-size: clamp(45px, 5vw, 65px);

            line-height: 0.95;

            font-weight: 800;

            letter-spacing: -3px;
        }


        .login-description {
            position: relative;

            z-index: 3;

            max-width: 400px;

            color: var(--text-muted);

            font-size: 15px;

            line-height: 1.75;
        }


        /* =========================================================
           FEATURES
        ========================================================= */

        .features {
            position: relative;

            z-index: 3;

            margin-top: 35px;

            display: flex;

            flex-direction: column;

            gap: 13px;
        }


        .feature {
            display: flex;

            align-items: center;

            gap: 10px;

            color: #aeb4c0;

            font-size: 13px;
        }


        .feature-icon {
            width: 22px;
            height: 22px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: rgba(99, 102, 241, 0.10);

            color: #818cf8;

            font-size: 11px;
        }


        /* =========================================================
           TERMS
        ========================================================= */

        .eula {
            position: absolute;

            left: 60px;
            right: 60px;
            bottom: 32px;

            z-index: 3;

            color: #626b79;

            font-size: 11px;

            line-height: 1.6;
        }


        .eula a {
            color: #89919e;

            text-decoration: none;
        }


        .eula a:hover {
            color: white;
        }


        /* =========================================================
           RIGHT PANEL
        ========================================================= */

        .right {
            position: relative;

            display: flex;

            align-items: center;
            justify-content: center;

            padding: 60px 55px;

            background: var(--background-soft);
        }


        /* =========================================================
           FORM
        ========================================================= */

        .form {
            width: 100%;

            max-width: 390px;
        }


        .form-header {
            margin-bottom: 32px;
        }


        .form-title {
            font-size: 28px;

            font-weight: 750;

            letter-spacing: -0.8px;

            margin-bottom: 8px;
        }


        .form-subtitle {
            color: var(--text-muted);

            font-size: 13px;

            line-height: 1.6;
        }


        /* =========================================================
           ALERT
        ========================================================= */

        .alert {
            display: flex;

            align-items: flex-start;

            gap: 10px;

            padding: 12px 14px;

            margin-bottom: 22px;

            border-radius: 10px;

            font-size: 13px;

            line-height: 1.5;
        }


        .alert-danger {
            color: #ff8a8a;

            background: rgba(255, 59, 48, 0.08);

            border: 1px solid rgba(255, 59, 48, 0.16);
        }


        .alert-success {
            color: #6ee7a0;

            background: rgba(34, 197, 94, 0.08);

            border: 1px solid rgba(34, 197, 94, 0.15);
        }


        /* =========================================================
           FORM GROUP
        ========================================================= */

        .form-group {
            margin-bottom: 20px;
        }


        .form-label {
            display: block;

            margin-bottom: 9px;

            color: var(--text-secondary);

            font-size: 13px;

            font-weight: 600;
        }


        /* =========================================================
           INPUT WRAPPER
        ========================================================= */

        .input-wrapper {
            position: relative;
        }


        .input-icon {
            position: absolute;

            left: 16px;

            top: 50%;

            transform: translateY(-50%);

            color: #697180;

            font-size: 15px;

            pointer-events: none;

            transition: var(--transition);
        }


        /* =========================================================
           INPUT
        ========================================================= */

        .form-input {
            width: 100%;

            height: 54px;

            padding:
                0
                48px
                0
                44px;

            color: #ffffff;

            background: var(--input);

            border: 1px solid var(--input-border);

            border-radius: var(--input-radius);

            outline: none;

            font-size: 14px;

            transition: var(--transition);
        }


        .form-input::placeholder {
            color: #555d6a;
        }


        .form-input:hover {
            border-color: var(--border-hover);
        }


        .form-input:focus {
            border-color: var(--primary);

            background: #0f131c;

            box-shadow:
                0 0 0 3px rgba(99, 102, 241, 0.09);
        }


        .input-wrapper:focus-within .input-icon {
            color: #818cf8;
        }


        /* =========================================================
           PASSWORD TOGGLE
        ========================================================= */

        .password-toggle {
            position: absolute;

            right: 14px;

            top: 50%;

            transform: translateY(-50%);

            width: 32px;
            height: 32px;

            display: flex;

            align-items: center;
            justify-content: center;

            border: none;

            background: transparent;

            color: #697180;

            cursor: pointer;

            border-radius: 7px;

            transition: var(--transition);
        }


        .password-toggle:hover {
            color: #ffffff;

            background: rgba(255, 255, 255, 0.05);
        }


        /* =========================================================
           ERROR
        ========================================================= */

        .field-error {
            margin-top: 7px;

            color: var(--danger);

            font-size: 12px;
        }


        .has-error .form-input {
            border-color: rgba(255, 92, 92, 0.7);
        }


        /* =========================================================
           REMEMBER / FORGOT
        ========================================================= */

        .form-options {
            display: flex;

            align-items: center;
            justify-content: space-between;

            margin-top: 3px;

            margin-bottom: 25px;
        }


        .remember {
            display: flex;

            align-items: center;

            gap: 8px;

            color: var(--text-muted);

            font-size: 12px;

            cursor: pointer;
        }


        .remember input {
            width: 15px;
            height: 15px;

            accent-color: var(--primary);

            cursor: pointer;
        }


        .forgot-link {
            color: #818cf8;

            font-size: 12px;

            font-weight: 600;

            text-decoration: none;

            transition: var(--transition);
        }


        .forgot-link:hover {
            color: #8b5cf6;

            text-decoration: underline;
        }


        /* =========================================================
           LOGIN BUTTON
        ========================================================= */

        .submit-button {
            position: relative;

            width: 100%;

            height: 54px;

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 9px;

            border: none;

            border-radius: var(--input-radius);

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--secondary)
                );

            color: #ffffff;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            overflow: hidden;

            box-shadow:
                0 12px 28px rgba(99, 102, 241, 0.20);

            transition: var(--transition);
        }


        .submit-button::before {
            content: "";

            position: absolute;

            top: 0;
            left: -100%;

            width: 100%;
            height: 100%;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 255, 255, 0.15),
                    transparent
                );

            transition: 0.5s;
        }


        .submit-button:hover::before {
            left: 100%;
        }


        .submit-button:hover {
            transform: translateY(-2px);

            box-shadow:
                0 16px 35px rgba(99, 102, 241, 0.28);
        }


        .submit-button:active {
            transform: translateY(0);
        }


        .submit-button:disabled {
            opacity: 0.65;

            cursor: not-allowed;

            transform: none;
        }


        /* =========================================================
           REGISTER
        ========================================================= */

        .register-link {
            margin-top: 25px;

            text-align: center;

            color: var(--text-muted);

            font-size: 13px;

            line-height: 1.6;
        }


        .register-link a {
            color: #818cf8;

            font-weight: 650;

            text-decoration: none;

            transition: var(--transition);
        }


        .register-link a:hover {
            color: #8b5cf6;

            text-decoration: underline;
        }


        /* =========================================================
           DIVIDER
        ========================================================= */

        .divider {
            display: flex;

            align-items: center;

            gap: 14px;

            margin: 25px 0;

            color: #555d6a;

            font-size: 11px;
        }


        .divider::before,
        .divider::after {
            content: "";

            flex: 1;

            height: 1px;

            background: #202631;
        }


        /* =========================================================
           SECURITY NOTE
        ========================================================= */

        .security-note {
            display: flex;

            justify-content: center;
            align-items: center;

            gap: 7px;

            color: #596271;

            font-size: 11px;
        }


        .security-icon {
            color: #22c55e;

            font-size: 12px;
        }


        /* =========================================================
           RESPONSIVE TABLET
        ========================================================= */

        @media (max-width: 850px) {

            .container {
                max-width: 650px;

                grid-template-columns: 1fr;

                min-height: auto;
            }


            .left {
                min-height: 350px;

                padding: 50px 45px;
            }


            .right {
                padding: 50px 45px;
            }


            .eula {
                left: 45px;
                right: 45px;
            }


            .features {
                margin-top: 25px;
            }
        }


        /* =========================================================
           RESPONSIVE MOBILE
        ========================================================= */

        @media (max-width: 550px) {

            .page {
                padding: 15px;
            }


            .container {
                border-radius: 16px;

                width: 100%;
            }


            .left {
                min-height: 300px;

                padding:
                    50px
                    28px
                    65px;
            }


            .home-link {
                top: 22px;
                left: 25px;
            }


            .brand {
                margin-bottom: 25px;
            }


            .brand-name {
                font-size: 25px;
            }


            .login-title {
                font-size: 44px;

                letter-spacing: -2px;
            }


            .login-description {
                font-size: 13px;

                line-height: 1.65;
            }


            .features {
                display: none;
            }


            .eula {
                left: 28px;
                right: 28px;

                bottom: 22px;

                font-size: 10px;
            }


            .right {
                padding:
                    40px
                    24px
                    45px;
            }


            .form-title {
                font-size: 25px;
            }


            .form-subtitle {
                font-size: 12px;
            }


            .form-options {
                flex-direction: row;

                gap: 10px;
            }


            .form-input,
            .submit-button {
                height: 52px;
            }
        }


        /* =========================================================
           VERY SMALL DEVICES
        ========================================================= */

        @media (max-width: 360px) {

            .page {
                padding: 8px;
            }


            .left {
                padding-left: 22px;
                padding-right: 22px;
            }


            .right {
                padding-left: 18px;
                padding-right: 18px;
            }


            .eula {
                left: 22px;
                right: 22px;
            }


            .login-title {
                font-size: 40px;
            }
        }


        /* =========================================================
           REDUCED MOTION
        ========================================================= */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;

                transition: none !important;

                animation: none !important;
            }
        }
    </style>
</head>


<body>

    <!-- Background decoration -->
    <div class="background-circle one"></div>
    <div class="background-circle two"></div>


    <main class="page">

        <div class="container">


            <!-- =====================================================
                 LEFT SIDE
            ====================================================== -->

            <section class="left">

                <a
                    href="{{ route('public.home') }}"
                    class="home-link"
                >
                    <span class="home-icon">←</span>
                    <span>Back to Home</span>
                </a>


                <!-- Brand -->

                <div class="brand">

                    <div class="brand-name">
                        MbunieEduHub
                    </div>

                    <div class="brand-line"></div>

                </div>


                <!-- Main title -->

                <h1 class="login-title">
                    Login
                </h1>


                <p class="login-description">
                    Welcome back to MbunieEduHub.
                    Sign in to access your account,
                    manage your services and continue
                    where you left off.
                </p>


                <!-- Features -->

                <div class="features">

                    <div class="feature">

                        <span class="feature-icon">
                            ✓
                        </span>

                        <span>
                            Secure account access
                        </span>

                    </div>


                    <div class="feature">

                        <span class="feature-icon">
                            ✓
                        </span>

                        <span>
                            Manage your services
                        </span>

                    </div>


                    <div class="feature">

                        <span class="feature-icon">
                            ✓
                        </span>

                        <span>
                            Access your MbunieEduHub dashboard
                        </span>

                    </div>

                </div>


                <!-- Terms -->

                <div class="eula">

                    By continuing, you agree to our

                    <a href="#">
                        Terms of Service
                    </a>

                    and

                    <a href="#">
                        Privacy Policy
                    </a>.

                </div>

            </section>



            <!-- =====================================================
                 RIGHT SIDE
            ====================================================== -->

            <section class="right">

                <form
                    method="POST"
                    action="{{ route('login') }}"
                    class="form"
                    id="loginForm"
                >

                    @csrf


                    <!-- Form Header -->

                    <div class="form-header">

                        <h2 class="form-title">
                            Welcome back
                        </h2>

                        <p class="form-subtitle">
                            Enter your credentials to access
                            your account.
                        </p>

                    </div>



                    <!-- =================================================
                         SESSION STATUS
                    ================================================== -->

                    @if (session('status'))

                        <div class="alert alert-success">

                            {{ session('status') }}

                        </div>

                    @endif



                    <!-- =================================================
                         VALIDATION ERRORS
                    ================================================== -->

                    @if ($errors->any())

                        <div class="alert alert-danger">

                            <div>

                                {{ $errors->first() }}

                            </div>

                        </div>

                    @endif



                    <!-- =================================================
                         EMAIL
                    ================================================== -->

                    <div
                        class="form-group
                        @error('email') has-error @enderror"
                    >

                        <label
                            for="email"
                            class="form-label"
                        >
                            Email Address
                        </label>


                        <div class="input-wrapper">

                            <span class="input-icon">
                                ✉
                            </span>


                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-input"
                                value="{{ old('email') }}"
                                placeholder="you@example.com"
                                autocomplete="email"
                                required
                                autofocus
                            >

                        </div>


                        @error('email')

                            <div class="field-error">
                                {{ $message }}
                            </div>

                        @enderror

                    </div>



                    <!-- =================================================
                         PASSWORD
                    ================================================== -->

                    <div
                        class="form-group
                        @error('password') has-error @enderror"
                    >

                        <label
                            for="password"
                            class="form-label"
                        >
                            Password
                        </label>


                        <div class="input-wrapper">

                            <span class="input-icon">
                                🔒
                            </span>


                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                id="togglePassword"
                                aria-label="Show password"
                            >
                                👁
                            </button>

                        </div>


                        @error('password')

                            <div class="field-error">
                                {{ $message }}
                            </div>

                        @enderror

                    </div>



                    <!-- =================================================
                         OPTIONS
                    ================================================== -->

                    <div class="form-options">

                        @if (Route::has('password.request'))

                            <a
                                href="{{ route('password.request') }}"
                                class="forgot-link"
                            >
                                Forgot password?
                            </a>

                        @endif

                    </div>



                    <!-- =================================================
                         LOGIN BUTTON
                    ================================================== -->

                    <button
                        type="submit"
                        class="submit-button"
                        id="submitButton"
                    >

                        <span id="buttonText">
                            Sign In
                        </span>

                        <span id="buttonArrow">
                            →
                        </span>

                    </button>



                    <!-- =================================================
                         REGISTER
                    ================================================== -->

                    @if (Route::has('register'))

                        <div class="register-link">

                            Don't have an account?

                            <a
                                href="{{ route('register') }}"
                            >
                                Create an account
                            </a>

                        </div>

                    @endif



                    <!-- Divider -->

                    <div class="divider">
                        Secure Login
                    </div>


                    <!-- Security -->

                    <div class="security-note">

                        <span class="security-icon">
                            🔒
                        </span>

                        <span>
                            Your information is protected
                        </span>

                    </div>

                </form>

            </section>

        </div>

    </main>



    <!-- =============================================================
         JAVASCRIPT
    ============================================================= -->

    <script>

        /*
        |--------------------------------------------------------------------------
        | PASSWORD SHOW / HIDE
        |--------------------------------------------------------------------------
        */

        const passwordInput =
            document.getElementById('password');

        const togglePassword =
            document.getElementById('togglePassword');


        if (passwordInput && togglePassword) {

            togglePassword.addEventListener(
                'click',
                function () {

                    const isPassword =
                        passwordInput.type === 'password';


                    passwordInput.type =
                        isPassword
                            ? 'text'
                            : 'password';


                    togglePassword.textContent =
                        isPassword
                            ? '🙈'
                            : '👁';


                    togglePassword.setAttribute(
                        'aria-label',
                        isPassword
                            ? 'Hide password'
                            : 'Show password'
                    );

                }
            );

        }



        /*
        |--------------------------------------------------------------------------
        | LOGIN BUTTON LOADING STATE
        |--------------------------------------------------------------------------
        */

        const loginForm =
            document.getElementById('loginForm');

        const submitButton =
            document.getElementById('submitButton');

        const buttonText =
            document.getElementById('buttonText');

        const buttonArrow =
            document.getElementById('buttonArrow');


        if (loginForm) {

            loginForm.addEventListener(
                'submit',
                function () {

                    submitButton.disabled = true;

                    buttonText.textContent =
                        'Signing in...';

                    buttonArrow.textContent =
                        '...';

                }
            );

        }



        /*
        |--------------------------------------------------------------------------
        | AUTO FOCUS
        |--------------------------------------------------------------------------
        */

        const emailInput =
            document.getElementById('email');


        if (emailInput) {

            setTimeout(function () {

                if (
                    !emailInput.value &&
                    window.innerWidth > 550
                ) {

                    emailInput.focus();

                }

            }, 300);

        }

    </script>

</body>
</html>
```
