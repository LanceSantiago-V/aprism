<?php

require_once __DIR__ . '/../includes/session_guard.php';

$fullName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$initials =
  strtoupper(substr($_SESSION['first_name'], 0, 1)) .
  strtoupper(substr($_SESSION['last_name'], 0, 1));

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Technical Administrator Dashboard - APRISM</title>

  <!-- Inter & JetBrains Mono Fonts from Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700;800&display=swap"
    rel="stylesheet" />

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />

  <style>
    :root {
      --sti-navy: #002447;
      --sti-blue: #0d6efd;
      --sti-yellow: #ffc72c;
      --sti-sidebar-bg: #002447;
      --sti-text-muted: #8fa0b5;
      --sti-bg-gray: #f4f6fa;
      --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
      --font-mono: 'JetBrains Mono', monospace;
    }

    body {
      background-color: var(--sti-bg-gray);
      font-family: var(--font-sans);
      color: #334155;
      overflow-x: hidden;
    }

    /* Sidebar styling */
    .sidebar {
      width: 280px;
      background-color: var(--sti-sidebar-bg);
      height: 100vh;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 1000;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 2.25rem 1.5rem;
      box-shadow: 4px 0 25px rgba(0, 0, 0, 0.05);
      transition:
        width .30s ease,
        padding .30s ease;
    }

    .sidebar.collapsed {
      width: 92px;
      padding-left: 1rem;
      padding-right: 1rem;
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 2.5rem;
    }

    .brand-logo-box {
      width: 44px;
      height: 44px;
      background-color: var(--sti-yellow);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 900;
      font-size: 1.4rem;
      color: var(--sti-navy);
      box-shadow: 0 4px 10px rgba(255, 199, 44, 0.2);
    }

    .brand-text {
      line-height: 1.25;
      opacity: 1;
      max-width: 180px;
      overflow: hidden;
      white-space: nowrap;
      transition:
        opacity .30s ease,
        max-width .30s ease;
    }

    .sidebar.collapsed .brand-text {
      opacity: 0;
      max-width: 0;
    }

    .brand-title {
      color: #ffffff;
      font-weight: 900;
      font-size: 1.2rem;
      letter-spacing: -0.02em;
      margin: 0;
    }

    .brand-subtitle {
      color: var(--sti-text-muted);
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin: 0;
    }

    .sidebar-menu {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .sidebar.collapsed .sidebar-link,
    .sidebar.collapsed .logout-link {
      justify-content: center;
      gap: 0;
    }

    .sidebar svg {
      width: 20px;
      height: 20px;
      min-width: 20px;
      flex-shrink: 0;
    }

    .sidebar.collapsed .sidebar-brand {
      justify-content: center;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      padding: 1rem 1.25rem;
      color: var(--sti-text-muted);
      font-weight: 600;
      font-size: 0.9rem;
      text-decoration: none;
      border-radius: 16px;
      transition: all .30s ease;
    }

    .sidebar-link,
    .logout-link {
      justify-content: flex-start;
    }

    .sidebar-link span,
    .logout-link span {
      opacity: 1;
      max-width: 140px;
      overflow: hidden;
      white-space: nowrap;
      transition:
        opacity .35s ease,
        max-width .35s ease;
    }

    .sidebar.collapsed .sidebar-link span,
    .sidebar.collapsed .logout-link span {
      opacity: 0;
      max-width: 0;
    }

    .sidebar-link:hover {
      color: #ffffff;
      background-color: rgba(255, 255, 255, 0.05);
    }

    .sidebar-link.active {
      background-color: var(--sti-yellow);
      color: var(--sti-navy);
      font-weight: 800;
    }

    .sidebar-footer {
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      padding-top: 1.5rem;
    }

    .logout-link {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      color: #ff5b5b;
      font-weight: 800;
      font-size: 0.85rem;
      text-decoration: none;
      padding: 0.85rem 1.25rem;
      border-radius: 12px;
      transition: all 0.2s ease;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .logout-link:hover {
      background-color: rgba(255, 91, 91, 0.08);
      color: #ff3333;
    }

    /* Main content wrapper */
    .main-content {
      margin-left: 280px;
      transition:
        margin-left .30s ease;
      padding: 2.25rem;
      min-height: 100vh;
    }

    .main-content.expanded {
      margin-left: 92px;
    }

    /* Top Navbar */
    .top-navbar {
      background-color: #ffffff;
      border-radius: 24px;
      padding: 0.85rem 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 4px 25px rgba(0, 0, 0, 0.015);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      border: 1px solid rgba(241, 245, 249, 0.8);
    }

    .navbar-left {
      display: flex;
      align-items: center;
      gap: 0.85rem;
    }

    .back-btn {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #f8fafc;
      border: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #94a3b8;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    #sidebarToggle {
      transition: transform .30s ease;
    }

    #sidebarToggle.rotated {
      transform: rotate(180deg);
    }

    .back-btn:hover {
      background-color: #f1f5f9;
      color: var(--sti-navy);
    }

    .search-wrapper {
      position: relative;
      width: 280px;
    }

    .search-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      width: 16px;
      height: 16px;
    }

    .navbar-search {
      width: 100%;
      background-color: #f8fafc;
      border: 1px solid #f1f5f9;
      border-radius: 30px;
      padding: 0.55rem 1rem 0.55rem 2.5rem;
      font-size: 0.85rem;
      outline: none;
      color: var(--sti-navy);
      transition: all 0.2s ease;
    }

    .navbar-search:focus {
      background-color: #ffffff;
      border-color: #cbd5e1;
      box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.05);
    }

    .navbar-right {
      display: flex;
      align-items: center;
      gap: 1.25rem;
    }

    .active-term-badge {
      background-color: #f1f5f9;
      border: 1px solid #e2e8f0;
      border-radius: 30px;
      padding: 0.45rem 1.15rem;
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--sti-navy);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .active-term-badge i {
      color: var(--sti-blue);
    }

    .notification-bell {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #f8fafc;
      border: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      cursor: pointer;
      position: relative;
      transition: all 0.2s ease;
    }

    .notification-bell:hover {
      background-color: #f1f5f9;
      color: var(--sti-navy);
    }

    .notification-dot {
      position: absolute;
      top: 10px;
      right: 11px;
      width: 8px;
      height: 8px;
      background-color: #ef4444;
      border-radius: 50%;
      border: 2px solid #ffffff;
    }

    .user-profile {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .profile-text {
      line-height: 1.25;
      text-align: right;
    }

    .profile-name {
      font-weight: 800;
      font-size: 0.85rem;
      color: var(--sti-navy);
      margin: 0;
    }

    .profile-role {
      font-size: 0.65rem;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin: 0;
    }

    .profile-avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background-color: #f1f5f9;
      border: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      font-weight: 800;
      font-size: 0.85rem;
    }

    /* Dashboard Header */
    .dashboard-header-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
    }

    .dashboard-title {
      font-size: 1.85rem;
      font-weight: 900;
      color: var(--sti-navy);
      letter-spacing: -0.03em;
      margin: 0;
    }

    .status-indicator {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #94a3b8;
      margin-top: 0.5rem;
    }

    .status-indicator strong {
      color: var(--sti-navy);
    }

    .status-pulse {
      display: inline-block;
      width: 8px;
      height: 8px;
      background-color: #10b981;
      border-radius: 50%;
      position: relative;
    }

    .status-pulse::after {
      content: '';
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      background-color: #10b981;
      border-radius: 50%;
      animation: pulse-ring 1.5s infinite;
    }

    @keyframes pulse-ring {
      0% {
        transform: scale(1);
        opacity: 0.75;
      }

      100% {
        transform: scale(2.5);
        opacity: 0;
      }
    }

    .refresh-btn {
      width: 48px;
      height: 48px;
      border-radius: 16px;
      background-color: var(--sti-blue);
      color: #ffffff;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 10px 15px -3px rgba(13, 110, 253, 0.2);
    }

    .refresh-btn:hover {
      background-color: var(--sti-navy);
      transform: translateY(-2px);
    }

    /* Stat Cards */
    .stat-card {
      background-color: #ffffff;
      border-radius: 28px;
      padding: 1.75rem;
      border: 1px solid rgba(241, 245, 249, 0.8);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.01);
      position: relative;
      overflow: hidden;
      height: 100%;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.025);
    }

    .stat-icon-box {
      width: 48px;
      height: 48px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.25rem;
    }

    .stat-icon-box.health {
      background-color: #e8f8f0;
      color: #10b981;
    }

    .stat-icon-box.sessions {
      background-color: #eff6ff;
      color: #3b82f6;
    }

    .stat-icon-box.db {
      background-color: #eef2ff;
      color: #4f46e5;
    }

    .stat-icon-box.disk {
      background-color: #fff7ed;
      color: #f97316;
    }

    .stat-label {
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #94a3b8;
      margin-bottom: 0.5rem;
    }

    .stat-value {
      font-size: 1.5rem;
      font-weight: 900;
      color: var(--sti-navy);
      letter-spacing: -0.02em;
      margin: 0;
    }

    .stat-card-badge {
      position: absolute;
      top: 1.75rem;
      right: 1.75rem;
      width: 10px;
      height: 10px;
      background-color: #10b981;
      border-radius: 50%;
    }

    .stat-card-badge::after {
      content: '';
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      background-color: #10b981;
      border-radius: 50%;
      animation: pulse-ring 2s infinite;
    }

    /* Content Cards */
    .section-card {
      background-color: #ffffff;
      border-radius: 36px;
      padding: 2.25rem;
      border: 1px solid rgba(226, 232, 240, 0.8);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.01);
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .section-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.75rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .section-title {
      font-size: 1.25rem;
      font-weight: 900;
      color: var(--sti-navy);
      letter-spacing: -0.02em;
      margin: 0;
    }

    /* Audit Log Search */
    .audit-search-wrapper {
      position: relative;
      width: 250px;
    }

    .audit-search-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      width: 14px;
      height: 14px;
    }

    .audit-search-input {
      width: 100%;
      background-color: #f8fafc;
      border: 1px solid #f1f5f9;
      border-radius: 12px;
      padding: 0.5rem 0.75rem 0.5rem 2rem;
      font-size: 0.75rem;
      outline: none;
      color: var(--sti-navy);
      transition: all 0.2s ease;
    }

    .audit-search-input:focus {
      background-color: #ffffff;
      border-color: #cbd5e1;
    }

    /* Table styling */
    .audit-table-container {
      border-radius: 18px;
      overflow: hidden;
      border: 1px solid #f1f5f9;
      flex: 1;
    }

    .audit-table {
      margin: 0;
      width: 100%;
    }

    .audit-table th {
      background-color: #f8fafc;
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #94a3b8;
      padding: 1.15rem 1.25rem;
      border-bottom: 1px solid #e2e8f0;
    }

    .audit-table td {
      padding: 1.15rem 1.25rem;
      font-size: 0.8rem;
      vertical-align: middle;
      color: #475569;
      border-bottom: 1px solid #f1f5f9;
    }

    .audit-table tr:last-child td {
      border-bottom: none;
    }

    .audit-table tbody tr:hover {
      background-color: #f8fafc;
    }

    .font-mono-custom {
      font-family: var(--font-mono);
      font-size: 0.75rem;
      color: #94a3b8;
    }

    /* Custom badges */
    .badge-role {
      font-size: 0.55rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 0.25rem 0.65rem;
      border-radius: 30px;
      display: inline-block;
    }

    .badge-role.admin {
      background-color: rgba(13, 110, 253, 0.08);
      color: var(--sti-blue);
      border: 1px solid rgba(13, 110, 253, 0.15);
    }

    .badge-role.teacher {
      background-color: #f1f5f9;
      color: #475569;
      border: 1px solid #e2e8f0;
    }

    .badge-role.guidance {
      background-color: #faf5ff;
      color: #7e22ce;
      border: 1px solid #f3e8ff;
    }

    .details-view-btn {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      color: #94a3b8;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .details-view-btn:hover {
      background-color: #f1f5f9;
      color: var(--sti-navy);
    }

    /* Backup & Recovery cards stack */
    .backup-stack {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      overflow-y: auto;
      flex: 1;
    }

    .backup-item-card {
      background-color: #f8fafc;
      border-radius: 20px;
      padding: 1.25rem;
      border: 1px solid rgba(226, 232, 240, 0.6);
      transition: all 0.2s ease;
    }

    .backup-item-card:hover {
      background-color: #f1f5f9;
      border-color: #cbd5e1;
    }

    .backup-header-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 0.75rem;
    }

    .backup-filename {
      font-family: var(--font-mono);
      font-size: 0.8rem;
      font-weight: 800;
      color: var(--sti-navy);
      word-break: break-all;
      margin: 0 0 0.35rem 0;
    }

    .backup-meta {
      font-size: 0.65rem;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-family: var(--font-mono);
      margin: 0;
    }

    .badge-status {
      font-size: 0.55rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 0.2rem 0.5rem;
      border-radius: 4px;
      white-space: nowrap;
    }

    .badge-status.successful {
      background-color: #d1fae5;
      color: #065f46;
    }

    .badge-status.warning {
      background-color: #fef3c7;
      color: #92400e;
    }

    .badge-status.failed {
      background-color: #fee2e2;
      color: #991b1b;
    }

    .backup-actions-row {
      border-top: 1px solid rgba(226, 232, 240, 0.8);
      margin-top: 1rem;
      padding-top: 0.75rem;
      display: flex;
      justify-content: flex-end;
      gap: 0.5rem;
    }

    .backup-btn {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background-color: #ffffff;
      border: 1px solid #e2e8f0;
      color: #64748b;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .backup-btn:hover.download {
      color: var(--sti-blue);
      border-color: var(--sti-blue);
    }

    .backup-btn:hover.restore {
      color: #10b981;
      border-color: #10b981;
    }

    .backup-btn:hover.archive {
      color: #ef4444;
      border-color: #ef4444;
    }

    /* Notification Toast Container */
    .toast-container-custom {
      position: fixed;
      top: 2rem;
      right: 2rem;
      z-index: 1100;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      pointer-events: none;
    }

    .toast-custom {
      background-color: #ffffff;
      border-radius: 16px;
      padding: 1rem 1.5rem;
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
      border: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      pointer-events: auto;
      animation: slide-in 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      max-width: 350px;
    }

    @keyframes slide-in {
      from {
        transform: translateX(120%);
        opacity: 0;
      }

      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    .toast-icon {
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .toast-icon.success {
      color: #10b981;
    }

    .toast-icon.info {
      color: #3b82f6;
    }

    .toast-icon.warning {
      color: #f59e0b;
    }

    .toast-text {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--sti-navy);
    }

    /* Custom Dialog Modal styling */
    .modal-custom-content {
      border-radius: 28px;
      border: none;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
      background-color: #ffffff;
    }

    .modal-header-custom {
      border-bottom: none;
      padding: 2.25rem 2.25rem 1rem 2.25rem;
    }

    .modal-body-custom {
      padding: 0 2.25rem 2.25rem 2.25rem;
    }

    .modal-footer-custom {
      border-top: none;
      padding: 0 2.25rem 2.25rem 2.25rem;
    }

    .modal-title-custom {
      font-size: 1.2rem;
      font-weight: 900;
      color: var(--sti-navy);
      text-transform: uppercase;
      letter-spacing: -0.01em;
      margin: 0;
    }

    .modal-btn-close {
      background-color: #f1f5f9;
      color: #475569;
      border: none;
      border-radius: 16px;
      font-weight: 800;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 0.85rem 1.5rem;
      cursor: pointer;
      width: 100%;
      transition: all 0.2s ease;
    }

    .modal-btn-close:hover {
      background-color: #e2e8f0;
    }

    /* Custom Backdrop Styling */
    .modal-backdrop {
      background-color: #000c1a !important;
      /* Extremely dark blue-navy overlay */
      opacity: 0 !important;
      transition: opacity 240ms cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .modal-backdrop.show {
      opacity: 0.32 !important;
      /* Soft darkening, extremely elegant */
      backdrop-filter: blur(5px) !important;
      -webkit-backdrop-filter: blur(5px) !important;
    }

    /* Custom Modal Transition (0.97 -> 1 scaling and opacity 0 -> 1) */
    #logoutModal.modal.fade {
      transition: opacity 240ms cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    #logoutModal.modal.fade .modal-dialog {
      transform: scale(0.97) !important;
      opacity: 0 !important;
      transition: transform 240ms cubic-bezier(0.16, 1, 0.3, 1), opacity 240ms cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    #logoutModal.modal.show .modal-dialog {
      transform: scale(1) !important;
      opacity: 1 !important;
    }

    #logoutModal .modal-dialog {
      max-width: 420px;
      /* Maximum width around 420-450px */
    }

    #logoutModal .modal-content {
      border: 1px solid rgba(226, 232, 240, 0.8) !important;
      /* Subtle 1px border */
      border-radius: 30px;
      /* Border radius between 28px-32px to match APRISM cards */
      overflow: hidden;
      box-shadow: 0 15px 35px -5px rgba(0, 36, 71, 0.05), 0 5px 15px -3px rgba(0, 36, 71, 0.02) !important;
      /* Soft shadow consistent with dashboard cards */
      background-color: #fafbfc;
      /* Off-white background */
      font-family: var(--font-sans);
      padding: 2.25rem 2rem;
      /* Compact layout with generous whitespace but reduced vertical spacing */
      position: relative;
      /* For position-relative close button */
    }

    #logoutModal .modal-title {
      font-size: 1.3rem;
      /* 1.3rem as requested */
      font-weight: 700;
      /* bold as requested */
      color: var(--sti-navy);
      /* APRISM navy */
      letter-spacing: -0.02em;
    }

   #logoutModal .logout-icon,
    #logoutModal .logout-icon svg {
      width: 18px;
      /* Subtle, reduced visual weight */
      height: 18px;
    }

    #logoutModal .logout-message {
      font-size: 0.95rem;
      color: #64748b;
      /* Slate gray */
      line-height: 1.6;
      margin: 0;
      font-weight: 500;
      /* Medium weight */
    }

    #logoutModal .logout-message strong {
      color: var(--sti-navy);
      font-weight: 700;
    }

    #logoutModal .logout-cancel-btn {
      flex: 1;
      background-color: #f1f5f9;
      /* Light gray */
      border: 1px solid #e2e8f0;
      color: #475569;
      border-radius: 16px;
      /* Same corner radius as dashboard buttons */
      font-weight: 700;
      font-size: 0.9rem;
      padding: 0.85rem 1.5rem;
      transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
      cursor: pointer;
    }

    #logoutModal .logout-cancel-btn:hover {
      background-color: #e2e8f0;
      color: var(--sti-navy);
      transform: translateY(-2px);
      /* Subtle hover elevation */
      box-shadow: 0 4px 12px rgba(0, 36, 71, 0.04);
    }

    #logoutModal .logout-confirm-btn {
      flex: 1;
      background-color: #ff5b5b;
      /* APRISM's existing logout red */
      color: #ffffff;
      border-radius: 16px;
      /* Consistent with dashboard button styles */
      font-weight: 700;
      font-size: 0.9rem;
      padding: 0.85rem 1.5rem;
      transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
      cursor: pointer;
      border: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    #logoutModal .logout-confirm-btn:hover {
      background-color: #ff3333;
      transform: translateY(-2px);
      /* Subtle hover elevation */
      box-shadow: 0 8px 20px -4px rgba(255, 91, 91, 0.35);
      /* Premium soft colored shadow */
      color: #ffffff;
    }

    /* Responsive toggles */
    .mobile-menu-toggle {
      display: none;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #f8fafc;
      border: 1px solid #f1f5f9;
      align-items: center;
      justify-content: center;
      color: #64748b;
      cursor: pointer;
    }

    /* Empty states */
    .empty-state {
      padding: 3rem 1.5rem;
      text-align: center;
      color: #94a3b8;
    }

    .empty-state i {
      width: 48px;
      height: 48px;
      margin-bottom: 1rem;
      color: #cbd5e1;
    }

    .empty-state p {
      font-size: 0.85rem;
      font-weight: 600;
      margin: 0;
    }

    @media (max-width: 1200px) {
      .sidebar {
        left: -280px;
      }

      .sidebar.open {
        left: 0;
      }

      .main-content {
        margin-left: 0;
      }

      .mobile-menu-toggle {
        display: flex;
      }
    }

    @media (max-width: 768px) {
      .top-navbar {
        flex-direction: column;
        align-items: stretch;
        border-radius: 16px;
        padding: 1.25rem;
      }

      .navbar-left {
        justify-content: space-between;
      }

      .search-wrapper {
        width: 100%;
      }

      .navbar-right {
        justify-content: space-between;
        flex-wrap: wrap;
        margin-top: 0.5rem;
      }

      .stat-card {
        border-radius: 20px;
        padding: 1.25rem;
      }

      .section-card {
        border-radius: 24px;
        padding: 1.5rem;
      }

      .toast-container-custom {
        top: auto;
        bottom: 2rem;
        right: 1rem;
        left: 1rem;
      }

      .toast-custom {
        max-width: none;
      }
    }
  </style>
</head>

<body>

  <!-- Sidebar Component -->
  <aside class="sidebar" id="sidebar">
    <div>
      <div class="sidebar-brand">
        <img src="../assets/images/aprism-logo.png" alt="APRISM Logo" style="width: 44px; height: auto;">
        <div class="brand-text">
          <h2 class="brand-title">APRISM</h2>
          <p class="brand-subtitle">STI Dasmariñas</p>
        </div>
      </div>

      <nav>
        <ul class="sidebar-menu">
          <li>
            <a href="<?= APP_URL ?>/dashboard/technical_admin.php" class="sidebar-link active">
              <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <li>
            <a href="<?= APP_URL ?>/dashboard/technical_admin_users.php" class="sidebar-link">
              <i data-lucide="users" class="w-5 h-5"></i>
              <span>Users</span>
            </a>
          </li>
          <li>
            <a href="#" class="sidebar-link">
              <i data-lucide="settings" class="w-5 h-5"></i>
              <span>Settings</span>
            </a>
          </li>
        </ul>
      </nav>
    </div>

    <div class="sidebar-footer">
      <a href="#" class="logout-link" data-bs-toggle="modal" data-bs-target="#logoutModal">
        <i data-lucide="log-out" class="w-5 h-5 text-danger"></i>
        <span>Logout</span>
      </a>
    </div>
  </aside>

  <!-- Main Content Wrapper -->
  <main class="main-content">

    <!-- Top Navigation Bar -->
    <header class="top-navbar">
      <div class="navbar-left">
        <button class="mobile-menu-toggle" id="menuToggle">
          <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
        <button class="back-btn" id="sidebarToggle">
          <i data-lucide="chevron-left" id="sidebarToggleIcon" class="sidebar-toggle-icon"> </i>
        </button>
        <div class="search-wrapper">
          <i data-lucide="search" class="search-icon"></i>
          <input type="text" class="navbar-search" placeholder="Search..." />
        </div>
      </div>

      <div class="navbar-right">
        <div class="active-term-badge">
          <i data-lucide="calendar" class="w-4 h-4"></i>
          <span>Academic Term: --</span>
        </div>
        <div class="notification-bell">
          <i data-lucide="bell" class="w-5 h-5"></i>
          <span class="notification-dot"></span>
        </div>
        <div class="user-profile">
          <div class="profile-text">
            <h4 class="profile-name"><?= htmlspecialchars($fullName) ?></h4>
            <p class="profile-role">Technical Administrator</p>
          </div>
          <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
        </div>
      </div>
    </header>

    <!-- Dashboard Title Banner -->
    <section class="dashboard-header-container">
      <div>
        <h1 class="dashboard-title">Technical Administrator Dashboard</h1>
        <div class="status-indicator">
          <span class="status-pulse"></span>
          <strong>Fully Operational</strong>
        </div>
      </div>
      <button class="refresh-btn">
        <i data-lucide="refresh-cw" class="w-5 h-5 animate-hover-spin"></i>
      </button>
    </section>

    <!-- Quick Stats Grid -->
    <section class="row g-4 mb-4">
      <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon-box health">
            <i data-lucide="shield-check" class="w-6 h-6"></i>
          </div>
          <p class="stat-label">System Health</p>
          <h3 class="stat-value" id="statHealth">--</h3>
          <span class="stat-card-badge"></span>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon-box sessions">
            <i data-lucide="users" class="w-6 h-6"></i>
          </div>
          <p class="stat-label">Active Sessions</p>
          <h3 class="stat-value" id="statSessions">--</h3>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon-box db">
            <i data-lucide="database" class="w-6 h-6"></i>
          </div>
          <p class="stat-label">DB Connectivity</p>
          <h3 class="stat-value" id="statDB">--</h3>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card">
          <div class="stat-icon-box disk">
            <i data-lucide="hard-drive" class="w-6 h-6"></i>
          </div>
          <p class="stat-label">Disk Allocation</p>
          <h3 class="stat-value" id="statDisk">--</h3>
        </div>
      </div>
    </section>

    <!-- Secondary Columns Grid (Audit Logs + Backups) -->
    <section class="row g-4">

      <!-- System Audit Logs Section -->
      <div class="col-12 col-xl-8">
        <div class="section-card">
          <div class="section-card-header">
            <h2 class="section-title">System Audit Log</h2>
            <div class="audit-search-wrapper">
              <i data-lucide="search" class="audit-search-icon"></i>
              <input type="text" class="audit-search-input" id="auditSearchInput" placeholder="Search audit trail..." />
            </div>
          </div>

          <div class="audit-table-container">
            <div class="table-responsive">
              <table class="table audit-table align-middle">
                <thead>
                  <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th class="text-center">IP Address</th>
                    <th class="text-center">Details</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="6">
                      <div class="empty-state">
                        <i data-lucide="file-search"></i>
                        <p class="mb-1 fw-bold">No audit records available.</p>
                        <small>Audit logs will appear here once the Audit Log module has been implemented.</small>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Backups & Recovery Section -->
      <div class="col-12 col-xl-4">
        <div class="section-card">
          <div class="section-card-header">
            <h2 class="section-title">Backups & Recovery</h2>
          </div>

          <div class="backup-stack">
            <div class="empty-state">
              <i data-lucide="database-backup"></i>
              <p class="mb-1 fw-bold">Coming Soon</p>
              <small>
                The Backup & Recovery module will be implemented in a future APRISM phase.
              </small>
            </div>
          </div>
        </div>
      </div>

    </section>

  </main>

  <?php require_once __DIR__ . '/../includes/logout_modal.php'; ?>

  <!-- Lucide Icons CDN -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- Bootstrap 5 JS Bundle (Includes Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>

  <!-- Custom Frontend Interaction Logic -->
  <script>
    // Mobile Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
    const menuToggle = document.getElementById('menuToggle');

    menuToggle?.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });

    // Close Sidebar on Mobile when Clicking Outside
    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 1200) {
        if (
          !sidebar.contains(e.target) &&
          !menuToggle.contains(e.target) &&
          sidebar.classList.contains('open')
        ) {
          sidebar.classList.remove('open');
        }
      }
    });

    sidebarToggle?.addEventListener('click', () => {

      sidebar.classList.toggle('collapsed');
      mainContent.classList.toggle('expanded');

      sidebarToggle.classList.toggle('rotated');

    });

    // Initial icon render
    lucide.createIcons();
  </script>
</body>

</html>