<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#07090f"
    >

    <title>Register - MBUNIETECH</title>


    <style>

        /* =========================================================
           ROOT
        ========================================================= */

        :root {

            --primary: #ff3b30;
            --primary-hover: #ff5147;
            --secondary: #ff006e;

            --background: #07090f;
            --background-soft: #0b0e15;

            --card: rgba(16, 19, 28, 0.96);

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
                    rgba(255, 59, 48, 0.10),
                    transparent 32%
                ),

                radial-gradient(
                    circle at 90% 90%,
                    rgba(255, 0, 110, 0.08),
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
           BACKGROUND DECORATION
        ========================================================= */

        .background-circle {

            position: fixed;

            border-radius: 50%;

            pointer-events: none;

            z-index: 0;

            filter: blur(2px);
        }


        .background-circle.one {

            width: 420px;
            height: 420px;

            top: -220px;
            left: -180px;

            background:
                rgba(255, 59, 48, 0.06);
        }


        .background-circle.two {

            width: 380px;
            height: 380px;

            right: -180px;
            bottom: -190px;

            background:
                rgba(255, 0, 110, 0.06);
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
        }


        /* =========================================================
           CONTAINER
        ========================================================= */

        .container {

            position: relative;

            z-index: 2;

            width: 100%;

            max-width: 1050px;

            min-height: 680px;

            display: grid;

            grid-template-columns: 1fr 1fr;

            background:
                var(--card);

            border:
                1px solid var(--border);

            border-radius:
                var(--radius);

            overflow: hidden;

            box-shadow:

                0 30px 80px
                rgba(0, 0, 0, 0.45),

                0 10px 30px
                rgba(0, 0, 0, 0.20);

            backdrop-filter:
                blur(20px);

            -webkit-backdrop-filter:
                blur(20px);
        }


        /* =========================================================
           LEFT
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
                    rgba(255, 59, 48, 0.14),
                    rgba(255, 0, 110, 0.04) 50%,
                    rgba(0, 0, 0, 0.12)
                );
        }


        .left::before {

            content: "";

            position: absolute;

            width: 320px;
            height: 320px;

            top: -130px;
            left: -130px;

            border-radius: 50%;

            background:

                radial-gradient(
                    circle,
                    rgba(255, 59, 48, 0.16),
                    transparent 70%
                );
        }


        .left::after {

            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            right: -150px;
            bottom: -160px;

            border-radius: 50%;

            background:

                radial-gradient(
                    circle,
                    rgba(255, 0, 110, 0.12),
                    transparent 70%
                );
        }


        /* =========================================================
           HOME
        ========================================================= */

        .home-link {

            position: absolute;

            top: 28px;
            left: 32px;

            z-index: 5;

            display: inline-flex;

            align-items: center;

            gap: 8px;

            color:
                var(--text-muted);

            font-size: 14px;

            font-weight: 500;

            text-decoration: none;

            transition:
                var(--transition);
        }


        .home-link:hover {

            color: #ffffff;

            transform:
                translateX(-3px);
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

            margin-bottom: 32px;
        }


        .brand-name {

            font-size: 30px;

            font-weight: 800;

            letter-spacing: -1px;

            background:

                linear-gradient(
                    90deg,
                    #ffffff,
                    #ff5b52,
                    #ff006e
                );

            -webkit-background-clip: text;

            background-clip: text;

            -webkit-text-fill-color:
                transparent;
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
           REGISTER TITLE
        ========================================================= */

        .register-title {

            position: relative;

            z-index: 3;

            margin-bottom: 18px;

            font-size:
                clamp(42px, 5vw, 62px);

            line-height: 0.95;

            font-weight: 800;

            letter-spacing: -3px;
        }


        .register-description {

            position: relative;

            z-index: 3;

            max-width: 400px;

            color:
                var(--text-muted);

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

            gap: 14px;
        }


        .feature {

            display: flex;

            align-items: center;

            gap: 10px;

            color:
                #aeb4c0;

            font-size: 13px;
        }


        .feature-icon {

            width: 22px;
            height: 22px;

            display: flex;

            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            border-radius: 50%;

            background:
                rgba(255, 59, 48, 0.10);

            color:
                #ff5b52;

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

            color:
                #626b79;

            font-size: 11px;

            line-height: 1.6;
        }


        .eula a {

            color:
                #89919e;

            text-decoration:
                none;
        }


        .eula a:hover {

            color:
                #ffffff;
        }


        /* =========================================================
           RIGHT
        ========================================================= */

        .right {

            position: relative;

            display: flex;

            align-items: center;
            justify-content: center;

            padding: 55px;

            background:
                var(--background-soft);

            overflow-y: auto;
        }


        /* =========================================================
           FORM
        ========================================================= */

        .form {

            width: 100%;

            max-width: 390px;
        }


        .form-header {

            margin-bottom: 30px;
        }


        .form-title {

            font-size: 28px;

            font-weight: 750;

            letter-spacing: -0.8px;

            margin-bottom: 8px;
        }


        .form-subtitle {

            color:
                var(--text-muted);

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

            color:
                #ff8a8a;

            background:
                rgba(255, 59, 48, 0.08);

            border:
                1px solid rgba(255, 59, 48, 0.16);
        }


        /* =========================================================
           FORM GROUP
        ========================================================= */

        .form-group {

            margin-bottom: 19px;
        }


        .form-label {

            display: block;

            margin-bottom: 9px;

            color:
                var(--text-secondary);

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

            transform:
                translateY(-50%);

            color:
                #697180;

            font-size: 14px;

            pointer-events: none;

            transition:
                var(--transition);
        }


        .input-wrapper:focus-within
        .input-icon {

            color:
                #ff5b52;
        }


        /* =========================================================
           INPUT
        ========================================================= */

        .form-input {

            width: 100%;

            height: 52px;

            padding:
                0 45px 0 44px;

            color:
                #ffffff;

            background:
                var(--input);

            border:
                1px solid var(--input-border);

            border-radius:
                var(--input-radius);

            outline: none;

            font-size: 14px;

            transition:
                var(--transition);
        }


        .form-input::placeholder {

            color:
                #555d6a;
        }


        .form-input:hover {

            border-color:
                var(--border-hover);
        }


        .form-input:focus {

            border-color:
                var(--primary);

            background:
                #0f131c;

            box-shadow:

                0 0 0 3px
                rgba(255, 59, 48, 0.09);
        }


        /* =========================================================
           PASSWORD TOGGLE
        ========================================================= */

        .password-toggle {

            position: absolute;

            right: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            width: 32px;
            height: 32px;

            display: flex;

            align-items: center;
            justify-content: center;

            border: none;

            background:
                transparent;

            color:
                #697180;

            cursor: pointer;

            border-radius: 7px;

            transition:
                var(--transition);
        }


        .password-toggle:hover {

            color:
                #ffffff;

            background:
                rgba(255,255,255,0.05);
        }


        /* =========================================================
           PASSWORD STRENGTH
        ========================================================= */

        .password-strength {

            display: none;

            margin-top: 9px;
        }


        .strength-bars {

            display: flex;

            gap: 4px;
        }


        .strength-bar {

            height: 3px;

            flex: 1;

            border-radius: 10px;

            background:
                #252b37;

            transition:
                var(--transition);
        }


        .strength-text {

            margin-top: 5px;

            color:
                #697180;

            font-size: 10px;
        }


        /* =========================================================
           ERRORS
        ========================================================= */

        .field-error {

            margin-top: 7px;

            color:
                var(--danger);

            font-size: 12px;
        }


        .has-error .form-input {

            border-color:
                rgba(255, 92, 92, 0.7);
        }


        /* =========================================================
           TERMS CHECKBOX
        ========================================================= */

        .terms {

            display: flex;

            align-items: flex-start;

            gap: 9px;

            margin-top: 5px;

            margin-bottom: 23px;

            color:
                var(--text-muted);

            font-size: 11px;

            line-height: 1.6;
        }


        .terms input {

            width: 15px;
            height: 15px;

            margin-top: 1px;

            flex-shrink: 0;

            accent-color:
                var(--primary);

            cursor: pointer;
        }


        .terms label {

            color:
                var(--text-muted);

            font-size: 11px;

            cursor: pointer;
        }


        .terms a {

            color:
                #ff5b52;

            text-decoration:
                none;

            font-weight: 600;
        }


        .terms a:hover {

            color:
                #ff006e;

            text-decoration:
                underline;
        }


        /* =========================================================
           REGISTER BUTTON
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

            border-radius:
                var(--input-radius);

            background:

                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--secondary)
                );

            color:
                #ffffff;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            overflow: hidden;

            box-shadow:

                0 12px 28px
                rgba(255, 59, 48, 0.20);

            transition:
                var(--transition);
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
                    rgba(255,255,255,0.15),
                    transparent
                );

            transition:
                0.5s;
        }


        .submit-button:hover::before {

            left: 100%;
        }


        .submit-button:hover {

            transform:
                translateY(-2px);

            box-shadow:

                0 16px 35px
                rgba(255, 59, 48, 0.28);
        }


        .submit-button:active {

            transform:
                translateY(0);
        }


        .submit-button:disabled {

            opacity:
                0.65;

            cursor:
                not-allowed;

            transform:
                none;
        }


        /* =========================================================
           LOGIN LINK
        ========================================================= */

        .register-link {

            margin-top: 23px;

            text-align:
                center;

            color:
                var(--text-muted);

            font-size: 13px;

            line-height: 1.6;
        }


        .register-link a {

            color:
                #ff5b52;

            font-weight:
                650;

            text-decoration:
                none;

            transition:
                var(--transition);
        }


        .register-link a:hover {

            color:
                #ff006e;

            text-decoration:
                underline;
        }


        /* =========================================================
           SECURITY
        ========================================================= */

        .security-note {

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 7px;

            margin-top: 24px;

            color:
                #596271;

            font-size: 10px;
        }


        .security-icon {

            color:
                #22c55e;

            font-size: 12px;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 850px) {

            .container {

                max-width: 650px;

                grid-template-columns:
                    1fr;

                min-height:
                    auto;
            }


            .left {

                min-height:
                    350px;

                padding:
                    50px 45px;
            }


            .right {

                padding:
                    50px 45px;
            }


            .eula {

                left: 45px;
                right: 45px;
            }


            .features {

                margin-top:
                    25px;
            }
        }


        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 550px) {

            .page {

                padding:
                    15px;
            }


            .container {

                width: 100%;

                border-radius:
                    16px;
            }


            .left {

                min-height:
                    290px;

                padding:
                    50px 28px 60px;
            }


            .home-link {

                top: 21px;
                left: 25px;
            }


            .brand {

                margin-bottom:
                    25px;
            }


            .brand-name {

                font-size:
                    25px;
            }


            .register-title {

                font-size:
                    43px;

                letter-spacing:
                    -2px;
            }


            .register-description {

                font-size:
                    13px;

                line-height:
                    1.65;
            }


            .features {

                display:
                    none;
            }


            .eula {

                left:
                    28px;

                right:
                    28px;

                bottom:
                    20px;

                font-size:
                    10px;
            }


            .right {

                padding:
                    40px 24px 45px;
            }


            .form-title {

                font-size:
                    25px;
            }


            .form-subtitle {

                font-size:
                    12px;
            }


            .form-input,
            .submit-button {

                height:
                    52px;
            }
        }


        /* =========================================================
           SMALL MOBILE
        ========================================================= */

        @media (max-width: 360px) {

            .page {

                padding:
                    8px;
            }


            .left {

                padding-left:
                    22px;

                padding-right:
                    22px;
            }


            .right {

                padding-left:
                    18px;

                padding-right:
                    18px;
            }


            .eula {

                left:
                    22px;

                right:
                    22px;
            }


            .register-title {

                font-size:
                    39px;
            }
        }


        /* =========================================================
           REDUCED MOTION
        ========================================================= */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {

                transition:
                    none !important;

                animation:
                    none !important;
            }
        }

    </style>

</head>


<body>


    <!-- =========================================================
         BACKGROUND
    ========================================================== -->

    <div class="background-circle one"></div>

    <div class="background-circle two"></div>



    <main class="page">


        <div class="container">


            <!-- =====================================================
                 LEFT PANEL
            ====================================================== -->

            <section class="left">


                <a
                    href="{{ route('public.home') }}"
                    class="home-link"
                >

                    <span class="home-icon">
                        ←
                    </span>

                    <span>
                        Back to Home
                    </span>

                </a>



                <!-- BRAND -->

                <div class="brand">

                    <div class="brand-name">
                        MBUNIETECH
                    </div>

                    <div class="brand-line"></div>

                </div>



                <!-- TITLE -->

                <h1 class="register-title">
                    Create
                    <br>
                    Account
                </h1>



                <p class="register-description">

                    Join MBUNIETECH and get access to
                    your personal account, services and
                    digital solutions.

                </p>



                <!-- FEATURES -->

                <div class="features">


                    <div class="feature">

                        <span class="feature-icon">
                            ✓
                        </span>

                        <span>
                            Fast and secure registration
                        </span>

                    </div>



                    <div class="feature">

                        <span class="feature-icon">
                            ✓
                        </span>

                        <span>
                            Manage your services easily
                        </span>

                    </div>



                    <div class="feature">

                        <span class="feature-icon">
                            ✓
                        </span>

                        <span>
                            One account for MBUNIETECH services
                        </span>

                    </div>


                </div>



                <!-- TERMS -->

                <div class="eula">

                    By creating an account, you agree to our

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
                 RIGHT PANEL
            ====================================================== -->

            <section class="right">


                <form
                    method="POST"
                    action="{{ route('register') }}"
                    class="form"
                    id="registerForm"
                >

                    @csrf



                    <!-- =================================================
                         HEADER
                    ================================================== -->

                    <div class="form-header">

                        <h2 class="form-title">
                            Create your account
                        </h2>

                        <p class="form-subtitle">

                            Fill in the information below
                            to create your MBUNIETECH account.

                        </p>

                    </div>



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
                         NAME
                    ================================================== -->

                    <div
                        class="form-group
                        @error('name') has-error @enderror"
                    >

                        <label
                            for="name"
                            class="form-label"
                        >
                            Full Name
                        </label>


                        <div class="input-wrapper">

                            <span class="input-icon">
                                👤
                            </span>


                            <input
                                id="name"
                                type="text"
                                name="name"
                                class="form-input"
                                value="{{ old('name') }}"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="Your full name"
                            >

                        </div>


                        @error('name')

                            <div class="field-error">
                                {{ $message }}
                            </div>

                        @enderror

                    </div>



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
                                id="email"
                                type="email"
                                name="email"
                                class="form-input"
                                value="{{ old('email') }}"
                                required
                                autocomplete="username"
                                placeholder="you@example.com"
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
                                id="password"
                                type="password"
                                name="password"
                                class="form-input"
                                required
                                autocomplete="new-password"
                                placeholder="Create a password"
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


                        <!-- Password strength -->

                        <div
                            class="password-strength"
                            id="passwordStrength"
                        >

                            <div class="strength-bars">

                                <span
                                    class="strength-bar"
                                ></span>

                                <span
                                    class="strength-bar"
                                ></span>

                                <span
                                    class="strength-bar"
                                ></span>

                                <span
                                    class="strength-bar"
                                ></span>

                            </div>


                            <div
                                class="strength-text"
                                id="strengthText"
                            >
                                Password strength
                            </div>

                        </div>


                        @error('password')

                            <div class="field-error">
                                {{ $message }}
                            </div>

                        @enderror

                    </div>



                    <!-- =================================================
                         CONFIRM PASSWORD
                    ================================================== -->

                    <div
                        class="form-group
                        @error('password_confirmation')
                        has-error
                        @enderror"
                    >

                        <label
                            for="password_confirmation"
                            class="form-label"
                        >
                            Confirm Password
                        </label>


                        <div class="input-wrapper">

                            <span class="input-icon">
                                🔐
                            </span>


                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                class="form-input"
                                required
                                autocomplete="new-password"
                                placeholder="Repeat your password"
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                id="toggleConfirmPassword"
                                aria-label="Show password"
                            >
                                👁
                            </button>

                        </div>


                        <div
                            class="field-error"
                            id="matchError"
                            style="display:none;"
                        >
                            Passwords do not match.
                        </div>


                        @error('password_confirmation')

                            <div class="field-error">
                                {{ $message }}
                            </div>

                        @enderror

                    </div>



                    <!-- =================================================
                         TERMS
                    ================================================== -->

                    <div class="terms">

                        <input
                            type="checkbox"
                            id="terms"
                            required
                        >


                        <label for="terms">

                            I agree to the

                            <a href="#">
                                Terms of Service
                            </a>

                            and

                            <a href="#">
                                Privacy Policy
                            </a>.

                        </label>

                    </div>



                    <!-- =================================================
                         REGISTER BUTTON
                    ================================================== -->

                    <button
                        type="submit"
                        class="submit-button"
                        id="submitButton"
                    >

                        <span id="buttonText">
                            Create Account
                        </span>

                        <span id="buttonArrow">
                            →
                        </span>

                    </button>



                    <!-- =================================================
                         LOGIN
                    ================================================== -->

                    <div class="register-link">

                        Already have an account?

                        <a href="{{ route('login') }}">
                            Login
                        </a>

                    </div>



                    <!-- =================================================
                         SECURITY
                    ================================================== -->

                    <div class="security-note">

                        <span class="security-icon">
                            🔒
                        </span>

                        <span>
                            Your information is securely protected
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
        | SHOW / HIDE PASSWORD
        |--------------------------------------------------------------------------
        */

        function setupPasswordToggle(
            inputId,
            buttonId
        ) {

            const input =
                document.getElementById(inputId);

            const button =
                document.getElementById(buttonId);


            if (!input || !button) {
                return;
            }


            button.addEventListener(
                'click',
                function () {

                    const isPassword =
                        input.type === 'password';


                    input.type =
                        isPassword
                            ? 'text'
                            : 'password';


                    button.textContent =
                        isPassword
                            ? '🙈'
                            : '👁';


                    button.setAttribute(
                        'aria-label',
                        isPassword
                            ? 'Hide password'
                            : 'Show password'
                    );

                }
            );

        }


        setupPasswordToggle(
            'password',
            'togglePassword'
        );


        setupPasswordToggle(
            'password_confirmation',
            'toggleConfirmPassword'
        );



        /*
        |--------------------------------------------------------------------------
        | PASSWORD STRENGTH
        |--------------------------------------------------------------------------
        */

        const password =
            document.getElementById('password');

        const strengthBox =
            document.getElementById('passwordStrength');

        const strengthBars =
            document.querySelectorAll('.strength-bar');

        const strengthText =
            document.getElementById('strengthText');


        if (password) {

            password.addEventListener(
                'input',
                function () {

                    const value =
                        password.value;


                    if (!value) {

                        strengthBox.style.display =
                            'none';

                        return;

                    }


                    strengthBox.style.display =
                        'block';


                    let score = 0;


                    if (value.length >= 8) {
                        score++;
                    }


                    if (/[A-Z]/.test(value)) {
                        score++;
                    }


                    if (/[0-9]/.test(value)) {
                        score++;
                    }


                    if (/[^A-Za-z0-9]/.test(value)) {
                        score++;
                    }


                    strengthBars.forEach(
                        function (bar, index) {

                            bar.style.background =
                                index < score
                                    ? '#ff3b30'
                                    : '#252b37';

                        }
                    );


                    if (score <= 1) {

                        strengthText.textContent =
                            'Weak password';

                    }
                    else if (score === 2) {

                        strengthText.textContent =
                            'Fair password';

                    }
                    else if (score === 3) {

                        strengthText.textContent =
                            'Good password';

                    }
                    else {

                        strengthText.textContent =
                            'Strong password';

                    }

                }
            );

        }



        /*
        |--------------------------------------------------------------------------
        | PASSWORD MATCH
        |--------------------------------------------------------------------------
        */

        const confirmPassword =
            document.getElementById(
                'password_confirmation'
            );

        const matchError =
            document.getElementById(
                'matchError'
            );


        function checkPasswordMatch() {

            if (
                !confirmPassword.value
            ) {

                matchError.style.display =
                    'none';

                return true;

            }


            if (
                password.value !==
                confirmPassword.value
            ) {

                matchError.style.display =
                    'block';

                return false;

            }


            matchError.style.display =
                'none';

            return true;

        }


        if (confirmPassword) {

            confirmPassword.addEventListener(
                'input',
                checkPasswordMatch
            );

        }



        /*
        |--------------------------------------------------------------------------
        | FORM SUBMIT
        |--------------------------------------------------------------------------
        */

        const registerForm =
            document.getElementById(
                'registerForm'
            );

        const submitButton =
            document.getElementById(
                'submitButton'
            );

        const buttonText =
            document.getElementById(
                'buttonText'
            );

        const buttonArrow =
            document.getElementById(
                'buttonArrow'
            );


        if (registerForm) {

            registerForm.addEventListener(
                'submit',
                function (event) {


                    /*
                     * Check password match
                     */

                    if (!checkPasswordMatch()) {

                        event.preventDefault();

                        confirmPassword.focus();

                        return;

                    }


                    /*
                     * Loading state
                     */

                    submitButton.disabled =
                        true;

                    buttonText.textContent =
                        'Creating account...';

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

        const nameInput =
            document.getElementById('name');


        if (
            nameInput &&
            window.innerWidth > 550
        ) {

            setTimeout(
                function () {

                    if (!nameInput.value) {
                        nameInput.focus();
                    }

                },
                300
            );

        }

    </script>


</body>

</html>
```
