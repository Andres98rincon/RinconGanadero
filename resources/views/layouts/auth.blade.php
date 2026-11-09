
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17392f">
    <meta name="application-name" content="{{ config('app.name') }}">

    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48" type="image/x-icon">
    <link rel="icon" href="{{ asset('images/rincon-ganadero.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('images/pwa/apple-touch-icon.png') }}">

    <title>@yield('title') | {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="m-0 min-h-screen min-h-[100dvh] bg-[#f7f8f4] font-sans text-[#202d27] antialiased">

    <!-- PANTALLA DE CARGA -->
    <div data-app-loading role="status" aria-live="polite"
        class="fixed inset-0 z-[1000] grid place-items-center bg-[#17392f] text-white transition-all duration-500">

        <div class="grid justify-items-center gap-3 text-center">

            <img src="{{ asset('images/rincon-ganadero.png') }}"
                alt=""
                class="h-16 w-16 object-contain">

            <div class="h-8 w-8 animate-spin rounded-full border-[3px] border-white/20 border-t-[#e9b779]"></div>

            <strong class="text-base font-bold tracking-wide">
                Rincón Ganadero
            </strong>

            <span class="text-xs text-white/65">
                Preparando tu espacio ganadero...
            </span>

        </div>
    </div>

    <!-- MENSAJES DE SESIÓN -->
    @if (session('status'))
        <div class="hidden" data-swal-toast="{{ session('status') }}" role="status"></div>
    @endif

    @if (session('error'))
        <div class="hidden" data-swal-error="{{ session('error') }}" role="alert"></div>
    @endif

    <!-- CONTENEDOR PRINCIPAL -->
    <main class="grid min-h-[100dvh] grid-cols-1 lg:grid-cols-[1.08fr_.92fr]">

        <!-- =====================================
             PANEL VISUAL
        ====================================== -->

        <section
            id="authVisual"
            aria-label="Gestión ganadera Rincón Ganadero"
            class="relative isolate block h-[360px] min-h-[360px] overflow-hidden bg-[#17392f] text-white sm:h-[400px] sm:min-h-[400px] md:h-[440px] md:min-h-[440px] lg:h-auto lg:min-h-screen">

            <!-- IMAGEN DE FONDO -->
            <img
                id="authParallaxImage"
                src="{{ asset('images/domino.jpeg') }}"
                alt="Ganado de Rincón Ganadero"
                fetchpriority="high"
                class="absolute inset-0 h-full w-full scale-[1.04] object-cover object-center transition-transform duration-200 ease-out lg:-inset-[3%] lg:h-[106%] lg:w-[106%]">

            <!-- DEGRADADO PRINCIPAL -->
            <div class="absolute inset-0 bg-gradient-to-t from-[#0c231b]/95 via-[#0c231b]/40 to-[#0c231b]/20"></div>

            <div class="absolute inset-0 bg-gradient-to-r from-[#0c231b]/30 to-transparent"></div>

            <!-- MARCA -->
            <a href="{{ route('login') }}"
                class="absolute left-5 top-5 z-20 flex items-center gap-3 text-white no-underline transition-opacity hover:opacity-80 sm:left-8 sm:top-7 lg:left-12 lg:top-10">

                <img
                    src="{{ asset('images/rincon-ganadero.png') }}"
                    alt="Logo Rincón Ganadero"
                    class="h-11 w-11 object-contain drop-shadow-lg sm:h-[54px] sm:w-[54px]">

                <span>
                    <strong class="block text-xs font-black tracking-[1.5px] sm:text-[15px]">
                        RINCÓN GANADERO
                    </strong>

                    <small class="mt-1 block text-[9px] uppercase tracking-[1.5px] text-white/80 sm:text-[11px]">
                        Gestión ganadera inteligente
                    </small>
                </span>

            </a>

            <!-- TEXTO PRINCIPAL -->
            <div
                id="authVisualText"
                class="absolute bottom-7 left-5 right-5 z-20 max-w-[600px] sm:bottom-9 sm:left-8 sm:right-8 lg:bottom-[13%] lg:left-[9%] lg:right-[9%]">

                <div class="mb-3 inline-flex items-center gap-2.5 text-[9px] font-extrabold tracking-[1.8px] text-[#e9b779] sm:mb-5 sm:text-[11px] sm:tracking-[2.5px] lg:mb-6">

                    <span class="h-0.5 w-6 bg-[#e9b779]"></span>

                    CONTROL · CUIDADO · CRECIMIENTO

                </div>

                <h1 class="text-[clamp(25px,5vw,66px)] font-black leading-[1.08] tracking-[-1px] sm:tracking-[-2px] lg:tracking-[-2.8px]">

                    Tu finca crece<br>
                    cuando todo está<br>
                    en buenas manos.

                </h1>

                <p class="mt-3 max-w-[410px] text-xs leading-5 text-white/85 sm:mt-5 sm:text-sm sm:leading-7 lg:mt-6 lg:text-[15px] lg:leading-8">

                    Un espacio seguro para cuidar tu ganado,
                    administrar tus fincas y tomar mejores
                    decisiones cada día.

                </p>

            </div>

            <!-- CRÉDITO -->
            <span class="absolute bottom-6 right-8 z-20 hidden text-[10px] tracking-wide text-white/55 lg:block">

                RINCÓN GANADERO · CONTROL Y GESTIÓN

            </span>

            <!-- ELEMENTOS DECORATIVOS -->
            <div class="pointer-events-none absolute -right-24 top-1/4 h-72 w-72 rounded-full border border-white/10"></div>

            <div class="pointer-events-none absolute -right-12 top-1/3 h-48 w-48 rounded-full border border-white/10"></div>

        </section>

        <!-- =====================================
             PANEL DEL FORMULARIO
        ====================================== -->

        <section
            class="relative flex min-h-[100dvh] min-w-0 flex-col items-center justify-center bg-[#fafbf8] px-5 py-10 sm:px-10 lg:px-[8%]">

            <!-- DECORACIÓN DE FONDO -->
            <div class="pointer-events-none absolute right-0 top-0 h-72 w-72 rounded-full bg-[#e2edde]/50 blur-3xl"></div>

            <div class="pointer-events-none absolute bottom-0 left-0 h-64 w-64 rounded-full bg-[#e9b779]/10 blur-3xl"></div>

            <!-- CONTENIDO -->
            <div id="authCard" class="relative z-10 w-full max-w-[440px]">

                @yield('content')

            </div>

            <!-- FOOTER -->
            <p class="relative z-10 mt-10 text-center text-[11px] leading-relaxed text-[#98a39a]">

                Rincón Ganadero

                <span class="px-1.5 text-[#e9b779]">◆</span>

                Administración segura de finca

            </p>

        </section>

    </main>

    <!-- =====================================
         JAVASCRIPT VISUAL
    ====================================== -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            /* =====================================
               OCULTAR PANTALLA DE CARGA
            ====================================== */

            const loader = document.querySelector('[data-app-loading]');

            if (loader) {
                requestAnimationFrame(() => {
                    loader.classList.add(
                        'opacity-0',
                        'invisible',
                        'pointer-events-none'
                    );
                });
            }

            /* =====================================
               ANIMACIÓN DE ENTRADA
            ====================================== */

            const animatedElements = [
                document.getElementById('authCard'),
                document.getElementById('authVisualText')
            ];

            animatedElements.forEach(element => {

                if (
                    !element ||
                    window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ) {
                    return;
                }

                element.animate(
                    [
                        {
                            opacity: 0,
                            transform: 'translateY(22px)'
                        },
                        {
                            opacity: 1,
                            transform: 'translateY(0)'
                        }
                    ],
                    {
                        duration: 800,
                        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
                        fill: 'both'
                    }
                );

            });

            /* =====================================
               EFECTO PARALLAX
            ====================================== */

            const visual = document.getElementById('authVisual');
            const image = document.getElementById('authParallaxImage');

            const supportsParallax =
                window.matchMedia('(hover: hover) and (pointer: fine)').matches &&
                !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (visual && image && supportsParallax) {

                visual.addEventListener('mousemove', function(event) {

                    const rect = visual.getBoundingClientRect();

                    const x = (event.clientX - rect.left) / rect.width - 0.5;
                    const y = (event.clientY - rect.top) / rect.height - 0.5;

                    image.style.transform =
                        `scale(1.08) translate(${x * -12}px, ${y * -12}px)`;

                });

                visual.addEventListener('mouseleave', function() {

                    image.style.transform = 'scale(1.04)';

                });

            }

            /* =====================================
               ICONOS SVG
            ====================================== */

            const icons = {

                email: `
                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="3"/>
                        <path d="m3 7 9 6 9-6"/>
                    </svg>
                `,

                password: `
                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="10" width="16" height="11" rx="2"/>
                        <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                    </svg>
                `,

                user: `
                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M5 21v-2a7 7 0 0 1 14 0v2"/>
                    </svg>
                `,

                eye: `
                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                `,

                eyeOff: `
                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 3 18 18"/>
                        <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
                        <path d="M9.9 5.2A11 11 0 0 1 12 5c6.5 0 10 7 10 7a16 16 0 0 1-3.1 3.8"/>
                        <path d="M6.2 6.2C3.5 8 2 12 2 12s3.5 7 10 7a10 10 0 0 0 3-.5"/>
                    </svg>
                `

            };

            /* =====================================
               MEJORAR VISUALMENTE LOS CAMPOS
               SIN CAMBIAR SU FUNCIONAMIENTO
            ====================================== */

            const card = document.getElementById('authCard');

            if (card) {

                // FORMULARIOS
                card.querySelectorAll('form').forEach(form => {

                    form.classList.add(
                        'flex',
                        'w-full',
                        'flex-col',
                        'gap-5'
                    );

                });

                // ETIQUETAS
                card.querySelectorAll('label').forEach(label => {

                    if (
                        label.querySelector(
                            'input[type="checkbox"], input[type="radio"]'
                        )
                    ) {

                        label.classList.add(
                            'inline-flex',
                            'items-center',
                            'gap-2',
                            'cursor-pointer',
                            'text-xs',
                            'font-medium',
                            'text-[#65736a]'
                        );

                    } else {

                        label.classList.add(
                            'mb-2',
                            'block',
                            'text-[13px]',
                            'font-bold',
                            'text-[#35483d]'
                        );

                    }

                });

                // CAMPOS
                card.querySelectorAll('input').forEach(input => {

                    if (
                        input.type === 'hidden' ||
                        input.type === 'checkbox' ||
                        input.type === 'radio' ||
                        input.type === 'submit' ||
                        input.type === 'button'
                    ) {

                        if (
                            input.type === 'checkbox' ||
                            input.type === 'radio'
                        ) {

                            input.classList.add(
                                'h-4',
                                'w-4',
                                'cursor-pointer',
                                'accent-[#245a46]'
                            );

                        }

                        return;

                    }

                    // EVITAR DUPLICAR WRAPPERS
                    if (
                        input.parentElement &&
                        input.parentElement.classList.contains('auth-input-wrap')
                    ) {
                        return;
                    }

                    const wrapper = document.createElement('div');

                    wrapper.className = 'auth-input-wrap relative w-full';

                    input.parentNode.insertBefore(wrapper, input);

                    wrapper.appendChild(input);

                    input.classList.add(
                        '!h-[55px]',
                        '!w-full',
                        '!rounded-xl',
                        '!border',
                        '!border-[#e5eae3]',
                        '!bg-white',
                        '!py-3',
                        '!pl-12',
                        '!pr-12',
                        '!text-sm',
                        '!text-[#202d27]',
                        '!outline-none',
                        'transition-all',
                        'duration-300',
                        'placeholder:!text-[#a5afa8]',
                        'focus:!border-[#39795c]',
                        'focus:!ring-4',
                        'focus:!ring-[#39795c]/10'
                    );

                    const isPassword = input.type === 'password';

                    const isEmail =
                        input.type === 'email' ||
                        /email|correo/i.test(
                            (input.name || '') + ' ' + (input.id || '')
                        );

                    const isUser =
                        /user|usuario|name/i.test(
                            (input.name || '') + ' ' + (input.id || '')
                        );

                    const icon = document.createElement('span');

                    icon.className =
                        'pointer-events-none absolute left-4 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center text-[#8c9a90]';

                    icon.innerHTML = isPassword
                        ? icons.password
                        : isEmail
                            ? icons.email
                            : icons.user;

                    icon.querySelector('svg').classList.add(
                        'h-[19px]',
                        'w-[19px]'
                    );

                    wrapper.prepend(icon);

                    // BOTÓN PARA VISUALIZAR CONTRASEÑA
                    if (isPassword) {

                        const toggle = document.createElement('button');

                        toggle.type = 'button';

                        toggle.className = [
                            'absolute',
                            'right-3',
                            'top-1/2',
                            '-translate-y-1/2',
                            'grid',
                            'h-9',
                            'w-9',
                            'place-items-center',
                            'rounded-lg',
                            'text-[#829087]',
                            'transition-colors',
                            'duration-200',
                            'hover:bg-[#edf3ed]',
                            'hover:text-[#17392f]',
                            'focus-visible:outline-none',
                            'focus-visible:ring-2',
                            'focus-visible:ring-[#245a46]'
                        ].join(' ');

                        toggle.innerHTML = icons.eye;

                        toggle.setAttribute(
                            'aria-label',
                            'Mostrar contraseña'
                        );

                        toggle.setAttribute('aria-pressed', 'false');

                        toggle.addEventListener('click', function() {

                            const showing = input.type === 'password';

                            input.type = showing ? 'text' : 'password';

                            toggle.innerHTML = showing
                                ? icons.eyeOff
                                : icons.eye;

                            toggle.setAttribute(
                                'aria-label',
                                showing
                                    ? 'Ocultar contraseña'
                                    : 'Mostrar contraseña'
                            );

                            toggle.setAttribute(
                                'aria-pressed',
                                String(showing)
                            );

                        });

                        toggle.querySelector('svg').classList.add(
                            'h-[19px]',
                            'w-[19px]'
                        );

                        wrapper.appendChild(toggle);

                    }

                });

                // BOTONES DE ENVÍO
                card.querySelectorAll(
                    'button[type="submit"], input[type="submit"]'
                ).forEach(button => {

                    button.classList.add(
                        '!flex',
                        '!min-h-[55px]',
                        '!w-full',
                        '!items-center',
                        '!justify-center',
                        '!rounded-xl',
                        '!border-0',
                        '!bg-gradient-to-r',
                        '!from-[#245a46]',
                        '!to-[#17392f]',
                        '!px-5',
                        '!py-3',
                        '!text-sm',
                        '!font-extrabold',
                        '!tracking-wide',
                        '!text-white',
                        '!shadow-lg',
                        'transition-all',
                        'duration-300',
                        'hover:-translate-y-0.5',
                        'hover:!brightness-110',
                        'hover:!shadow-xl',
                        'active:!scale-[.985]',
                        'disabled:!cursor-wait',
                        'disabled:!opacity-70'
                    );

                });

                // ENLACES
                card.querySelectorAll('a').forEach(link => {

                    link.classList.add(
                        'font-semibold',
                        'text-[#245a46]',
                        'no-underline',
                        'transition-colors',
                        'duration-200',
                        'hover:text-[#b17c43]'
                    );

                });

                // ERRORES
                card.querySelectorAll(
                    '.text-danger, .invalid-feedback, [role="alert"]'
                ).forEach(error => {

                    error.classList.add(
                        'mt-2',
                        'block',
                        'text-xs',
                        'leading-relaxed',
                        'text-red-600'
                    );

                });

            }

        });
    </script>

</body>
</html>
