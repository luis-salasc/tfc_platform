<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kiosco | {{ $organization->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        :root { --ink:#070908; --blue:#1556df; --paper:#f5f4ed; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--ink); color:var(--paper); font-family:'TFC Manrope',Arial,sans-serif; }
        main { min-height:100vh; min-height:100dvh; padding:clamp(14px,4vw,56px); display:grid; place-items:center; background:linear-gradient(135deg,rgba(21,86,223,.16),transparent 42%),repeating-linear-gradient(135deg,transparent 0 29px,rgba(255,255,255,.025) 30px); }
        .shell { width:min(1280px,100%); min-height:min(720px,calc(100vh - 80px)); display:grid; grid-template-columns:.88fr 1.12fr; overflow:hidden; border:1px solid rgba(255,255,255,.28); box-shadow:12px 12px 0 var(--blue); }
        .brand,.scanner { padding:clamp(28px,5vw,70px); }
        .brand { display:flex; flex-direction:column; justify-content:space-between; border-right:1px solid rgba(255,255,255,.18); }
        .logo { font:400 clamp(32px,3vw,46px)/.73 'TFC Syne',Arial Black,sans-serif; letter-spacing:-.08em; }
        .logo small,.overline,.foot { display:block; color:var(--blue); font:800 11px 'TFC Manrope',Arial,sans-serif; letter-spacing:.18em; text-transform:uppercase; }
        .logo small { margin-bottom:8px; }
        .brand h1,h2 { margin:42px 0 20px; font:400 clamp(56px,7vw,105px)/.78 'TFC Syne',Arial Black,sans-serif; letter-spacing:-.07em; text-transform:uppercase; }
        .brand h1 em { font-style:normal; color:var(--blue); }
        .brand p { max-width:350px; color:#c5c6be; font-size:17px; line-height:1.55; }
        .scanner { display:grid; place-items:center; background:#171914; }
        .panel { width:min(620px,100%); min-height:490px; padding:clamp(18px,4cqi,28px); display:grid; align-content:center; justify-items:center; gap:clamp(8px,1.8vh,16px); container-type:inline-size; text-align:center; overflow:hidden; border:1px solid transparent; background:transparent; transition:background .25s,border-color .25s; }
        .mark { width:108px; height:108px; margin:auto; display:grid; place-items:center; color:var(--blue); }
        .mark svg { width:100%; height:100%; overflow:visible; }
        .mark::after { content:none; }
        .success .mark { color:#75edaf; }.warning .mark { color:#ffd34e; }.error .mark { color:#ff9a9a; }.duplicate .mark { color:#c6cae0; }
        .overline { margin:0; color:#c5c6be; font-size:clamp(10px,2.2cqi,12px); line-height:1.3; }
        .panel h2 { width:100%; max-width:100%; margin:0; font-size:clamp(30px,9cqi,58px); line-height:.92; overflow-wrap:break-word; text-wrap:balance; }
        .panel.title-long h2 { font-size:clamp(28px,7.6cqi,48px); line-height:.95; }
        .panel.title-extra-long h2 { font-size:clamp(25px,6.7cqi,42px); line-height:1; }
        #message { width:min(100%,520px); min-height:0; margin:0; color:#c5c6be; font-size:clamp(16px,3.7cqi,22px); line-height:1.4; text-wrap:balance; }
        .status { margin-top:4px; font-size:clamp(10px,2.2cqi,12px); font-weight:800; letter-spacing:.1em; line-height:1.35; text-transform:uppercase; }
        .status::before { content:''; display:inline-block; width:8px; height:8px; margin-right:9px; border-radius:50%; background:var(--blue); box-shadow:0 0 0 6px rgba(21,86,223,.16); }
        .success { background:#103f36; border-color:#35d08d; }
        .celebration { background:#124fc8; border-color:#84acff; }
        .warning { background:#4b3710; border-color:#ffd34e; }
        .error { background:#511b22; border-color:#ff7777; }
        .duplicate { background:#24283a; border-color:#b8bbca; }
        .capture { position:fixed; left:-10000px; opacity:0; }
        main.is-celebration { padding:0; background:linear-gradient(135deg,#0a2869,#1556df 55%,#07183f); }
        .is-celebration .shell { width:100%; min-height:100vh; min-height:100dvh; grid-template-columns:1fr; border:0; box-shadow:none; }
        .is-celebration .brand { display:none; }
        .is-celebration .scanner { min-height:100vh; min-height:100dvh; padding:0; background:transparent; }
        .is-celebration .panel { width:100%; min-height:100vh; min-height:100dvh; height:100%; padding:clamp(18px,4vw,72px); align-content:space-evenly; border:0; background:transparent; }
        .is-celebration .panel h2 { max-width:min(1180px,100%); margin:0; font-size:clamp(38px,min(7vw,12vh),108px); line-height:.9; }
        .is-celebration .panel.title-long h2 { font-size:clamp(34px,min(6vw,10vh),88px); }
        .is-celebration .panel.title-extra-long h2 { font-size:clamp(30px,min(5.2vw,8.5vh),72px); }
        .celebration-emoji { font-size:clamp(38px,5vw,76px); line-height:1; letter-spacing:.18em; }
        .is-celebration #message { min-height:0; font-size:clamp(19px,2.6vw,32px); }
        .is-celebration .mark { width:clamp(76px,10vw,132px); height:clamp(76px,10vw,132px); color:#d9e4ff; }
        @media (max-width:760px) {
            main { padding:14px; }
            .shell { min-height:calc(100vh - 28px); min-height:calc(100dvh - 28px); grid-template-columns:1fr; box-shadow:7px 7px 0 var(--blue); }
            .brand { display:none; }
            .scanner { min-height:calc(100vh - 28px); min-height:calc(100dvh - 28px); padding:clamp(14px,4vw,24px); }
            .panel { min-height:0; height:min(650px,calc(100vh - 64px)); height:min(650px,calc(100dvh - 64px)); }
            .mark { width:clamp(74px,20vw,98px); height:clamp(74px,20vw,98px); }
        }
        @media (min-width:761px) and (max-height:700px) {
            main { padding:28px; }
            .shell { min-height:calc(100vh - 56px); }
            .brand,.scanner { padding:28px clamp(28px,5vw,56px); }
            .brand h1 { margin:24px 0 14px; font-size:clamp(48px,6vw,72px); }
            .panel { min-height:0; height:min(490px,calc(100vh - 112px)); padding:18px; gap:8px; }
            .panel h2 { font-size:clamp(29px,8cqi,48px); }
            .panel.title-long h2 { font-size:clamp(26px,6.8cqi,40px); }
            .panel.title-extra-long h2 { font-size:clamp(23px,6cqi,35px); }
            .mark { width:82px; height:82px; }
            .mark::after { font-size:42px; }
            #message { font-size:clamp(15px,3.2cqi,18px); }
        }
        @media (max-width:560px) {
            .panel { padding:clamp(14px,4vw,20px); gap:clamp(7px,1.5vh,12px); }
            .panel h2 { font-size:clamp(28px,9cqi,44px); }
            .panel.title-long h2 { font-size:clamp(25px,7.5cqi,37px); }
            .panel.title-extra-long h2 { font-size:clamp(22px,6.5cqi,32px); }
            #message { font-size:clamp(15px,4cqi,19px); }
            .status { font-size:10px; }
        }
        @media (orientation:portrait) and (min-width:761px) and (max-width:900px) {
            main { padding:24px; }
            .shell { min-height:calc(100dvh - 48px); grid-template-columns:1fr; }
            .brand { display:none; }
            .scanner { min-height:calc(100dvh - 48px); padding:32px; }
        }
    </style>
</head>
<body>
    <main>
        <div class="shell">
            <aside class="brand">
                <div>
                    <div class="logo"><small>THE</small>FITNESS<br>CLUB</div>
                    <h1>Tu <em>entreno.</em><br>Tu momento.</h1>
                    <p>Acerca tu código QR al lector para confirmar tu entrada.</p>
                </div>
                <span class="foot">Kiosco de acceso · listo para escanear</span>
            </aside>
            <section class="scanner">
                <div id="state" class="panel">
                    <div class="mark" aria-hidden="true">
                        <svg viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="35" y="7" width="43" height="61" rx="7" stroke="currentColor" stroke-width="2"/>
                            <path d="M49 61h15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <rect x="43" y="17" width="9" height="9" rx="1" stroke="currentColor" stroke-width="2"/>
                            <rect x="61" y="17" width="9" height="9" rx="1" stroke="currentColor" stroke-width="2"/>
                            <rect x="43" y="35" width="9" height="9" rx="1" stroke="currentColor" stroke-width="2"/>
                            <path d="M60 35h4v4h6v6h-5v-3h-5v-7Z" fill="currentColor"/>
                            <path d="M11 61c5-5 10-4 14 0l8 8V48c0-3 2-5 5-5s5 2 5 5v10-6c0-3 2-5 5-5s5 2 5 5v7-4c0-3 2-5 5-5s5 2 5 5v6-2c0-3 2-5 5-5s5 2 5 5v10c0 11-8 20-19 20H39c-6 0-11-2-15-6L11 70c-3-3-3-6 0-9Z" fill="var(--ink)" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M22 28h7M18 35h11M22 42h7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <p id="eyebrow" class="overline">Registro de entrada</p>
                    <h2 id="title">Escanea<br>tu QR</h2>
                    <div id="celebration-emoji" class="celebration-emoji" hidden aria-hidden="true"></div>
                    <p id="message">Acerca tu código al lector.</p>
                    <span id="status" class="status">Esperando lectura</span>
                </div>
                <input id="capture" class="capture" autocomplete="off">
            </section>
        </div>
    </main>
    <script>
        const screen = document.querySelector('main');
        const capture = document.querySelector('#capture');
        const state = document.querySelector('#state');
        const title = document.querySelector('#title');
        const message = document.querySelector('#message');
        const eyebrow = document.querySelector('#eyebrow');
        const status = document.querySelector('#status');
        const celebrationEmoji = document.querySelector('#celebration-emoji');
        let reset;

        const home = () => {
            screen.classList.remove('is-celebration');
            state.className = 'panel';
            eyebrow.textContent = 'Registro de entrada';
            title.innerHTML = 'Escanea<br>tu QR';
            message.textContent = 'Acerca tu código al lector.';
            status.textContent = 'Esperando lectura';
            celebrationEmoji.hidden = true;
            celebrationEmoji.textContent = '';
            capture.value = '';
            capture.focus();
        };

        const show = (response) => {
            clearTimeout(reset);
            const result = response?.state ? response : {
                state: 'error',
                title: 'Entrada no confirmada',
                message: 'No se ha podido procesar el código. Avisa a tu entrenador.',
            };
            screen.classList.toggle('is-celebration', result.state === 'celebration');
            state.className = `panel ${result.state}`;
            const titleLength = [...String(result.title).replace(/\s+/g, '')].length;
            state.classList.toggle('title-long', titleLength > 18);
            state.classList.toggle('title-extra-long', titleLength > 28);
            eyebrow.textContent = result.state === 'error' ? 'Necesitamos ayuda' : 'Registro de asistencia';
            title.textContent = result.title;
            message.textContent = result.message;
            status.textContent = result.state === 'error' ? 'Entrada no confirmada' : 'Lectura procesada';
            celebrationEmoji.hidden = result.state !== 'celebration';
            celebrationEmoji.textContent = result.state === 'celebration'
                ? `${String.fromCodePoint(0x1F973)} ${String.fromCodePoint(0x1F382)}`
                : '';
            reset = setTimeout(home, result.state === 'error' ? 15000 : 8000);
        };

        capture.addEventListener('change', async () => {
            const code = capture.value.trim();
            capture.value = '';
            capture.focus();

            if (!code) return;

            try {
                const response = await fetch('{{ route('kiosk.store', $organization) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ code }),
                });
                show(await response.json());
            } catch (_) {
                show();
            }
        });

        document.addEventListener('click', () => capture.focus());
        home();
    </script>
</body>
</html>
