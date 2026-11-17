<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | FoodMetrics</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #F3F3ED;
            color: #292F2B;
            font-family: Arial, sans-serif;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 32px;
            background: #292F2B;
            color: white;
        }
        .brand {
            font-size: 20px;
            font-weight: bold;
        }
        .brand span { color: #B8C18F; }
        .logout-button {
            padding: 10px 16px;
            border: 1px solid #777F65;
            border-radius: 8px;
            background: transparent;
            color: white;
            cursor: pointer;
        }
        .logout-button:hover { background: #414940; }
        main {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 24px;
        }
        .welcome {
            margin-bottom: 28px;
        }
        .welcome h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }
        .welcome p {
            color: #747A70;
        }
        .profile-card {
            max-width: 550px;
            padding: 26px;
            border: 1px solid #E1E4D9;
            border-radius: 14px;
            background: white;
        }
        .profile-card h2 {
            margin-top: 0;
            font-size: 18px;
        }
        .detail {
            padding: 13px 0;
            border-bottom: 1px solid #ECEDE7;
        }
        .detail:last-child { border-bottom: 0; }
        .detail-label {
            display: block;
            margin-bottom: 5px;
            color: #858B80;
            font-size: 12px;
        }
        .detail-value {
            font-size: 15px;
            font-weight: 600;
        }
        .notice {
            margin-top: 22px;
            padding: 16px;
            border-left: 4px solid #858E62;
            border-radius: 6px;
            background: #E9EBDD;
            color: #424A37;
            font-size: 14px;
            line-height: 1.5;
        }
        @media (max-width: 600px) {
            header { padding: 16px 18px; }
            main { margin-top: 28px; }
            .welcome h1 { font-size: 23px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="brand">Food<span>Metrics</span></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="logout-button" type="submit">Log out</button>
        </form>
    </header>

    <main>
        <section class="welcome">
            <h1>Welcome, {{ $user->name }}</h1>
            <p>You are signed in to the Olive Grove Kitchen management system.</p>
        </section>

        <section class="profile-card">
            <h2>Account Information</h2>

            <div class="detail">
                <span class="detail-label">Full name</span>
                <span class="detail-value">{{ $user->name }}</span>
            </div>

            <div class="detail">
                <span class="detail-label">Email address</span>
                <span class="detail-value">{{ $user->email }}</span>
            </div>

            <div class="detail">
                <span class="detail-label">Role</span>
                <span class="detail-value">{{ ucwords(str_replace('_', ' ', $user->role)) }}</span>
            </div>

            <div class="detail">
                <span class="detail-label">Account status</span>
                <span class="detail-value">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
        </section>

        <div class="notice">
            Authentication is active. Your dashboard modules and permissions
            will be connected in the next backend development steps.
        </div>
    </main>
</body>
</html>