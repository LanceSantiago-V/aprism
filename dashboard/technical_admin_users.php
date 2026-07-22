<?php

require_once __DIR__ . '/../includes/role_helper.php';


$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../includes/flash_message.php';
require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../config/database.php';

try {

  $sql = "
        SELECT
            u.user_id,
            u.role_id,
            u.employee_number,
            u.username,
            u.email,
            u.first_name,
            u.last_name,
            u.account_status,
            u.last_login_at,
            r.role_name
        FROM users u
        INNER JOIN roles r
            ON u.role_id = r.role_id
        ORDER BY
            u.last_name ASC,
            u.first_name ASC
    ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute();

  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

  $users = [];

}

$fullName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$initials =
  strtoupper(substr($_SESSION['first_name'], 0, 1)) .
  strtoupper(substr($_SESSION['last_name'], 0, 1));

$successMessage = $flash['success'] ?? null;

$temporaryPassword = $_SESSION['temporary_password'] ?? null;

$temporaryPasswordUser =
  $_SESSION['temporary_password_user'] ?? null;

unset($_SESSION['temporary_password']);
unset($_SESSION['temporary_password_user']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Users - APRISM</title>

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

    /* Page Title Banner */
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

    /* Header Actions Group */
    .header-actions-group {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .header-action-btn {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      border: 1px solid rgba(226, 232, 240, 0.8);
      background-color: #ffffff;
      color: #64748b;
    }

    .header-action-btn:hover {
      background-color: #f8fafc;
      transform: translateY(-2px);
    }

    .header-action-btn.btn-primary-action {
      background-color: var(--sti-blue);
      color: #ffffff;
      border: none;
      box-shadow: 0 8px 16px rgba(13, 110, 253, 0.15);
    }

    .header-action-btn.btn-primary-action:hover {
      background-color: var(--sti-navy);
    }

    .header-action-btn.btn-import-action {
      color: var(--sti-blue);
    }

    .header-action-btn.btn-export-action {
      color: #f59e0b;
    }

    /* Content Cards */
    .section-card {
      background-color: #ffffff;
      border-radius: 32px;
      padding: 2.25rem;
      border: 1px solid rgba(226, 232, 240, 0.8);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.01);
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    /* Filtering Controls */
    .filter-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .filter-search-wrapper {
      position: relative;
    }

    .filter-search-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      width: 16px;
      height: 16px;
    }

    .filter-search-input {
      width: 100%;
      background-color: #f8fafc;
      border: 1px solid #f1f5f9;
      border-radius: 16px;
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      font-size: 0.8rem;
      outline: none;
      color: var(--sti-navy);
      transition: all 0.2s ease;
    }

    .filter-search-input:focus {
      background-color: #ffffff;
      border-color: #cbd5e1;
    }

    .filter-select {
      background-color: #f8fafc;
      border: 1px solid #f1f5f9;
      border-radius: 16px;
      padding: 0.75rem 1rem;
      font-size: 0.75rem;
      font-weight: 700;
      color: #475569;
      outline: none;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      transition: all 0.2s ease;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 1rem center;
      background-size: 1rem;
      appearance: none;
    }

    .filter-select:focus {
      background-color: #ffffff;
      border-color: #cbd5e1;
    }

    /* Table styling */
    .user-table-container {
      border-radius: 20px;
      overflow: hidden;
      border: 1px solid #f1f5f9;
    }

    .user-table {
      margin: 0;
      width: 100%;
    }

    .user-table th {
      background-color: #f8fafc;
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: #94a3b8;
      padding: 1.15rem 1.25rem;
      border-bottom: 1px solid #e2e8f0;
      text-align: center;
    }

    .user-table th.col-name {
      text-align: left;
    }

    .user-table td {
      padding: 1.15rem 1.25rem;
      font-size: 0.8rem;
      vertical-align: middle;
      color: #475569;
      border-bottom: 1px solid #f1f5f9;
      text-align: center;
    }

    .user-table td.col-name {
      text-align: left;
    }

    .user-table tr:last-child td {
      border-bottom: none;
    }

    .user-table tbody tr:hover {
      background-color: #f8fafc;
    }

    .font-mono-custom {
      font-family: var(--font-mono);
      font-size: 0.75rem;
      color: #94a3b8;
    }

    /* Avatar Initials */
    .avatar-initials {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background-color: #f1f5f9;
      border: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--sti-navy);
      font-weight: 800;
      font-size: 0.8rem;
    }

    /* Custom badges */
    .badge-role {
      font-size: 0.55rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 0.35rem 0.75rem;
      border-radius: 30px;
      display: inline-block;
    }

    .badge-role.role-admin {
      background-color: rgba(13, 110, 253, 0.08);
      color: var(--sti-blue);
      border: 1px solid rgba(13, 110, 253, 0.15);
    }

    .badge-role.role-teacher {
      background-color: rgba(99, 102, 241, 0.08);
      color: #4f46e5;
      border: 1px solid rgba(99, 102, 241, 0.15);
    }

    .badge-role.role-academic {
      background-color: rgba(168, 85, 247, 0.08);
      color: #7e22ce;
      border: 1px solid rgba(168, 85, 247, 0.15);
    }

    .badge-role.role-guidance {
      background-color: rgba(245, 158, 11, 0.08);
      color: #d97706;
      border: 1px solid rgba(245, 158, 11, 0.15);
    }

    /* Permissions tiny badges */
    .badge-perm {
      font-size: 0.55rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.02em;
      padding: 0.2rem 0.5rem;
      border-radius: 6px;
      background-color: #f1f5f9;
      color: #475569;
      border: 1px solid #e2e8f0;
      margin: 0.1rem;
      display: inline-block;
    }

    .badge-perm.perm-user-mgmt {
      background-color: #fff1f2;
      color: #e11d48;
      border: 1px solid #ffe4e6;
    }

    .badge-perm.perm-adviser {
      background-color: #f3e8ff;
      color: #7e22ce;
      border: 1px solid #e9d5ff;
    }

    .badge-perm.perm-intervention {
      background-color: #ecfdf5;
      color: #059669;
      border: 1px solid #d1fae5;
    }

    /* Status dot badge */
    .status-dot-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .status-dot-badge.active {
      color: #10b981;
    }

    .status-dot-badge.disabled {
      color: #ef4444;
    }

    .status-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      display: inline-block;
    }

    .status-dot.active {
      background-color: #10b981;
    }

    .status-dot.disabled {
      background-color: #ef4444;
    }

    /* Actions buttons */
    .action-btn-container {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 0.25rem;
    }

    .action-row-btn {
      width: 28px;
      height: 28px;
      border-radius: 8px;
      background-color: transparent;
      border: none;
      color: #94a3b8;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .action-row-btn:hover {
      background-color: #ffffff;
    }

    .action-row-btn.btn-view:hover {
      color: var(--sti-blue);
    }

    .action-row-btn.btn-edit:hover {
      color: var(--sti-blue);
    }

    .action-row-btn.btn-permissions:hover {
      color: #d97706;
    }

    .action-row-btn.btn-reset:hover {
      color: #f59e0b;
    }

    .action-row-btn.btn-toggle-disable:hover {
      color: #ef4444;
    }

    .action-row-btn.btn-toggle-enable:hover {
      color: #10b981;
    }

    /* Empty states */
    .empty-state {
      padding: 4rem 2rem;
      text-align: center;
      color: #94a3b8;
    }

    .empty-state svg {
      width: 48px;
      height: 48px;
      margin-bottom: 1.25rem;
      color: #cbd5e1;
    }

    .empty-state p {
      font-size: 0.9rem;
      font-weight: 700;
      margin: 0;
      color: var(--sti-navy);
    }

    .empty-state small {
      display: block;
      margin-top: 0.35rem;
      font-size: 0.75rem;
      color: #94a3b8;
    }

    /* Custom Toast Notification */
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
      border-radius: 20px;
      padding: 1.25rem 1.5rem;
      box-shadow: 0 15px 45px rgba(0, 0, 0, 0.1);
      border: 1px solid rgba(226, 232, 240, 0.8);
      display: flex;
      align-items: center;
      gap: 0.85rem;
      pointer-events: auto;
      max-width: 380px;
      opacity: 0;
      transform: translateY(-20px);
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .toast-custom.show {
      opacity: 1;
      transform: translateY(0);
    }

    .toast-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .toast-icon.success {
      background-color: #e8f8f0;
      color: #10b981;
    }

    .toast-icon.info {
      background-color: #eff6ff;
      color: #3b82f6;
    }

    .toast-icon.warning {
      background-color: #fff7ed;
      color: #f97316;
    }

    .toast-content {
      flex: 1;
    }

    .toast-title {
      font-size: 0.65rem;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #94a3b8;
      margin: 0;
    }

    .toast-text {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--sti-navy);
      margin: 0.15rem 0 0 0;
    }

    /* Backdrop Blur */
    .modal-backdrop {
      background-color: #000c1a !important;
      opacity: 0 !important;
      transition: opacity 240ms cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .modal-backdrop.show {
      opacity: 0.32 !important;
      backdrop-filter: blur(5px) !important;
      -webkit-backdrop-filter: blur(5px) !important;
    }

    /* Dialog styling */
    .modal.fade {
      transition: opacity 240ms cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .modal.fade .modal-dialog {
      transform: scale(0.97) !important;
      opacity: 0 !important;
      transition: transform 240ms cubic-bezier(0.16, 1, 0.3, 1), opacity 240ms cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .modal.show .modal-dialog {
      transform: scale(1) !important;
      opacity: 1 !important;
    }

    .modal-content-custom {
      border: 1px solid rgba(226, 232, 240, 0.8) !important;
      border-radius: 30px !important;
      overflow: hidden;
      box-shadow: 0 15px 35px -5px rgba(0, 36, 71, 0.05), 0 5px 15px -3px rgba(0, 36, 71, 0.02) !important;
      background-color: #fafbfc !important;
      font-family: var(--font-sans);
    }

    .modal-header-custom {
      padding: 1.75rem 2rem 1.25rem 2rem;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .modal-header-icon-box {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .modal-header-icon-box.primary {
      background-color: #eff6ff;
      color: var(--sti-blue);
    }

    .modal-header-icon-box.warning {
      background-color: #fff7ed;
      color: #f59e0b;
    }

    .modal-header-icon-box.success {
      background-color: #e8f8f0;
      color: #10b981;
    }

    .modal-header-title-wrapper {
      margin-left: 0.85rem;
      flex: 1;
    }

    .modal-title-custom {
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--sti-navy);
      letter-spacing: -0.02em;
      margin: 0;
    }

    .modal-subtitle-custom {
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #94a3b8;
      margin: 0.15rem 0 0 0;
    }

    .modal-close-icon-btn {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background-color: #f1f5f9;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .modal-close-icon-btn:hover {
      background-color: #e2e8f0;
      color: var(--sti-navy);
    }

    .modal-body-custom {
      padding: 2rem;
    }

    .modal-footer-custom {
      padding: 1.25rem 2rem 1.75rem 2rem;
      border-top: 1px solid #f1f5f9;
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
    }

    /* Modal Forms */
    .form-group-custom {
      margin-bottom: 1.25rem;
    }

    .form-group-custom:last-child {
      margin-bottom: 0;
    }

    .form-label-custom {
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #94a3b8;
      margin-bottom: 0.5rem;
      display: block;
    }

    .form-control-custom {
      width: 100%;
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 0.75rem 1rem;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--sti-navy);
      outline: none;
      transition: all 0.2s ease;
    }

    .form-control-custom:focus {
      background-color: #ffffff;
      border-color: #cbd5e1;
      box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.05);
    }

    .form-control-custom:disabled {
      background-color: #f1f5f9;
      color: #94a3b8;
      cursor: not-allowed;
      border-color: #e2e8f0;
    }

    /* Modal Button actions */
    .modal-btn-dismiss {
      background-color: #f1f5f9;
      border: 1px solid #e2e8f0;
      color: #475569;
      border-radius: 14px;
      font-weight: 700;
      font-size: 0.85rem;
      padding: 0.75rem 1.5rem;
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .modal-btn-dismiss:hover {
      background-color: #e2e8f0;
      color: var(--sti-navy);
      transform: translateY(-1px);
    }

    .modal-btn-action {
      background-color: var(--sti-blue);
      border: none;
      color: #ffffff;
      border-radius: 14px;
      font-weight: 700;
      font-size: 0.85rem;
      padding: 0.75rem 1.5rem;
      transition: all 0.2s ease;
      cursor: pointer;
      box-shadow: 0 6px 12px rgba(13, 110, 253, 0.1);
    }

    .modal-btn-action:hover {
      background-color: var(--sti-navy);
      transform: translateY(-1px);
    }

    .modal-btn-action.btn-danger-action {
      background-color: #ff5b5b;
      box-shadow: 0 6px 12px rgba(255, 91, 91, 0.1);
    }

    .modal-btn-action.btn-danger-action:hover {
      background-color: #ff3333;
    }

    /* ==========================================
   Shared Logout Modal Styling
   ========================================== */

    #logoutModal.modal.fade {
      transition: opacity 240ms cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    #logoutModal.modal.fade .modal-dialog {
      transform: scale(0.97);
      opacity: 0;
      transition:
        transform 240ms cubic-bezier(0.16, 1, 0.3, 1),
        opacity 240ms cubic-bezier(0.16, 1, 0.3, 1);
    }

    #logoutModal.modal.show .modal-dialog {
      transform: scale(1);
      opacity: 1;
    }

    #logoutModal .modal-dialog {
      max-width: 420px;
    }

    #logoutModal .modal-content {
      background: #fafbfc;
      border: 1px solid rgba(226, 232, 240, .8);
      border-radius: 30px;
      box-shadow:
        0 15px 35px -5px rgba(0, 36, 71, .05),
        0 5px 15px -3px rgba(0, 36, 71, .02);
      padding: 2.25rem 2rem;
      overflow: hidden;
      font-family: var(--font-sans);
    }

    #logoutModal .modal-body {
      padding-top: .5rem;
    }

    #logoutModal .modal-title {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--sti-navy);
      letter-spacing: -.02em;
    }

    #logoutModal .logout-message {
      font-size: .95rem;
      line-height: 1.6;
      color: #64748b;
      font-weight: 500;
    }

    #logoutModal .logout-message strong {
      color: var(--sti-navy);
    }

    #logoutModal .logout-cancel-btn,
    #logoutModal .logout-confirm-btn {
      flex: 1;
      border-radius: 16px;
      padding: .85rem 1.5rem;
      font-size: .9rem;
      font-weight: 700;
      transition: all .22s cubic-bezier(.16, 1, .3, 1);
    }

    #logoutModal .logout-cancel-btn {
      background: #f1f5f9;
      border: 1px solid #e2e8f0;
      color: #475569;
    }

    #logoutModal .logout-cancel-btn:hover {
      background: #e2e8f0;
      color: var(--sti-navy);
      transform: translateY(-2px);
    }

    #logoutModal .logout-confirm-btn {
      background: #ff5b5b;
      color: #fff;
      border: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    #logoutModal .logout-confirm-btn:hover {
      background: #ff3333;
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px -4px rgba(255, 91, 91, .35);
    }

    /* Warning alert card */
    .alert-card-warning {
      background-color: #fff7ed;
      border: 1px solid #ffedd5;
      border-radius: 16px;
      padding: 1rem 1.25rem;
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      margin-top: 1.25rem;
    }

    .alert-card-warning svg {
      width: 18px;
      height: 18px;
      color: #f97316;
      flex-shrink: 0;
      margin-top: 0.15rem;
    }

    .alert-card-warning-text {
      font-size: 0.75rem;
      font-weight: 600;
      color: #c2410c;
      line-height: 1.5;
      margin: 0;
    }

    /* Drag Drop Area */
    .drag-drop-area {
      border: 2px dashed #cbd5e1;
      border-radius: 20px;
      padding: 2.5rem 1.5rem;
      text-align: center;
      background-color: #f8fafc;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .drag-drop-area.dragover {
      border-color: var(--sti-blue);
      background-color: rgba(13, 110, 253, 0.03);
    }

    .drag-drop-icon-circle {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background-color: #ffffff;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem auto;
      color: var(--sti-blue);
    }

    .drag-drop-primary-text {
      font-size: 0.85rem;
      font-weight: 700;
      color: #475569;
      margin-bottom: 0.25rem;
    }

    .drag-drop-secondary-text {
      font-size: 0.7rem;
      font-weight: 600;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    /* Checklist Items styling */
    .permissions-checklist {
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 18px;
      padding: 1.25rem;
      max-height: 240px;
      overflow-y: auto;
    }

    .checklist-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background-color: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 0.75rem 1rem;
      margin-bottom: 0.5rem;
      cursor: pointer;
      transition: all 0.2s ease;
      user-select: none;
    }

    .checklist-item:last-child {
      margin-bottom: 0;
    }

    .checklist-item:hover {
      border-color: #cbd5e1;
    }

    .checklist-item-left {
      display: flex;
      flex-direction: column;
      gap: 0.15rem;
    }

    .checklist-item-title {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--sti-navy);
    }

    .checklist-item-desc {
      font-size: 0.65rem;
      font-weight: 600;
      color: #94a3b8;
    }

    .checklist-checkbox {
      width: 18px;
      height: 18px;
      border-radius: 6px;
      border: 1px solid #cbd5e1;
      cursor: pointer;
      accent-color: var(--sti-blue);
    }

    /* View Modal Specific Header */
    .view-profile-header {
      background-color: var(--sti-navy);
      padding: 2rem;
      color: #ffffff;
      position: relative;
    }

    .view-profile-avatar {
      width: 58px;
      height: 58px;
      border-radius: 50%;
      background-color: #ffffff;
      border: 3px solid rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--sti-navy);
      font-weight: 900;
      font-size: 1.25rem;
    }

    .view-profile-details {
      margin-left: 1rem;
    }

    .view-profile-name {
      font-size: 1.25rem;
      font-weight: 800;
      letter-spacing: -0.02em;
      margin: 0;
    }

    .view-profile-sub {
      font-size: 0.7rem;
      font-weight: 700;
      color: var(--sti-yellow);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-top: 0.15rem;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }

    .view-details-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    .view-detail-card {
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 1rem;
    }

    .view-detail-label {
      font-size: 0.6;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #94a3b8;
      margin-bottom: 0.25rem;
    }

    .view-detail-value {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--sti-navy);
      margin: 0;
    }

    .view-detail-value.mono {
      font-family: var(--font-mono);
      font-size: 0.75rem;
    }

    /* Temporary password display area */
    .temp-password-box {
      background-color: #ecfdf5;
      border: 1px dashed #a7f3d0;
      border-radius: 16px;
      padding: 1.25rem;
      text-align: center;
      margin: 1rem 0;
    }

    .temp-password-value {
      font-family: var(--font-mono);
      font-size: 1.5rem;
      font-weight: 800;
      color: #047857;
      letter-spacing: 0.1em;
      user-select: all;
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

    #sidebarToggleIcon {
      transition: transform .30s ease;
    }

    #sidebarToggle.rotated #sidebarToggleIcon {
      transform: rotate(180deg);
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

      .filter-grid {
        grid-template-columns: 1fr;
      }

      .section-card {
        border-radius: 24px;
        padding: 1.5rem;
      }
    }
  </style>
</head>

<body>

  <!-- Sidebar Component -->
  <aside class="sidebar" id="sidebar">
    <div>
      <div class="sidebar-brand">
        <!-- Exact image loading pattern from reference -->
        <img src="../assets/images/aprism-logo.png" alt="APRISM Logo" style="width: 44px; height: auto;"
          onerror="this.style.display='none'; document.getElementById('brandFallback').style.display='flex';" />
        <div id="brandFallback" class="brand-logo-box" style="display: none;">A</div>
        <div class="brand-text">
          <h2 class="brand-title">APRISM</h2>
          <p class="brand-subtitle">STI Dasmariñas</p>
        </div>
      </div>

      <nav>
        <ul class="sidebar-menu">
          <li>
            <a href="technical_admin.php" class="sidebar-link">
              <i data-lucide="layout-dashboard"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <li>
            <a href="technical_admin_users.php" class="sidebar-link active">
              <i data-lucide="users"></i>
              <span>Users</span>
            </a>
          </li>
          <li>
            <a href="#" class="sidebar-link">
              <i data-lucide="settings"></i>
              <span>Settings</span>
            </a>
          </li>
        </ul>
      </nav>
    </div>

    <div class="sidebar-footer">
      <a href="#" class="logout-link" data-bs-toggle="modal" data-bs-target="#logoutModal">
        <i data-lucide="log-out"></i>
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
          <i data-lucide="menu"></i>
        </button>
        <button class="back-btn" id="sidebarToggle">
          <i data-lucide="chevron-left" id="sidebarToggleIcon"></i>
        </button>
        <div class="search-wrapper">
          <i data-lucide="search" class="search-icon"></i>
          <input type="text" class="navbar-search" placeholder="Search Users" id="globalSearch" />
        </div>
      </div>

      <div class="navbar-right">
        <div class="active-term-badge">
          <i data-lucide="calendar"></i>
          <span>Academic Term: --</span>
        </div>
        <div class="notification-bell">
          <i data-lucide="bell"></i>
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

    <!-- Page Header Title & Core Actions -->
    <section class="dashboard-header-container">
      <div>
        <h1 class="dashboard-title">Manage Users</h1>
        <div class="status-indicator">
          <span class="status-pulse"></span>
          <strong>Active User Database</strong>
        </div>
      </div>
      <div class="header-actions-group">
        <!-- Primary Action: Create User -->
        <button class="header-action-btn btn-primary-action" id="btnAddUser" title="Create User">
          <i data-lucide="user-plus"></i>
        </button>
        <!-- Secondary Action: Import Users -->
        <button class="header-action-btn btn-import-action" id="btnImportUsers" title="Import Users">
          <i data-lucide="upload"></i>
        </button>
        <!-- Secondary Action: Export Users -->
        <button class="header-action-btn btn-export-action" id="btnExportUsers" title="Export Users">
          <i data-lucide="file-spreadsheet"></i>
        </button>
      </div>
    </section>

    <!-- Main Content Panel -->
    <section class="section-card">

      <!-- Table Search & Filter Controls -->
      <div class="filter-grid">
        <div class="filter-search-wrapper">
          <i data-lucide="search" class="filter-search-icon"></i>
          <input type="text" class="filter-search-input" id="searchFilter" placeholder="Search by name or email..." />
        </div>

        <select class="filter-select" id="roleFilter">
          <option value="All">All Main Roles</option>
          <option value="Admin">Technical Admin</option>
          <option value="Teacher">Teacher</option>
          <option value="AcademicHead">Academic Admin/Head</option>
          <option value="Guidance">Disciplinary Officer</option>
        </select>

        <select class="filter-select" id="permissionFilter">
          <option value="All">All Permissions</option>
          <option value="Adviser View">Adviser View</option>
          <option value="Program-Level View">Program-Level View</option>
          <option value="Report Export">Report Export</option>
          <option value="Intervention Access">Intervention Access</option>
          <option value="User Management">User Management</option>
          <option value="Template Configuration">Template Configuration</option>
        </select>

        <select class="filter-select" id="statusFilter">
          <option value="All">All Statuses</option>
          <option value="Active">Active Only</option>
          <option value="Disabled">Disabled Only</option>
        </select>
      </div>

      <!-- Users Directory Table Grid -->
      <div class="user-table-container">
        <div class="table-responsive">
          <table class="table user-table align-middle">
            <thead>
              <tr>
                <th class="col-name" style="width: 25%;">Name</th>
                <th style="width: 20%;">Institutional Email</th>
                <th style="width: 15%;">Role</th>
                <th style="width: 20%;">Permissions</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 10%;">Last Login</th>
                <th style="width: 10%;">Actions</th>
              </tr>
            </thead>
            <tbody id="usersTableBody">

              <?php if (empty($users)): ?>

                <tr>
                  <td colspan="7">
                    <div class="empty-state">
                      <i data-lucide="users"></i>
                      <p class="mb-1 fw-bold">No users available.</p>
                      <small>
                        No records found. Please use the
                        "Add User" button to create the first account.
                      </small>
                    </div>
                  </td>
                </tr>

              <?php else: ?>

                <?php foreach ($users as $user): ?>

                  <tr>

                    <td>
                      <strong>
                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                      </strong>
                      <br>
                      <small class="text-muted">
                        <?= htmlspecialchars($user['employee_number']) ?>
                      </small>
                    </td>

                    <td>
                      <?= htmlspecialchars($user['email']) ?>
                    </td>

                    <td>
                      <?= htmlspecialchars($user['role_name']) ?>
                    </td>

                    <td>
                      --
                    </td>

                    <td>
                      <?= htmlspecialchars($user['account_status']) ?>
                    </td>

                    <td>

                      <?php if ($user['last_login_at'] === null): ?>

                        Never

                      <?php else: ?>

                        <?= htmlspecialchars($user['last_login_at']) ?>

                      <?php endif; ?>

                    </td>

                    <td>

                      <div class="action-btn-container">

                        <button type="button" class="action-row-btn btn-view" data-user-id="<?= (int) $user['user_id'] ?>"
                          data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>" title="View User">

                          <i data-lucide="eye"></i>

                        </button>

                        <button type="button" class="action-row-btn btn-edit" data-user-id="<?= (int) $user['user_id'] ?>"
                          data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>" title="Edit User">

                          <i data-lucide="edit"></i>

                        </button>

                        <button type="button" class="action-row-btn btn-permissions"
                          data-user-id="<?= (int) $user['user_id'] ?>"
                          data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>"
                          title="Manage Permissions">

                          <i data-lucide="shield"></i>

                        </button>

                        <button type="button" class="action-row-btn btn-reset" data-user-id="<?= (int) $user['user_id'] ?>"
                          data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>" title="Reset Password">

                          <i data-lucide="key"></i>

                        </button>

                        <?php
                        $isActive = $user['account_status'] === 'Active';
                        ?>

                        <button type="button"
                          class="action-row-btn <?= $isActive ? 'btn-toggle-disable' : 'btn-toggle-enable' ?>"
                          data-user-id="<?= (int) $user['user_id'] ?>"
                          data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>"
                          title="<?= $isActive ? 'Disable Account' : 'Enable Account' ?>">
                          <i data-lucide="<?= $isActive ? 'ban' : 'check-circle' ?>"></i>
                        </button>

                      </div>

                    </td>

                  </tr>

                <?php endforeach; ?>

              <?php endif; ?>

            </tbody>
          </table>
        </div>
      </div>

    </section>

  </main>

  <!-- ==========================================
       MODALS
       ========================================== -->

  <?php require_once __DIR__ . '/../includes/logout_modal.php'; ?>

  <!-- Create User Modal -->
  <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content modal-content-custom">
        <div class="modal-header-custom">
          <div class="d-flex align-items-center">
            <div class="modal-header-icon-box primary">
              <i data-lucide="user-plus"></i>
            </div>
            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom">Create User</h3>
              <p class="modal-subtitle-custom">Primary directory entry registration</p>
            </div>
          </div>
          <button class="modal-close-icon-btn" data-bs-dismiss="modal">
            <i data-lucide="x"></i>
          </button>
        </div>
        <form id="addUserForm" action="<?= APP_URL ?>/actions/users/create_user.php" method="POST">
          <div class="modal-body-custom">

            <div class="row g-3">
              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Employee Number</label>
                <input type="text" class="form-control-custom font-mono" id="addEmployeeNumber" name="employee_number"
                  placeholder="e.g. EMP-2026-001" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Username</label>
                <input type="text" class="form-control-custom" id="addUsername" name="username"
                  placeholder="e.g. msantos" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">First Name</label>
                <input type="text" class="form-control-custom" id="addFirstName" name="first_name"
                  placeholder="e.g. Maria" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Last Name</label>
                <input type="text" class="form-control-custom" id="addLastName" name="last_name"
                  placeholder="e.g. Santos" required />
              </div>

              <div class="col-12 form-group-custom">
                <label class="form-label-custom">Institutional Email</label>
                <input type="email" class="form-control-custom" id="addEmail" name="email"
                  placeholder="e.g. maria.santos@dasmarinas.sti.edu.ph" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Role</label>
                <select class="form-control-custom" id="addRole" name="role_id" required>
                  <option value="1">Technical Administrator</option>
                  <option value="2">Academic Head</option>
                  <option value="3">Teacher</option>
                  <option value="4">Disciplinary Officer</option>
                </select>
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Status</label>
                <select class="form-control-custom" id="addStatus" name="account_status" required>
                  <option value="Active">Active</option>
                  <option value="Disabled">Disabled</option>
                </select>
              </div>
            </div>

            <!-- Permissions Checklist inside the create dialog -->
            <div class="form-group-custom mt-4">
              <label class="form-label-custom">Initial Security Clearances</label>
              <div class="permissions-checklist" id="addPermissionsContainer">
                <!-- Populated dynamically -->
              </div>
            </div>

          </div>
          <div class="modal-footer-custom">
            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="modal-btn-action">Create User</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content modal-content-custom">
        <div class="modal-header-custom">
          <div class="d-flex align-items-center">
            <div class="modal-header-icon-box primary">
              <i data-lucide="edit"></i>
            </div>
            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom">Save Changes</h3>
              <p class="modal-subtitle-custom">Modify core administrative profile</p>
            </div>
          </div>
          <button class="modal-close-icon-btn" data-bs-dismiss="modal">
            <i data-lucide="x"></i>
          </button>
        </div>
        <form id="editUserForm" action="<?= APP_URL ?>/actions/users/update_user.php" method="POST">

          <input type="hidden" name="user_id" id="editUserId">
          <div class="modal-body-custom">

            <div class="row g-3">
              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Employee Number</label>
                <input type="text" class="form-control-custom font-mono" id="editEmployeeNumber" disabled />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Username</label>
                <input type="text" class="form-control-custom" id="editUsername" name="username" placeholder="Username"
                  required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">First Name</label>
                <input type="text" class="form-control-custom" id="editFirstName" name="first_name"
                  placeholder="First Name" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Last Name</label>
                <input type="text" class="form-control-custom" id="editLastName" name="last_name"
                  placeholder="Last Name" required />
              </div>

              <div class="col-12 form-group-custom">
                <label class="form-label-custom">Institutional Email</label>
                <input type="email" class="form-control-custom" id="editEmail" name="email"
                  placeholder="Institutional Email" required />
              </div>

              <select class="form-control-custom" id="editRole" name="role_id" required>

                <option value="1">Technical Administrator</option>
                <option value="2">Academic Head</option>
                <option value="3">Teacher</option>
                <option value="4">Disciplinary Officer</option>
              </select>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Status</label>
                <select class="form-control-custom" id="editStatus" name="account_status" required>
                  <option value="Active">Active</option>
                  <option value="Disabled">Disabled</option>
                </select>
              </div>
            </div>

            <!-- Deletion warning alert box -->
            <div class="alert-card-warning">
              <i data-lucide="alert-circle"></i>
              <p class="alert-card-warning-text">
                <strong>Platform Data Policy Note:</strong> Deleting institutional personnel records permanently is
                restricted to maintain Student Advisory histories, Intervention Referrals, and system auditing logs. Set
                the status to <strong>Disabled</strong> to prevent future access instead.
              </p>
            </div>

          </div>
          <div class="modal-footer-custom">
            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="modal-btn-action">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Manage Permissions Modal -->
  <div class="modal fade" id="permissionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">
        <div class="modal-header-custom">
          <div class="d-flex align-items-center">
            <div class="modal-header-icon-box warning">
              <i data-lucide="shield"></i>
            </div>
            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom">Manage Permissions</h3>
              <p class="modal-subtitle-custom">Configure security scopes & clearances</p>
            </div>
          </div>
          <button class="modal-close-icon-btn" data-bs-dismiss="modal">
            <i data-lucide="x"></i>
          </button>
        </div>
        <form id="permissionsForm">
          <input type="hidden" id="permsUserId" />
          <div class="modal-body-custom">
            <p class="text-xs text-muted mb-3" style="font-size: 0.75rem; font-weight: 500; line-height: 1.5;">
              Modifying these credentials directly overrides the default clearances assigned to this personnel's role.
              Check or uncheck to adjust user access.
            </p>

            <div class="permissions-checklist" id="editPermissionsContainer">
              <!-- Populated dynamically -->
            </div>

            <!-- Role transformation alert box -->
            <div class="alert-card-warning mt-3"
              style="background-color: #f3e8ff; border-color: #e9d5ff; color: #6b21a8;">
              <i data-lucide="sparkles" style="color: #a855f7;"></i>
              <p class="alert-card-warning-text" style="color: #6b21a8;">
                <strong>Role Transformation Note:</strong> Applying the <strong>Adviser View</strong> clearance to a
                general <strong>Teacher</strong> accounts transforms them into a Class Adviser, unlocking the advisory
                student rosters.
              </p>
            </div>

          </div>
          <div class="modal-footer-custom">
            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="modal-btn-action">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View User Modal -->
  <div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">
        <div class="view-profile-header d-flex align-items-center">
          <div class="view-profile-avatar" id="viewUserAvatar">--</div>
          <div class="view-profile-details">
            <h3 class="view-profile-name" id="viewUserName">--</h3>
            <div class="view-profile-sub">
              <i data-lucide="shield-check"></i>
              <span id="viewUserRole">--</span>
            </div>
          </div>
          <button class="modal-close-icon-btn" data-bs-dismiss="modal"
            style="position: absolute; top: 1.5rem; right: 1.5rem; background: rgba(255,255,255,0.15); color: white;">
            <i data-lucide="x"></i>
          </button>
        </div>
        <div class="modal-body-custom">

          <div class="view-details-grid mb-4">
            <div class="view-detail-card">
              <div class="view-detail-label">Employee Number</div>
              <p class="view-detail-value mono" id="viewUserEmpNum">--</p>
            </div>
            <div class="view-detail-card">
              <div class="view-detail-label">Username</div>
              <p class="view-detail-value" id="viewUserUsername">--</p>
            </div>
            <div class="view-detail-card">
              <div class="view-detail-label">Status</div>
              <div class="status-dot-badge active" id="viewUserStatusBadge">
                <span class="status-dot active"></span>
                <span class="status-text">--</span>
              </div>
            </div>
            <div class="view-detail-card">
              <div class="view-detail-label">Last Login</div>
              <p class="view-detail-value font-mono-custom" id="viewUserLastLogin">--</p>
            </div>
          </div>

          <div class="form-group-custom mb-4">
            <label class="form-label-custom">Institutional Email</label>
            <p class="fw-bold text-dark m-0" style="font-size: 0.85rem;" id="viewUserEmail">--</p>
          </div>

          <div class="form-group-custom">
            <label class="form-label-custom">Assigned Security Clearance Scopes</label>
            <div id="viewUserPermissions" class="pt-1">
              <!-- Populated dynamically -->
            </div>
          </div>

        </div>
        <div class="modal-footer-custom">
          <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal" style="width: 100%;">Close Profile
            Info</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Import Users Modal -->
  <div class="modal fade" id="importUsersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">
        <div class="modal-header-custom">
          <div class="d-flex align-items-center">
            <div class="modal-header-icon-box primary">
              <i data-lucide="upload"></i>
            </div>
            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom">Import Users</h3>
              <p class="modal-subtitle-custom">Batch enroll student counselors & academic heads</p>
            </div>
          </div>
          <button class="modal-close-icon-btn" data-bs-dismiss="modal">
            <i data-lucide="x"></i>
          </button>
        </div>
        <div class="modal-body-custom">
          <p class="text-xs text-muted mb-3" style="font-size: 0.75rem; font-weight: 500; line-height: 1.5;">
            Upload institutional rosters with coordinates configured in CSV or Excel spreadsheet files. Select file
            directories to map active student-advisory relationships.
          </p>

          <div class="drag-drop-area" id="dropzone">
            <div class="drag-drop-icon-circle">
              <i data-lucide="file-spreadsheet"></i>
            </div>
            <p class="drag-drop-primary-text">Drag and drop file here, or browse files</p>
            <p class="drag-drop-secondary-text">Supported formats: CSV, XLS, XLSX</p>
            <input type="file" id="importFileInput" accept=".csv, .xls, .xlsx" style="display: none;" />
          </div>

          <!-- Column schema template specification -->
          <div class="p-3 bg-light rounded-4 mt-3" style="border: 1px solid #e2e8f0;">
            <span class="form-label-custom" style="margin-bottom: 0.25rem;">Required Spreadsheet Schema:</span>
            <p class="font-mono-custom m-0 text-dark" style="font-size: 0.7rem; font-weight: 600;">employeeNumber,
              username, firstName, lastName, email, role, status, permissions</p>
            <small class="text-muted d-block mt-1" style="font-size: 0.65rem;">* Columns must precisely map to
              institutional values. Missing rows will fall back to placeholders.</small>
          </div>

        </div>
        <div class="modal-footer-custom">
          <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">Close Window</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Reset Password Confirmation Modal -->
  <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">

        <div class="modal-header-custom">

          <div class="d-flex align-items-center">

            <div class="modal-header-icon-box warning">
              <i data-lucide="key"></i>
            </div>

            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom">
                Reset Password
              </h3>

              <p class="modal-subtitle-custom">
                Generate a new temporary password
              </p>
            </div>

          </div>

          <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

            <i data-lucide="x"></i>

          </button>

        </div>

        <form id="resetPasswordForm" action="<?= APP_URL ?>/actions/users/reset_password.php" method="POST">

          <input type="hidden" name="user_id" id="resetUserId">

          <div class="modal-body-custom">

            <p style="
              font-size: 0.85rem;
              line-height: 1.6;
              color: #64748b;
            ">

              Are you sure you want to reset the password for
              <strong id="resetUserName">this user</strong>?

            </p>

            <div class="alert-card-warning">

              <i data-lucide="alert-circle"></i>

              <p class="alert-card-warning-text">

                A new temporary password will be generated.
                The user's current password will stop working
                immediately, and they will be required to change
                the temporary password during their next login.

              </p>

            </div>

          </div>

          <div class="modal-footer-custom">

            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

              Cancel

            </button>

            <button type="submit" class="modal-btn-action">

              Reset Password

            </button>

          </div>

        </form>

      </div>
    </div>
  </div>

  <!-- Account Status Confirmation Modal -->
  <div class="modal fade" id="accountStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">

        <div class="modal-header-custom">

          <div class="d-flex align-items-center">

            <div class="modal-header-icon-box warning">
              <i data-lucide="shield"></i>
            </div>

            <div class="modal-header-title-wrapper">

              <h3 class="modal-title-custom" id="accountStatusModalTitle">

                Update Account Status

              </h3>

              <p class="modal-subtitle-custom">

                Confirm this administrative action

              </p>

            </div>

          </div>

          <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

            <i data-lucide="x"></i>

          </button>

        </div>

        <form id="accountStatusForm" action="<?= APP_URL ?>/actions/users/toggle_account_status.php" method="POST">

          <input type="hidden" name="user_id" id="accountStatusUserId">

          <div class="modal-body-custom">

            <p id="accountStatusMessage" style="
              font-size:0.85rem;
              line-height:1.6;
              color:#64748b;
            ">

              --

            </p>

            <div class="alert-card-warning">

              <i data-lucide="alert-circle"></i>

              <p class="alert-card-warning-text">

                This action changes the user's ability to sign in.
                Existing academic records and audit logs remain
                preserved.

              </p>

            </div>

          </div>

          <div class="modal-footer-custom">

            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

              Cancel

            </button>

            <button type="submit" class="modal-btn-action" id="accountStatusSubmitButton">

              Confirm

            </button>

          </div>

        </form>

      </div>
    </div>
  </div>

  <!-- Temporary Password Security Token Modal -->
  <div class="modal fade" id="tempPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom" style="padding: 2.25rem 2rem;">
        <div class="modal-body p-0 text-center">
          <div class="logout-icon"
            style="width: 44px; height: 44px; border-radius: 50%; background-color: rgba(255, 199, 44, 0.08); color: #f59e0b; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
            <i data-lucide="key" style="width: 18px; height: 18px;"></i>
          </div>
          <h3 class="modal-title-custom mb-2 text-center" style="font-size: 1.3rem; font-weight: 700;">Security Code
            Generated</h3>
          <p class="logout-message"
            style="font-size: 0.95rem; color: #64748b; line-height: 1.6; font-weight: 500; margin-bottom: 1rem;">A
            temporary secure authorization code has been generated for:</p>

          <div class="p-3 rounded-4 mb-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
            <span id="tempPassUserName" class="d-block text-dark fw-bold" style="font-size: 0.85rem;">--</span>
          </div>

          <span class="form-label-custom" style="margin-bottom: 0.25rem;">Temporary Password</span>
          <div class="temp-password-box">

            <div class="d-flex align-items-center justify-content-between">

              <div class="temp-password-value" id="generatedTempPassword">
                --
              </div>

              <button type="button" id="copyTempPasswordBtn"
                class="btn btn-sm btn-light d-flex align-items-center gap-2" style="
                border-radius:12px;
                font-weight:700;
                padding:0.45rem 0.75rem;
            ">
                <i data-lucide="copy" id="copyPasswordIcon" style="width:16px;height:16px;"></i>

                <span id="copyPasswordText">
                  Copy
                </span>

              </button>

            </div>

          </div>

          <p class="text-muted mb-4" style="font-size: 0.65rem; font-weight: 500; line-height: 1.5;">
            This temporary password is shown only once for security reasons.
            Copy it before closing this dialog. If it is lost, simply perform
            another password reset to generate a new temporary password.
          </p>

          <button id="acknowledgeTempPasswordBtn" class="modal-btn-action w-100"
            data-bs-dismiss="modal">Acknowledged</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ==========================================
       TOAST NOTIFICATION CONTAINER
       ========================================== -->
  <div class="toast-container-custom" id="toastContainer"></div>

  <!-- Lucide Icons CDN -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- Bootstrap 5 JS Bundle (Includes Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>

  <!-- Frontend logic -->
  <script>

    // Permission options definition
    const PERMISSION_OPTIONS = [
      { name: 'Adviser View', desc: 'Access Advisory Student Rosters / Records' },
      { name: 'Program-Level View', desc: 'Allows comprehensive department statistics access' },
      { name: 'Report Export', desc: 'Generates and downloads excel diagnostic summaries' },
      { name: 'Intervention Access', desc: 'Creates and archives support intervention referrals' },
      { name: 'User Management', desc: 'Full directory control master scope' },
      { name: 'Template Configuration', desc: 'System UI presets and standard configs modifier' }
    ];

    document.addEventListener('DOMContentLoaded', () => {
      // Create permissions list UI
      populatePermissionsChecklists();

      // Setup Modals
      const addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
      const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
      const originalEditValues = {
        username: '',
        email: '',
        firstName: '',
        lastName: '',
        roleId: '',
        accountStatus: ''
      };
      const permissionsModal = new bootstrap.Modal(document.getElementById('permissionsModal'));
      const viewUserModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
      const importUsersModal = new bootstrap.Modal(document.getElementById('importUsersModal'));
      const resetPasswordModal = new bootstrap.Modal(
        document.getElementById('resetPasswordModal')
      );
      const tempPasswordModal = new bootstrap.Modal(document.getElementById('tempPasswordModal'));

      const accountStatusModal =
        new bootstrap.Modal(
          document.getElementById('accountStatusModal')
        );

      // DOM Elements
      const usersTableBody = document.getElementById('usersTableBody');
      const searchFilter = document.getElementById('searchFilter');
      const roleFilter = document.getElementById('roleFilter');
      const permissionFilter = document.getElementById('permissionFilter');
      const statusFilter = document.getElementById('statusFilter');
      const globalSearch = document.getElementById('globalSearch');

      const addUserForm = document.getElementById('addUserForm');
      const editUserForm = document.getElementById('editUserForm');
      const permissionsForm = document.getElementById('permissionsForm');

      function updateEditSubmitButtonState() {

        const submitButton =
          editUserForm.querySelector(
            'button[type="submit"]'
          );

        const hasChanges =
          document.getElementById('editUsername').value !== originalEditValues.username ||
          document.getElementById('editEmail').value !== originalEditValues.email ||
          document.getElementById('editFirstName').value !== originalEditValues.firstName ||
          document.getElementById('editLastName').value !== originalEditValues.lastName ||
          document.getElementById('editRole').value !== originalEditValues.roleId ||
          document.getElementById('editStatus').value !== originalEditValues.accountStatus;

        submitButton.disabled = !hasChanges;

        submitButton.textContent = 'Save Changes';

        return hasChanges;

      }

      [
        'editUsername',
        'editEmail',
        'editFirstName',
        'editLastName',
        'editRole',
        'editStatus'
      ].forEach(id => {

        document
          .getElementById(id)
          .addEventListener('input', updateEditSubmitButtonState);

        document
          .getElementById(id)
          .addEventListener('change', updateEditSubmitButtonState);

      });

      document
        .getElementById('editUserModal')
        .addEventListener('shown.bs.modal', () => {

          document
            .getElementById('editUsername')
            .focus();

        });

      if (editUserForm) {

        editUserForm.addEventListener('submit', (event) => {

          const submitButton =
            editUserForm.querySelector(
              'button[type="submit"]'
            );

          const hasChanges = updateEditSubmitButtonState();

          if (!hasChanges) {

            event.preventDefault();

            updateEditSubmitButtonState();

            showToast(
              'No Changes',
              'There are no changes to save.',
              'info'
            );

            return;

          }

          const emailField =
            document.getElementById('editEmail');

          emailField.value =
            emailField.value.trim().toLowerCase();

          const email =
            emailField.value;

          const institutionalEmailPattern =
            /^[^\s@]+@dasmarinas\.sti\.edu\.ph$/;

          if (!institutionalEmailPattern.test(email)) {

            event.preventDefault();

            showToast(
              'Invalid Email',
              'Please enter a valid STI College Dasmariñas institutional email address.',
              'warning'
            );

            emailField.focus();

            return;

          }

          submitButton.disabled = true;

          submitButton.textContent =
            'Saving...';

        });

      }

      const btnAddUser = document.getElementById('btnAddUser');
      const btnImportUsers = document.getElementById('btnImportUsers');
      const btnExportUsers = document.getElementById('btnExportUsers');

      const sidebar = document.getElementById('sidebar');
      const mainContent = document.querySelector('.main-content');
      const sidebarToggle = document.getElementById('sidebarToggle');
      const menuToggle = document.getElementById('menuToggle');
      const toastContainer = document.getElementById('toastContainer');
      const dropzone = document.getElementById('dropzone');
      const importFileInput = document.getElementById('importFileInput');

      // Sidebar collapses
      sidebarToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        sidebarToggle.classList.toggle('rotated');
      });

      menuToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
      });

      // Close sidebar mobile click out
      document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1200) {
          if (
            sidebar &&
            menuToggle &&
            !sidebar.contains(e.target) &&
            !menuToggle.contains(e.target) &&
            sidebar.classList.contains('open')
          ) {
            sidebar.classList.remove('open');
          }
        }
      });

      // Toast feedback helper
      function showToast(title, text, type = 'success') {
        const toast = document.createElement('div');
        toast.className = 'toast-custom';

        let icon = 'check';
        if (type === 'warning') icon = 'alert-circle';
        if (type === 'info') icon = 'info';

        toast.innerHTML = `
          <div class="toast-icon ${type}">
            <i data-lucide="${icon}"></i>
          </div>
          <div class="toast-content">
            <h5 class="toast-title">${title}</h5>
            <p class="toast-text">${text}</p>
          </div>
        `;

        toastContainer.appendChild(toast);
        lucide.createIcons();

        setTimeout(() => toast.classList.add('show'), 10);

        setTimeout(() => {
          toast.classList.remove('show');
          setTimeout(() => toast.remove(), 300);
        }, 4500);
      }

      <?php if ($successMessage): ?>

        showToast(
          'Success',
          <?= json_encode($successMessage) ?>,
          'success'
        );

      <?php endif; ?>

      <?php if ($temporaryPassword): ?>

        document.getElementById('generatedTempPassword').textContent =
          <?= json_encode($temporaryPassword) ?>;

        document.getElementById('tempPassUserName').textContent =
          <?= json_encode($temporaryPasswordUser) ?>;

        tempPasswordModal.show();

      <?php endif; ?>

      document
        .getElementById('tempPasswordModal')
        .addEventListener('show.bs.modal', () => {

          document.getElementById('copyPasswordText').textContent =
            'Copy';

          const icon =
            document.getElementById('copyPasswordIcon');

          icon.setAttribute('data-lucide', 'copy');

          document.getElementById('copyTempPasswordBtn').disabled = false;

          lucide.createIcons();

        });

      document
        .getElementById('tempPasswordModal')
        .addEventListener('shown.bs.modal', () => {

          document
            .getElementById('acknowledgeTempPasswordBtn')
            ?.focus();

        });

      const copyTempPasswordBtn =
        document.getElementById('copyTempPasswordBtn');

      if (copyTempPasswordBtn) {

        copyTempPasswordBtn.addEventListener('click', async () => {

          const password =
            document.getElementById('generatedTempPassword').textContent.trim();

          try {

            await navigator.clipboard.writeText(password);

            document.getElementById('copyPasswordText').textContent =
              'Copied!';

            const icon =
              document.getElementById('copyPasswordIcon');

            icon.setAttribute('data-lucide', 'check');

            lucide.createIcons();

            copyTempPasswordBtn.disabled = true;

            setTimeout(() => {

              document.getElementById('copyPasswordText').textContent =
                'Copy';

              icon.setAttribute('data-lucide', 'copy');

              lucide.createIcons();

              copyTempPasswordBtn.disabled = false;

            }, 2000);

          } catch (error) {

            console.error('Unable to copy password:', error);

          }

        });

      }

      const resetPasswordForm =
        document.getElementById('resetPasswordForm');

      if (resetPasswordForm) {

        resetPasswordForm.addEventListener('submit', () => {

          const submitButton =
            resetPasswordForm.querySelector(
              'button[type="submit"]'
            );

          submitButton.disabled = true;

          submitButton.textContent =
            'Resetting...';

        });

      }

      // Populate checklists inside dialog modals
      function populatePermissionsChecklists() {
        const renderChecklist = (options, prefix) => {
          return options.map((opt) => `
            <label class="checklist-item" for="${prefix}-${opt.name.toLowerCase().replace(/\s+/g, '-')}">
              <div class="checklist-item-left">
                <span class="checklist-item-title">${opt.name}</span>
                <span class="checklist-item-desc">${opt.desc}</span>
              </div>
              <input type="checkbox" class="checklist-checkbox" value="${opt.name}" id="${prefix}-${opt.name.toLowerCase().replace(/\s+/g, '-')}" />
            </label>
          `).join('');
        };

        document.getElementById('addPermissionsContainer').innerHTML = renderChecklist(PERMISSION_OPTIONS, 'add');
        document.getElementById('editPermissionsContainer').innerHTML = renderChecklist(PERMISSION_OPTIONS, 'edit');
      }

      // Format clean UI labels
      function getRoleLabel(role) {
        if (role === 'Admin') return 'Technical Admin';
        if (role === 'Teacher') return 'Teacher';
        if (role === 'AcademicHead') return 'Academic Admin/Head';
        if (role === 'Guidance') return 'Guidance/DO';
        return role;
      }

      if (searchFilter) {

        searchFilter.addEventListener("input", function () {

          const keyword = this.value.trim().toLowerCase();

          const rows = document.querySelectorAll("#usersTableBody tr");

          rows.forEach(function (row) {

            const text = row.innerText.toLowerCase();

            if (text.includes(keyword)) {
              row.style.display = "";
            } else {
              row.style.display = "none";
            }

          });

        });

      }

      addUserForm.addEventListener('submit', function (event) {

        const emailField =
          document.getElementById('addEmail');

        emailField.value =
          emailField.value.trim().toLowerCase();

        const email =
          emailField.value;

        const institutionalEmailPattern =
          /^[^\s@]+@dasmarinas\.sti\.edu\.ph$/;

        if (!institutionalEmailPattern.test(email)) {

          event.preventDefault();

          showToast(
            'Invalid Email',
            'Please enter a valid STI College Dasmariñas institutional email address.',
            'warning'
          );

          emailField.focus();

          return;

        }

      });

      // Trigger Create User modal
      btnAddUser.addEventListener('click', () => {
        addUserForm.reset();
        document.querySelectorAll('#addPermissionsContainer .checklist-checkbox').forEach(cb => cb.checked = false);
        addUserModal.show();
      });

      // Trigger Import Roster dialog
      btnImportUsers.addEventListener('click', () => {
        importUsersModal.show();
      });

      // Drag and drop import directory interactions
      if (dropzone) {
        dropzone.addEventListener('click', () => {
          importFileInput.click();
        });

        importFileInput.addEventListener('change', () => {
          if (importFileInput.files.length > 0) {
            showToast('Database Session Required', 'Spreadsheet enrollment parses values into active server databases. Establish sessions first.', 'warning');
            importUsersModal.hide();
          }
        });

        dropzone.addEventListener('dragover', (e) => {
          e.preventDefault();
          dropzone.classList.add('dragover');
        });

        dropzone.addEventListener('dragleave', () => {
          dropzone.classList.remove('dragover');
        });

        dropzone.addEventListener('drop', (e) => {
          e.preventDefault();
          dropzone.classList.remove('dragover');
          if (e.dataTransfer.files.length > 0) {
            showToast('Database Session Required', 'Spreadsheet enrollment parses values into active server databases. Establish sessions first.', 'warning');
            importUsersModal.hide();
          }
        });
      }

      // Export action triggers warning notification in sandbox mode
      btnExportUsers.addEventListener('click', () => {
        showToast('Database Session Required', 'Data Export: An active database session is required to compile and download personnel registries.', 'warning');
      });


      // Actions delegation inside directory grid
      usersTableBody.addEventListener('click', (e) => {
        const btn = e.target.closest('.action-row-btn');
        if (!btn) return;

        // 1. View User details
        if (btn.classList.contains('btn-view')) {

          const firstName = btn.dataset.firstName;
          const lastName = btn.dataset.lastName;

          const initials =
            (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();

          document.getElementById("viewUserAvatar").innerText = initials;

          document.getElementById("viewUserName").innerText =
            firstName + " " + lastName;

          document.getElementById("viewUserRole").innerText =
            btn.dataset.role;

          document.getElementById("viewUserEmpNum").innerText =
            btn.dataset.employeeNumber;

          document.getElementById("viewUserUsername").innerText =
            btn.dataset.username;

          document.getElementById("viewUserEmail").innerText =
            btn.dataset.email;

          document.getElementById("viewUserLastLogin").innerText =
            btn.dataset.lastLogin || "--";

          const badgeContainer =
            document.getElementById("viewUserStatusBadge");

          const status =
            btn.dataset.status;

          const statusClass =
            status === "Active" ? "active" : "disabled";

          badgeContainer.className =
            `status-dot-badge ${statusClass}`;

          badgeContainer.querySelector(".status-dot").className =
            `status-dot ${statusClass}`;

          badgeContainer.querySelector(".status-text").innerText =
            status;

          document.getElementById("viewUserPermissions").innerHTML =
            '<span class="text-muted italic" style="font-size:0.7rem;">--</span>';

          viewUserModal.show();
        }

        // 2. Edit User details
        else if (btn.classList.contains('btn-edit')) {
          document.getElementById('editUserId').value =
            btn.dataset.userId;

          document.getElementById('editEmployeeNumber').value =
            btn.dataset.employeeNumber;

          document.getElementById('editUsername').value =
            btn.dataset.username;

          document.getElementById('editFirstName').value =
            btn.dataset.firstName;

          document.getElementById('editLastName').value =
            btn.dataset.lastName;

          document.getElementById('editEmail').value =
            btn.dataset.email;

          // Populate the Role dropdown using the database role_id.
          document.getElementById("editRole").value =
            btn.dataset.roleId;

          document.getElementById('editStatus').value =
            btn.dataset.status;

          originalEditValues.username =
            btn.dataset.username;

          originalEditValues.email =
            btn.dataset.email;

          originalEditValues.firstName =
            btn.dataset.firstName;

          originalEditValues.lastName =
            btn.dataset.lastName;

          originalEditValues.roleId =
            btn.dataset.roleId;

          originalEditValues.accountStatus =
            btn.dataset.status;

          updateEditSubmitButtonState();

          editUserModal.show();

        }

        // 3. Manage permissions checklists
        else if (btn.classList.contains('btn-permissions')) {
          document.getElementById("permsUserId").value =
            btn.dataset.userId;

          document.querySelectorAll(
            "#editPermissionsContainer .checklist-checkbox"
          ).forEach(cb => {
            cb.checked = false;
          });

          permissionsModal.show();
        }

        // 4. Reset security keys / Password
        else if (btn.classList.contains('btn-reset')) {

          const fullName =
            `${btn.dataset.firstName} ${btn.dataset.lastName}`;

          document.getElementById('resetUserId').value =
            btn.dataset.userId;

          document.getElementById('resetUserName').textContent =
            fullName;

          resetPasswordModal.show();

        }

        // 5. Account Activation / Deactivation
        else if (
          btn.classList.contains("btn-toggle-disable") ||
          btn.classList.contains("btn-toggle-enable")
        ) {

          const isDisable =
            btn.classList.contains("btn-toggle-disable");

          const fullName =
            `${btn.dataset.firstName} ${btn.dataset.lastName}`;

          document.getElementById(
            "accountStatusUserId"
          ).value = btn.dataset.userId;

          document.getElementById(
            "accountStatusModalTitle"
          ).textContent =
            isDisable
              ? "Disable Account"
              : "Enable Account";

          document.getElementById(
            "accountStatusMessage"
          ).innerHTML =
            isDisable
              ? `Are you sure you want to <strong>disable</strong> <strong>${fullName}</strong>?`
              : `Are you sure you want to <strong>enable</strong> <strong>${fullName}</strong>?`;

          const submitButton =
            document.getElementById(
              "accountStatusSubmitButton"
            );

          submitButton.textContent =
            isDisable
              ? "Disable Account"
              : "Enable Account";

          submitButton.classList.remove(
            "btn-danger-action"
          );

          if (isDisable) {

            submitButton.classList.add(
              "btn-danger-action"
            );

          }

          accountStatusModal.show();

        }

      });

      // Initial Lucide Icons parse
      lucide.createIcons();

    });
  </script>
</body>

</html>