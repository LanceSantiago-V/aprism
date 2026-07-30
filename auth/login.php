<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';

if (
  isset($_SESSION['user_id']) &&
  isset($_SESSION['role_id']) &&
  isset($_SESSION['employee_number']) &&
  isset($_SESSION['username'])
) {
  header('Location: ' . APP_URL . '/dashboard.php');
  exit;
}

$error_msg = $flash['error'] ?? '';
$success_msg = $flash['success'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>APRISM - Student Monitoring and Intervention Support System</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
    rel="stylesheet">

  <style>
    :root {
      --sti-navy: #002147;
      --sti-blue: #005BAB;
      --bg-slate-50: #f8fafc;
      --border-slate-100: #f1f5f9;
      --text-slate-400: #94a3b8;
    }

    body {
      background-color: var(--bg-slate-50);
      background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
      background-size: 24px 24px;
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 1.5rem;
      position: relative;
    }

    .login-card {
      background-color: #ffffff;
      border-radius: 3rem;
      box-shadow: 0 25px 50px -12px rgba(0, 33, 71, 0.12);
      padding: 3rem;
      border: 1px solid var(--border-slate-100);
      width: 100%;
      max-width: 440px;
      transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
      position: relative;
      z-index: 10;
    }

    @media (max-width: 576px) {
      .login-card {
        padding: 2.5rem 1.75rem;
        border-radius: 2rem;
      }
    }

    .brand-logo-wrapper {
      display: flex;
      justify-content: center;
      margin-bottom: 0;
      width: 100%;
    }

    .brand-logo-container {
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 0;
      width: 80px;
    }

    .brand-logo-img {
      width: 100%;
      height: auto;
      display: block;
    }

    .brand-header {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .brand-title {
      font-family: Arial, sans-serif;
      font-size: 3rem;
      font-weight: 900;
      color: var(--sti-navy);
      letter-spacing: -0.05em;
      text-transform: uppercase;
      margin-top: 0.2rem;
      margin-bottom: 0.45rem;
      line-height: 1.1;
    }

    .brand-subtitle-container {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
      margin-bottom: 2rem;
    }

    .brand-subtitle-line {
      font-size: 10px;
      font-weight: 900;
      text-transform: uppercase;
      color: var(--sti-blue);
      letter-spacing: 0.25em;
      line-height: 1.2;
      margin: 0;
    }

    .input-group-custom {
      margin-bottom: 1.75rem;
      position: relative;
    }

    .input-label {
      font-size: 11px;
      font-weight: 900;
      color: var(--text-slate-400);
      text-transform: uppercase;
      letter-spacing: 0.15em;
      margin-bottom: 0.5rem;
      display: block;
      text-align: left;
      padding-left: 0.25rem;
    }

    .input-label .required-asterisk {
      color: #f43f5e;
      font-weight: bold;
      font-style: italic;
    }

    .input-field-container {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 1.25rem;
      top: 50%;
      transform: translateY(-50%);
      color: #cbd5e1;
      transition: color 0.25s ease;
      pointer-events: none;
      display: flex;
      align-items: center;
    }

    .input-field {
      width: 100%;
      padding: 1.1rem 1.25rem 1.1rem 3.25rem;
      background-color: var(--bg-slate-50);
      border: 2px solid transparent;
      border-radius: 1rem;
      font-size: 0.875rem;
      font-weight: 700;
      color: var(--sti-navy);
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      outline: none;
    }

    .input-field::placeholder {
      color: #cbd5e1;
      font-weight: 400;
    }

    .input-field:focus {
      outline: none;
      background-color: #ffffff;
      border-color: var(--sti-blue);
      box-shadow: 0 0 0 4px rgba(0, 91, 171, 0.08);
    }

    .input-field:focus+.input-icon {
      color: var(--sti-blue);
    }

    .toggle-password-btn {
      position: absolute;
      right: 1.25rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #cbd5e1;
      padding: 0;
      cursor: pointer;
      transition: color 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 0.25rem;
    }

    .toggle-password-btn:hover {
      color: var(--sti-blue);
    }

    .login-submit-btn:focus-visible,
    .toggle-password-btn:focus-visible {
      outline: 2px solid var(--sti-blue);
      outline-offset: 2px;
      box-shadow: 0 0 0 4px rgba(0, 91, 171, 0.25);
    }

    .error-alert-custom {
      padding: 0.75rem 1.1rem;
      background-color: rgba(244, 63, 94, 0.05);
      border: 1px solid rgba(244, 63, 94, 0.15);
      color: #e11d48;
      font-size: 12px;
      font-weight: 500;
      border-radius: 1rem;
      display: flex;
      align-items: center;
      gap: 0.6rem;
      margin-bottom: 1.5rem;
      text-align: left;
      animation: shake 0.4s cubic-bezier(.36, .07, .19, .97) both;
    }

    .error-dot {
      width: 6px;
      height: 6px;
      background-color: #f43f5e;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .success-alert-custom {
      padding: 0.75rem 1.1rem;
      background-color: rgba(34, 197, 94, 0.06);
      border: 1px solid rgba(34, 197, 94, 0.18);
      color: #15803d;
      font-size: 12px;
      font-weight: 500;
      border-radius: 1rem;
      display: flex;
      align-items: center;
      gap: 0.6rem;
      margin-bottom: 1.5rem;
      text-align: left;
    }

    .success-dot {
      width: 6px;
      height: 6px;
      background-color: #22c55e;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .login-submit-btn {
      width: 100%;
      background-color: var(--sti-blue);
      color: #ffffff;
      border: none;
      border-radius: 1rem;
      padding: 1.25rem 1rem;
      font-size: 0.875rem;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.2em;
      box-shadow: 0 10px 25px -5px rgba(0, 91, 171, 0.2);
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      margin-top: 1.75rem;
    }

    .login-submit-btn:hover {
      background-color: var(--sti-navy);
      transform: scale(1.02);
      box-shadow: 0 12px 28px -5px rgba(0, 33, 71, 0.25);
    }

    .login-submit-btn:active {
      transform: scale(0.98);
    }

    .login-submit-btn:disabled {
      opacity: 0.65;
      cursor: not-allowed;
      transform: none !important;
      box-shadow: none !important;
    }

    .divider-line {
      height: 1px;
      width: 2rem;
      background-color: #cbd5e1;
      opacity: 0.5;
      display: inline-block;
    }

    @keyframes shake {

      10%,
      90% {
        transform: translate3d(-1px, 0, 0);
      }

      20%,
      80% {
        transform: translate3d(2px, 0, 0);
      }

      30%,
      50%,
      70% {
        transform: translate3d(-3px, 0, 0);
      }

      40%,
      60% {
        transform: translate3d(3px, 0, 0);
      }
    }
  </style>
</head>

<body>

  <div id="login-card" class="login-card text-center">

    <div class="brand-header">
      <div class="brand-logo-wrapper">
        <div class="brand-logo-container">
          <img src="../assets/images/aprism-logo.png" alt="APRISM Logo" class="brand-logo-img">
        </div>
      </div>

      <h1 class="brand-title">APRISM</h1>
      <div class="brand-subtitle-container">
        <p class="brand-subtitle-line">Student Monitoring and</p>
        <p class="brand-subtitle-line">Intervention Support System</p>
      </div>
    </div>

    <div id="success-alert" class="success-alert-custom <?php echo empty($success_msg) ? 'd-none' : ''; ?>"
      role="status" aria-live="polite">

      <span class="success-dot"></span>

      <span id="success-text" class="flex-grow-1">
        <?php echo htmlspecialchars($success_msg); ?>
      </span>

    </div>

    <div id="error-alert" class="error-alert-custom <?php echo empty($error_msg) ? 'd-none' : ''; ?>" role="alert"
      aria-live="assertive">
      <span class="error-dot"></span>
      <span id="error-text" class="flex-grow-1"><?php echo htmlspecialchars($error_msg); ?></span>
    </div>

    <form id="login-form" method="POST" action="authenticate.php" novalidate>

      <div class="input-group-custom">
        <label for="username-input" class="input-label">
          Institutional Email <span class="required-asterisk">*</span>
        </label>
        <div class="input-field-container">
          <input type="text" id="username-input" name="username" class="input-field" placeholder="sample@dasmarinas.sti.edu.ph"
            required autocomplete="email" autofocus>
          <div class="input-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"
              aria-hidden="true" focusable="false">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="input-group-custom">
        <label for="password-input" class="input-label">
          Password <span class="required-asterisk">*</span>
        </label>
        <div class="input-field-container">
          <input type="password" id="password-input" name="password" class="input-field" placeholder="••••••••" required
            autocomplete="current-password">
          <div class="input-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"
              aria-hidden="true" focusable="false">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <button type="button" id="toggle-password" class="toggle-password-btn" title="Show password"
            aria-label="Show password">
            <svg id="eye-icon-svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20"
              height="20" aria-hidden="true" focusable="false">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" id="login-submit" class="login-submit-btn">
        <span>Login</span>
      </button>

    </form>
  </div>

  <footer id="login-footer" class="mt-5 text-center">
    <p class="text-uppercase mb-2"
      style="font-size: 11px; font-weight: 900; letter-spacing: 0.4em; margin-bottom: 0.5rem; color: #cbd5e1;">STI
      COLLEGE DASMARIÑAS</p>
    <div class="d-flex align-items-center justify-content-center gap-3">
      <span class="divider-line"></span>
      <span class="text-secondary fw-bold text-uppercase"
        style="font-size: 9px; font-weight: 700; letter-spacing: 0.15em; color: #94a3b8;">Official Information
        System</span>
      <span class="divider-line"></span>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const togglePasswordBtn = document.getElementById('toggle-password');
      const passwordInput = document.getElementById('password-input');
      const usernameInput = document.getElementById('username-input');
      const loginForm = document.getElementById('login-form');
      const errorAlert = document.getElementById('error-alert');
      const errorText = document.getElementById('error-text');
      const eyeIcon = document.getElementById('eye-icon-svg');
      const submitBtn = document.getElementById('login-submit');

      if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function () {
          const isPassword = passwordInput.getAttribute('type') === 'password';
          passwordInput.setAttribute('type', isPassword ? 'text' : 'password');

          if (isPassword) {
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M3 3l18 18" />
              `;
            togglePasswordBtn.setAttribute('title', 'Hide password');
            togglePasswordBtn.setAttribute('aria-label', 'Hide password');
          } else {
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              `;
            togglePasswordBtn.setAttribute('title', 'Show password');
            togglePasswordBtn.setAttribute('aria-label', 'Show password');
          }
        });
      }

      if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
          const username = usernameInput.value.trim();
          const password = passwordInput.value.trim();

          usernameInput.value = username;
          passwordInput.value = password;

          let validationError = '';
          if (!username) {
            validationError = 'Username is required.';
          } else if (!password) {
            validationError = 'Password is required.';
          }

          if (validationError) {
            e.preventDefault();
            errorText.textContent = validationError;
            errorAlert.classList.remove('d-none');

            errorAlert.style.animation = 'none';
            // Restart shake animation
            errorAlert.offsetHeight;
            errorAlert.style.animation = '';
          } else {
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <span>Logging In...</span>
            `;
          }
        });
      }
    });
  </script>
</body>

</html>