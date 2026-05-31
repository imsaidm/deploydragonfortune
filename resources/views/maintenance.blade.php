<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>DragonFortune.ai Maintenance</title>
    <style>
        :root {
            color-scheme: light;
            --page: #dbe8ee;
            --surface: #ffffff;
            --text: #333640;
            --muted: #64748b;
            --border: #e2e8f0;
            --blue: #0d6efd;
            --blue-soft: #9ee6ff;
            --green: #22c55e;
            --green-dark: #16823f;
            --shadow: 0 28px 60px rgba(15, 23, 42, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: clamp(22px, 5vw, 70px);
            background: var(--page);
            color: var(--text);
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .maintenance-card {
            width: min(100%, 1110px);
            min-height: min(78vh, 720px);
            display: grid;
            grid-template-rows: 1fr auto;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .content {
            min-height: 520px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: clamp(42px, 7vw, 74px) clamp(22px, 5vw, 56px) 34px;
            text-align: center;
            overflow: hidden;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: clamp(74px, 8vw, 110px);
            color: var(--text);
            font-size: 21px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0;
        }

        .brand-mark {
            width: 20px;
            height: 20px;
            display: inline-grid;
            place-items: center;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--green), #14b8a6 52%, var(--blue) 53%, var(--blue));
            color: #ffffff;
        }

        h1 {
            max-width: 650px;
            margin: 0;
            color: var(--text);
            font-size: clamp(34px, 5vw, 50px);
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: 0;
        }

        .message {
            max-width: 470px;
            margin: 24px auto 0;
            color: var(--muted);
            font-size: clamp(13px, 1.8vw, 16px);
            line-height: 1.45;
        }

        .plug-scene {
            width: min(1180px, calc(100vw - 44px));
            max-width: none;
            margin-top: 48px;
            margin-inline: calc((min(100%, 1110px) - min(1180px, calc(100vw - 44px))) / 2);
            display: block;
        }

        .footer {
            min-height: 66px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(14px, 2.5vw, 34px);
            padding: 14px 22px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
            text-align: center;
        }

        .footer strong {
            color: var(--text);
            font-weight: 600;
        }

        .footer-link {
            color: var(--text);
            font-weight: 600;
        }

        .socials {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .social {
            width: 24px;
            height: 24px;
            display: inline-grid;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 50%;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 700;
        }

        @media (max-width: 760px) {
            body {
                padding: 18px;
            }

            .maintenance-card {
                min-height: calc(100vh - 36px);
            }

            .content {
                min-height: 560px;
            }

            .brand {
                margin-bottom: 58px;
            }

            .plug-scene {
                width: 860px;
                margin-top: 42px;
                transform: translateX(-22px);
            }

            .footer {
                align-items: center;
                flex-direction: column;
                gap: 6px;
            }
        }

        @media (max-width: 480px) {
            .content {
                min-height: 610px;
                padding-inline: 18px;
            }

            .brand {
                margin-bottom: 46px;
                font-size: 18px;
            }

            .plug-scene {
                width: 760px;
                transform: translateX(-70px);
            }
        }
    </style>
</head>
<body>
    <main class="maintenance-card" aria-labelledby="maintenance-title">
        <section class="content">
            <div class="brand" aria-label="DragonFortune.ai">
                <span class="brand-mark" aria-hidden="true">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M5 13l4 4L19 7"></path>
                    </svg>
                </span>
                <span>DragonFortune.ai</span>
            </div>

            <h1 id="maintenance-title">The site is currently down for maintenance</h1>

            <p class="message">
                We apologize for any inconvenience caused.<br>
                We are almost done.
            </p>

            <svg class="plug-scene" viewBox="0 0 1180 250" role="img" aria-label="Two disconnected power plugs">
                <path d="M0 118H215c20 0 36 16 36 36v13c0 19 15 34 34 34h126" fill="none" stroke="#9ee6ff" stroke-width="24" stroke-linecap="round"></path>
                <path d="M0 118H215c20 0 36 16 36 36v13c0 19 15 34 34 34h126" fill="none" stroke="#32b5e9" stroke-width="12" stroke-linecap="round" opacity="0.8"></path>

                <path d="M1180 118H965c-20 0-36 16-36 36v13c0 19-15 34-34 34H769" fill="none" stroke="#19e65c" stroke-width="24" stroke-linecap="round"></path>
                <path d="M1180 118H965c-20 0-36 16-36 36v13c0 19-15 34-34 34H769" fill="none" stroke="#10b981" stroke-width="12" stroke-linecap="round" opacity="0.85"></path>

                <g transform="translate(378 88)">
                    <rect x="0" y="42" width="82" height="34" rx="8" fill="#168fce"></rect>
                    <rect x="64" y="22" width="76" height="76" rx="4" fill="#1ca9e8"></rect>
                    <rect x="92" y="22" width="30" height="76" fill="#0d6efd" opacity="0.55"></rect>
                    <rect x="140" y="4" width="20" height="112" rx="3" fill="#8edcff"></rect>
                    <rect x="160" y="30" width="16" height="26" rx="5" fill="#0d6efd"></rect>
                    <rect x="160" y="66" width="16" height="26" rx="5" fill="#0d6efd"></rect>
                    <rect x="-32" y="40" width="40" height="38" rx="8" fill="#0d6efd"></rect>
                </g>

                <g transform="translate(620 88)">
                    <rect x="118" y="42" width="82" height="34" rx="8" fill="#16823f"></rect>
                    <rect x="42" y="22" width="76" height="76" rx="4" fill="#16c957"></rect>
                    <rect x="64" y="22" width="32" height="76" fill="#08ec55" opacity="0.7"></rect>
                    <rect x="22" y="4" width="20" height="112" rx="3" fill="#0fd84f"></rect>
                    <rect x="6" y="30" width="16" height="26" rx="5" fill="#22c55e"></rect>
                    <rect x="6" y="66" width="16" height="26" rx="5" fill="#22c55e"></rect>
                    <rect x="196" y="40" width="40" height="38" rx="8" fill="#16823f"></rect>
                </g>

                <path d="M590 56c0 15-12 27-27 27V56h27Z" fill="#11a9e8"></path>
                <path d="M615 74c0-16 13-29 29-29v29h-29Z" fill="#1ee85c"></path>
                <path d="M571 196c12-8 24-16 36-24l8 24h-44Z" fill="#83ddff"></path>
                <path d="M638 169c11 8 23 16 35 24h-44l9-24Z" fill="#20d65b"></path>
            </svg>
        </section>

        <footer class="footer">
            <span>You can contact us:</span>
            <span><strong>Website:</strong> <span class="footer-link">dragonfortune.ai</span></span>
            <span><strong>Status:</strong> <span class="footer-link">maintenance</span></span>
            <span class="socials" aria-hidden="true">
                <span class="social">f</span>
                <span class="social">x</span>
                <span class="social">in</span>
                <span class="social">tg</span>
            </span>
        </footer>
    </main>
</body>
</html>
