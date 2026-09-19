<?php
/** Shared authenticated NHRE sidebar. The parent page must load auth_check.php first. */
$sidebarRole = (string)($_SESSION['role'] ?? '');
$sidebarName = (string)($_SESSION['fullname'] ?? 'NHRE User');
$sidebarPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$sidebarInitials = mb_strtoupper(mb_substr(trim($sidebarName), 0, 1));
$sidebarUnread = unread_notification_count((int)($_SESSION['user_id'] ?? 0));

$sidebarPhoto = '';
$sidebarAvatar = '';
try {
    $sidebarPhotoStmt = db()->prepare('SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
    $sidebarPhotoStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $sidebarPhoto = (string)$sidebarPhotoStmt->fetchColumn();
} catch (PDOException $e) {
}
$sidebarAvatar = user_avatar_url($sidebarPhoto, (int)($_SESSION['user_id'] ?? 0), $sidebarRole);

$sidebarLinks = [
    [in_array($sidebarRole, ['Hospital Admin', 'System Admin'], true) ? 'admin_dashboard.php' : 'dashboard.php', 'fa-house', 'Dashboard'],
    ['profile.php', 'fa-user', 'My Profile'],
];

if ($sidebarRole === 'Patient') {
    $sidebarLinks = array_merge($sidebarLinks, [
        ['medical_records.php', 'fa-notes-medical', 'Medical Records'],
        ['appointments.php', 'fa-calendar-check', 'Appointments'],
        ['prescriptions.php', 'fa-prescription', 'Prescriptions'],
        ['pharmacy.php', 'fa-pills', 'Pharmacy'],
        ['medical_tests.php', 'fa-flask-vial', 'Test Booking'],
        ['vaccination.php', 'fa-syringe', 'Vaccinations'],
        ['allergies.php', 'fa-triangle-exclamation', 'Allergies'],
        ['blood_donation.php', 'fa-droplet', 'Blood Information'],
        ['medical_documents.php', 'fa-folder-open', 'Medical Documents'],
        ['data_access.php', 'fa-shield-halved', 'Data Access'],
    ]);
} elseif ($sidebarRole === 'Doctor') {
    $sidebarLinks = array_merge($sidebarLinks, [
        ['my_patients.php', 'fa-user-group', 'My Patients'],
        ['patient_search.php', 'fa-magnifying-glass', 'Patient Search'],
        ['appointments.php', 'fa-calendar-check', 'Appointments'],
        ['medical_records.php', 'fa-notes-medical', 'Medical Records'],
        ['prescriptions.php', 'fa-pills', 'Prescriptions'],
        ['medical_tests.php', 'fa-flask-vial', 'Lab Reports'],
        ['medical_documents.php', 'fa-folder-open', 'Medical Documents'],
        ['access_requests.php', 'fa-shield-halved', 'Access Requests'],
    ]);
} elseif ($sidebarRole === 'Lab Technician') {
    $sidebarLinks = array_merge($sidebarLinks, [
        ['lab_test_requests.php', 'fa-flask-vial', 'Test Requests'],
        ['vaccination.php', 'fa-syringe', 'Vaccination Bookings'],
        ['laboratory_reports.php', 'fa-file-medical', 'Laboratory Reports'],
        ['patient_search.php', 'fa-magnifying-glass', 'Patient Search'],
        ['test_history.php', 'fa-clock-rotate-left', 'Test History'],
    ]);
} elseif ($sidebarRole === 'Pharmacist') {
    $sidebarLinks = array_merge($sidebarLinks, [
        ['pharmacy.php', 'fa-pills', 'Pharmacy'],
        ['prescriptions.php', 'fa-prescription', 'Prescriptions'],
        ['inventory.php', 'fa-boxes-stacked', 'Medicine Inventory'],
        ['stock.php', 'fa-boxes-packing', 'Stock Management'],
        ['patient_search.php', 'fa-magnifying-glass', 'Patient Search'],
        ['dispensing_history.php', 'fa-clock-rotate-left', 'Dispensing History'],
    ]);
} elseif ($sidebarRole === 'Hospital Admin') {
    $sidebarLinks = array_merge($sidebarLinks, [
        ['hospital_profile.php', 'fa-hospital', 'Hospital Profile'],
        ['admin_credentials.php?role=Doctor', 'fa-user-doctor', 'Doctors'],
        ['admin_credentials.php?role=Patient', 'fa-user-group', 'Patients'],
        ['admin_credentials.php?role=staff', 'fa-users', 'Hospital Staff'],
        ['admin_operations.php?view=departments', 'fa-table-cells-large', 'Departments'],
        ['appointments.php', 'fa-calendar-check', 'Appointments'],
        ['admin_operations.php?view=medical-records', 'fa-notes-medical', 'Medical Records'],
        ['medical_tests.php', 'fa-flask-vial', 'Laboratory Services'],
        ['admin_operations.php?view=prescriptions', 'fa-pills', 'Prescriptions'],
        ['admin_operations.php?view=access-requests', 'fa-shield-halved', 'Access Requests'],
        ['admin_credentials.php', 'fa-users-gear', 'Account Directory'],
        ['admin_operations.php?view=reports', 'fa-chart-column', 'Reports & Analytics'],
        ['admin_operations.php?view=audit-logs', 'fa-clipboard-list', 'Audit Logs'],
    ]);
} elseif ($sidebarRole === 'System Admin') {
    $sidebarLinks = array_merge($sidebarLinks, [
        ['admin_dashboard.php', 'fa-house', 'Dashboard'],
        ['admin_operations.php?view=user-management', 'fa-users-gear', 'Users'],
        ['admin_operations.php?view=organizations', 'fa-building', 'Organizations'],
        ['admin_operations.php?view=roles-permissions', 'fa-user-shield', 'Roles & Permissions'],
        ['admin_operations.php?view=patients', 'fa-user-injured', 'Patients'],
        ['admin_operations.php?view=audit-logs', 'fa-clipboard-list', 'Audit Logs'],
        ['admin_operations.php?view=data-exchange', 'fa-network-wired', 'Data Exchange'],
        ['admin_operations.php?view=reports', 'fa-chart-column', 'Reports'],
        ['admin_operations.php?view=settings', 'fa-sliders', 'Settings'],
    ]);
}

$sidebarLinks[] = ['notifications.php', 'fa-bell', 'Notifications'];
$sidebarLinks[] = [in_array($sidebarRole, ['Patient', 'Doctor'], true) ? 'settings.php' : ($sidebarRole === 'System Admin' ? 'admin_operations.php?view=settings' : 'settings.php'), 'fa-gear', 'Settings'];
$sidebarLinks[] = ['help_support.php', 'fa-circle-question', 'Help & Support'];
?>
<button class="sidebar-toggle" type="button" aria-label="Open navigation" aria-controls="nhreSidebar" aria-expanded="false">
  <i class="fa-solid fa-bars"></i>
</button>
<div class="sidebar-backdrop" hidden></div>
<aside class="nhre-sidebar" id="nhreSidebar" aria-label="NHRE account navigation">
  <div class="sidebar-brand">
    <img src="assets/images/nhre-logo.svg" alt="NHRE" class="nhre-logo-img">
    <button class="sidebar-collapse" type="button" aria-label="Collapse navigation"><i class="fa-solid fa-angles-left"></i></button>
  </div>
  <p class="sidebar-tagline">National Healthcare<br>Record Exchange</p>
  <a class="sidebar-user" href="profile.php" title="View profile">
    <span class="sidebar-avatar" aria-hidden="true">
      <img src="<?= e($sidebarAvatar) ?>" alt="">
    </span>
    <span class="sidebar-user-text"><strong><?= e($sidebarName) ?></strong><small><?= e($sidebarRole) ?></small></span>
  </a>
  <nav class="sidebar-menu">
    <span class="sidebar-label">Workspace</span>
    <?php foreach ($sidebarLinks as $sidebarLink): ?>
      <?php
      [$sidebarHref, $sidebarIcon, $sidebarLabel] = $sidebarLink;
      $sidebarPlaceholder = $sidebarLink[3] ?? false;

      $sidebarPath = basename((string)parse_url($sidebarHref, PHP_URL_PATH) ?: $sidebarHref);
      $sidebarActive = $sidebarPage === $sidebarPath;
      if ($sidebarActive && in_array($sidebarPath, ['coming_soon.php', 'admin_operations.php', 'admin_credentials.php'], true)) {
          parse_str((string)parse_url($sidebarHref, PHP_URL_QUERY), $sidebarParams);
          $sidebarActive = true;
          foreach ($sidebarParams as $sidebarParam => $sidebarValue) {
              if ((string)($_GET[$sidebarParam] ?? '') !== (string)$sidebarValue) {
                  $sidebarActive = false;
                  break;
              }
          }
      }
      ?>
      <a href="<?= e($sidebarHref) ?>" class="sidebar-link <?= $sidebarActive ? 'is-active' : '' ?>" title="<?= e($sidebarLabel) ?>">
        <i class="fa-solid <?= e($sidebarIcon) ?>"></i>
        <span><?= e($sidebarLabel) ?></span>
        <?php if ($sidebarPlaceholder): ?><em>Planned</em><?php endif; ?>
        <?php if ($sidebarLabel === 'Notifications' && $sidebarUnread > 0): ?><em class="sidebar-unread"><?= $sidebarUnread > 99 ? '99+' : $sidebarUnread ?></em><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <a class="sidebar-logout" href="logout.php" title="Logout"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Logout</span></a>
</aside>
