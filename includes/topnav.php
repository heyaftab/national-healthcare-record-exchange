<?php
/** Shared authenticated NHRE top navigation bar. The parent page must load auth_check.php first. */
$topUnread = unread_notification_count((int)($_SESSION['user_id'] ?? 0));
$currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$topDashboard = in_array((string)($_SESSION['role'] ?? ''), ['Hospital Admin', 'System Admin'], true) ? 'admin_dashboard.php' : 'dashboard.php';
?>
<nav class="dashboard-nav">
  <div class="container d-flex align-items-center justify-content-between gap-3">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e($topDashboard) ?>">
      <img src="assets/images/nhre-logo.svg" alt="NHRE" class="nhre-logo-img">
    </a>
    <div class="d-flex align-items-center gap-2">
      <?php if ($currentPage !== $topDashboard): ?>
        <a href="<?= e($topDashboard) ?>" class="btn btn-dashboard-logout ripple"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
      <?php endif; ?>
      <div class="notification-wrap" id="notificationWrap">
        <span class="notification-badge" id="notificationBadge"<?= $topUnread === 0 ? ' hidden style="display:none;"' : '' ?>><?= $topUnread > 0 ? ($topUnread > 99 ? '99+' : $topUnread) : '' ?></span>
        <button type="button" class="notification-icon-button ripple" id="notificationBell" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
          <i class="fa-solid fa-bell"></i>
        </button>
        <div class="notification-overlay" id="notificationOverlay" hidden>
          <input type="hidden" id="notificationCsrf" value="<?= csrf_token() ?>">
          <div class="notification-overlay-head">
            <strong>Notifications</strong>
            <button type="button" class="notification-mark-read" id="markAllRead">Mark all read</button>
          </div>
          <div class="notification-overlay-list" id="notificationList"></div>
          <a href="notifications.php" class="notification-overlay-footer">View all notifications</a>
        </div>
      </div>
      <a href="logout.php" class="btn btn-dashboard-logout ripple">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span>Logout</span>
      </a>
    </div>
  </div>
</nav>
