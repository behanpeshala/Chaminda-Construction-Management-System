<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - CCMS' : 'CCMS Enterprise System'; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/ccms/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Fixed top header -->
<!-- Fixed top header -->
<nav class="navbar navbar-dark ccms-topbar fixed-top">
  <div class="container-fluid px-2 px-sm-3">
    <div class="d-flex align-items-center">
      <button class="btn btn-sm btn-outline-light d-lg-none me-2 p-1 px-2" type="button" id="sidebarToggleBtn" aria-label="Toggle navigation">
        <i class="bi bi-list fs-5"></i>
      </button>
      <a class="navbar-brand fw-bold d-flex align-items-center gap-2 me-1 me-sm-2" href="/ccms/dashboard.php">
        <img src="/ccms/assets/images/logo.png" alt="CCMS Logo" style="height: 30px; width: 30px; object-fit: contain; background: #ffffff; padding: 2px; border-radius: 6px; border: 1px solid #10B981;">
        <span>CCMS</span>
      </a>
    </div>
    
    <div class="d-flex align-items-center text-white gap-2 gap-sm-3">
      <!-- Navbar Notifications Bell Dropdown -->
      <div class="dropdown me-1">
        <button class="btn btn-sm btn-outline-light position-relative rounded-circle d-flex align-items-center justify-content-center p-0" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="width:36px; height:36px;">
          <i class="bi bi-bell-fill text-warning fs-6"></i>
          <span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-dark d-none" style="font-size:0.65rem">
            0
          </span>
        </button>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow-lg border border-success border-opacity-25 mt-2 ccms-notif-dropdown" aria-labelledby="notificationDropdown">
          <div class="d-flex align-items-center justify-content-between p-3 bg-black bg-opacity-40 border-bottom border-secondary border-opacity-25">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-bell-fill text-warning"></i>
              <h6 class="mb-0 fw-bold text-light">System Activity</h6>
            </div>
            <button type="button" class="btn btn-link btn-sm text-info p-0 text-decoration-none small" id="markAllReadBtn">
              <i class="bi bi-check2-all"></i> Mark Read
            </button>
          </div>
          <div id="notificationList" class="p-0" style="max-height: 360px; overflow-y: auto;">
            <div class="text-center py-4 text-muted">
              <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
              <div class="small mt-2">Loading notifications...</div>
            </div>
          </div>
        </div>
      </div>

      <!-- User Profile Badge -->
      <div class="d-flex align-items-center gap-1 gap-sm-2 bg-white bg-opacity-10 px-2 px-sm-3 py-1 rounded-pill border border-light border-opacity-25 user-badge-container">
        <i class="bi bi-person-circle fs-6"></i>
        <span class="fw-medium small d-none d-sm-inline text-truncate" style="max-width: 120px;"><?php echo htmlspecialchars(current_name()); ?></span>
        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill ms-1 d-none d-md-inline" style="font-size:0.7rem"><?php echo htmlspecialchars(current_role()); ?></span>
      </div>

      <!-- Logout Button -->
      <a href="/ccms/logout.php" class="btn btn-sm btn-outline-light d-flex align-items-center gap-1 rounded-pill px-2 px-sm-3" title="Logout">
        <i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline">Logout</span>
      </a>
    </div>
  </div>
</nav>

<!-- Mobile Sidebar Backdrop Overlay -->
<div class="ccms-sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- Toast Popup Notification Container -->
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; margin-top: 60px;"></div>

<!-- Flash notification script payload -->
<?php if (!empty($_SESSION['flash'])): ?>
<script>
  window.SESSION_TOAST = <?php echo json_encode($_SESSION['flash']); ?>;
</script>
<?php unset($_SESSION['flash']); ?>
<?php endif; ?>
