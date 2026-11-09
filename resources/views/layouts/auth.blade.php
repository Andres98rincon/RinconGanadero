<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f2a21">
    <meta name="application-name" content="{{ config('app.name') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48" type="image/x-icon">
    <link rel="icon" href="{{ asset('images/rincon-ganadero.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/pwa/apple-touch-icon.png') }}">
    <title>@yield('title') | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Estilos propios del acceso: van aquí para que el diseño no dependa de recompilar los assets. --}}
    <style>
        :root { --ga-forest: #0f2a21; --ga-forest-2: #17392f; --ga-green: #245a46; --ga-leaf: #3f8b65; --ga-gold: #e9b779; --ga-gold-2: #d38c4e; --ga-ink: #1d2b24; --ga-muted: #6f7d74; --ga-line: #e3e9e1; }
        html, body.ga-body { margin: 0; min-height: 100%; }
        body.ga-body { min-height: 100vh; min-height: 100dvh; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; color: var(--ga-ink); background: var(--ga-forest); -webkit-font-smoothing: antialiased; overflow-x: hidden; }

        /* Pantalla de carga */
        .ga-loader { position: fixed; inset: 0; z-index: 1000; display: grid; place-items: center; background: var(--ga-forest); color: #fff; transition: opacity .45s ease, visibility .45s ease; }
        .ga-loader.is-ready { opacity: 0; visibility: hidden; pointer-events: none; }
        .ga-loader-inner { display: grid; justify-items: center; gap: 14px; }
        .ga-loader img { width: 64px; height: 64px; object-fit: contain; animation: ga-breathe 1.6s ease-in-out infinite; }
        .ga-loader-bar { width: 120px; height: 3px; overflow: hidden; border-radius: 3px; background: rgb(255 255 255 / 12%); }
        .ga-loader-bar::after { display: block; width: 40%; height: 100%; border-radius: 3px; background: var(--ga-gold); content: ''; animation: ga-loading 1.1s ease-in-out infinite; }

        /* Fondo animado */
        .ga-stage { position: relative; isolation: isolate; display: grid; min-height: 100vh; min-height: 100dvh; place-items: center; padding: 28px 20px; overflow: hidden; }
        .ga-stage::before { position: absolute; inset: -20%; z-index: -3; background: radial-gradient(40% 50% at 15% 20%, rgb(63 139 101 / 55%), transparent 70%), radial-gradient(35% 45% at 85% 80%, rgb(211 140 78 / 30%), transparent 70%), radial-gradient(50% 60% at 70% 10%, rgb(36 90 70 / 60%), transparent 70%), var(--ga-forest); content: ''; animation: ga-drift 22s ease-in-out infinite alternate; }
        .ga-stage::after { position: absolute; inset: 0; z-index: -2; background-image: linear-gradient(rgb(255 255 255 / 3.5%) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255 / 3.5%) 1px, transparent 1px); background-size: 44px 44px; mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%); content: ''; }
        .ga-orb { position: absolute; z-index: -1; border-radius: 50%; filter: blur(2px); pointer-events: none; }
        .ga-orb-1 { top: 12%; left: 8%; width: 10px; height: 10px; background: var(--ga-gold); box-shadow: 0 0 22px 6px rgb(233 183 121 / 45%); animation: ga-float 9s ease-in-out infinite; }
        .ga-orb-2 { right: 10%; bottom: 14%; width: 7px; height: 7px; background: #9fdcb1; box-shadow: 0 0 18px 5px rgb(159 220 177 / 40%); animation: ga-float 11s ease-in-out -3s infinite; }
        .ga-orb-3 { top: 18%; right: 22%; width: 5px; height: 5px; background: #fff; box-shadow: 0 0 14px 4px rgb(255 255 255 / 35%); animation: ga-float 13s ease-in-out -6s infinite; }

        /* Tarjeta principal */
        .ga-card { position: relative; display: grid; width: min(100%, 1040px); min-height: 600px; grid-template-columns: 1.02fr 1fr; overflow: hidden; border: 1px solid rgb(255 255 255 / 10%); border-radius: 28px; background: #fbfcfa; box-shadow: 0 40px 90px -20px rgb(0 0 0 / 55%), 0 0 0 1px rgb(255 255 255 / 4%); animation: ga-rise .9s cubic-bezier(.16, 1, .3, 1) both; }

        /* Panel visual */
        .ga-visual { position: relative; isolation: isolate; display: flex; min-height: 100%; flex-direction: column; justify-content: space-between; padding: 30px; overflow: hidden; color: #fff; }
        .ga-visual-img { position: absolute; inset: 0; z-index: -2; width: 100%; height: 100%; object-fit: cover; object-position: center 30%; animation: ga-kenburns 26s ease-in-out infinite alternate; }
        .ga-visual::before { position: absolute; inset: 0; z-index: -1; background: linear-gradient(180deg, rgb(10 30 23 / 70%) 0%, rgb(10 30 23 / 15%) 32%, rgb(10 30 23 / 35%) 58%, rgb(10 30 23 / 94%) 100%); content: ''; }
        .ga-brand { display: inline-flex; width: fit-content; align-items: center; gap: 12px; color: #fff; text-decoration: none; }
        .ga-brand img { width: 46px; height: 46px; object-fit: contain; filter: drop-shadow(0 6px 12px rgb(0 0 0 / 35%)); }
        .ga-brand strong { display: block; font-size: 14px; font-weight: 800; letter-spacing: 1.6px; }
        .ga-brand small { display: block; margin-top: 3px; color: rgb(255 255 255 / 72%); font-size: 10px; letter-spacing: 1.4px; text-transform: uppercase; }
        .ga-visual-copy { max-width: 420px; }
        .ga-kicker { display: inline-flex; align-items: center; gap: 10px; color: var(--ga-gold); font-size: 10px; font-weight: 800; letter-spacing: 2.4px; }
        .ga-kicker::before { width: 26px; height: 2px; border-radius: 2px; background: currentColor; content: ''; }
        .ga-visual h1 { margin: 14px 0 12px; font-size: clamp(28px, 3.1vw, 40px); font-weight: 800; line-height: 1.08; letter-spacing: -1.2px; }
        .ga-visual h1 em { color: var(--ga-gold); font-style: normal; }
        .ga-visual-copy > p { margin: 0 0 20px; color: rgb(255 255 255 / 82%); font-size: 13.5px; line-height: 1.7; }
        .ga-features { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0; list-style: none; }
        .ga-features li { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border: 1px solid rgb(255 255 255 / 16%); border-radius: 999px; background: rgb(255 255 255 / 9%); color: #fff; font-size: 11.5px; font-weight: 600; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); opacity: 0; animation: ga-fade-up .7s cubic-bezier(.16, 1, .3, 1) both; }
        .ga-features li:nth-child(1) { animation-delay: .55s; }
        .ga-features li:nth-child(2) { animation-delay: .68s; }
        .ga-features li:nth-child(3) { animation-delay: .81s; }
        .ga-features svg { width: 15px; height: 15px; fill: none; stroke: var(--ga-gold); stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

        /* Panel del formulario */
        .ga-panel { position: relative; display: flex; flex-direction: column; justify-content: center; padding: 44px clamp(28px, 5vw, 56px) 30px; background: radial-gradient(120% 60% at 100% 0%, rgb(226 237 222 / 70%), transparent 60%), #fbfcfa; }
        .ga-panel-inner { width: 100%; max-width: 380px; margin: 0 auto; }
        .ga-panel-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; max-width: 380px; width: 100%; margin: 26px auto 0; padding-top: 18px; border-top: 1px solid var(--ga-line); color: #98a39a; font-size: 11px; }
        .ga-panel-foot span { display: inline-flex; align-items: center; gap: 6px; }
        .ga-panel-foot svg { width: 13px; height: 13px; fill: none; stroke: var(--ga-leaf); stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .ga-install { display: inline-flex; align-items: center; gap: 6px; padding: 0; border: 0; background: none; color: var(--ga-green); font: inherit; font-weight: 700; cursor: pointer; }
        .ga-install:hover { color: var(--ga-gold-2); }
        .ga-install[hidden] { display: none; }

        /* Contenido de las vistas (login, recuperar, restablecer) */
        .ga-body .ga-panel .auth-heading { margin: 0 0 24px; }
        .ga-body .ga-panel .auth-icon { display: none; }
        .ga-body .ga-panel .eyebrow { display: inline-flex; align-items: center; gap: 7px; margin: 0 0 14px; padding: 5px 11px; border: 1px solid #dbe8da; border-radius: 999px; background: #eef5ec; color: var(--ga-green); font-size: 10px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; }
        .ga-body .ga-panel .eyebrow::before { width: 6px; height: 6px; border-radius: 50%; background: var(--ga-gold-2); box-shadow: 0 0 0 3px rgb(211 140 78 / 18%); content: ''; animation: ga-pulse 2s ease-in-out infinite; }
        .ga-body .ga-panel .auth-heading h2 { margin: 0 0 6px; color: var(--ga-forest-2); font-size: 30px; font-weight: 800; letter-spacing: -.9px; line-height: 1.15; }
        .ga-body .ga-panel .auth-heading p { margin: 0; color: var(--ga-muted); font-size: 13.5px; line-height: 1.6; }
        .ga-body .ga-panel .auth-form { display: grid; gap: 16px; }
        .ga-body .ga-panel .field { display: grid; gap: 7px; margin: 0; }
        .ga-body .ga-panel .field label, .ga-body .ga-panel .auth-label-row label { margin: 0; color: #35483d; font-size: 12.5px; font-weight: 700; }
        .ga-body .ga-panel .auth-label-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .ga-body .ga-panel .auth-label-row a, .ga-body .ga-panel .auth-back-link { color: var(--ga-green); font-size: 12px; font-weight: 700; text-decoration: none; transition: color .2s ease; }
        .ga-body .ga-panel .auth-label-row a:hover, .ga-body .ga-panel .auth-back-link:hover { color: var(--ga-gold-2); }
        .ga-body .ga-panel .auth-back-link { display: inline-flex; margin-top: 18px; }
        .ga-input { position: relative; }
        .ga-input > svg { position: absolute; top: 50%; left: 15px; width: 18px; height: 18px; fill: none; stroke: #8c9a90; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; transform: translateY(-50%); pointer-events: none; transition: stroke .2s ease; }
        .ga-input:focus-within > svg { stroke: var(--ga-green); }
        .ga-body .ga-panel .control { width: 100%; height: 50px; min-height: 50px; box-sizing: border-box; padding: 0 46px 0 45px; border: 1.5px solid var(--ga-line); border-radius: 13px; background: #fff; color: var(--ga-ink); font: inherit; font-size: 14px; outline: none; box-shadow: 0 1px 2px rgb(16 40 31 / 4%); transition: border-color .2s ease, box-shadow .2s ease, background .2s ease; }
        .ga-body .ga-panel .control:hover { border-color: #cfdccd; }
        .ga-body .ga-panel .control:focus { border-color: var(--ga-leaf); background: #fff; box-shadow: 0 0 0 4px rgb(63 139 101 / 14%); }
        .ga-body .ga-panel .control:-webkit-autofill { -webkit-box-shadow: 0 0 0 40px #fff inset; }
        .ga-eye { position: absolute; top: 50%; right: 7px; display: grid; width: 36px; height: 36px; place-items: center; padding: 0; border: 0; border-radius: 9px; background: transparent; color: #829087; cursor: pointer; transform: translateY(-50%); transition: background .2s ease, color .2s ease; }
        .ga-eye:hover { background: #edf3ed; color: var(--ga-forest-2); }
        .ga-eye svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .ga-body .ga-panel .auth-remember { display: inline-flex; width: fit-content; align-items: center; gap: 9px; color: #5f6d64; font-size: 12.5px; cursor: pointer; user-select: none; }
        .ga-body .ga-panel .auth-remember input { width: 17px; height: 17px; margin: 0; accent-color: var(--ga-green); cursor: pointer; }
        .ga-body .ga-panel .auth-submit { position: relative; display: flex; width: 100%; min-height: 52px; align-items: center; justify-content: center; gap: 10px; margin: 4px 0 0; padding: 0 20px; overflow: hidden; border: 0; border-radius: 13px; background: linear-gradient(135deg, #2b6a51 0%, #17392f 100%); color: #fff; font: inherit; font-size: 14px; font-weight: 800; letter-spacing: .3px; text-decoration: none; cursor: pointer; box-shadow: 0 12px 24px -10px rgb(23 57 47 / 70%); transition: transform .2s ease, box-shadow .2s ease, filter .2s ease; }
        .ga-body .ga-panel .auth-submit::after { position: absolute; top: 0; left: -60%; width: 40%; height: 100%; background: linear-gradient(100deg, transparent, rgb(255 255 255 / 28%), transparent); content: ''; transform: skewX(-20deg); transition: left .7s ease; }
        .ga-body .ga-panel .auth-submit:hover { transform: translateY(-2px); box-shadow: 0 18px 30px -12px rgb(23 57 47 / 75%); filter: brightness(1.08); }
        .ga-body .ga-panel .auth-submit:hover::after { left: 120%; }
        .ga-body .ga-panel .auth-submit:active { transform: translateY(0) scale(.99); }
        .ga-body .ga-panel .auth-submit span { margin: 0; font-size: 17px; transition: transform .2s ease; }
        .ga-body .ga-panel .auth-submit:hover span { transform: translateX(4px); }
        .ga-body .ga-panel .auth-submit.is-loading { pointer-events: none; filter: saturate(.8); }
        .ga-body .ga-panel .auth-submit.is-loading span { display: none; }
        .ga-body .ga-panel .auth-submit.is-loading::before { width: 17px; height: 17px; border: 2.5px solid rgb(255 255 255 / 35%); border-top-color: #fff; border-radius: 50%; content: ''; animation: ga-spin .7s linear infinite; }
        .ga-body .ga-panel .google-login-button { margin-top: 0; border: 1.5px solid var(--ga-line); background: #fff; color: var(--ga-ink); box-shadow: 0 1px 2px rgb(16 40 31 / 5%); }
        .ga-body .ga-panel .google-login-button::after { display: none; }
        .ga-body .ga-panel .google-login-button:hover { border-color: #cfdccd; background: #f7faf6; filter: none; box-shadow: 0 8px 18px -10px rgb(23 57 47 / 35%); }
        .ga-body .ga-panel .google-login-button svg { width: 18px; height: 18px; }
        .ga-body .ga-panel .auth-divider { display: flex; align-items: center; gap: 12px; margin: 20px 0 14px; color: #98a39a; font-size: 11.5px; }
        .ga-body .ga-panel .auth-divider::before, .ga-body .ga-panel .auth-divider::after { height: 1px; flex: 1; background: var(--ga-line); content: ''; }
        .ga-body .ga-panel .auth-status, .ga-body .ga-panel .auth-error { display: flex; gap: 9px; margin: 0 0 16px; padding: 12px 14px; border: 1px solid #d4e7d5; border-radius: 12px; background: #f0f8ef; color: #2f6a41; font-size: 12.5px; line-height: 1.5; animation: ga-fade-up .5s ease both; }
        .ga-body .ga-panel .auth-error { border-color: #f1d1c7; background: #fff4f0; color: #9c3f2a; animation: ga-fade-up .5s ease both, ga-shake .45s ease .15s; }

        /* Entrada escalonada del formulario */
        .ga-panel-inner > *, .ga-body .ga-panel .auth-form > * { animation: ga-fade-up .7s cubic-bezier(.16, 1, .3, 1) both; }
        .ga-panel-inner > :nth-child(1) { animation-delay: .25s; }
        .ga-panel-inner > :nth-child(2) { animation-delay: .32s; }
        .ga-panel-inner > :nth-child(3) { animation-delay: .39s; }
        .ga-panel-inner > :nth-child(n + 4) { animation-delay: .46s; }
        .ga-body .ga-panel .auth-form > :nth-child(2) { animation-delay: .4s; }
        .ga-body .ga-panel .auth-form > :nth-child(3) { animation-delay: .48s; }
        .ga-body .ga-panel .auth-form > :nth-child(4) { animation-delay: .56s; }
        .ga-body .ga-panel .auth-form > :nth-child(n + 5) { animation-delay: .64s; }

        @keyframes ga-rise { from { opacity: 0; transform: translateY(26px) scale(.985); } }
        @keyframes ga-fade-up { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        @keyframes ga-kenburns { from { transform: scale(1.04) translate(0, 0); } to { transform: scale(1.14) translate(-2%, -2%); } }
        @keyframes ga-drift { from { transform: translate(0, 0) rotate(0deg); } to { transform: translate(3%, -3%) rotate(6deg); } }
        @keyframes ga-float { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(14px, -22px); } }
        @keyframes ga-pulse { 50% { box-shadow: 0 0 0 6px rgb(211 140 78 / 0%); } }
        @keyframes ga-spin { to { transform: rotate(360deg); } }
        @keyframes ga-breathe { 50% { transform: scale(1.07); } }
        @keyframes ga-loading { 0% { transform: translateX(-100%); } 100% { transform: translateX(260%); } }
        @keyframes ga-shake { 20%, 60% { transform: translateX(-4px); } 40%, 80% { transform: translateX(4px); } }

        /* Tableta */
        @media (max-width: 900px) {
            .ga-card { width: min(100%, 560px); min-height: 0; grid-template-columns: 1fr; border-radius: 24px; }
            .ga-visual { min-height: 230px; padding: 22px 24px; }
            .ga-visual h1 { margin: 10px 0 0; font-size: 26px; }
            .ga-visual-copy > p, .ga-features { display: none; }
            .ga-panel { padding: 30px 28px 24px; }
        }

        /* Celular: experiencia de app a pantalla completa */
        @media (max-width: 560px) {
            .ga-stage { padding: 0; place-items: stretch; }
            .ga-card { width: 100%; min-height: 100vh; min-height: 100dvh; grid-template-rows: auto 1fr; border: 0; border-radius: 0; box-shadow: none; animation: none; }
            .ga-visual { min-height: 0; height: 34vh; max-height: 270px; padding: calc(18px + env(safe-area-inset-top)) 20px 34px; }
            .ga-brand img { width: 40px; height: 40px; }
            .ga-brand strong { font-size: 13px; }
            .ga-visual h1 { font-size: 23px; letter-spacing: -.6px; }
            .ga-kicker { font-size: 9px; }
            .ga-panel { position: relative; z-index: 2; margin-top: -24px; padding: 28px 22px calc(20px + env(safe-area-inset-bottom)); border-radius: 24px 24px 0 0; box-shadow: 0 -12px 30px rgb(10 30 23 / 18%); justify-content: flex-start; animation: ga-sheet .7s cubic-bezier(.16, 1, .3, 1) both; }
            .ga-body .ga-panel .auth-heading { margin-bottom: 20px; }
            .ga-body .ga-panel .auth-heading h2 { font-size: 26px; }
            .ga-body .ga-panel .control { height: 52px; font-size: 16px; }
            .ga-panel-foot { margin-top: auto; padding-top: 16px; }
            .ga-orb { display: none; }
            @keyframes ga-sheet { from { transform: translateY(40px); opacity: 0; } }
        }
        @media (max-height: 640px) and (min-width: 561px) { .ga-card { min-height: 0; } .ga-panel { padding-top: 30px; padding-bottom: 22px; } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body class="ga-body">
    <div class="ga-loader" data-app-loading role="status" aria-live="polite">
        <div class="ga-loader-inner">
            <img src="{{ asset('images/rincon-ganadero.png') }}" alt="">
            <div class="ga-loader-bar" aria-hidden="true"></div>
            <span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Cargando Rincón Ganadero…</span>
        </div>
    </div>

    @if (session('status'))
        <div hidden data-swal-toast="{{ session('status') }}" role="status"></div>
    @endif
    @if (session('error'))
        <div hidden data-swal-error="{{ session('error') }}" role="alert"></div>
    @endif

    <main class="ga-stage">
        <span class="ga-orb ga-orb-1" aria-hidden="true"></span>
        <span class="ga-orb ga-orb-2" aria-hidden="true"></span>
        <span class="ga-orb ga-orb-3" aria-hidden="true"></span>

        <div class="ga-card">
            <section class="ga-visual" aria-label="Rincón Ganadero">
                <img class="ga-visual-img" src="{{ asset('images/domino.jpeg') }}" alt="" fetchpriority="high">

                <a class="ga-brand" href="{{ route('login') }}">
                    <img src="{{ asset('images/rincon-ganadero.png') }}" alt="Logo de Rincón Ganadero">
                    <span>
                        <strong>RINCÓN GANADERO</strong>
                        <small>Gestión ganadera inteligente</small>
                    </span>
                </a>

                <div class="ga-visual-copy">
                    <span class="ga-kicker">CONTROL · CUIDADO · CRECIMIENTO</span>
                    <h1>Tu hato, <em>bajo control</em> desde cualquier lugar.</h1>
                    <p>Inventario, producción, reproducción y finanzas de tus fincas en una sola aplicación, incluso en el potrero y sin señal.</p>
                    <ul class="ga-features">
                        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 8h3v3H7zM14 8h3v3h-3zM7 14h3v3H7zM14 14h3v3h-3z"/></svg> Animales con QR</li>
                        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01M3 3l18 18"/></svg> Funciona sin conexión</li>
                        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg> Alertas del hato</li>
                    </ul>
                </div>
            </section>

            <section class="ga-panel">
                <div class="ga-panel-inner" id="authCard">
                    @yield('content')
                </div>
                <div class="ga-panel-foot">
                    <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 4v5c0 5-3.4 8-8 9-4.6-1-8-4-8-9V7z"/><path d="m9 12 2 2 4-4"/></svg> Conexión segura</span>
                    <button class="ga-install" type="button" data-pwa-install>
                        <svg viewBox="0 0 24 24" aria-hidden="true" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-5-5 5 5 5-5"/><path d="M4 17v4h16v-4"/></svg>
                        Instalar app
                    </button>
                </div>
            </section>
        </div>
    </main>

    <script>
        (() => {
            const hideLoader = () => document.querySelector('[data-app-loading]')?.classList.add('is-ready');
            if (document.readyState === 'complete') {
                hideLoader();
            } else {
                window.addEventListener('load', hideLoader, { once: true });
                window.setTimeout(hideLoader, 2500);
            }

            const icons = {
                email: '<path d="M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z"/><path d="m3.5 7 8.5 6 8.5-6"/>',
                lock: '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
                eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
                eyeOff: '<path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A11 11 0 0 1 12 5c6.5 0 10 7 10 7a16 16 0 0 1-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.5 7 10 7a10 10 0 0 0 3-.5"/>',
            };
            const svg = (paths) => `<svg viewBox="0 0 24 24" aria-hidden="true">${paths}</svg>`;

            document.querySelectorAll('.ga-panel input.control').forEach((input) => {
                if (input.closest('.ga-input')) return;
                const wrapper = document.createElement('div');
                wrapper.className = 'ga-input';
                input.before(wrapper);
                wrapper.append(input);
                wrapper.insertAdjacentHTML('afterbegin', svg(input.type === 'password' ? icons.lock : icons.email));

                if (input.type === 'password') {
                    const toggle = document.createElement('button');
                    toggle.type = 'button';
                    toggle.className = 'ga-eye';
                    toggle.setAttribute('aria-label', 'Mostrar contraseña');
                    toggle.innerHTML = svg(icons.eye);
                    toggle.addEventListener('click', () => {
                        const isHidden = input.type === 'password';
                        input.type = isHidden ? 'text' : 'password';
                        toggle.innerHTML = svg(isHidden ? icons.eyeOff : icons.eye);
                        toggle.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
                        input.focus();
                    });
                    wrapper.append(toggle);
                }
            });

            document.querySelectorAll('.ga-panel form').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    window.setTimeout(() => {
                        if (event.defaultPrevented) return;
                        form.querySelector('.auth-submit')?.classList.add('is-loading');
                    }, 0);
                });
            });
        })();
    </script>
</body>
</html>
