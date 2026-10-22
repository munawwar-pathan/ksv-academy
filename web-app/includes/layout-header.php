<?php
/**
 * Shared HRMS Dashboard Topbar and Sidebar Component
 */
require_once __DIR__ . '/../config.php';

// Requires logged in user
$current_user = require_login();
$active_menu = $active_menu ?? 'dashboard';

// Active Session and list of sessions
$active_session = get_active_session();
$all_sessions = get_all_sessions();
$school_settings = get_school_settings();

// Determine role display name
$role_names = [
    'super_admin' => 'Super Admin',
    'staff' => 'Faculty & Staff',
    'parent' => 'Parent Portal',
    'student' => 'Student Portal'
];
$role_title = $role_names[$current_user['role']] ?? ucfirst($current_user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?> | KSV Academy HRMS</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  
  <!-- HRMS Stylesheet -->
  <link rel="stylesheet" href="<?php echo HRMS_URL; ?>/assets/css/hrms.css?v=<?php echo time(); ?>">
  <link rel="icon" type="image/jpeg" href="<?php echo htmlspecialchars($school_settings['logo_url']); ?>">
</head>
<body>

<div class="hrms-app-wrapper">

  <!-- Left Sidebar Navigation -->
  <aside class="hrms-sidebar" id="hrmsSidebar">
    
    <!-- Brand Title & Logo -->
    <div class="sidebar-brand">
      <img src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="KSV Academy Logo" class="sidebar-brand-logo">
      <div class="sidebar-brand-text">
        <span class="brand-name"><?php echo htmlspecialchars($school_settings['school_name']); ?></span>
        <span class="brand-badge">HRMS &amp; Portal</span>
      </div>
    </div>

    <!-- Sidebar Menu Navigation -->
    <div class="sidebar-nav-container">
      
      <?php if ($current_user['role'] === 'super_admin'): ?>
        <!-- SUPER ADMIN MENU -->
        <div class="nav-section-title">Overview</div>
        <ul class="sidebar-menu">
          <li class="sidebar-menu-item <?php echo $active_menu === 'dashboard' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/dashboard">
              <i class="fa-solid fa-gauge-high"></i>
              <span>Dashboard</span>
            </a>
          </li>
        </ul>

        <div class="nav-section-title">Students &amp; Academics</div>
        <ul class="sidebar-menu">
          <li class="sidebar-menu-item <?php echo $active_menu === 'students' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/students">
              <i class="fa-solid fa-user-graduate"></i>
              <span>Student Roaster</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'student-attendance' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/student-attendance">
              <i class="fa-solid fa-qrcode"></i>
              <span>Attendance &amp; QR Scan</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'fees' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/fees">
              <i class="fa-solid fa-receipt"></i>
              <span>Fee Management</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'student-id' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/student-id">
              <i class="fa-solid fa-id-badge"></i>
              <span>Student ID Card</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'marksheet' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/marksheet">
              <i class="fa-solid fa-file-lines"></i>
              <span>Marksheet Management</span>
            </a>
          </li>
        </ul>

        <div class="nav-section-title">Staff &amp; Human Resources</div>
        <ul class="sidebar-menu">
          <li class="sidebar-menu-item <?php echo $active_menu === 'staff' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/staff">
              <i class="fa-solid fa-chalkboard-user"></i>
              <span>Faculty &amp; Staff</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'staff-attendance' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/staff-attendance">
              <i class="fa-solid fa-calendar-check"></i>
              <span>Staff Attendance</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'payroll' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/payroll">
              <i class="fa-solid fa-money-check-dollar"></i>
              <span>Salary Management</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'staff-id' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/staff-id">
              <i class="fa-solid fa-address-card"></i>
              <span>Staff ID Card</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'staff-periods' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/staff-periods">
              <i class="fa-solid fa-clock-rotate-left"></i>
              <span>Staff Periods</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'leaves' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/leaves">
              <i class="fa-solid fa-plane-departure"></i>
              <span>Leave Approvals</span>
            </a>
          </li>
        </ul>

        <div class="nav-section-title">Expense Management</div>
        <ul class="sidebar-menu">
          <li class="sidebar-menu-item <?php echo $active_menu === 'expenses' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/expenses">
              <i class="fa-solid fa-wallet"></i>
              <span>Expenses List &amp; Add</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'expense-categories' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/expense-categories">
              <i class="fa-solid fa-tags"></i>
              <span>Expense Categories</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'expense-report' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/expense-report">
              <i class="fa-solid fa-chart-pie"></i>
              <span>Expense Report</span>
            </a>
          </li>
        </ul>

        <div class="nav-section-title">Settings &amp; System</div>
        <ul class="sidebar-menu">
          <li class="sidebar-menu-item <?php echo in_array($active_menu, ['school-settings', 'settings']) ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/school-settings">
              <i class="fa-solid fa-sliders"></i>
              <span>Settings</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'gallery' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/gallery">
              <i class="fa-solid fa-images"></i>
              <span>Gallery</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'popup' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/popup">
              <i class="fa-solid fa-window-restore"></i>
              <span>Popup</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'announcements' ? 'active' : ''; ?>">
            <a href="<?php echo ADMIN_URL; ?>/announcements">
              <i class="fa-solid fa-bullhorn"></i>
              <span>Announcements</span>
            </a>
          </li>
        </ul>

      <?php elseif ($current_user['role'] === 'staff'): ?>
        <?php 
          $staff_perms = get_staff_permissions($current_user['id']);
        ?>
        <!-- STAFF MENU -->
        <div class="nav-section-title">Faculty Portal</div>
        <ul class="sidebar-menu">
          <?php if (in_array('dashboard', $staff_perms)): ?>
            <li class="sidebar-menu-item <?php echo $active_menu === 'dashboard' ? 'active' : ''; ?>">
              <a href="<?php echo STAFF_URL; ?>/dashboard">
                <i class="fa-solid fa-gauge-high"></i>
                <span>My Dashboard</span>
              </a>
            </li>
          <?php endif; ?>
          <?php if (in_array('attendance', $staff_perms)): ?>
            <li class="sidebar-menu-item <?php echo $active_menu === 'attendance' ? 'active' : ''; ?>">
              <a href="<?php echo STAFF_URL; ?>/attendance">
                <i class="fa-solid fa-clock"></i>
                <span>Attendance &amp; Punch</span>
              </a>
            </li>
          <?php endif; ?>
          <?php if (in_array('staff-id', $staff_perms)): ?>
            <li class="sidebar-menu-item <?php echo $active_menu === 'staff-id' ? 'active' : ''; ?>">
              <a href="<?php echo STAFF_URL; ?>/staff-id">
                <i class="fa-solid fa-id-card"></i>
                <span>My Staff ID Card</span>
              </a>
            </li>
          <?php endif; ?>
          <?php if (in_array('leaves', $staff_perms)): ?>
            <li class="sidebar-menu-item <?php echo $active_menu === 'leaves' ? 'active' : ''; ?>">
              <a href="<?php echo STAFF_URL; ?>/leaves">
                <i class="fa-solid fa-plane-departure"></i>
                <span>Apply for Leave</span>
              </a>
            </li>
          <?php endif; ?>
          <?php if (in_array('students', $staff_perms)): ?>
            <li class="sidebar-menu-item <?php echo $active_menu === 'students' ? 'active' : ''; ?>">
              <a href="<?php echo STAFF_URL; ?>/students">
                <i class="fa-solid fa-users"></i>
                <span>My Students &amp; Batches</span>
              </a>
            </li>
          <?php endif; ?>
          <?php if (in_array('payroll', $staff_perms)): ?>
            <li class="sidebar-menu-item <?php echo $active_menu === 'payroll' ? 'active' : ''; ?>">
              <a href="<?php echo STAFF_URL; ?>/payroll">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Salary Slips</span>
              </a>
            </li>
          <?php endif; ?>
          <?php if (in_array('announcements', $staff_perms)): ?>
            <li class="sidebar-menu-item <?php echo $active_menu === 'announcements' ? 'active' : ''; ?>">
              <a href="<?php echo STAFF_URL; ?>/announcements">
                <i class="fa-solid fa-bullhorn"></i>
                <span>Noticeboard</span>
              </a>
            </li>
          <?php endif; ?>
        </ul>

        <?php 
          $has_admin_modules = false;
          $admin_module_keys = ['admin_students', 'admin_student_attendance', 'admin_marksheet', 'admin_staff_periods', 'admin_staff_attendance'];
          foreach ($admin_module_keys as $amk) {
            if (in_array($amk, $staff_perms)) { $has_admin_modules = true; break; }
          }
        ?>
        <?php if ($has_admin_modules): ?>
          <div class="nav-section-title">Academic &amp; Operations</div>
          <ul class="sidebar-menu">
            <?php if (in_array('admin_students', $staff_perms)): ?>
              <li class="sidebar-menu-item <?php echo $active_menu === 'students' ? 'active' : ''; ?>">
                <a href="<?php echo ADMIN_URL; ?>/students">
                  <i class="fa-solid fa-user-graduate"></i>
                  <span>Student Roaster</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (in_array('admin_student_attendance', $staff_perms)): ?>
              <li class="sidebar-menu-item <?php echo $active_menu === 'student-attendance' ? 'active' : ''; ?>">
                <a href="<?php echo ADMIN_URL; ?>/student-attendance">
                  <i class="fa-solid fa-qrcode"></i>
                  <span>Attendance &amp; QR Scan</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (in_array('admin_marksheet', $staff_perms)): ?>
              <li class="sidebar-menu-item <?php echo $active_menu === 'marksheet' ? 'active' : ''; ?>">
                <a href="<?php echo ADMIN_URL; ?>/marksheet">
                  <i class="fa-solid fa-file-lines"></i>
                  <span>Marksheet Management</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (in_array('admin_staff_periods', $staff_perms)): ?>
              <li class="sidebar-menu-item <?php echo $active_menu === 'staff-periods' ? 'active' : ''; ?>">
                <a href="<?php echo ADMIN_URL; ?>/staff-periods">
                  <i class="fa-solid fa-clock-rotate-left"></i>
                  <span>Staff Periods</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (in_array('admin_staff_attendance', $staff_perms)): ?>
              <li class="sidebar-menu-item <?php echo $active_menu === 'staff-attendance' ? 'active' : ''; ?>">
                <a href="<?php echo ADMIN_URL; ?>/staff-attendance">
                  <i class="fa-solid fa-calendar-check"></i>
                  <span>Staff Attendance</span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        <?php endif; ?>

      <?php else: ?>
        <!-- PARENT & STUDENT MENU -->
        <div class="nav-section-title">Parent &amp; Student Portal</div>
        <ul class="sidebar-menu">
          <li class="sidebar-menu-item <?php echo $active_menu === 'dashboard' ? 'active' : ''; ?>">
            <a href="<?php echo PARENT_URL; ?>/dashboard">
              <i class="fa-solid fa-house-chimney-user"></i>
              <span>Student Overview</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'attendance' ? 'active' : ''; ?>">
            <a href="<?php echo PARENT_URL; ?>/attendance">
              <i class="fa-solid fa-calendar-days"></i>
              <span>Attendance History</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'fees' ? 'active' : ''; ?>">
            <a href="<?php echo PARENT_URL; ?>/fees">
              <i class="fa-solid fa-credit-card"></i>
              <span>Fee Receipts &amp; Dues</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'student-id' ? 'active' : ''; ?>">
            <a href="<?php echo PARENT_URL; ?>/student-id">
              <i class="fa-solid fa-id-badge"></i>
              <span>Student ID Card</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'marksheet' ? 'active' : ''; ?>">
            <a href="<?php echo PARENT_URL; ?>/marksheet">
              <i class="fa-solid fa-file-lines"></i>
              <span>Exam Marksheet</span>
            </a>
          </li>
          <li class="sidebar-menu-item <?php echo $active_menu === 'announcements' ? 'active' : ''; ?>">
            <a href="<?php echo PARENT_URL; ?>/announcements">
              <i class="fa-solid fa-bullhorn"></i>
              <span>Academy Notices</span>
            </a>
          </li>
        </ul>
      <?php endif; ?>

      <div class="nav-section-title">Links</div>
      <ul class="sidebar-menu">
        <li class="sidebar-menu-item">
          <a href="/" target="_blank">
            <i class="fa-solid fa-globe"></i>
            <span>Main Website</span>
          </a>
        </li>
      </ul>

    </div>

    <!-- Sidebar Bottom Logout (Clean URL) -->
    <div class="sidebar-footer">
      <a href="<?php echo HRMS_URL; ?>/logout" class="sidebar-logout-btn">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
        <span>Sign Out</span>
      </a>
    </div>

  </aside>

  <!-- Auto-scroll active menu item into view -->
  <script>
  (function() {
    function scrollActiveSidebarMenu() {
      var nav = document.querySelector('.sidebar-nav-container');
      if (!nav) return;
      var activeItem = nav.querySelector('.sidebar-menu-item.active');
      if (activeItem) {
        var containerHeight = nav.clientHeight;
        var itemTop = activeItem.offsetTop;
        var itemHeight = activeItem.clientHeight;
        var targetScroll = itemTop - (containerHeight / 2) + (itemHeight / 2);
        nav.scrollTop = Math.max(0, targetScroll);
      }
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', scrollActiveSidebarMenu);
    } else {
      scrollActiveSidebarMenu();
    }
    window.addEventListener('load', scrollActiveSidebarMenu);
  })();
  </script>

  <!-- Right Main View -->
  <div class="hrms-main-content">
    
    <!-- Top Header Navigation -->
    <header class="hrms-top-header">
      <div class="header-left">
        <button id="mobileMenuToggle" class="mobile-menu-btn" aria-label="Toggle Navigation">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div>
          <h1 class="page-title"><?php echo htmlspecialchars($page_heading ?? 'Dashboard Overview'); ?></h1>
          <p class="page-subtitle"><?php echo htmlspecialchars($page_subheading ?? 'Welcome to KSV Academy Management Portal'); ?></p>
        </div>
      </div>

      <div class="header-right" style="display:flex; align-items:center; gap:12px;">
        
        <!-- ACADEMIC SESSION SWITCHER (Session Management) -->
        <div class="header-session-picker" style="display:flex; align-items:center; gap:8px; background:rgba(0,146,223,0.08); border:1px solid rgba(0,146,223,0.22); border-radius:8px; padding:6px 12px;">
          <i class="fa-solid fa-calendar-week" style="color:var(--primary-600); font-size:14px;"></i>
          <span style="font-size:12px; font-weight:700; color:var(--neutral-700);">Session:</span>
          <select id="sessionSwitcher" onchange="if(this.value) window.location.href='?switch_session=' + this.value" style="font-size:13px; font-weight:700; color:var(--primary-700); background:transparent; border:none; outline:none; cursor:pointer;">
            <?php foreach ($all_sessions as $sess_item): ?>
              <option value="<?php echo $sess_item['id']; ?>" <?php echo $sess_item['id'] == $active_session['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($sess_item['session_name']); ?><?php echo $sess_item['is_current'] ? ' (Active)' : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="header-date-badge">
          <i class="fa-regular fa-calendar"></i>
          <span><?php echo date('l, d M Y'); ?></span>
        </div>
      </div>
    </header>

    <!-- Page Body Starts -->
    <main class="hrms-page-body">
      
      <!-- Flash Alert notifications -->
      <?php if ($flash_err = get_flash('error')): ?>
        <div class="flash-alert error">
          <i class="fa-solid fa-circle-exclamation"></i>
          <span><?php echo htmlspecialchars($flash_err); ?></span>
        </div>
      <?php endif; ?>

      <?php if ($flash_succ = get_flash('success')): ?>
        <div class="flash-alert success">
          <i class="fa-solid fa-circle-check"></i>
          <span><?php echo htmlspecialchars($flash_succ); ?></span>
        </div>
      <?php endif; ?>
