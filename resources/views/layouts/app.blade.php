<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | Olive Grove Kitchen</title>

    <script>
        (() => {
            try {
                const saved = localStorage.getItem('fm-theme');
                document.documentElement.dataset.theme = saved === 'dark' ? 'dark' : 'light';
            } catch (error) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="fm-app">

@php
    $currentUser = auth()->user();
    $role = $currentUser?->role;
@endphp

<div class="fm-shell">
    <aside class="fm-sidebar" id="fm-sidebar">
        <div class="fm-brand">
            <a href="{{ route('dashboard') }}" class="fm-brand-link">
                <img src="{{ asset('yes.jpg') }}" alt="Olive Grove Kitchen" class="fm-brand-mark">
                <span class="fm-brand-copy">
                    <strong>Olive Grove Kitchen</strong>
                </span>
            </a>

            <button type="button"
                    class="fm-icon-btn fm-sidebar-close"
                    id="fm-sidebar-close"
                    aria-label="Close navigation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="m18 6-12 12M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="fm-sidebar-content">
            <p class="fm-nav-label">WORKSPACE</p>

            <nav class="fm-nav" aria-label="Main navigation">

                @if(in_array($role, ['admin', 'cashier'], true))
                    <a href="{{ route('orders.index') }}"
                       class="fm-nav-link {{ request()->routeIs('orders.index', 'orders.show') ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M5 4h14v17H5z"/>
                            <path d="M8 8h8M8 12h8M8 16h5"/>
                        </svg>
                        <span>Orders</span>
                    </a>

                    <a href="{{ route('cashier.orders.create') }}"
                       class="fm-nav-link {{ request()->routeIs('cashier.orders.create') ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 8v8M8 12h8"/>
                        </svg>
                        <span>New Order</span>
                    </a>
                @endif

                @if($role === 'admin')
                    <p class="fm-nav-label fm-nav-label-spaced">MANAGEMENT</p>

                    <a href="{{ route('admin.index') }}"
                       class="fm-nav-link {{ request()->routeIs('admin.index') ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 20h18M5 20V9l7-5 7 5v11"/>
                            <path d="M9 20v-6h6v6"/>
                        </svg>
                        <span>Admin Overview</span>
                    </a>

                    <a href="{{ route('admin.deliveries.index') }}"
                       class="fm-nav-link {{ request()->routeIs('admin.deliveries.*') ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 6h12v12H3z"/>
                            <path d="M15 10h4l3 4v4h-7z"/>
                            <circle cx="7.5" cy="18" r="1.8"/>
                            <circle cx="18.5" cy="18" r="1.8"/>
                        </svg>
                        <span>Delivery Assignments</span>
                    </a>
                @endif

                @if(in_array($role, ['admin', 'delivery_staff'], true))
                    <a href="{{ route('delivery.index') }}"
                       class="fm-nav-link {{ request()->routeIs('delivery.index', 'delivery.show') ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 6h12v12H3z"/>
                            <path d="M15 10h4l3 4v4h-7z"/>
                            <circle cx="7.5" cy="18" r="1.8"/>
                            <circle cx="18.5" cy="18" r="1.8"/>
                        </svg>
                        <span>Delivery Records</span>
                    </a>
                @endif

                @if(in_array($role, ['admin', 'inventory_staff'], true))
                    <a href="{{ route('inventory.index') }}"
                       class="fm-nav-link {{ request()->routeIs('inventory.*') ? 'is-active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 7 12 3l8 4v10l-8 4-8-4z"/>
                            <path d="m4 7 8 4 8-4M12 11v10"/>
                        </svg>
                        <span>Inventory Access</span>
                    </a>
                @endif
            </nav>
        </div>

        <div class="fm-sidebar-footer">
            <div class="fm-sidebar-user">
                <div class="fm-avatar">
                    {{ strtoupper(substr($currentUser?->name ?? 'U', 0, 1)) }}
                </div>
                <div class="fm-sidebar-user-copy">
                    <strong>{{ $currentUser?->name }}</strong>
                    <small>{{ ucwords(str_replace('_', ' ', $role ?? 'user')) }}</small>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="fm-logout-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M10 17l5-5-5-5M15 12H3"/>
                        <path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>
                    </svg>
                    <span>Sign out</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="fm-sidebar-overlay" id="fm-sidebar-overlay"></div>

    <main class="fm-main">
        <header class="fm-topbar">
            <div class="fm-topbar-left">
                <button type="button"
                        class="fm-icon-btn fm-menu-toggle"
                        id="fm-menu-toggle"
                        aria-label="Open navigation"
                        aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <div class="fm-breadcrumb">

                    <strong>@yield('breadcrumb', 'Dashboard')</strong>
                </div>
            </div>

            <div class="fm-topbar-actions">
                <button type="button"
                        class="fm-icon-btn"
                        id="fm-theme-toggle"
                        aria-label="Toggle color theme"
                        title="Toggle light and dark mode">
                    <svg class="fm-theme-icon-light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>
                    </svg>
                    <svg class="fm-theme-icon-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z"/>
                    </svg>
                </button>

                <div class="fm-topbar-profile">
                    <div class="fm-avatar fm-avatar-small">
                        {{ strtoupper(substr($currentUser?->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="fm-topbar-profile-copy">
                        <strong>{{ $currentUser?->name }}</strong>
                        <small>{{ ucwords(str_replace('_', ' ', $role ?? 'user')) }}</small>
                    </div>
                </div>
            </div>
        </header>

        <div class="fm-content">
            @yield('content')
        </div>

        <footer class="fm-footer">
            <span>Olive Grove Kitchen Management System</span>
        </footer>
    </main>
</div>

<div
    class="fm-modal-backdrop"
    id="fm-message-modal"
    data-fm-modal
    data-auto-open="{{ session()->has('success') || session()->has('error') || session()->has('warning') || session()->has('info') || $errors->any() ? 'true' : 'false' }}"
    data-modal-type="{{ $errors->any() ? 'validation' : (session()->has('error') ? 'error' : (session()->has('warning') ? 'warning' : (session()->has('info') ? 'info' : 'success'))) }}"
    aria-hidden="true"
>
    <section class="fm-modal" role="dialog" aria-modal="true"
             aria-labelledby="fm-modal-title" aria-describedby="fm-modal-message">
        <button type="button" class="fm-modal-close" data-fm-modal-close aria-label="Close dialog">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round">
                <path d="m18 6-12 12M6 6l12 12"/>
            </svg>
        </button>

        <div class="fm-modal-icon" data-modal-icon aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="m5 12 4 4L19 6"/>
            </svg>
        </div>

        <h2 class="fm-modal-title" id="fm-modal-title">
            @if($errors->any())
                Please review the following
            @elseif(session('error'))
                Something went wrong
            @elseif(session('warning'))
                Warning
            @elseif(session('info'))
                Information
            @else
                Success
            @endif
        </h2>

        <div class="fm-modal-message" id="fm-modal-message">
            @if($errors->any())
                <ul class="fm-modal-error-list">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @elseif(session('success'))
                {{ session('success') }}
            @elseif(session('error'))
                {{ session('error') }}
            @elseif(session('warning'))
                {{ session('warning') }}
            @elseif(session('info'))
                {{ session('info') }}
            @endif
        </div>

        <div class="fm-modal-actions">
            <button type="button" class="fm-btn fm-btn-primary" data-fm-modal-close>
                Close
            </button>
        </div>
    </section>
</div>

@stack('scripts')
</body>
</html>