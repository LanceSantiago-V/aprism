<?php

declare(strict_types=1);

$toastFlash = is_array($flash ?? null) ? $flash : [];
?>
<div class="toast-container-custom" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
<script>
(() => {
    const flash = <?= json_encode($toastFlash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const isError = Boolean(flash.error);
    const message = flash.error || flash.success || flash.warning;
    if (!message) return;

    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast-custom';
    const icon = document.createElement('div');
    icon.className = `toast-icon ${isError ? 'warning' : 'success'}`;
    const iconElement = document.createElement('i');
    iconElement.setAttribute('data-lucide', isError ? 'triangle-alert' : 'circle-check');
    iconElement.setAttribute('aria-hidden', 'true');
    icon.appendChild(iconElement);

    const content = document.createElement('div');
    content.className = 'toast-content';
    const title = document.createElement('h5');
    title.className = 'toast-title';
    title.textContent = isError ? 'Action not completed' : 'Success';
    const body = document.createElement('p');
    body.className = 'toast-text';
    body.textContent = String(message);
    content.append(title, body);
    toast.append(icon, content);
    container.appendChild(toast);

    window.lucide?.createIcons?.();
    requestAnimationFrame(() => toast.classList.add('show'));
    window.setTimeout(() => {
        toast.classList.remove('show');
        window.setTimeout(() => toast.remove(), 240);
    }, 4800);
})();
</script>
