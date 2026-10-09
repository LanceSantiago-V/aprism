<?php

declare(strict_types=1);

http_response_code(404);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$applicationPath = defined('APP_URL')
    ? APP_URL
    : dirname(dirname(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''))));

$applicationPath = $applicationPath === '.'
    ? ''
    : rtrim($applicationPath, '/');

$loginUrl = $applicationPath . '/auth/login.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Page not found — APRISM</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #14213d; background: #eef3f8; }
        * { box-sizing: border-box; }
        body { display: grid; min-width: 320px; min-height: 100vh; margin: 0; padding: 1.25rem; place-items: center; background: radial-gradient(circle at 50% 0%, #ffffff 0, #eef3f8 62%); }
        main { width: min(100%, 30rem); padding: clamp(2rem, 6vw, 4rem) clamp(1.25rem, 6vw, 4rem); border: 1px solid #dbe5ee; border-radius: 24px; background: rgba(255, 255, 255, 0.96); box-shadow: 0 24px 60px rgba(20, 33, 61, 0.12); text-align: center; }
        .brand { margin-bottom: 2rem; color: #14213d; font-size: 0.9rem; font-weight: 800; letter-spacing: 0.16em; }
        .status-icon { display: grid; width: 64px; height: 64px; margin: 0 auto 1.5rem; place-items: center; border-radius: 20px; background: #fff5cc; color: #a97900; }
        .status-icon svg { width: 32px; height: 32px; }
        h1 { margin: 0; font-size: clamp(1.55rem, 4vw, 2.15rem); line-height: 1.15; }
        p { max-width: 30rem; margin: 1rem auto 0; color: #60758a; font-size: 0.98rem; line-height: 1.65; }
        .recovery-action { display: inline-flex; min-height: 46px; margin-top: 2rem; align-items: center; justify-content: center; padding: 0.75rem 1.25rem; border-radius: 12px; background: #14213d; color: #ffffff; font-size: 0.9rem; font-weight: 750; text-decoration: none; }
        .recovery-action:hover { background: #20345d; }
        @media (max-width: 430px) { body { padding: 0.75rem; } main { border-radius: 18px; } .brand { margin-bottom: 1.5rem; } }
    </style>
</head>
<body>
    <main>
        <div class="brand">APRISM</div>
        <div class="status-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/><path d="M8.5 8.5 15.5 15.5"/><path d="m15.5 8.5-7 7"/>
            </svg>
        </div>
        <h1>Page not found</h1>
        <p>The page you requested is unavailable or may have moved.</p>
        <a class="recovery-action" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Return to APRISM</a>
    </main>
</body>
</html>
