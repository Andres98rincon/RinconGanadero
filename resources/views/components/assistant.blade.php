{{--
    Asistente del hato: botón flotante + chat. Responde con los datos de la app (sin IA externa).
    Estilos y script van aquí para que funcione sin recompilar los assets.
--}}
<div class="hx-assistant" data-assistant data-ask-url="{{ route('assistant.ask') }}" data-user="{{ auth()->id() }}" data-user-name="{{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}">
    <button type="button" class="hx-fab" data-assistant-toggle aria-label="Asistente del hato: pregúntale a tu hato" title="Asistente del hato" aria-haspopup="dialog" aria-expanded="false" aria-controls="hx-assistant-panel">
        <span class="hx-fab-ring" aria-hidden="true"></span>
        <svg class="hx-fab-icon hx-fab-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.8 7L4 20.5l1.5-4.7A8 8 0 1 1 21 12Z"/><path d="M8.5 10.5h.01M12 10.5h.01M15.5 10.5h.01"/></svg>
        <svg class="hx-fab-icon hx-fab-close" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        <span class="hx-fab-label">Pregúntale a tu hato</span>
    </button>

    <section class="hx-panel" id="hx-assistant-panel" role="dialog" aria-modal="false" aria-labelledby="hx-assistant-title" hidden>
        <header class="hx-head">
            <span class="hx-avatar" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="M19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/></svg>
            </span>
            <div class="hx-head-text">
                <strong id="hx-assistant-title">Asistente del hato</strong>
                <small><span class="hx-online-dot" aria-hidden="true"></span> Responde con los datos de tus fincas</small>
            </div>
            <button type="button" class="hx-icon-btn" data-assistant-clear aria-label="Borrar conversación" title="Borrar conversación">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3"/></svg>
            </button>
            <button type="button" class="hx-icon-btn" data-assistant-close aria-label="Cerrar asistente" title="Cerrar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="hx-log" data-assistant-log role="log" aria-live="polite" aria-relevant="additions"></div>

        <div class="hx-chips" data-assistant-chips aria-label="Preguntas sugeridas"></div>

        <form class="hx-form" data-assistant-form autocomplete="off">
            <label class="hx-visually-hidden" for="hx-assistant-input">Escribe tu pregunta</label>
            <textarea id="hx-assistant-input" rows="1" maxlength="300" placeholder="Ej.: ¿cuántas vacas tengo?" data-assistant-input data-voice-ready="true"></textarea>
            <button type="button" class="hx-icon-btn hx-mic" data-assistant-mic aria-label="Dictar pregunta" title="Dictar pregunta" hidden>
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
            </button>
            <button type="submit" class="hx-send" aria-label="Enviar pregunta" disabled>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
            </button>
        </form>
        <p class="hx-foot">Solo consulta la información de esta aplicación. No se guardan tus preguntas.</p>
    </section>
</div>

<style>
    .hx-assistant { --hx-forest: #17392f; --hx-green: #245a46; --hx-leaf: #3f8b65; --hx-gold: #e9b779; --hx-ink: #1d2b24; --hx-muted: #6f7d74; --hx-line: #e3e9e1; --hx-paper: #fff; --hx-bg: #f6f8f4; font-family: inherit; }
    html[data-theme="dark"] .hx-assistant { --hx-ink: #e5eee8; --hx-muted: #aabbb0; --hx-line: #30463c; --hx-paper: #17251f; --hx-bg: #101a16; }
    .hx-visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

    .hx-fab { position: fixed; right: 24px; bottom: 24px; z-index: 950; display: inline-flex; height: 58px; min-width: 58px; align-items: center; gap: 10px; padding: 0 17px; border: 0; border-radius: 999px; background: linear-gradient(135deg, #2b6a51, var(--hx-forest)); color: #fff; font: inherit; font-size: 14px; font-weight: 700; cursor: pointer; box-shadow: 0 16px 34px -12px rgb(16 40 31 / 65%), 0 0 0 1px rgb(255 255 255 / 8%) inset; transition: transform .25s cubic-bezier(.16, 1, .3, 1), box-shadow .25s ease, padding .25s ease; animation: hx-pop .6s cubic-bezier(.16, 1, .3, 1) .6s both; }
    .hx-fab:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 20px 40px -12px rgb(16 40 31 / 70%); }
    .hx-fab:active { transform: scale(.96); }
    .hx-fab:focus-visible { outline: 3px solid var(--hx-gold); outline-offset: 3px; }
    .hx-fab-icon { width: 24px; height: 24px; flex: none; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
    .hx-fab-open { stroke: var(--hx-gold); }
    .hx-fab-close { display: none; }
    .hx-fab-ring { position: absolute; inset: 0; border-radius: inherit; box-shadow: 0 0 0 0 rgb(233 183 121 / 55%); animation: hx-ring 2.6s ease-out 1.4s 3; pointer-events: none; }
    .hx-assistant.is-open .hx-fab { padding: 0; width: 58px; justify-content: center; }
    .hx-assistant.is-open .hx-fab-label, .hx-assistant.is-open .hx-fab-open, .hx-assistant.is-open .hx-fab-ring { display: none; }
    .hx-assistant.is-open .hx-fab-close { display: block; }
    .hx-assistant.is-used .hx-fab-label { display: none; }
    .hx-assistant.is-used .hx-fab { padding: 0; width: 58px; justify-content: center; }

    .hx-panel { position: fixed; right: 24px; bottom: 96px; z-index: 949; display: flex; width: min(400px, calc(100vw - 32px)); height: min(620px, calc(100vh - 130px)); flex-direction: column; overflow: hidden; border: 1px solid var(--hx-line); border-radius: 22px; background: var(--hx-bg); color: var(--hx-ink); box-shadow: 0 30px 70px -20px rgb(10 30 23 / 45%); transform-origin: bottom right; animation: hx-open .35s cubic-bezier(.16, 1, .3, 1) both; }
    .hx-panel[hidden] { display: none; }
    .hx-head { display: flex; align-items: center; gap: 11px; padding: 14px 12px 14px 16px; background: linear-gradient(135deg, #1f4d3d, var(--hx-forest)); color: #fff; }
    .hx-avatar { display: grid; width: 40px; height: 40px; flex: none; place-items: center; border-radius: 13px; background: rgb(255 255 255 / 12%); }
    .hx-avatar svg { width: 22px; height: 22px; fill: var(--hx-gold); }
    .hx-head-text { display: grid; flex: 1; min-width: 0; gap: 2px; }
    .hx-head-text strong { font-size: 15px; }
    .hx-head-text small { display: inline-flex; align-items: center; gap: 6px; color: rgb(255 255 255 / 75%); font-size: 11.5px; }
    .hx-online-dot { width: 7px; height: 7px; border-radius: 50%; background: #7ee2a0; box-shadow: 0 0 0 3px rgb(126 226 160 / 22%); }
    .hx-icon-btn { display: grid; width: 36px; height: 36px; flex: none; place-items: center; padding: 0; border: 0; border-radius: 10px; background: transparent; color: inherit; cursor: pointer; transition: background .15s ease; }
    .hx-icon-btn:hover { background: rgb(255 255 255 / 12%); }
    .hx-icon-btn svg { width: 19px; height: 19px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

    .hx-log { display: flex; flex: 1; flex-direction: column; gap: 12px; padding: 16px; overflow-y: auto; overscroll-behavior: contain; scroll-behavior: smooth; }
    .hx-msg { max-width: 88%; animation: hx-msg .35s cubic-bezier(.16, 1, .3, 1) both; }
    .hx-msg-user { align-self: flex-end; padding: 10px 14px; border-radius: 18px 18px 5px 18px; background: linear-gradient(135deg, #2b6a51, var(--hx-forest)); color: #fff; font-size: 14px; line-height: 1.45; white-space: pre-wrap; overflow-wrap: anywhere; }
    .hx-msg-bot { align-self: flex-start; display: grid; gap: 10px; padding: 12px 14px; border: 1px solid var(--hx-line); border-radius: 18px 18px 18px 5px; background: var(--hx-paper); font-size: 14px; line-height: 1.5; box-shadow: 0 6px 16px -12px rgb(16 40 31 / 40%); }
    .hx-msg-bot p { margin: 0; overflow-wrap: anywhere; }
    .hx-msg-error { border-color: #f1d1c7; background: #fff4f0; color: #9c3f2a; }
    html[data-theme="dark"] .hx-msg-error { border-color: #6e3b35; background: #321d1a; color: #f0aaa0; }
    .hx-items { display: grid; gap: 0; margin: 0; padding: 0; list-style: none; border: 1px solid var(--hx-line); border-radius: 12px; overflow: hidden; }
    .hx-items li { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 11px; font-size: 13px; }
    .hx-items li + li { border-top: 1px solid var(--hx-line); }
    .hx-items li:nth-child(even) { background: color-mix(in srgb, var(--hx-bg) 70%, transparent); }
    .hx-items span:first-child { color: var(--hx-muted); }
    .hx-items strong { color: var(--hx-ink); text-align: right; white-space: nowrap; }
    .hx-items a { color: var(--hx-green); font-weight: 600; text-decoration: none; }
    html[data-theme="dark"] .hx-items a { color: #9fdcb1; }
    .hx-items a:hover { text-decoration: underline; }
    .hx-link { display: inline-flex; width: fit-content; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; background: #eaf3e8; color: var(--hx-green); font-size: 13px; font-weight: 700; text-decoration: none; transition: background .15s ease, transform .15s ease; }
    .hx-link:hover { background: #dcecd9; transform: translateX(2px); }
    html[data-theme="dark"] .hx-link { background: #244535; color: #9fdcb1; }
    .hx-typing { display: inline-flex; gap: 5px; padding: 14px 16px; }
    .hx-typing span { width: 7px; height: 7px; border-radius: 50%; background: var(--hx-leaf); animation: hx-dot 1.1s ease-in-out infinite; }
    .hx-typing span:nth-child(2) { animation-delay: .15s; }
    .hx-typing span:nth-child(3) { animation-delay: .3s; }

    .hx-chips { display: flex; gap: 7px; padding: 0 14px 10px; overflow-x: auto; scrollbar-width: none; }
    .hx-chips::-webkit-scrollbar { display: none; }
    .hx-chips:empty { display: none; }
    .hx-chip { flex: none; padding: 7px 12px; border: 1px solid #cfe1cc; border-radius: 999px; background: var(--hx-paper); color: var(--hx-green); font: inherit; font-size: 12.5px; font-weight: 600; white-space: nowrap; cursor: pointer; transition: background .15s ease, border-color .15s ease, transform .15s ease; }
    .hx-chip:hover { border-color: var(--hx-leaf); background: #eef6ec; transform: translateY(-1px); }
    html[data-theme="dark"] .hx-chip { border-color: #3c624d; color: #9fdcb1; }
    html[data-theme="dark"] .hx-chip:hover { background: #1d3028; }

    .hx-form { display: flex; align-items: flex-end; gap: 6px; margin: 0 12px; padding: 6px 6px 6px 14px; border: 1.5px solid var(--hx-line); border-radius: 18px; background: var(--hx-paper); transition: border-color .2s ease, box-shadow .2s ease; }
    .hx-form:focus-within { border-color: var(--hx-leaf); box-shadow: 0 0 0 4px rgb(63 139 101 / 13%); }
    .hx-form textarea { flex: 1; min-height: 36px; max-height: 110px; padding: 8px 0; border: 0; background: transparent; color: var(--hx-ink); font: inherit; font-size: 15px; line-height: 1.35; resize: none; outline: none; }
    .hx-form textarea::placeholder { color: #9aa69e; }
    .hx-form .hx-icon-btn { color: var(--hx-muted); }
    .hx-form .hx-icon-btn:hover { background: #eef3ed; color: var(--hx-green); }
    .hx-mic.is-listening { background: #fde8e4; color: #c0392b; animation: hx-pulse 1.2s ease-in-out infinite; }
    .hx-send { display: grid; width: 40px; height: 40px; flex: none; place-items: center; border: 0; border-radius: 13px; background: linear-gradient(135deg, #2b6a51, var(--hx-forest)); color: #fff; cursor: pointer; transition: transform .15s ease, opacity .15s ease; }
    .hx-send:disabled { cursor: default; opacity: .4; }
    .hx-send:not(:disabled):hover { transform: scale(1.06); }
    .hx-send svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
    .hx-foot { margin: 0; padding: 8px 16px 12px; color: var(--hx-muted); font-size: 10.5px; text-align: center; }

    @keyframes hx-pop { from { transform: translateY(20px) scale(.8); } }
    @keyframes hx-ring { 0% { box-shadow: 0 0 0 0 rgb(233 183 121 / 55%); } 100% { box-shadow: 0 0 0 18px rgb(233 183 121 / 0%); } }
    @keyframes hx-open { from { opacity: 0; transform: translateY(16px) scale(.94); } }
    @keyframes hx-msg { from { opacity: 0; transform: translateY(8px); } }
    @keyframes hx-dot { 0%, 80%, 100% { opacity: .3; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-4px); } }
    @keyframes hx-pulse { 50% { box-shadow: 0 0 0 6px rgb(192 57 43 / 15%); } }

    /* Celular: encima de la barra inferior y chat a pantalla completa */
    @media (max-width: 760px) {
        .hx-fab { right: 14px; bottom: calc(82px + env(safe-area-inset-bottom)); height: 54px; min-width: 54px; padding: 0; width: 54px; justify-content: center; }
        .hx-fab-label { display: none; }
        .hx-assistant.is-open .hx-fab { display: none; }
        .hx-panel { inset: 0; width: 100%; height: 100%; height: 100dvh; border: 0; border-radius: 0; animation-name: hx-sheet; z-index: 1100; }
        .hx-head { padding-top: calc(12px + env(safe-area-inset-top)); }
        .hx-form { margin-bottom: 0; }
        .hx-foot { padding-bottom: calc(10px + env(safe-area-inset-bottom)); }
        .hx-msg { max-width: 92%; }
        @keyframes hx-sheet { from { transform: translateY(100%); } }
    }
    @media (prefers-reduced-motion: reduce) {
        .hx-assistant *, .hx-fab, .hx-panel { animation: none !important; transition: none !important; }
    }
    @media print { .hx-assistant { display: none; } }
</style>

<script>
    (() => {
        const root = document.querySelector('[data-assistant]');
        if (!root || root.dataset.ready) return;
        root.dataset.ready = 'true';

        const panel = root.querySelector('.hx-panel');
        const toggle = root.querySelector('[data-assistant-toggle]');
        const log = root.querySelector('[data-assistant-log]');
        const chips = root.querySelector('[data-assistant-chips]');
        const form = root.querySelector('[data-assistant-form]');
        const input = root.querySelector('[data-assistant-input]');
        const send = form.querySelector('.hx-send');
        const mic = root.querySelector('[data-assistant-mic]');
        const storageKey = `hato-assistant-${root.dataset.user}`;
        const usedKey = `hato-assistant-used-${root.dataset.user}`;
        const firstName = root.dataset.userName || '';
        let history = [];
        let busy = false;

        const storage = {
            get(key, fallback) { try { return JSON.parse(sessionStorage.getItem(key)) ?? fallback; } catch { return fallback; } },
            set(key, value) { try { sessionStorage.setItem(key, JSON.stringify(value)); } catch { /* almacenamiento no disponible */ } },
            remove(key) { try { sessionStorage.removeItem(key); } catch { /* almacenamiento no disponible */ } },
        };
        try { if (localStorage.getItem(usedKey)) root.classList.add('is-used'); } catch { /* sin almacenamiento */ }

        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
        const isSafeUrl = (url) => { try { return new URL(url, window.location.href).origin === window.location.origin; } catch { return false; } };
        const el = (tag, className, text) => { const node = document.createElement(tag); if (className) node.className = className; if (text !== undefined) node.textContent = text; return node; };
        const scrollToEnd = () => { log.scrollTop = log.scrollHeight; };

        const renderUser = (text) => { log.append(el('div', 'hx-msg hx-msg-user', text)); scrollToEnd(); };

        const renderBot = (data, isError = false) => {
            const bubble = el('div', `hx-msg hx-msg-bot${isError ? ' hx-msg-error' : ''}`);
            bubble.append(el('p', '', data.answer));

            if (Array.isArray(data.items) && data.items.length) {
                const list = el('ul', 'hx-items');
                data.items.forEach((item) => {
                    const row = el('li');
                    if (item.url && isSafeUrl(item.url)) {
                        const anchor = el('a', '', item.label);
                        anchor.href = item.url;
                        row.append(anchor);
                    } else {
                        row.append(el('span', '', item.label));
                    }
                    row.append(el('strong', '', item.value));
                    list.append(row);
                });
                bubble.append(list);
            }

            if (data.link?.url && isSafeUrl(data.link.url)) {
                const link = el('a', 'hx-link', `${data.link.label} →`);
                link.href = data.link.url;
                bubble.append(link);
            }

            log.append(bubble);
            renderChips(data.suggestions || []);
            scrollToEnd();
        };

        const renderChips = (suggestions) => {
            chips.replaceChildren(...suggestions.slice(0, 5).map((question) => {
                const chip = el('button', 'hx-chip', question);
                chip.type = 'button';
                chip.addEventListener('click', () => ask(question));
                return chip;
            }));
        };

        const save = () => storage.set(storageKey, history.slice(-30));

        const restore = () => {
            history = storage.get(storageKey, []);
            log.replaceChildren();
            if (!history.length) {
                welcome();
                return;
            }
            history.forEach((entry) => entry.role === 'user' ? renderUser(entry.text) : renderBot(entry.data, entry.error));
        };

        const welcome = () => renderBot({
            answer: `¡Hola${firstName ? ', ' + firstName : ''}! 👋 Soy el asistente de tu hato. Pregúntame lo que necesites saber de tus animales, la leche, los pesajes, la reproducción o las finanzas.`,
            suggestions: ['¿Cuántos animales tengo?', '¿Cuánta leche se produjo esta semana?', '¿Qué tengo pendiente?', '¿Qué animales no se han pesado?', '¿Cómo registro un pesaje?'],
        });

        const showTyping = () => {
            const typing = el('div', 'hx-msg hx-msg-bot hx-typing');
            typing.setAttribute('aria-label', 'El asistente está escribiendo');
            typing.append(el('span'), el('span'), el('span'));
            log.append(typing);
            scrollToEnd();
            return typing;
        };

        const ask = async (question) => {
            const text = question.trim().slice(0, 300);
            if (!text || busy) return;

            busy = true;
            input.value = '';
            autoSize();
            renderUser(text);
            history.push({ role: 'user', text });
            chips.replaceChildren();
            const typing = showTyping();
            const startedAt = performance.now();

            let data;
            let isError = false;
            if (!navigator.onLine) {
                data = { answer: 'Estás sin conexión. Necesito internet para consultar los datos; vuelve a preguntarme cuando se recupere la señal.' };
                isError = true;
            } else {
                try {
                    const response = await fetch(root.dataset.askUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                        body: JSON.stringify({ message: text }),
                    });

                    if (response.status === 429) throw new Error('Estás preguntando muy rápido. Espera un momento y vuelve a intentarlo.');
                    if (response.status === 401 || response.status === 419) throw new Error('Tu sesión expiró. Recarga la página para seguir usando el asistente.');
                    const body = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(body.errors?.message?.[0] || 'No pude responder en este momento. Inténtalo de nuevo.');
                    data = body;
                } catch (error) {
                    data = { answer: error.message && !error.message.startsWith('Failed') ? error.message : 'No pude conectarme con el servidor. Revisa tu conexión e inténtalo de nuevo.' };
                    isError = true;
                }
            }

            const wait = Math.max(0, 450 - (performance.now() - startedAt));
            window.setTimeout(() => {
                typing.remove();
                renderBot(data, isError);
                history.push({ role: 'bot', data, error: isError });
                save();
                busy = false;
                updateSend();
                if (window.matchMedia('(hover: hover)').matches) input.focus();
            }, wait);
        };

        const open = () => {
            panel.hidden = false;
            root.classList.add('is-open', 'is-used');
            toggle.setAttribute('aria-expanded', 'true');
            try { localStorage.setItem(usedKey, '1'); } catch { /* sin almacenamiento */ }
            if (!log.childElementCount) restore();
            scrollToEnd();
            window.setTimeout(() => input.focus(), 60);
        };

        const close = () => {
            panel.hidden = true;
            root.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        };

        const autoSize = () => { input.style.height = 'auto'; input.style.height = `${Math.min(input.scrollHeight, 110)}px`; };
        const updateSend = () => { send.disabled = busy || input.value.trim() === ''; };

        toggle.addEventListener('click', () => (panel.hidden ? open() : close()));
        root.querySelector('[data-assistant-close]').addEventListener('click', close);
        root.querySelector('[data-assistant-clear]').addEventListener('click', () => { history = []; storage.remove(storageKey); log.replaceChildren(); welcome(); input.focus(); });
        form.addEventListener('submit', (event) => { event.preventDefault(); ask(input.value); });
        input.addEventListener('input', () => { autoSize(); updateSend(); });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                ask(input.value);
            }
        });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !panel.hidden) close(); });

        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (Recognition && window.isSecureContext) {
            mic.hidden = false;
            let recognition = null;
            mic.addEventListener('click', () => {
                if (recognition) { recognition.stop(); return; }
                recognition = new Recognition();
                recognition.lang = 'es-CO';
                recognition.interimResults = true;
                recognition.onresult = (event) => {
                    input.value = [...event.results].map((result) => result[0].transcript).join(' ');
                    autoSize();
                    updateSend();
                    if (event.results[event.results.length - 1].isFinal) ask(input.value);
                };
                recognition.onend = () => { recognition = null; mic.classList.remove('is-listening'); };
                recognition.onerror = () => { recognition = null; mic.classList.remove('is-listening'); };
                mic.classList.add('is-listening');
                recognition.start();
            });
        }
    })();
</script>
