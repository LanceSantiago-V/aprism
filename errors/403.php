<?php

declare(strict_types=1);

if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/app.php';
}

http_response_code(403);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$applicationPath = rtrim((string) APP_URL, '/');
$roleNavigation = require __DIR__ . '/../includes/navigation/sidebar_items.php';
$roleId = (int) ($_SESSION['role_id'] ?? 0);
$roleDashboard = $roleNavigation[$roleId][0]['url'] ?? null;
$returnUrl = is_string($roleDashboard)
    ? $roleDashboard
    : $applicationPath . '/auth/login.php';
$returnLabel = is_string($roleDashboard)
    ? 'Return to your dashboard'
    : 'Return to APRISM';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Access denied — APRISM</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #14213d; background: #eef3f8; }
        * { box-sizing: border-box; }
        body { display: grid; min-width: 320px; min-height: 100vh; margin: 0; padding: 1.25rem; place-items: center; background: #eef3f8; }
        main { width: min(100%, 30rem); padding: clamp(2rem, 6vw, 4rem) clamp(1.25rem, 6vw, 4rem); border: 1px solid #dbe5ee; border-radius: 20px; background: #ffffff; box-shadow: 0 16px 40px rgba(20, 33, 61, 0.1); text-align: center; }
        .brand { margin-bottom: 2rem; color: #14213d; font-size: 0.9rem; font-weight: 800; letter-spacing: 0.16em; }
        .status-icon { display: grid; width: 64px; height: 64px; margin: 0 auto 1.5rem; place-items: center; border-radius: 18px; background: #fff5cc; color: #8a6500; }
        .status-icon svg { width: 32px; height: 32px; }
        h1 { margin: 0; font-size: clamp(1.55rem, 4vw, 2.15rem); line-height: 1.15; }
        p { max-width: 30rem; margin: 1rem auto 0; color: #60758a; font-size: 0.98rem; line-height: 1.65; }
        .recovery-action { display: inline-flex; min-height: 46px; margin-top: 2rem; align-items: center; justify-content: center; padding: 0.75rem 1.25rem; border-radius: 12px; background: #14213d; color: #ffffff; font-size: 0.9rem; font-weight: 750; text-decoration: none; }
        .recovery-action:hover { background: #20345d; }
        .recovery-action:focus-visible { outline: 3px solid #1f78c1; outline-offset: 3px; }
        @media (max-width: 430px) { body { padding: 0.75rem; } main { border-radius: 16px; } .brand { margin-bottom: 1.5rem; } }
    </style>
</head>
<body>
    <main>
        <div class="brand">APRISM</div>
        <div class="status-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3 3.8 7.2v4.9c0 4.4 3.2 7.6 8.2 8.9 5-1.3 8.2-4.5 8.2-8.9V7.2L12 3Z"/>
                <path d="M9 12h6"/>
            </svg>
        </div>
        <h1>Access denied</h1>
        <p>Your account cannot access this page. Return to APRISM to continue in your current workspace.</p>
        <a class="recovery-action" href="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($returnLabel, ENT_QUOTES, 'UTF-8') ?></a>
    </main>
</body>
</html>
