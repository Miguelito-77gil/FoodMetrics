<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Olive Grove Kitchen</title>
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
        padding: 24px;
        font-family: Arial, sans-serif;
        color: #292F2B;
        background: #20271F;
        isolation: isolate;
        position: relative;
    }

    body::before {
        content: "";
        position: fixed;
        inset: 0;
        z-index: -2;
        background-image: url("/yes.jpg");
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        transform: scale(1.02);
    }

    body::after {
        content: "";
        position: fixed;
        inset: 0;
        z-index: -1;
        background:
            linear-gradient(
                135deg,
                rgba(22, 29, 24, .78),
                rgba(31, 40, 30, .58),
                rgba(15, 20, 17, .78)
            );
        backdrop-filter: blur(3px);
        -webkit-backdrop-filter: blur(3px);
    }

    .login-card {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 420px;
        padding: 40px;
        background: rgba(255, 255, 255, .88);
        border: 1px solid rgba(255, 255, 255, .65);
        border-radius: 22px;
        box-shadow:
            0 30px 80px rgba(0, 0, 0, .35),
            0 8px 25px rgba(0, 0, 0, .12),
            inset 0 1px 0 rgba(255, 255, 255, .8);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        animation: loginFloat 4s ease-in-out infinite;
    }

    @keyframes loginFloat {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-9px);
        }
    }

    .brand {
        margin-bottom: 32px;
        text-align: center;
    }

    .brand-mark {
        display: block;
        width: 76px;
        height: 82px;
        margin: 0 auto;
        object-fit: contain;
        border-radius: 12px;
        background: #F3F3ED;
        box-shadow: 0 4px 15px rgba(41, 47, 43, .10);
    }

    h1 {
        margin: 18px 0 8px;
        font-size: 27px;
        color: #292F2B;
    }

    .subtitle {
        margin: 0;
        color: #747A70;
        line-height: 1.5;
        font-size: 14px;
    }

    label {
        display: block;
        margin: 18px 0 8px;
        font-size: 14px;
        font-weight: 600;
    }

    input[type="email"],
    input[type="password"] {
        width: 100%;
        padding: 13px 14px;
        border: 1px solid #D9DDD2;
        border-radius: 9px;
        background: rgba(255, 255, 255, .9);
        color: #292F2B;
        font-size: 14px;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }

    input:focus {
        border-color: #858E62;
        box-shadow: 0 0 0 3px rgba(133, 142, 98, .18);
    }

    .remember {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 18px 0;
        color: #62695F;
        font-size: 13px;
    }

    .remember label {
        margin: 0;
        font-weight: normal;
    }

    button {
        width: 100%;
        padding: 14px;
        border: 0;
        border-radius: 9px;
        background: #858E62;
        color: white;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: background .2s, transform .2s, box-shadow .2s;
    }

    button:hover {
        background: #707A50;
        box-shadow: 0 5px 15px rgba(112, 122, 80, .25);
    }

    button:active {
        transform: scale(.98);
    }

    .login-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 5000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15, 20, 17, .58);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .2s ease, visibility .2s ease;
    }

    .login-modal-backdrop.is-open {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    .login-modal {
        position: relative;
        width: min(100%, 420px);
        padding: 30px;
        border: 1px solid #E4E6DD;
        border-radius: 15px;
        background: #FFFFFF;
        color: #292F2B;
        box-shadow: 0 24px 70px rgba(0, 0, 0, .24);
        transform: translateY(10px);
        transition: transform .2s ease;
    }

    .login-modal-backdrop.is-open .login-modal {
        transform: translateY(0);
    }

    .login-modal-icon {
        display: grid;
        width: 46px;
        height: 46px;
        place-items: center;
        margin-bottom: 17px;
        border-radius: 13px;
        background: #F8EAE7;
        color: #A33D35;
    }

    .login-modal-icon svg {
        width: 24px;
        height: 24px;
    }

    .login-modal h2 {
        margin: 0 0 9px;
        font-size: 19px;
    }

    .login-modal p {
        margin: 0;
        color: #70766F;
        font-size: 13px;
        line-height: 1.7;
        overflow-wrap: anywhere;
    }

    .login-modal-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 23px;
    }

    .login-modal-actions button {
        width: auto;
        min-width: 100px;
        padding: 11px 18px;
    }

    body.login-modal-open {
        overflow: hidden;
    }

    .footer {
        margin-top: 28px;
        text-align: center;
        color: #8A9085;
        font-size: 12px;
    }

    @media (max-width: 520px) {
        body {
            padding: 16px;
        }

        .login-card {
            padding: 30px 24px;
            border-radius: 18px;
        }

        .login-modal-backdrop {
            align-items: flex-end;
            padding: 12px;
        }

        .login-modal {
            width: 100%;
            padding: 25px 21px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .login-card {
            animation: none;
        }

        *, *::before, *::after {
            transition-duration: .01ms !important;
        }
    }
</style>
</head>
<body>
    <main class="login-card">
        <div class="brand">
            <img src="{{ asset('yes.jpg') }}" alt="Olive Grove Kitchen" class="brand-mark">
            <h1>Welcome back</h1>
            <p class="subtitle">Sign in to access the Olive Grove Kitchen management system.</p>
        </div>


        <form method="POST" action="{{ route('login.submit') }}">
            @csrf

            <label for="email">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="Enter your email"
                autocomplete="username"
                required
                autofocus
            >

            <label for="password">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
            >

            <div class="remember">
                <input id="remember" type="checkbox" name="remember" value="1">
                <label for="remember">Remember me</label>
            </div>

            <button type="submit">Sign in</button>
        </form>

        <div class="footer">
            Olive Grove Kitchen &copy; {{ date('Y') }}
        </div>
    </main>

    @if ($errors->any())
        <div class="login-modal-backdrop is-open" id="login-error-modal"
             role="presentation" aria-hidden="false">
            <section class="login-modal" role="alertdialog" aria-modal="true"
                     aria-labelledby="login-modal-title"
                     aria-describedby="login-modal-message">
                <div class="login-modal-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="m15 9-6 6m0-6 6 6"/>
                    </svg>
                </div>
                <h2 id="login-modal-title">Unable to sign in</h2>
                <p id="login-modal-message">{{ $errors->first() }}</p>
                <div class="login-modal-actions">
                    <button type="button" id="login-modal-close">Close</button>
                </div>
            </section>
        </div>

        <script>
            (() => {
                const modal = document.getElementById('login-error-modal');
                const closeButton = document.getElementById('login-modal-close');

                const closeModal = () => {
                    modal?.classList.remove('is-open');
                    modal?.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('login-modal-open');
                };

                if (modal) {
                    document.body.classList.add('login-modal-open');
                    closeButton?.focus();
                    closeButton?.addEventListener('click', closeModal);

                    modal.addEventListener('click', (event) => {
                        if (event.target === modal) closeModal();
                    });

                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') closeModal();
                    });
                }
            })();
        </script>
    @endif
</body>
</html>
