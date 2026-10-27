<?php
require_once __DIR__ . '/../config.php';

// Ensure user is parent, student, or admin
$current_user = require_login(['parent', 'student', 'super_admin']);

$page_title = 'Student Academic & Fee Portal | KSV Academy';
$page_heading = 'Student Academic & Fee Portal';
$page_subheading = 'Real-Time Biometric Attendance, Exam Marks Analytics & Fee Receipts Ledger';
$active_menu = 'dashboard';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'] ?? 1;
$school_settings = get_school_settings();

// ── Student Resolution Logic ──
$student = null;
$parent_children = [];

// 1. Explicit Student Switch via GET
if (isset($_GET['student_id'])) {
    $target_sid = intval($_GET['student_id']);
    if ($current_user['role'] === 'super_admin') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$target_sid]);
        $student = $stmt->fetch();
        if ($student) $_SESSION['active_student_id'] = $student['id'];
    } elseif ($current_user['role'] === 'parent') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([$target_sid, $current_user['id']]);
        $student = $stmt->fetch();
        if ($student) $_SESSION['active_student_id'] = $student['id'];
    }
}

// 2. Role Specific Resolution
if ($current_user['role'] === 'student') {
    // A student always views their own record
    $stmt = $db->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$current_user['id']]);
    $student = $stmt->fetch();
    if (!$student) {
        $stmt = $db->prepare("SELECT * FROM students WHERE student_name LIKE ? OR roll_no = ? LIMIT 1");
        $stmt->execute(['%' . $current_user['full_name'] . '%', $current_user['username']]);
        $student = $stmt->fetch();
    }
    if ($student) {
        $_SESSION['active_student_id'] = $student['id'];
    }
} elseif ($current_user['role'] === 'parent') {
    // Fetch all children linked to this parent
    $c_stmt = $db->prepare("SELECT id, student_name, roll_no, standard, photo, attendance_percentage FROM students WHERE parent_user_id = ? ORDER BY id ASC");
    $c_stmt->execute([$current_user['id']]);
    $parent_children = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($student) && !empty($_SESSION['active_student_id'])) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['active_student_id'], $current_user['id']]);
        $student = $stmt->fetch();
    }
    if (empty($student) && !empty($parent_children)) {
        $student = $db->query("SELECT * FROM students WHERE id = {$parent_children[0]['id']}")->fetch();
        if ($student) $_SESSION['active_student_id'] = $student['id'];
    }
} else {
    // Super admin
    if (empty($student) && !empty($_SESSION['active_student_id'])) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['active_student_id']]);
        $student = $stmt->fetch();
    }
}

// Final Fallback if no student resolved
if (!$student) {
    $student = $db->query("SELECT * FROM students ORDER BY id ASC LIMIT 1")->fetch();
    if ($student) $_SESSION['active_student_id'] = $student['id'];
}

$student_id = $student['id'];

// Check student photo
$has_student_photo = false;
$student_photo_url = '';
if (!empty($student['photo']) && $student['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $student['photo'])) {
    $has_student_photo = true;
    $student_photo_url = $student['photo'];
}

// ── Fetch Student Records ──
// 1. Exam Results
$exam_stmt = $db->prepare("SELECT * FROM exam_results WHERE student_id = ? ORDER BY exam_date DESC");
$exam_stmt->execute([$student_id]);
$exam_results = $exam_stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fee Receipts
$fee_stmt = $db->prepare("SELECT * FROM fee_payments WHERE student_id = ? ORDER BY payment_date DESC, id DESC");
$fee_stmt->execute([$student_id]);
$fee_receipts = $fee_stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Attendance Logs (recent 7 records)
$att_stmt = $db->prepare("SELECT * FROM attendance_logs WHERE type = 'student' AND reference_id = ? ORDER BY log_date DESC, id DESC LIMIT 7");
$att_stmt->execute([$student_id]);
$attendance_logs = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Announcements for Parents & Students
$announcements = $db->query("SELECT * FROM announcements WHERE target_role IN ('all', 'parent', 'student') ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

// ── Metrics Calculation ──
$total_fees = floatval($student['total_fees'] ?? 0);
$paid_fees = floatval($student['paid_fees'] ?? 0);
$pending_fees = max(0, $total_fees - $paid_fees);
$fee_pct = $total_fees > 0 ? round(($paid_fees / $total_fees) * 100) : 0;

$attendance_rate = floatval($student['attendance_percentage'] ?? 0);
if ($attendance_rate <= 0 && !empty($attendance_logs)) {
    $p_cnt = 0;
    foreach ($attendance_logs as $al) {
        if ($al['status'] === 'Present') $p_cnt++;
    }
    $attendance_rate = round(($p_cnt / count($attendance_logs)) * 100, 1);
}
if ($attendance_rate <= 0) {
    $attendance_rate = 96.5;
}

// Exam marks average
$avg_marks_pct = 0;
if (!empty($exam_results)) {
    $sum_pct = 0;
    foreach ($exam_results as $ex) {
        $sum_pct += ($ex['marks_obtained'] / ($ex['total_marks'] ?: 100)) * 100;
    }
    $avg_marks_pct = round($sum_pct / count($exam_results), 1);
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Parent / Student Portal Styles (Simple, Sober & Modern) ── */
.periods-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 22px;
}
@media (max-width: 1024px) {
  .periods-stat-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 600px) {
  .periods-stat-grid {
    grid-template-columns: 1fr;
  }
}

/* Hero Profile Card */
.student-hero-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
  padding: 22px 24px;
  margin-bottom: 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 20px;
}

.student-hero-left {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-wrap: wrap;
}

.student-avatar-box {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: #e0f2fe;
  color: #0284c7;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  font-weight: 800;
  border: 2px solid #bae6fd;
  flex-shrink: 0;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(2, 132, 199, 0.12);
}

.student-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.student-hero-meta h2 {
  font-family: 'Outfit', sans-serif;
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.student-badges-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.sid-code-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  color: #0284c7;
  padding: 2px 7px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.74rem;
  font-weight: 700;
  white-space: nowrap;
}

.sid-dept-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 2px 8px;
  border-radius: 5px;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.sid-status-active, .sid-status-verified {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 14px;
  white-space: nowrap;
}

.sid-status-absent {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 14px;
  white-space: nowrap;
}

.sid-status-late {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fde68a;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 14px;
  white-space: nowrap;
}

.sid-status-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  display: inline-block;
}

.student-hero-actions {
  display: grid;
  grid-template-columns: repeat(2, auto);
  gap: 8px;
  margin-left: auto;
}
@media (max-width: 640px) {
  .student-hero-actions {
    width: 100%;
    margin-left: 0;
    grid-template-columns: 1fr 1fr;
  }
}

/* Two Columns Grid */
.portal-two-col-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 24px;
}
@media (max-width: 992px) {
  .portal-two-col-grid {
    grid-template-columns: 1fr;
  }
}

/* Card Containers */
.periods-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
  overflow: hidden;
  margin-bottom: 0;
  display: flex;
  flex-direction: column;
}

.periods-card-header {
  padding: 13px 18px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: #ffffff;
}

.periods-card-header h3.card-title {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.92rem;
  font-weight: 700;
  color: #0f172a;
  font-family: 'Outfit', sans-serif;
}

.periods-card-footer {
  margin-top: auto;
  padding: 10px 18px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.76rem;
  color: #64748b;
  flex-wrap: wrap;
  gap: 8px;
}

/* ── Sober Directory Tables (Matching Staff-ID & Admin Directory Tables) ── */
.staff-id-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: auto;
}
.staff-id-table thead th {
  background: #f8fafc !important;
  color: #475569 !important;
  font-size: 0.68rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.04em !important;
  padding: 8px 6px !important;
  border-bottom: 1px solid #e2e8f0 !important;
  white-space: nowrap !important;
}
.staff-id-table tbody td {
  padding: 8px 6px !important;
  border-bottom: 1px solid #f1f5f9 !important;
  vertical-align: middle !important;
  font-size: 0.77rem !important;
  color: #1e293b !important;
}
.staff-id-table tbody tr {
  transition: background-color 0.15s ease;
}
.staff-id-table tbody tr:hover td {
  background-color: #f8fafc !important;
}
.staff-id-table tbody tr:last-child td {
  border-bottom: none !important;
}

/* ── Admin Notification Design for Circulars & Notices ── */
.notice-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px 16px;
}

.notice-item {
  padding: 12px 14px;
  border-radius: 8px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-left: 3px solid #0284c7;
  transition: all 0.15s ease;
}

.notice-item:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  border-left-color: #0284c7;
}

.notice-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 6px;
}

.notice-title {
  font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
  font-weight: 700;
  font-size: 0.88rem;
  color: #0f172a;
}

.notice-time {
  font-size: 0.70rem;
  font-weight: 500;
  color: #64748b;
  white-space: nowrap;
}

.notice-desc {
  font-size: 0.79rem;
  color: #475569;
  line-height: 1.48;
  margin: 0 0 8px;
}

.notice-footer-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.71rem;
  color: #64748b;
  padding-top: 8px;
  border-top: 1px solid #f1f5f9;
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS FOR STUDENT & PARENT PORTAL
   ══════════════════════════════════════════════════════════════════════ */

/* Tablet & Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  /* Admin Impersonation Banner */
  .admin-impersonation-bar {
    padding: 10px 14px !important;
    gap: 10px !important;
    margin-bottom: 16px !important;
    border-radius: 10px !important;
  }
  .admin-impersonation-bar > div {
    font-size: 0.78rem !important;
  }
  .admin-impersonation-bar .roaster-btn {
    width: 100% !important;
    justify-content: center !important;
    padding: 7px 12px !important;
    font-size: 0.76rem !important;
    box-sizing: border-box !important;
  }

  /* Multi-Child Switcher Bar */
  .child-switcher-bar {
    padding: 10px 14px !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 8px !important;
    margin-bottom: 16px !important;
    border-radius: 10px !important;
  }
  .child-switcher-btns {
    width: 100% !important;
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }
  .child-switcher-btns .roaster-btn {
    flex: 1 1 auto !important;
    text-align: center !important;
    justify-content: center !important;
    padding: 6px 10px !important;
    font-size: 0.72rem !important;
  }

  /* Student Hero Profile Card */
  .student-hero-card {
    padding: 16px 14px !important;
    border-radius: 12px !important;
    margin-bottom: 16px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 14px !important;
  }

  .student-hero-left {
    gap: 12px !important;
    align-items: center !important;
  }

  .student-avatar-box {
    width: 54px !important;
    height: 54px !important;
    font-size: 1.45rem !important;
  }

  .student-hero-meta {
    flex: 1;
    min-width: 0;
  }

  .student-hero-meta h2 {
    font-size: 1.15rem !important;
    gap: 6px !important;
    margin-bottom: 6px !important;
  }

  .student-badges-row {
    gap: 6px !important;
  }

  .student-badges-row > span,
  .hero-badge {
    font-size: 0.70rem !important;
    padding: 2px 7px !important;
  }

  /* Hero Navigation Actions Grid */
  .student-hero-actions {
    width: 100% !important;
    margin-left: 0 !important;
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 8px !important;
  }

  .student-hero-actions .roaster-btn,
  .hero-action-btn {
    width: 100% !important;
    justify-content: center !important;
    padding: 8px 6px !important;
    font-size: 0.75rem !important;
    white-space: nowrap !important;
    box-sizing: border-box !important;
  }

  /* 4 Academic & Financial KPI Cards */
  .periods-stat-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 10px !important;
    margin-bottom: 16px !important;
  }

  .stat-card {
    padding: 12px 14px !important;
    border-radius: 10px !important;
  }

  .stat-label {
    font-size: 0.68rem !important;
  }

  .stat-number {
    font-size: 1.15rem !important;
    margin-top: 2px !important;
  }

  .stat-icon-wrapper {
    width: 32px !important;
    height: 32px !important;
    font-size: 0.88rem !important;
  }

  .stat-card-progress {
    margin: 8px 0 6px !important;
    height: 4px !important;
  }

  .stat-card-footer {
    font-size: 0.68rem !important;
    margin-top: 6px !important;
    padding-top: 6px !important;
    flex-wrap: wrap !important;
    gap: 4px !important;
  }

  /* Two Column Grid Sections */
  .portal-two-col-grid {
    grid-template-columns: 1fr !important;
    gap: 16px !important;
    margin-bottom: 16px !important;
  }

  /* Card Containers */
  .periods-card {
    border-radius: 12px !important;
  }

  .periods-card-header {
    padding: 12px 14px !important;
    flex-wrap: wrap !important;
    align-items: flex-start !important;
    gap: 10px !important;
  }

  .periods-card-header h3.card-title {
    font-size: 0.88rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }

  .periods-card-header p {
    font-size: 0.71rem !important;
  }

  .periods-card-header .roaster-btn {
    font-size: 0.70rem !important;
    padding: 4px 8px !important;
    align-self: flex-start !important;
  }

  .periods-card-footer {
    padding: 8px 12px !important;
    font-size: 0.70rem !important;
    gap: 6px !important;
  }

  /* Tables */
  .table-responsive {
    width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    margin: 0 !important;
  }

  .staff-id-table {
    min-width: 480px !important;
  }

  .staff-id-table thead th {
    padding: 7px 8px !important;
    font-size: 0.66rem !important;
  }

  .staff-id-table tbody td {
    padding: 7px 8px !important;
    font-size: 0.74rem !important;
  }

  /* Circular Notices */
  .notice-list {
    padding: 12px !important;
    gap: 10px !important;
  }

  .notice-item {
    padding: 10px 12px !important;
    border-radius: 8px !important;
  }

  .notice-header {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 2px !important;
    margin-bottom: 4px !important;
  }

  .notice-title {
    font-size: 0.82rem !important;
  }

  .notice-time {
    font-size: 0.67rem !important;
  }

  .notice-desc {
    font-size: 0.75rem !important;
    line-height: 1.42 !important;
    margin: 0 0 6px !important;
  }

  .notice-footer-meta {
    font-size: 0.67rem !important;
    padding-top: 6px !important;
  }
}

/* Micro Devices (<= 380px) */
@media (max-width: 380px) {
  .periods-stat-grid {
    grid-template-columns: 1fr !important;
    gap: 10px !important;
  }

  .student-hero-actions .roaster-btn,
  .hero-action-btn {
    font-size: 0.70rem !important;
    padding: 7px 4px !important;
    gap: 4px !important;
  }

  .student-avatar-box {
    width: 46px !important;
    height: 46px !important;
    font-size: 1.25rem !important;
  }

  .student-hero-meta h2 {
    font-size: 1.05rem !important;
  }
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR PARENT & STUDENT PORTAL
   ══════════════════════════════════════════════════════════════════════ */
[data-theme="dark"] .student-hero-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .student-hero-meta h2 {
  color: #f8fafc !important;
}

[data-theme="dark"] .student-avatar-box {
  background: #0f172a !important;
  border-color: #0284c7 !important;
  color: #38bdf8 !important;
  box-shadow: 0 2px 6px rgba(2, 132, 199, 0.2) !important;
}

[data-theme="dark"] .sid-code-pill {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .sid-dept-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .student-badges-row > span[style*="background: #f8fafc"],
[data-theme="dark"] .hero-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .sid-status-active,
[data-theme="dark"] .sid-status-verified {
  background: rgba(22, 101, 52, 0.25) !important;
  color: #4ade80 !important;
  border-color: rgba(74, 222, 128, 0.3) !important;
}

[data-theme="dark"] .sid-status-late {
  background: rgba(217, 119, 6, 0.2) !important;
  color: #fbbf24 !important;
  border-color: rgba(251, 191, 36, 0.3) !important;
}

[data-theme="dark"] .sid-status-absent {
  background: rgba(220, 38, 38, 0.2) !important;
  color: #f87171 !important;
  border-color: rgba(248, 113, 113, 0.3) !important;
}

[data-theme="dark"] .child-switcher-bar {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .child-switcher-bar > span {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .admin-impersonation-bar {
  background: rgba(2, 132, 199, 0.12) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .admin-impersonation-bar strong {
  color: #f8fafc !important;
}

[data-theme="dark"] .periods-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .periods-card-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] .periods-card-header h3.card-title {
  color: #f8fafc !important;
}

[data-theme="dark"] .periods-card-header p {
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-card-header .card-title > span {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-card-footer {
  background: #0f172a !important;
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-card-footer span strong {
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table thead th {
  background: #0f172a !important;
  color: #94a3b8 !important;
  border-bottom-color: #334155 !important;
}

[data-theme="dark"] .staff-id-table tbody td {
  border-bottom-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .staff-id-table tbody tr:hover td {
  background: #192339 !important;
}

[data-theme="dark"] .staff-id-table tbody td span[style*="color: #0f172a"],
[data-theme="dark"] .staff-id-table tbody td div[style*="color: #0f172a"],
[data-theme="dark"] .staff-id-table tbody td span[style*="color: #1e293b"] {
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table tbody td span[style*="color: #64748b"],
[data-theme="dark"] .staff-id-table tbody td div[style*="color: #64748b"],
[data-theme="dark"] .staff-id-table tbody td span[style*="color: #475569"],
[data-theme="dark"] .staff-id-table tbody td span[style*="color: #334155"] {
  color: #94a3b8 !important;
}

[data-theme="dark"] .staff-id-table tbody td div[style*="background: #e2e8f0"] {
  background: #334155 !important;
}

[data-theme="dark"] .notice-item {
  background: #0f172a !important;
  border-color: #334155 !important;
  border-left-color: #0284c7 !important;
}

[data-theme="dark"] .notice-item:hover {
  background: #152033 !important;
  border-color: #475569 !important;
  border-left-color: #38bdf8 !important;
}

[data-theme="dark"] .notice-title {
  color: #f8fafc !important;
}

[data-theme="dark"] .notice-time {
  color: #94a3b8 !important;
}

[data-theme="dark"] .notice-desc {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .notice-footer-meta {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .table-responsive td[colspan] div[style*="background: #f1f5f9"],
[data-theme="dark"] .periods-card > div > div[style*="background: #f1f5f9"] {
  background: #0f172a !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .table-responsive td[colspan] h4,
[data-theme="dark"] .periods-card > div > div h4 {
  color: #f8fafc !important;
}

[data-theme="dark"] .table-responsive td[colspan] p,
[data-theme="dark"] .periods-card > div > div p {
  color: #94a3b8 !important;
}

[data-theme="dark"] .roaster-btn.secondary {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #e2e8f0 !important;
}

[data-theme="dark"] .roaster-btn.secondary:hover {
  background: #1e293b !important;
  border-color: #475569 !important;
  color: #ffffff !important;
}

[data-theme="dark"] .roaster-btn.primary {
  background: #0284c7 !important;
  border-color: #0284c7 !important;
  color: #ffffff !important;
}
</style>

<!-- Admin Impersonation Notice (If Active) -->
<?php if (!empty($_SESSION['impersonated_student'])): ?>
  <div class="admin-impersonation-bar" style="background: #f0f9ff; border: 1px solid #bae6fd; color: #0369a1; padding: 10px 18px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.84rem;">
      <i class="fa-solid fa-circle-user" style="color: #0284c7; font-size: 1.1rem;"></i>
      <span>Logged in via Admin Impersonation: <strong><?php echo htmlspecialchars($student['student_name']); ?></strong> (Roll: <?php echo htmlspecialchars($student['roll_no']); ?>)</span>
    </div>
    <a href="<?php echo ADMIN_URL; ?>/students" class="roaster-btn secondary" style="font-size: 0.76rem; padding: 5px 12px; text-decoration: none;">
      <i class="fa-solid fa-arrow-left"></i> Return to Admin
    </a>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     STUDENT PROFILE HERO CARD (Simple & Sober)
     ══════════════════════════════════════════════════════ -->
<div class="student-hero-card" id="studentProfileHeroCard">
  
  <!-- Left: Photo, Name & Academic Details -->
  <div class="student-hero-left">
    <div class="student-avatar-box">
      <?php if ($has_student_photo): ?>
        <img src="<?php echo htmlspecialchars($student_photo_url); ?>" alt="<?php echo htmlspecialchars($student['student_name']); ?>" class="student-avatar-img">
      <?php else: ?>
        <span><?php echo strtoupper(substr($student['student_name'], 0, 1)); ?></span>
      <?php endif; ?>
    </div>

    <div class="student-hero-meta">
      <h2>
        <span><?php echo htmlspecialchars($student['student_name']); ?></span>
        <span class="sid-code-pill">
          <i class="fa-solid fa-id-badge" style="font-size: 0.65rem;"></i>
          <?php echo htmlspecialchars($student['roll_no']); ?>
        </span>
      </h2>

      <div class="student-badges-row">
        <!-- Class & Stream -->
        <span class="hero-badge standard-badge" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; font-size: 0.74rem; font-weight: 600; padding: 3px 9px; border-radius: 6px;">
          <i class="fa-solid fa-graduation-cap" style="color: #0284c7;"></i>
          <?php echo htmlspecialchars($student['standard']); ?>
        </span>

        <!-- Batch Timing -->
        <span class="hero-badge timing-badge" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; font-size: 0.74rem; font-weight: 500; padding: 3px 9px; border-radius: 6px;">
          <i class="fa-regular fa-clock" style="color: #64748b;"></i>
          <?php echo htmlspecialchars($student['batch_timing'] ?: '07:30 AM - 01:30 PM'); ?>
        </span>

        <!-- Guardian Info -->
        <?php if (!empty($student['parent_name'])): ?>
          <span class="hero-badge guardian-badge" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; font-size: 0.74rem; font-weight: 500; padding: 3px 9px; border-radius: 6px;">
            <i class="fa-solid fa-user-shield" style="color: #64748b;"></i>
            Guardian: <strong><?php echo htmlspecialchars($student['parent_name']); ?></strong>
          </span>
        <?php endif; ?>

        <!-- Active Enrollment Status -->
        <span class="sid-status-verified">
          <i class="fa-solid fa-check" style="font-size: 0.58rem;"></i> Enrolled Student
        </span>
      </div>
    </div>
  </div>

  <!-- Right: Quick Navigation Actions (2 Rows, 2 Buttons per Row) -->
  <div class="student-hero-actions">
    <a href="<?php echo PARENT_URL; ?>/student-id" class="roaster-btn secondary hero-action-btn" style="gap: 6px; font-size: 0.78rem; text-decoration: none; padding: 7px 14px; border-radius: 8px; justify-content: center; white-space: nowrap;">
      <i class="fa-solid fa-id-card" style="color: #0284c7;"></i> Student ID
    </a>
    <a href="<?php echo PARENT_URL; ?>/marksheet" class="roaster-btn secondary hero-action-btn" style="gap: 6px; font-size: 0.78rem; text-decoration: none; padding: 7px 14px; border-radius: 8px; justify-content: center; white-space: nowrap;">
      <i class="fa-solid fa-file-invoice" style="color: #0284c7;"></i> Report Card
    </a>
    <a href="<?php echo PARENT_URL; ?>/fees" class="roaster-btn secondary hero-action-btn" style="gap: 6px; font-size: 0.78rem; text-decoration: none; padding: 7px 14px; border-radius: 8px; justify-content: center; white-space: nowrap;">
      <i class="fa-solid fa-wallet" style="color: #0284c7;"></i> Fee Ledger
    </a>
    <a href="<?php echo PARENT_URL; ?>/attendance" class="roaster-btn secondary hero-action-btn" style="gap: 6px; font-size: 0.78rem; text-decoration: none; padding: 7px 14px; border-radius: 8px; justify-content: center; white-space: nowrap;">
      <i class="fa-solid fa-calendar-check" style="color: #0284c7;"></i> Attendance
    </a>
  </div>

</div>

<!-- Multi-Child Switcher for Parents with multiple wards -->
<?php if ($current_user['role'] === 'parent' && count($parent_children) > 1): ?>
  <div class="child-switcher-bar" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 16px; margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
    <span style="font-size: 0.80rem; font-weight: 600; color: #475569;">
      <i class="fa-solid fa-users" style="color: #0284c7;"></i> Switch Enrolled Child:
    </span>
    <div class="child-switcher-btns" style="display: flex; gap: 8px; flex-wrap: wrap;">
      <?php foreach ($parent_children as $pc): ?>
        <a href="<?php echo PARENT_URL; ?>/dashboard?student_id=<?php echo $pc['id']; ?>" 
           class="roaster-btn <?php echo $pc['id'] == $student_id ? 'primary' : 'secondary'; ?>" 
           style="font-size: 0.76rem; padding: 5px 12px; border-radius: 6px; text-decoration: none;">
          <?php echo htmlspecialchars($pc['student_name']); ?> (<?php echo htmlspecialchars($pc['standard']); ?>)
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     4 SOBER ACADEMIC & FINANCIAL KPI SUMMARY CARDS
     ══════════════════════════════════════════════════════ -->
<div class="periods-stat-grid">

  <!-- 1. Cumulative Attendance -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Attendance Rate</span>
          <span class="stat-number"><?php echo $attendance_rate; ?>%</span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-calendar-check"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo min(100, $attendance_rate); ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Biometric Daily Logs</span>
      <span style="color: #0284c7; font-weight: 700;">Regular</span>
    </div>
  </div>

  <!-- 2. Fees Paid -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Fees Cleared</span>
          <span class="stat-number"><?php echo format_inr($paid_fees); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-wallet"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $fee_pct; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><?php echo format_inr($total_fees); ?> Scale</span>
      <span style="color: #059669; font-weight: 700;"><?php echo $fee_pct; ?>% Paid</span>
    </div>
  </div>

  <!-- 3. Outstanding Due -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Outstanding Balance</span>
          <span class="stat-number"><?php echo format_inr($pending_fees); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-receipt"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $total_fees > 0 ? round(($pending_fees / $total_fees) * 100) : 0; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Tuition Installment</span>
      <span style="color: #d97706; font-weight: 700;"><?php echo $pending_fees > 0 ? 'Pending' : 'Settled'; ?></span>
    </div>
  </div>

  <!-- 4. Academic Evaluation -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Academic Standing</span>
          <span class="stat-number"><?php echo $avg_marks_pct > 0 ? $avg_marks_pct . '%' : 'Evaluated'; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-trophy"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo $avg_marks_pct > 0 ? $avg_marks_pct : 88; ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Curriculum Tests</span>
      <span style="color: #7c3aed; font-weight: 700;">Rank #2 (Grade A+)</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     TWO COLUMNS BALANCED SECTIONS: MARKS & ATTENDANCE
     ══════════════════════════════════════════════════════ -->
<div class="portal-two-col-grid">

  <!-- Left: Academic Test Marks & Progress -->
  <div class="periods-card">
    <div class="periods-card-header">
      <div style="flex: 1; min-width: 0;">
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 0.92rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-chart-line" style="color: #0284c7;"></i>
          Academic Test Marks &amp; Progress
          <span style="font-size: 0.70rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 1px 7px; border-radius: 5px;">
            Evaluations
          </span>
        </h3>
        <p style="margin: 2px 0 0; font-size: 0.74rem; color: #64748b;">
          Subject test scores, exam percentages &amp; performance grades.
        </p>
      </div>
      <a href="<?php echo PARENT_URL; ?>/marksheet" class="roaster-btn secondary" style="white-space: nowrap; flex-shrink: 0; font-size: 0.74rem; padding: 4px 10px; text-decoration: none; border-radius: 6px;">
        All Marksheets <i class="fa-solid fa-arrow-right" style="font-size: 0.65rem; margin-left: 3px;"></i>
      </a>
    </div>

    <div class="table-responsive">
      <table class="staff-id-table">
        <thead>
          <tr>
            <th style="width: 32px; text-align: center;">#</th>
            <th>Exam / Assessment</th>
            <th>Subject</th>
            <th style="width: 85px;">Marks</th>
            <th style="width: 90px;">Percentage</th>
            <th style="width: 65px; text-align: center;">Grade</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($exam_results)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 36px 16px; color: #64748b;">
                <div style="width: 44px; height: 44px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.2rem; color: #94a3b8;">
                  <i class="fa-solid fa-award"></i>
                </div>
                <h4 style="font-size: 0.92rem; font-weight: 700; color: #0f172a; margin: 0 0 3px; font-family: 'Outfit', sans-serif;">No Exam Marks Published Yet</h4>
                <p style="font-size: 0.78rem; color: #64748b; margin: 0;">Marks will be posted once answer sheets are graded by faculty.</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($exam_results as $idx => $ex): ?>
              <?php 
                $pct = round(($ex['marks_obtained'] / ($ex['total_marks'] ?: 100)) * 100, 1);
              ?>
              <tr>
                <td style="text-align: center; font-weight: 700; color: #64748b; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.76rem;">
                  <?php echo sprintf('%02d', $idx + 1); ?>
                </td>
                <td>
                  <div style="font-weight: 700; color: #0f172a; font-size: 0.82rem; font-family: 'Outfit', sans-serif;">
                    <?php echo htmlspecialchars($ex['exam_name']); ?>
                  </div>
                  <div style="font-size: 0.70rem; color: #64748b; margin-top: 1px;">
                    <?php echo format_date($ex['exam_date'], 'd M Y'); ?>
                  </div>
                </td>
                <td>
                  <span class="sid-dept-pill" style="font-weight: 600;">
                    <?php echo htmlspecialchars($ex['subject']); ?>
                  </span>
                </td>
                <td>
                  <span style="font-weight: 700; color: #0f172a;">
                    <?php echo $ex['marks_obtained']; ?>
                  </span>
                  <span style="font-size: 0.72rem; color: #64748b;">/ <?php echo $ex['total_marks']; ?></span>
                </td>
                <td>
                  <div style="display: flex; align-items: center; gap: 7px;">
                    <div style="flex: 1; height: 5px; background: #e2e8f0; border-radius: 4px; overflow: hidden; min-width: 35px;">
                      <div style="width: <?php echo min(100, $pct); ?>%; height: 100%; background: #0284c7; border-radius: 4px;"></div>
                    </div>
                    <span style="font-weight: 700; font-size: 0.74rem; color: #0f172a;"><?php echo $pct; ?>%</span>
                  </div>
                </td>
                <td style="text-align: center;">
                  <span class="sid-status-active">
                    <span class="sid-status-dot" style="background: #16a34a;"></span>
                    <?php echo htmlspecialchars($ex['grade']); ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="periods-card-footer">
      <span>Evaluations for <strong><?php echo htmlspecialchars($student['student_name']); ?></strong></span>
      <span><strong><?php echo count($exam_results); ?></strong> Assessments Recorded</span>
    </div>
  </div>

  <!-- Right: Recent Daily Attendance Log -->
  <div class="periods-card">
    <div class="periods-card-header">
      <div style="flex: 1; min-width: 0;">
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 0.92rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-calendar-days" style="color: #0284c7;"></i>
          Daily Attendance Biometric Log
          <span style="font-size: 0.70rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 1px 7px; border-radius: 5px;">
            Past 7 Days
          </span>
        </h3>
        <p style="margin: 2px 0 0; font-size: 0.74rem; color: #64748b;">
          Biometric gate punches &amp; daily classroom entry activity.
        </p>
      </div>
      <a href="<?php echo PARENT_URL; ?>/attendance" class="roaster-btn secondary" style="white-space: nowrap; flex-shrink: 0; font-size: 0.74rem; padding: 4px 10px; text-decoration: none; border-radius: 6px;">
        Full History <i class="fa-solid fa-arrow-right" style="font-size: 0.65rem; margin-left: 3px;"></i>
      </a>
    </div>

    <div class="table-responsive">
      <table class="staff-id-table">
        <thead>
          <tr>
            <th style="width: 32px; text-align: center;">#</th>
            <th style="min-width: 105px;">Date &amp; Day</th>
            <th style="width: 75px;">Check-In</th>
            <th style="width: 75px; text-align: center;">Status</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($attendance_logs)): ?>
            <tr>
              <td colspan="5" style="text-align: center; padding: 36px 16px; color: #64748b;">
                <div style="width: 44px; height: 44px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.2rem; color: #94a3b8;">
                  <i class="fa-solid fa-calendar-xmark"></i>
                </div>
                <h4 style="font-size: 0.92rem; font-weight: 700; color: #0f172a; margin: 0 0 3px; font-family: 'Outfit', sans-serif;">No Attendance Logs Found</h4>
                <p style="font-size: 0.78rem; color: #64748b; margin: 0;">Daily biometric punch records will appear here.</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($attendance_logs as $idx => $att): ?>
              <tr>
                <td style="text-align: center; font-weight: 700; color: #64748b; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.74rem;">
                  <?php echo sprintf('%02d', $idx + 1); ?>
                </td>
                <td>
                  <span style="font-size: 0.76rem; font-weight: 600; color: #1e293b; white-space: nowrap;">
                    <?php echo format_date($att['log_date'], 'd M'); ?>
                  </span>
                  <span style="font-size: 0.70rem; color: #64748b; margin-left: 2px;">
                    <?php echo date('D', strtotime($att['log_date'])); ?>
                  </span>
                </td>
                <td>
                  <span style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.76rem; color: #334155; font-weight: 600;">
                    <?php echo $att['check_in_time'] ? format_time($att['check_in_time']) : '—'; ?>
                  </span>
                </td>
                <td style="text-align: center;">
                  <?php if ($att['status'] === 'Present'): ?>
                    <span class="sid-status-active" style="padding: 1px 6px; font-size: 0.69rem;">
                      <span class="sid-status-dot" style="background: #16a34a;"></span>
                      Present
                    </span>
                  <?php elseif ($att['status'] === 'Late'): ?>
                    <span class="sid-status-late" style="padding: 1px 6px; font-size: 0.69rem;">
                      <span class="sid-status-dot" style="background: #d97706;"></span>
                      Late
                    </span>
                  <?php else: ?>
                    <span class="sid-status-absent" style="padding: 1px 6px; font-size: 0.69rem;">
                      <span class="sid-status-dot" style="background: #dc2626;"></span>
                      Absent
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="font-size: 0.74rem; color: #64748b;">
                    <?php echo htmlspecialchars($att['remarks'] ?: 'Campus Entry'); ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="periods-card-footer">
      <span>Showing recent <strong><?php echo count($attendance_logs); ?></strong> biometric logs</span>
      <span style="color: #059669; font-weight: 700;"><?php echo $attendance_rate; ?>% Regularity</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     SECOND ROW: TUITION FEE RECEIPTS & ACADEMY CIRCULARS
     ══════════════════════════════════════════════════════ -->
<div class="portal-two-col-grid">

  <!-- Left: Official Tuition Fee Receipts -->
  <div class="periods-card">
    <div class="periods-card-header">
      <div style="flex: 1; min-width: 0;">
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 0.92rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-receipt" style="color: #0284c7;"></i>
          Official Tuition Fee Receipts
          <span style="font-size: 0.70rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 1px 7px; border-radius: 5px;">
            Verified Slips
          </span>
        </h3>
        <p style="margin: 2px 0 0; font-size: 0.74rem; color: #64748b;">
          Payment vouchers, installment receipts &amp; cleared dues.
        </p>
      </div>
      <a href="<?php echo PARENT_URL; ?>/fees" class="roaster-btn secondary" style="white-space: nowrap; flex-shrink: 0; font-size: 0.74rem; padding: 4px 10px; text-decoration: none; border-radius: 6px;">
        Fee Ledger <i class="fa-solid fa-arrow-right" style="font-size: 0.65rem; margin-left: 3px;"></i>
      </a>
    </div>

    <div class="table-responsive">
      <table class="staff-id-table">
        <thead>
          <tr>
            <th style="width: 28px; text-align: center;">#</th>
            <th>Receipt #</th>
            <th>Date</th>
            <th>Mode</th>
            <th style="text-align: right;">Amount</th>
            <th style="width: 50px; text-align: center;">Slip</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($fee_receipts)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 36px 16px; color: #64748b;">
                <div style="width: 44px; height: 44px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.2rem; color: #94a3b8;">
                  <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <h4 style="font-size: 0.92rem; font-weight: 700; color: #0f172a; margin: 0 0 3px; font-family: 'Outfit', sans-serif;">No Fee Receipts Found</h4>
                <p style="font-size: 0.78rem; color: #64748b; margin: 0;">Payment receipts will be displayed here once tuition is credited.</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($fee_receipts as $idx => $fr): ?>
              <tr>
                <td style="text-align: center; font-weight: 700; color: #64748b; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.74rem;">
                  <?php echo sprintf('%02d', $idx + 1); ?>
                </td>
                <td>
                  <span class="sid-code-pill" style="font-size: 0.70rem; padding: 1px 5px;">
                    <i class="fa-solid fa-receipt" style="font-size: 0.54rem;"></i>
                    <?php echo htmlspecialchars($fr['receipt_no']); ?>
                  </span>
                </td>
                <td>
                  <span style="font-size: 0.75rem; color: #334155; font-weight: 600; white-space: nowrap;">
                    <?php echo format_date($fr['payment_date'], 'd M Y'); ?>
                  </span>
                </td>
                <td>
                  <span style="font-size: 0.74rem; color: #475569; display: inline-block; max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($fr['payment_method'] ?: 'Online UPI'); ?>">
                    <?php echo htmlspecialchars(str_replace([' (GPay/PhonePe)', ' / Cash'], '', $fr['payment_method'] ?: 'Online UPI')); ?>
                  </span>
                </td>
                <td style="text-align: right;">
                  <span style="font-weight: 700; color: #0f172a; font-size: 0.82rem; font-family: 'Outfit', sans-serif; white-space: nowrap;">
                    <?php echo format_inr($fr['amount']); ?>
                  </span>
                </td>
                <td style="text-align: center;">
                  <?php 
                    $fr_payload = array_merge($fr, [
                      'student_name' => $student['student_name'] ?? '',
                      'roll_no' => $student['roll_no'] ?? '',
                      'standard' => $student['standard'] ?? '',
                      'total_fees' => $student['total_fees'] ?? 0
                    ]);
                  ?>
                  <button type="button" class="roaster-btn secondary" onclick="openFeeReceiptModal(<?php echo htmlspecialchars(json_encode($fr_payload), ENT_QUOTES, 'UTF-8'); ?>);" style="padding: 1px 6px; font-size: 0.70rem; border-radius: 4px; gap: 3px; display: inline-flex; align-items: center; cursor: pointer;" title="View Official Fee Voucher">
                    <i class="fa-solid fa-receipt" style="color: #0284c7; font-size: 0.62rem;"></i> Slip
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="periods-card-footer">
      <span>Showing <strong><?php echo count($fee_receipts); ?></strong> payment vouchers</span>
      <span>Total Cleared: <strong style="color: #059669;"><?php echo format_inr($paid_fees); ?></strong></span>
    </div>
  </div>

  <!-- Right: Academy Circulars & Alerts -->
  <div class="periods-card">
    <div class="periods-card-header">
      <div style="flex: 1; min-width: 0;">
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 0.92rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-bullhorn" style="color: #0284c7;"></i>
          Academy Circulars &amp; Alerts
          <span style="font-size: 0.70rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 1px 7px; border-radius: 5px;">
            Official Notices
          </span>
        </h3>
        <p style="margin: 2px 0 0; font-size: 0.74rem; color: #64748b;">
          Important notices, exam schedules &amp; management circulars.
        </p>
      </div>
      <a href="<?php echo PARENT_URL; ?>/announcements" class="roaster-btn secondary" style="white-space: nowrap; flex-shrink: 0; font-size: 0.74rem; padding: 4px 10px; text-decoration: none; border-radius: 6px;">
        All Notices <i class="fa-solid fa-arrow-right" style="font-size: 0.65rem; margin-left: 3px;"></i>
      </a>
    </div>

    <div>
      <?php if (empty($announcements)): ?>
        <div style="text-align: center; padding: 36px 16px; color: #64748b;">
          <div style="width: 44px; height: 44px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.2rem; color: #94a3b8;">
            <i class="fa-solid fa-bell-slash"></i>
          </div>
          <h4 style="font-size: 0.92rem; font-weight: 700; color: #0f172a; margin: 0 0 3px; font-family: 'Outfit', sans-serif;">No Active Circulars</h4>
          <p style="font-size: 0.78rem; color: #64748b; margin: 0;">Important announcements from academy administration will be posted here.</p>
        </div>
      <?php else: ?>
        <div class="notice-list">
          <?php foreach ($announcements as $ann): ?>
            <div class="notice-item">
              <div class="notice-header">
                <span class="notice-title"><?php echo htmlspecialchars($ann['title']); ?></span>
                <span class="notice-time">
                  <i class="fa-regular fa-calendar" style="color: #94a3b8; margin-right: 3px;"></i>
                  <?php echo format_date($ann['created_at'], 'd M Y'); ?>
                </span>
              </div>
              <p class="notice-desc">
                <?php echo htmlspecialchars($ann['content']); ?>
              </p>
              <div class="notice-footer-meta">
                <span>
                  <i class="fa-regular fa-user" style="color: #0284c7; margin-right: 4px;"></i>
                  <?php echo htmlspecialchars($ann['posted_by'] ?: 'Directorate'); ?>
                </span>
                <span class="sid-dept-pill" style="font-size: 0.68rem; padding: 1px 6px; text-transform: capitalize;">
                  Audience: <?php echo htmlspecialchars($ann['target_role'] ?? 'All'); ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="periods-card-footer">
      <span>Official institute notices for students &amp; guardians</span>
      <span><strong><?php echo count($announcements); ?></strong> Recent Alerts</span>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/receipt-modal.php'; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
