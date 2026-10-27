<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
if (in_array($current_user['role'], ['student', 'parent'])) {
    require_menu_permission('marksheet');
}
$page_title = 'Student Marksheet & Report Card | KSV Academy HRMS';
$page_heading = 'Student Academic Marksheet';
$page_subheading = 'Official Academic Statement of Marks, Examination Evaluations & Printable Report Cards';
$active_menu = 'marksheet';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// ── Student Resolution ──
$student = null;
$selected_student_id = intval($_GET['student_id'] ?? ($_GET['id'] ?? 0));
$selected_marksheet_id = intval($_GET['marksheet_id'] ?? 0);

// Check for Impersonation Session
if (!empty($_SESSION['impersonated_student'])) {
    $s_id = intval($_SESSION['impersonated_student']['id']);
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$s_id]);
    $student = $stmt->fetch();
    if ($student) $selected_student_id = $student['id'];
}

// 1. Direct query parameter override (?student_id=X)
if ($selected_student_id > 0 && !$student) {
    if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$selected_student_id]);
        $student = $stmt->fetch();
        if ($student) $_SESSION['active_student_id'] = $student['id'];
    } elseif ($current_user['role'] === 'parent') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([$selected_student_id, $current_user['id']]);
        $student = $stmt->fetch();
        if ($student) $_SESSION['active_student_id'] = $student['id'];
    }
}

// 2. Role Specific Resolution
$parent_children = [];
if ($current_user['role'] === 'student') {
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
        $selected_student_id = $student['id'];
    }
} elseif ($current_user['role'] === 'parent') {
    $c_stmt = $db->prepare("SELECT id, student_name, roll_no, standard, photo, section FROM students WHERE parent_user_id = ? ORDER BY id ASC");
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
    if ($student) $selected_student_id = $student['id'];
} else {
    // Super admin / Staff selector list
    $all_students_stmt = $db->prepare("SELECT id, student_name, roll_no, standard FROM students WHERE session_id = ? ORDER BY standard ASC, student_name ASC");
    $all_students_stmt->execute([$session_id]);
    $students_list = $all_students_stmt->fetchAll();

    if ($selected_student_id === 0 && !empty($students_list)) {
        $selected_student_id = $students_list[0]['id'];
    }
    if ($selected_student_id > 0 && !$student) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$selected_student_id]);
        $student = $stmt->fetch();
    }
}

// Fallback if no student resolved
if (!$student) {
    $student = $db->query("SELECT * FROM students ORDER BY id ASC LIMIT 1")->fetch();
    if ($student) {
        $_SESSION['active_student_id'] = $student['id'];
        $selected_student_id = $student['id'];
    }
}

$student_profile = $student;
$selected_student_id = intval($student['id'] ?? 1);

// ── Fetch Marksheets for this Student ──
$marksheets_stmt = $db->prepare("SELECT * FROM marksheets WHERE student_id = ? AND session_id = ? ORDER BY exam_date DESC, id DESC");
$marksheets_stmt->execute([$selected_student_id, $session_id]);
$student_marksheets = $marksheets_stmt->fetchAll();

// Select active marksheet to display
$active_marksheet = null;
if ($selected_marksheet_id > 0) {
    foreach ($student_marksheets as $ms) {
        if ($ms['id'] == $selected_marksheet_id) {
            $active_marksheet = $ms;
            break;
        }
    }
}
if (!$active_marksheet && !empty($student_marksheets)) {
    $active_marksheet = $student_marksheets[0];
}

// Student photo detection
$has_student_photo = false;
$student_photo_url = '';
if ($student_profile && !empty($student_profile['photo']) && $student_profile['photo'] !== '/assets/imgs/ksv-logo.jpg') {
    $photo_file = __DIR__ . '/../..' . $student_profile['photo'];
    if (file_exists($photo_file)) {
        $has_student_photo = true;
        $student_photo_url = $student_profile['photo'];
    }
}

// Verification QR code for marksheet
$verification_qr_url = '';
if ($student_profile && $active_marksheet) {
    $qr_payload = 'KSV-EVAL-ID:' . $active_marksheet['id'] . '|ROLL:' . $student_profile['roll_no'] . '|SCORE:' . $active_marksheet['percentage'] . '%|' . $active_marksheet['overall_grade'];
    $verification_qr_url = get_qr_code_url($qr_payload, '120x120');
}

// Avatar gradient fallback
$first_letter = $student_profile ? strtoupper(substr($student_profile['student_name'], 0, 1)) : 'S';
$grad_palette = [
    'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)',
    'linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)',
    'linear-gradient(135deg, #059669 0%, #047857 100%)',
    'linear-gradient(135deg, #d97706 0%, #b45309 100%)',
    'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)',
];
$avatar_bg = $grad_palette[ord($first_letter) % count($grad_palette)];

// Calculate Student Performance Stats
$total_evals = count($student_marksheets);
$max_score = 0;
$score_sum = 0;
$passed_count = 0;
foreach ($student_marksheets as $m) {
    $pct = floatval($m['percentage']);
    if ($pct > $max_score) $max_score = $pct;
    $score_sum += $pct;
    if ($m['result_status'] === 'PASS') $passed_count++;
}
$avg_score = $total_evals > 0 ? round($score_sum / $total_evals, 1) : 0;
$pass_rate = $total_evals > 0 ? round(($passed_count / $total_evals) * 100) : 100;

require_once __DIR__ . '/../includes/layout-header.php';
?>

<!-- html2canvas for High-Resolution Marksheet Export -->
<script src="<?php echo HRMS_URL; ?>/assets/js/html2canvas.min.js"></script>

<style>
/* ── Strict A4 Single-Page Print Setup (Identical to admin/marksheet.php) ── */
@media print {
  @page {
    size: A4 portrait;
    margin: 8mm 10mm;
  }
  
  html, body {
    margin: 0 !important;
    padding: 0 !important;
    background: #ffffff !important;
    width: 100% !important;
    height: 100% !important;
    overflow: hidden !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  body * {
    visibility: hidden;
  }

  .hrms-sidebar,
  .hrms-topbar,
  .no-print,
  .academic-stat-grid,
  .scholar-control-card,
  .roaster-toolbar-card,
  .hrms-card.no-print,
  .child-selector-banner,
  .periods-card,
  header,
  footer,
  nav {
    display: none !important;
  }

  .marksheet-print-canvas,
  .marksheet-print-canvas * {
    visibility: visible;
  }

  .marksheet-print-canvas {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    right: 0 !important;
    margin: 0 auto !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    background: #ffffff !important;
    box-shadow: none !important;
    border: 2px solid #0092df !important;
    border-radius: 12px !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: avoid !important;
    break-after: avoid !important;
  }

  .report-header-banner,
  .report-details-grid,
  .report-table,
  .report-summary-kpi-row,
  .report-remarks-card,
  .report-signatures-row {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }
}

/* ── Academic KPI Statistics Grid (4 Cards like staff/payroll.php) ── */
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

/* ── Scholar Control Banner ── */
.scholar-control-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 16px 22px;
  margin-bottom: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
}
.current-scholar-pill {
  display: flex;
  align-items: center;
  gap: 14px;
}
.scholar-mini-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #e0f2fe;
}
.scholar-mini-init {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  font-weight: 800;
  font-family: 'Outfit', sans-serif;
}
.current-scholar-meta h4 {
  margin: 0 0 3px;
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: 'Outfit', sans-serif;
}
.roll-badge-pill {
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  color: #0284c7;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 6px;
  font-family: monospace;
}
.meta-pills {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.80rem;
}

/* ── High-End Certificate Style Report Card Container ── */
.report-card-container {
  max-width: 860px;
  margin: 0 auto 30px;
  background: #ffffff;
  border-radius: 16px;
  border: 4px double #0092df;
  box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(0, 146, 223, 0.15);
  position: relative;
  overflow: hidden;
  box-sizing: border-box;
}

/* Dynamic Tier Sizing */
.report-card-container.tier-few { padding: 34px 44px; }
.report-card-container.tier-medium { padding: 26px 36px; }
.report-card-container.tier-compact { padding: 20px 28px; }
.report-card-container.tier-dense { padding: 16px 22px; }

/* Header Banner */
.report-header-banner {
  position: relative;
  z-index: 1;
  text-align: center;
  border-bottom: 2px solid #0092df;
  padding-bottom: 16px;
  margin-bottom: 20px;
}
.report-brand-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.report-school-title {
  margin: 0;
  font-weight: 900;
  color: #007cbd;
  letter-spacing: 0.5px;
  font-family: 'Outfit', sans-serif;
  line-height: 1.15;
  font-size: 26px;
}
.report-school-tagline {
  font-weight: 800;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-top: 2px;
  font-size: 12px;
}
.report-session-ribbon {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #0092df 0%, #0074b3 100%);
  color: #ffffff;
  border-radius: 30px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 1px;
  box-shadow: 0 3px 10px rgba(0, 146, 223, 0.25);
  padding: 5px 20px;
  font-size: 11px;
  margin-top: 10px;
}

/* Student & Exam Details Box */
.report-details-grid {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 14px 20px;
  margin-bottom: 20px;
  gap: 12px 24px;
  font-size: 12.5px;
}
.detail-item {
  display: flex;
  align-items: baseline;
  gap: 8px;
}
.detail-item strong {
  color: #475569;
  font-weight: 700;
  min-width: 105px;
  font-size: 12px;
}
.detail-item span {
  color: #0f172a;
}

/* Statement of Marks Table */
.report-table {
  width: 100%;
  border-collapse: collapse;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #cbd5e1;
  margin-bottom: 20px;
}
.report-table th, .report-table td {
  border: 1px solid #cbd5e1;
  padding: 10px 14px;
  font-size: 12.5px;
}
.report-table thead th {
  background: #0f172a;
  color: #ffffff;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  text-align: center;
  font-size: 11.5px;
}
.report-table thead th:first-child {
  text-align: left;
}
.report-table tbody tr:nth-child(even) {
  background: #fcfdfe;
}
.report-table tbody tr:hover {
  background: #f0f9ff;
}

/* Performance Summary KPI Row */
.report-summary-kpi-row {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}
.summary-kpi-card {
  border-radius: 10px;
  text-align: center;
  border: 1px solid transparent;
  padding: 12px 14px;
}
.summary-kpi-card.percentage {
  background: #f0fdf4;
  border-color: #bbf7d0;
}
.summary-kpi-card.grade {
  background: #eff6ff;
  border-color: #bfdbfe;
}
.summary-kpi-card.pass {
  background: #f0fdf4;
  border-color: #86efac;
}
.summary-kpi-card.fail {
  background: #fef2f2;
  border-color: #fca5a5;
}
.kpi-title {
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 4px;
  font-size: 10.5px;
}
.kpi-val {
  font-weight: 900;
  font-family: 'Outfit', sans-serif;
  line-height: 1.1;
  font-size: 22px;
}

/* Remarks Callout */
.report-remarks-card {
  position: relative;
  z-index: 1;
  color: #334155;
  background: #f8fafc;
  border-radius: 8px;
  border-left: 4px solid #0092df;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  margin-bottom: 26px;
  font-size: 12.5px;
}
.report-remarks-card i {
  color: #0092df;
  font-size: 1.1rem;
  margin-top: 2px;
}

/* Digital Signatures and Official Seals */
.report-signatures-row {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  border-top: 1px solid #cbd5e1;
  font-weight: 700;
  color: #475569;
  padding-top: 18px;
  font-size: 11.5px;
}
.sig-block {
  text-align: center;
  width: 150px;
}
.sig-line {
  border-top: 1px solid #94a3b8;
  padding-top: 4px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

/* Grade Pills */
.grade-pill-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-weight: 800;
  font-size: 11px;
}
.grade-pill-badge.pass {
  background: #e0f2fe;
  color: #0284c7;
}
.grade-pill-badge.fail {
  background: #fee2e2;
  color: #dc2626;
}

/* Multi-child Selector Pill Banner */
.child-selector-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  padding: 10px 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  margin-bottom: 20px;
}

/* Evaluation History Ledger Card */
.periods-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
  overflow: hidden;
  margin-bottom: 30px;
}
.periods-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  background: #ffffff;
}
.staff-id-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.staff-id-table thead th {
  background: #f8fafc;
  color: #475569;
  font-size: 0.70rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 11px 12px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.staff-id-table tbody td {
  padding: 11px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.82rem;
  color: #1e293b;
}
.staff-id-table tbody tr:hover {
  background-color: #f8fafc;
}
.sid-code-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  color: #0284c7;
  padding: 2px 7px;
  border-radius: 5px;
  font-family: monospace;
  font-size: 0.74rem;
  font-weight: 700;
  white-space: nowrap;
}
.sid-status-verified {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  font-size: 0.70rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 14px;
  white-space: nowrap;
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS FOR STUDENT MARKSHEET
   ══════════════════════════════════════════════════════════════════════ */

/* Tablet & Mobile Screens (<= 768px) */
@media (max-width: 768px) {
  /* Multi-Child Selector */
  .child-selector-banner {
    padding: 10px 14px !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 8px !important;
    margin-bottom: 16px !important;
  }
  .child-selector-banner > div {
    width: 100% !important;
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }
  .child-selector-banner .roaster-btn {
    flex: 1 1 auto !important;
    text-align: center !important;
    justify-content: center !important;
    padding: 6px 10px !important;
    font-size: 0.72rem !important;
  }

  /* Scholar Control Bar */
  .scholar-control-card {
    padding: 12px 14px !important;
    margin-bottom: 18px !important;
    border-radius: 12px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 12px !important;
  }

  .current-scholar-pill {
    width: 100% !important;
    justify-content: flex-start !important;
    gap: 10px !important;
  }

  .scholar-mini-avatar,
  .scholar-mini-init {
    width: 38px !important;
    height: 38px !important;
    font-size: 0.95rem !important;
    flex-shrink: 0 !important;
  }

  .current-scholar-meta h4 {
    font-size: 0.95rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }

  .current-scholar-meta .meta-pills {
    font-size: 0.72rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }

  .marksheet-top-actions {
    width: 100% !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 8px !important;
  }

  .marksheet-exam-form {
    width: 100% !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 6px !important;
  }

  .marksheet-exam-select {
    width: 100% !important;
    height: 38px !important;
    box-sizing: border-box !important;
    font-size: 0.82rem !important;
    border-radius: 8px !important;
  }

  .marksheet-action-buttons-wrap {
    width: 100% !important;
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 8px !important;
  }

  .marksheet-download-btn,
  .marksheet-print-btn {
    width: 100% !important;
    height: 38px !important;
    justify-content: center !important;
    font-size: 0.78rem !important;
    padding: 0 10px !important;
    box-sizing: border-box !important;
    border-radius: 8px !important;
    white-space: nowrap !important;
  }

  /* Report Card Canvas Viewport Scaling */
  .report-card-container {
    padding: 16px 14px !important;
    margin-bottom: 22px !important;
    border-radius: 12px !important;
    border-width: 1.5px !important;
    box-sizing: border-box !important;
  }

  .report-header-banner {
    padding-bottom: 12px !important;
    margin-bottom: 14px !important;
  }

  .report-brand-row {
    gap: 10px !important;
    align-items: center !important;
  }

  .school-logo-img {
    width: 44px !important;
    height: 44px !important;
  }

  .verification-qr-wrap img {
    width: 44px !important;
    height: 44px !important;
  }

  .report-school-title {
    font-size: 1.15rem !important;
    line-height: 1.2 !important;
  }

  .report-school-tagline {
    font-size: 0.68rem !important;
  }

  .report-school-subinfo {
    font-size: 0.64rem !important;
    line-height: 1.3 !important;
  }

  .report-session-ribbon {
    padding: 4px 14px !important;
    font-size: 0.68rem !important;
    margin-top: 8px !important;
    letter-spacing: 0.5px !important;
    white-space: normal !important;
    line-height: 1.3 !important;
    text-align: center !important;
  }

  .report-card-container .report-details-grid {
    padding: 10px 12px !important;
    margin-bottom: 12px !important;
    gap: 8px 14px !important;
    font-size: 0.76rem !important;
  }

  .detail-item strong {
    min-width: 85px !important;
    font-size: 0.74rem !important;
  }

  .detail-item span {
    font-size: 0.76rem !important;
  }

  /* Responsive Statement of Marks Table */
  .report-table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    width: 100% !important;
    margin: 10px 0 14px !important;
  }

  .report-card-container .report-table {
    min-width: 480px !important;
    margin: 0 !important;
  }

  .report-card-container .report-table th,
  .report-card-container .report-table td {
    padding: 7px 8px !important;
    font-size: 0.76rem !important;
  }

  .report-card-container .report-summary-kpi-row {
    gap: 8px !important;
    margin-bottom: 14px !important;
  }

  .report-card-container .summary-kpi-card {
    padding: 8px 6px !important;
    border-radius: 8px !important;
  }

  .kpi-title {
    font-size: 0.64rem !important;
  }

  .kpi-val {
    font-size: 1.15rem !important;
  }

  .report-card-container .report-remarks-card {
    padding: 10px 12px !important;
    font-size: 0.76rem !important;
    margin-bottom: 16px !important;
  }

  .report-card-container .report-signatures-row {
    padding-top: 14px !important;
    font-size: 0.70rem !important;
  }

  .sig-block {
    width: auto !important;
    flex: 1 !important;
    max-width: 120px !important;
  }

  .sig-block .sig-spacer {
    height: 20px !important;
  }

  .sig-block .sig-crest-circle {
    width: 38px !important;
    height: 38px !important;
    font-size: 7px !important;
  }

  .sig-line {
    font-size: 0.68rem !important;
    padding-top: 2px !important;
  }

  .sig-principal-name {
    font-size: 0.80rem !important;
    height: 20px !important;
  }

  /* Evaluation History Ledger Card */
  .periods-card {
    border-radius: 12px !important;
    margin-bottom: 20px !important;
  }

  .periods-card-header {
    padding: 12px 14px !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 10px !important;
  }

  .periods-card-header > span {
    align-self: flex-start !important;
  }

  .table-responsive {
    width: 100% !important;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    margin: 0 !important;
  }

  .staff-id-table {
    min-width: 620px !important;
  }

  .staff-id-table thead th {
    padding: 8px 8px !important;
    font-size: 0.68rem !important;
  }

  .staff-id-table tbody td {
    padding: 8px 8px !important;
    font-size: 0.76rem !important;
  }
}

/* Micro Devices (<= 576px) */
@media (max-width: 576px) {
  .report-card-container .report-details-grid {
    grid-template-columns: 1fr !important;
    gap: 6px !important;
  }

  .report-card-container .report-summary-kpi-row {
    grid-template-columns: 1fr !important;
    gap: 8px !important;
  }

  .marksheet-action-buttons-wrap {
    grid-template-columns: 1fr !important;
  }
}

/* Micro Devices (<= 360px) */
@media (max-width: 360px) {
  .report-brand-row {
    flex-direction: column !important;
    text-align: center !important;
  }

  .report-brand-row > div {
    width: 100% !important;
    text-align: center !important;
  }
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR STUDENT MARKSHEET
   ══════════════════════════════════════════════════════════════════════ */
[data-theme="dark"] .child-selector-banner {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .child-selector-banner > span {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .scholar-control-card {
  background: #1e293b !important;
  border-color: #334155 !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .current-scholar-meta h4 {
  color: #f8fafc !important;
}

[data-theme="dark"] .marksheet-exam-select {
  background: #0f172a !important;
  border-color: #334155 !important;
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

[data-theme="dark"] .periods-card-header h3 {
  color: #f8fafc !important;
}

[data-theme="dark"] .periods-card-header p {
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-card-header > span {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}

[data-theme="dark"] .periods-card-header > span strong {
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

[data-theme="dark"] .staff-id-table tr[style*="background: #f0f9ff"] td {
  background: rgba(2, 132, 199, 0.12) !important;
}

[data-theme="dark"] .staff-id-table tbody td strong[style*="color: #0f172a"] {
  color: #f8fafc !important;
}

[data-theme="dark"] .staff-id-table tbody td span[style*="color: #475569"] {
  color: #94a3b8 !important;
}

[data-theme="dark"] .roaster-btn.secondary {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .roaster-btn.secondary:hover {
  background: #1e293b !important;
  border-color: #475569 !important;
  color: #ffffff !important;
}
</style>

<!-- ══════════════════════════════════════════════════════
     MULTI-CHILD SELECTOR (FOR PARENTS)
     ══════════════════════════════════════════════════════ -->
<?php if ($current_user['role'] === 'parent' && count($parent_children) > 1): ?>
  <div class="child-selector-banner no-print">
    <span style="font-size: 0.80rem; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 6px;">
      <i class="fa-solid fa-children" style="color: #0284c7;"></i> Select Student:
    </span>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <?php foreach ($parent_children as $pc): ?>
        <a href="<?php echo PARENT_URL; ?>/marksheet?student_id=<?php echo $pc['id']; ?>" 
           class="roaster-btn <?php echo $pc['id'] == $selected_student_id ? 'primary' : 'secondary'; ?>" 
           style="font-size: 0.76rem; padding: 5px 12px; border-radius: 6px; text-decoration: none;">
          <?php echo htmlspecialchars($pc['student_name']); ?> (<?php echo htmlspecialchars($pc['standard']); ?>)
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>



<!-- ══════════════════════════════════════════════════════
     TOP SCHOLAR CONTROL & EXAM SELECTION BAR (Exact match to admin/marksheet.php)
     ══════════════════════════════════════════════════════ -->
<div class="scholar-control-card no-print">
  
  <!-- Current Scholar Info Pill -->
  <div class="current-scholar-pill">
    <?php if ($has_student_photo): ?>
      <img src="<?php echo htmlspecialchars($student_photo_url); ?>" alt="" class="scholar-mini-avatar">
    <?php else: ?>
      <div class="scholar-mini-init" style="background: <?php echo $avatar_bg; ?>;">
        <?php echo $first_letter; ?>
      </div>
    <?php endif; ?>

    <div class="current-scholar-meta">
      <h4>
        <span><?php echo htmlspecialchars($student_profile['student_name'] ?? 'Select Scholar'); ?></span>
        <?php if ($student_profile): ?>
          <span class="roll-badge-pill">
            <?php echo htmlspecialchars($student_profile['roll_no']); ?>
          </span>
        <?php endif; ?>
      </h4>
      <div class="meta-pills">
        <span style="color: #64748b;">
          <?php echo htmlspecialchars($student_profile['standard'] ?? 'Standard'); ?> &bull; Section <?php echo htmlspecialchars($student_profile['section'] ?? 'A'); ?>
        </span>
        <span style="color: #cbd5e1;">&bull;</span>
        <a href="#examHistoryTable" style="color: var(--hrms-primary); text-decoration: none; font-weight: 700;">
          <i class="fa-solid fa-list-check"></i> Evaluation History
        </a>
      </div>
    </div>
  </div>

  <!-- Right: Exam Selector & Action Buttons -->
  <div class="marksheet-top-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    
    <?php if (!empty($student_marksheets)): ?>
      <form method="GET" action="<?php echo PARENT_URL; ?>/marksheet" class="marksheet-exam-form" style="display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;">
        <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
        <label style="font-size: 13px; font-weight: 700; color: #475569; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; flex-shrink: 0;">
          <i class="fa-solid fa-book-bookmark" style="color: #0092df;"></i> <span>Exam:</span>
        </label>
        <select name="marksheet_id" onchange="this.form.submit()" class="hrms-input marksheet-exam-select" style="padding: 7px 12px; border-radius: 8px; font-weight: 700; font-size: 0.82rem; border: 1.5px solid #cbd5e1; background: #f8fafc;">
          <?php foreach ($student_marksheets as $m_item): ?>
            <option value="<?php echo $m_item['id']; ?>" <?php echo ($active_marksheet && $m_item['id'] == $active_marksheet['id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($m_item['exam_name']); ?> (<?php echo $m_item['percentage']; ?>% • <?php echo $m_item['overall_grade']; ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>

    <?php if ($active_marksheet): ?>
      <div class="marksheet-action-buttons-wrap" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <!-- Download PNG -->
        <button type="button" onclick="downloadMarksheetPNG();" class="roaster-btn secondary marksheet-download-btn" style="gap: 6px;" title="Download Marksheet as High-Resolution PNG">
          <i class="fa-solid fa-download"></i>
          <span>Download (PNG)</span>
        </button>

        <!-- Print Report Card -->
        <button type="button" onclick="window.print();" class="roaster-add-btn marksheet-print-btn" style="padding: 7px 16px; font-size: 0.82rem;" title="Print official A4 Statement of Marks">
          <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-print"></i></div>
          <span>Print Report Card</span>
        </button>
      </div>
    <?php endif; ?>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     OFFICIAL PRINTABLE REPORT CARD (High-End Certificate Style like admin/marksheet.php)
     ══════════════════════════════════════════════════════ -->
<?php if ($active_marksheet && $student_profile): ?>
  <?php 
    $subjects = json_decode($active_marksheet['subjects_json'], true) ?: [];
    $is_overall_pass = ($active_marksheet['result_status'] === 'PASS');
    $sub_count = count($subjects);
    if ($sub_count <= 4) {
        $tier_class = 'tier-few';
    } elseif ($sub_count <= 7) {
        $tier_class = 'tier-medium';
    } elseif ($sub_count <= 10) {
        $tier_class = 'tier-compact';
    } else {
        $tier_class = 'tier-dense';
    }
  ?>
  <div id="report-card-view" class="marksheet-print-canvas report-card-container <?php echo $tier_class; ?>" data-sub-count="<?php echo $sub_count; ?>">
    
    <!-- Watermark Logo -->
    <div style="position: absolute; top: 52%; left: 50%; transform: translate(-50%, -50%); opacity: 0.04; pointer-events: none; z-index: 0;">
      <img src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="" style="width: 360px; height: 360px;">
    </div>

    <!-- Official Header Section -->
    <div class="report-header-banner">
      <div class="report-brand-row">
        
        <!-- Left: Academy Crest -->
        <div style="width: 80px; text-align: left;">
          <img class="school-logo-img" src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="KSV Logo" style="width: 64px; height: 64px; border-radius: 50%; border: 2px solid #0092df; padding: 2px; background: #ffffff;">
        </div>

        <!-- Center: Institutional Identity -->
        <div style="flex: 1; text-align: center;">
          <h1 class="report-school-title">
            <?php echo htmlspecialchars($school_settings['school_name']); ?>
          </h1>
          <div class="report-school-tagline">
            <?php echo htmlspecialchars($school_settings['tagline']); ?>
          </div>
          <div class="report-school-subinfo" style="font-size: 11px; color: #64748b; margin-top: 4px;">
            <?php echo htmlspecialchars($school_settings['address']); ?> &bull; Helpline: <?php echo htmlspecialchars($school_settings['phone']); ?>
          </div>
        </div>

        <!-- Right: Verification QR Code -->
        <div class="verification-qr-wrap" style="width: 80px; text-align: right;">
          <?php if (!empty($verification_qr_url)): ?>
            <div style="display: inline-block; padding: 4px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.05);" title="Biometric QR Verification Pass">
              <img src="<?php echo $verification_qr_url; ?>" alt="Verification QR" style="width: 58px; height: 58px; display: block;">
              <span class="qr-verify-badge" style="font-size: 7.5px; font-weight: 800; color: #0284c7; text-transform: uppercase; display: block; text-align: center; margin-top: 2px;">VERIFIED</span>
            </div>
          <?php endif; ?>
        </div>

      </div>
      
      <!-- Academic Session Ribbon -->
      <div class="report-session-ribbon">
        <i class="fa-solid fa-award"></i>
        <span>STATEMENT OF ACADEMIC MARKS &bull; SESSION <?php echo htmlspecialchars($active_session['session_name']); ?></span>
      </div>
    </div>

    <!-- Student & Exam Details Box -->
    <div class="report-details-grid">
      <div class="detail-item">
        <strong>Scholar Name:</strong>
        <span style="font-size: 14.5px; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <?php echo htmlspecialchars($student_profile['student_name']); ?>
        </span>
      </div>
      <div class="detail-item">
        <strong>Class &amp; Section:</strong>
        <span style="font-weight: 800; color: #007cbd;">
          <?php echo htmlspecialchars($student_profile['standard']); ?> &bull; Section <?php echo htmlspecialchars($student_profile['section'] ?? 'A'); ?>
        </span>
      </div>

      <div class="detail-item">
        <strong>Roll Number:</strong>
        <span style="font-family: monospace; font-weight: 800; font-size: 13.5px; color: #0284c7; background: #e0f2fe; padding: 1px 8px; border-radius: 4px;">
          <?php echo htmlspecialchars($student_profile['roll_no']); ?>
        </span>
      </div>
      <div class="detail-item">
        <strong>Father's Name:</strong>
        <span style="font-weight: 700;">
          <?php echo htmlspecialchars($student_profile['parent_name'] ?? '—'); ?>
        </span>
      </div>

      <div class="detail-item">
        <strong>Admission No:</strong>
        <span style="font-family: monospace; color: #475569; font-weight: 600;">
          <?php echo htmlspecialchars($student_profile['admission_no'] ?? ('ADM-' . $student_profile['id'])); ?>
        </span>
      </div>
      <div class="detail-item">
        <strong>Examination:</strong>
        <span style="color: #0f172a; font-weight: 800;">
          <?php echo htmlspecialchars($active_marksheet['exam_name']); ?>
          <span style="font-weight: 600; color: #64748b; font-size: 11.5px;">(<?php echo date('M Y', strtotime($active_marksheet['exam_date'])); ?>)</span>
        </span>
      </div>
    </div>

    <!-- Statement of Marks Table -->
    <div class="report-table-responsive" style="position: relative; z-index: 1;">
      <table class="report-table">
        <thead>
          <tr>
            <th style="width: 6%; text-align: center;">#</th>
            <th style="text-align: left; width: 44%;">SUBJECT TITLE</th>
            <th style="width: 14%;">MAX MARKS</th>
            <th style="width: 14%;">PASS MARKS</th>
            <th style="width: 14%;">OBTAINED</th>
            <th style="width: 14%;">GRADE</th>
          </tr>
        </thead>
        <tbody>
          <?php $idx_sub = 1; ?>
          <?php foreach ($subjects as $sub): ?>
            <tr>
              <td style="text-align: center; color: #94a3b8; font-weight: 700;"><?php echo $idx_sub++; ?></td>
              <td style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($sub['name']); ?></td>
              <td style="text-align: center; color: #64748b; font-weight: 600;"><?php echo $sub['max']; ?></td>
              <td style="text-align: center; color: #64748b; font-weight: 600;"><?php echo $sub['pass']; ?></td>
              <td style="text-align: center; font-weight: 900; font-size: 14px; font-family: 'Outfit', sans-serif; color: <?php echo $sub['obtained'] >= $sub['pass'] ? '#059669' : '#dc2626'; ?>;">
                <?php echo $sub['obtained']; ?>
              </td>
              <td style="text-align: center; font-weight: 800; color: #0284c7;">
                <span class="grade-pill-badge <?php echo $sub['obtained'] >= $sub['pass'] ? 'pass' : 'fail'; ?>">
                  <?php echo $sub['grade']; ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          <!-- Grand Total Row -->
          <tr style="background: #f8fafc; font-weight: 900; font-size: 14px; border-top: 2px solid #0092df;">
            <td colspan="2" style="text-align: left; font-weight: 900; color: #0f172a; letter-spacing: 0.04em;">
              GRAND TOTAL MARKS
            </td>
            <td style="text-align: center; color: #334155;"><?php echo $active_marksheet['total_max']; ?></td>
            <td style="text-align: center; color: #94a3b8;">—</td>
            <td style="text-align: center; color: #007cbd; font-size: 16px; font-family: 'Outfit', sans-serif;"><?php echo $active_marksheet['total_obtained']; ?></td>
            <td style="text-align: center; color: #007cbd;"><?php echo $active_marksheet['overall_grade']; ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Performance Summary KPI Row -->
    <div class="report-summary-kpi-row">
      <div class="summary-kpi-card percentage">
        <div class="kpi-title" style="color: #15803d;">OVERALL PERCENTAGE</div>
        <div class="kpi-val" style="color: #166534;"><?php echo $active_marksheet['percentage']; ?>%</div>
      </div>
      <div class="summary-kpi-card grade">
        <div class="kpi-title" style="color: #1d4ed8;">ACADEMIC GRADE</div>
        <div class="kpi-val" style="color: #1e40af;"><?php echo $active_marksheet['overall_grade']; ?></div>
      </div>
      <div class="summary-kpi-card <?php echo $is_overall_pass ? 'pass' : 'fail'; ?>">
        <div class="kpi-title" style="color: <?php echo $is_overall_pass ? '#15803d' : '#991b1b'; ?>;">EXAMINATION RESULT</div>
        <div class="kpi-val" style="color: <?php echo $is_overall_pass ? '#166534' : '#b91c1c'; ?>; display: flex; align-items: center; justify-content: center; gap: 6px;">
          <i class="fa-solid <?php echo $is_overall_pass ? 'fa-circle-check' : 'fa-circle-xmark'; ?>" style="font-size: 1.1rem;"></i>
          <span><?php echo $active_marksheet['result_status']; ?></span>
        </div>
      </div>
    </div>

    <!-- Remarks Callout -->
    <div class="report-remarks-card">
      <i class="fa-solid fa-quote-left"></i>
      <div>
        <strong>Faculty Evaluation &amp; Discipline Remarks:</strong>
        <div style="margin-top: 3px; font-style: italic; color: #475569;">
          "<?php echo htmlspecialchars(!empty(trim($active_marksheet['remarks'] ?? '')) ? $active_marksheet['remarks'] : 'Consistent academic effort, good participation and disciplined conduct throughout the examination period.'); ?>"
        </div>
      </div>
    </div>

    <!-- Signatures and Official Seals -->
    <div class="report-signatures-row">
      <div class="sig-block">
        <div class="sig-spacer" style="height: 38px;"></div>
        <div class="sig-line">Class Teacher</div>
      </div>
      
      <div class="sig-block">
        <div class="sig-crest-circle" style="width: 54px; height: 54px; border: 2px dashed #0092df; border-radius: 50%; margin: 0 auto 6px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 8px; color: #0092df; font-weight: 900; line-height: 1.2;">
          <span>KSV</span>
          <span style="font-size: 6.5px; color: #64748b;">OFFICIAL</span>
        </div>
        <div class="sig-crest-lbl" style="font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase;">Institutional Crest</div>
      </div>

      <div class="sig-block">
        <div class="sig-principal-name" style="font-family: 'Outfit', cursive; font-size: 16px; color: #007cbd; height: 28px; font-weight: 800;">Salam Khan</div>
        <div class="sig-line">Principal Signature</div>
      </div>
    </div>

  </div>
<?php else: ?>
  <div class="hrms-card no-print" style="text-align: center; padding: 50px 20px; margin-bottom: 28px; border-radius: 14px; border: 1.5px dashed #cbd5e1;">
    <div style="width: 58px; height: 58px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; font-size: 1.6rem;">
      <i class="fa-solid fa-file-circle-question"></i>
    </div>
    <h3 style="margin: 0 0 6px; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
      No Marksheet Record Generated Yet
    </h3>
    <p style="margin: 0; color: #64748b; font-size: 0.9rem; max-width: 500px; margin-left: auto; margin-right: auto;">
      Scholar <strong><?php echo htmlspecialchars($student_profile['student_name'] ?? 'Selected Scholar'); ?></strong> does not have an evaluation for Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.
    </p>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     EXAMINATION HISTORY REGISTER TABLE CARD
     ══════════════════════════════════════════════════════ -->
<?php if (!empty($student_marksheets)): ?>
<div class="periods-card no-print" id="examHistoryTable">
  <div class="periods-card-header">
    <div style="display: flex; align-items: center; gap: 10px;">
      <div style="width: 32px; height: 32px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
        <i class="fa-solid fa-clock-rotate-left"></i>
      </div>
      <div>
        <h3 style="font-size: 0.96rem; font-weight: 700; color: #0f172a; margin: 0; font-family: 'Outfit', sans-serif;">
          Academic Evaluation History
        </h3>
        <p style="font-size: 0.74rem; color: #64748b; margin: 2px 0 0;">
          All term assessments, unit tests &amp; examination report cards for <?php echo htmlspecialchars($student_profile['student_name']); ?>.
        </p>
      </div>
    </div>

    <span style="font-size: 0.76rem; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 6px;">
      Total Evaluated: <strong style="color: #0f172a;"><?php echo count($student_marksheets); ?> Exams</strong>
    </span>
  </div>

  <div class="table-responsive" style="overflow-x: auto;">
    <table class="staff-id-table">
      <thead>
        <tr>
          <th style="width: 44px; text-align: center;">#</th>
          <th>Examination Title</th>
          <th>Exam Date</th>
          <th style="text-align: center;">Total Marks</th>
          <th style="text-align: center;">Percentage</th>
          <th style="text-align: center;">Grade</th>
          <th style="text-align: center;">Result</th>
          <th style="text-align: right; width: 140px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($student_marksheets as $idx => $ms_row): ?>
          <tr style="<?php echo ($active_marksheet && $ms_row['id'] == $active_marksheet['id']) ? 'background: #f0f9ff;' : ''; ?>">
            <td style="text-align: center; color: #64748b; font-weight: 600; font-size: 0.76rem;">
              <?php echo sprintf('%02d', $idx + 1); ?>
            </td>
            <td>
              <strong style="color: #0f172a; font-size: 0.84rem;">
                <?php echo htmlspecialchars($ms_row['exam_name']); ?>
              </strong>
              <?php if ($active_marksheet && $ms_row['id'] == $active_marksheet['id']): ?>
                <span style="font-size: 0.65rem; background: #0284c7; color: #fff; padding: 1px 6px; border-radius: 4px; margin-left: 6px; font-weight: 700;">ACTIVE</span>
              <?php endif; ?>
            </td>
            <td>
              <span style="color: #475569; font-size: 0.80rem; display: flex; align-items: center; gap: 5px;">
                <i class="fa-regular fa-calendar" style="color: #64748b; font-size: 0.70rem;"></i>
                <?php echo format_date($ms_row['exam_date'], 'd M Y'); ?>
              </span>
            </td>
            <td style="text-align: center; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
              <?php echo $ms_row['total_obtained']; ?> / <?php echo $ms_row['total_max']; ?>
            </td>
            <td style="text-align: center; font-weight: 800; color: #0284c7; font-family: 'Outfit', sans-serif;">
              <?php echo $ms_row['percentage']; ?>%
            </td>
            <td style="text-align: center;">
              <span class="grade-pill-badge <?php echo $ms_row['result_status'] === 'PASS' ? 'pass' : 'fail'; ?>">
                <?php echo $ms_row['overall_grade']; ?>
              </span>
            </td>
            <td style="text-align: center;">
              <span class="sid-status-verified" style="<?php echo $ms_row['result_status'] !== 'PASS' ? 'background: #fef2f2; color: #991b1b; border-color: #fca5a5;' : ''; ?>">
                <i class="fa-solid <?php echo $ms_row['result_status'] === 'PASS' ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
                <?php echo $ms_row['result_status']; ?>
              </span>
            </td>
            <td style="text-align: right;">
              <a href="<?php echo PARENT_URL; ?>/marksheet?student_id=<?php echo $selected_student_id; ?>&marksheet_id=<?php echo $ms_row['id']; ?>" 
                 class="roaster-btn <?php echo ($active_marksheet && $ms_row['id'] == $active_marksheet['id']) ? 'primary' : 'secondary'; ?>" 
                 style="padding: 3px 9px; font-size: 0.74rem; text-decoration: none; border-radius: 6px; gap: 4px; display: inline-flex; align-items: center;">
                <i class="fa-solid fa-eye"></i> View Report
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     HIGH-RES PNG DOWNLOAD LOGIC (html2canvas)
     ══════════════════════════════════════════════════════ -->
<script>
function downloadMarksheetPNG() {
  const card = document.getElementById('report-card-view');
  if (!card) return;

  const studentName = <?php echo json_encode($student_profile['student_name'] ?? 'Scholar'); ?>;
  const examName = <?php echo json_encode($active_marksheet['exam_name'] ?? 'Marksheet'); ?>;
  const filename = `Marksheet_${studentName.replace(/\s+/g, '_')}_${examName.replace(/\s+/g, '_')}.png`;

  html2canvas(card, {
    scale: 2.5,
    useCORS: true,
    backgroundColor: '#ffffff',
    logging: false
  }).then(canvas => {
    const link = document.createElement('a');
    link.download = filename;
    link.href = canvas.toDataURL('image/png');
    link.click();
  }).catch(err => {
    console.error('Download error:', err);
    alert('Unable to generate high-resolution image. Please use Print instead.');
  });
}
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
