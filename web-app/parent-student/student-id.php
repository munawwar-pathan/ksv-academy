<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
if (in_array($current_user['role'], ['student', 'parent'])) {
    require_menu_permission('student-id');
}
$page_title = 'Student ID Card | KSV Academy HRMS';
$page_heading = 'Student Identity Card & Biometric QR Pass';
$page_subheading = 'Official Academic Identification Badge & Biometric QR Pass';
$active_menu = 'student-id';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// ── Resolve Active Student Record ──
$student = null;
$selected_id = intval($_GET['id'] ?? 0);

// Check for Impersonation Session
if (!empty($_SESSION['impersonated_student'])) {
    $s_id = intval($_SESSION['impersonated_student']['id']);
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$s_id]);
    $student = $stmt->fetch();
    if ($student) $selected_id = $student['id'];
}

// 1. Direct query parameter override (?id=X)
if ($selected_id > 0 && !$student) {
    if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$selected_id]);
        $student = $stmt->fetch();
        if ($student) $_SESSION['active_student_id'] = $student['id'];
    } elseif ($current_user['role'] === 'parent') {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND parent_user_id = ? LIMIT 1");
        $stmt->execute([$selected_id, $current_user['id']]);
        $student = $stmt->fetch();
        if ($student) $_SESSION['active_student_id'] = $student['id'];
    }
}

// 2. Role Specific Resolution
$parent_children = [];
if ($current_user['role'] === 'student') {
    // A student always views their own ID card
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
        $selected_id = $student['id'];
    }
} elseif ($current_user['role'] === 'parent') {
    // Fetch all children linked to this parent
    $c_stmt = $db->prepare("SELECT id, student_name, roll_no, standard, photo FROM students WHERE parent_user_id = ? ORDER BY id ASC");
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
    if ($student) $selected_id = $student['id'];
} else {
    // Super admin / Staff selector list
    $all_students_stmt = $db->prepare("SELECT id, student_name, roll_no, standard FROM students WHERE session_id = ? ORDER BY standard ASC, student_name ASC");
    $all_students_stmt->execute([$session_id]);
    $students_list = $all_students_stmt->fetchAll();

    if ($selected_id === 0 && !empty($students_list)) {
        $selected_id = $students_list[0]['id'];
    }
    if ($selected_id > 0 && !$student) {
        $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$selected_id]);
        $student = $stmt->fetch();
    }
}

// Fallback if no student resolved
if (!$student) {
    $student = $db->query("SELECT * FROM students ORDER BY id ASC LIMIT 1")->fetch();
    if ($student) {
        $_SESSION['active_student_id'] = $student['id'];
        $selected_id = $student['id'];
    }
}

// Check student photo
$has_student_photo = false;
$student_photo_url = '';
if ($student && !empty($student['photo']) && $student['photo'] !== '/assets/imgs/ksv-logo.jpg') {
    $photo_file = __DIR__ . '/../..' . $student['photo'];
    if (file_exists($photo_file)) {
        $has_student_photo = true;
        $student_photo_url = $student['photo'];
    }
}

// Generate Biometric QR Code
$qr_data = $student ? ($student['qr_code'] ?: 'STU-' . $student['id'] . '-' . $student['roll_no']) : 'KSV-STUDENT';
$qr_image_url = get_qr_code_url($qr_data, '220x220');

// Avatar gradient fallback
$first_letter = $student ? strtoupper(substr($student['student_name'], 0, 1)) : 'S';
$grad_palette = [
    'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)',
    'linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)',
    'linear-gradient(135deg, #059669 0%, #047857 100%)',
    'linear-gradient(135deg, #d97706 0%, #b45309 100%)',
    'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)',
];
$avatar_bg = $grad_palette[ord($first_letter) % count($grad_palette)];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<!-- html2canvas for High-Resolution Card Export -->
<script src="<?php echo HRMS_URL; ?>/assets/js/html2canvas.min.js"></script>

<style>
/* ── Premium PVC Student ID Card Styles (Identical CR80 Standard like staff-id.php) ── */
.id-card-stage {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  gap: 40px;
  flex-wrap: wrap;
  padding: 10px 0 30px;
}

.id-badge-holder {
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Simulated Lanyard Clip at top */
.lanyard-clip {
  width: 48px;
  height: 24px;
  background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%);
  border-radius: 6px 6px 0 0;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
  position: relative;
  z-index: 5;
  margin-bottom: -10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.lanyard-clip::after {
  content: '';
  width: 20px;
  height: 6px;
  background: #334155;
  border-radius: 3px;
}

/* Card Physical Dimension (Standard CR80 Ratio 85.6mm x 54mm) */
.pvc-card {
  width: 326px;
  min-height: 512px;
  background: #ffffff;
  border-radius: 18px;
  box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.2), 0 0 0 1px rgba(15, 23, 42, 0.08);
  overflow: hidden;
  position: relative;
  display: flex;
  flex-direction: column;
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
  user-select: none;
}
.pvc-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 28px 55px -12px rgba(0, 146, 223, 0.25), 0 0 0 1px rgba(0, 146, 223, 0.2);
}

/* Top Lanyard Punch Slot */
.card-lanyard-slot {
  width: 44px;
  height: 10px;
  background: #f1f5f9;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  margin: 10px auto 0;
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.12);
  position: relative;
  z-index: 4;
}

/* Card Front Header Banner */
.card-front-header {
  background: linear-gradient(135deg, #0f172a 0%, #0092df 65%, #0284c7 100%);
  color: #ffffff;
  padding: 10px 16px 38px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.card-front-header::before {
  content: '';
  position: absolute;
  top: -40px;
  right: -40px;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  pointer-events: none;
}
.card-front-header::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  right: 0;
  height: 24px;
  background: #ffffff;
  clip-path: ellipse(56% 100% at 50% 100%);
}

.cf-logo-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.cf-logo-wrap {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: #ffffff;
  padding: 2.5px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
}
.cf-logo-wrap img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 7px;
}
.cf-brand-name {
  font-family: 'Outfit', sans-serif;
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 0.02em;
  color: #ffffff;
  line-height: 1.15;
}
.cf-sub-tag {
  font-size: 8.5px;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: rgba(255, 255, 255, 0.85);
  font-weight: 700;
  margin-top: 2px;
}

.cf-session-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(15, 23, 42, 0.35);
  backdrop-filter: blur(4px);
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 8.5px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-top: 6px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Photo and Smart Chip Row */
.cf-photo-container {
  position: relative;
  margin-top: -38px;
  z-index: 5;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.cf-smart-chip {
  position: absolute;
  top: 14px;
  left: 20px;
  width: 32px;
  height: 24px;
  background: linear-gradient(135deg, #fcd34d 0%, #f59e0b 50%, #d97706 100%);
  border-radius: 5px;
  border: 1px solid #b45309;
  box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.6), 0 2px 4px rgba(0,0,0,0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: #78350f;
}

.cf-photo-frame {
  width: 98px;
  height: 98px;
  border-radius: 50%;
  border: 4px solid #ffffff;
  box-shadow: 0 6px 16px rgba(0, 146, 223, 0.28);
  background: #f8fafc;
  overflow: hidden;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cf-photo-frame img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.cf-photo-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 38px;
  font-weight: 900;
  font-family: 'Outfit', sans-serif;
}

/* Card Front Body Details */
.card-front-body {
  padding: 8px 20px 14px;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.cf-student-name {
  margin: 6px 0 2px;
  font-size: 17px;
  font-weight: 900;
  color: #0f172a;
  font-family: 'Outfit', sans-serif;
  letter-spacing: -0.01em;
  text-align: center;
}
.cf-standard-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #e0f2fe;
  color: #0284c7;
  padding: 3px 12px;
  border-radius: 12px;
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: 0.02em;
  margin-bottom: 12px;
  border: 1px solid #bae6fd;
}

/* Key Value Table */
.cf-info-list {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  background: #f8fafc;
  margin-bottom: 10px;
}
.cf-info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 5px 10px;
  font-size: 11px;
  border-bottom: 1px solid #eef2f6;
}
.cf-info-row:last-child {
  border-bottom: none;
}
.cf-label {
  color: #64748b;
  font-weight: 700;
  font-size: 9.5px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.cf-val {
  color: #0f172a;
  font-weight: 700;
  text-align: right;
}
.cf-val.mono {
  font-family: monospace;
  font-size: 11.5px;
  color: #0284c7;
  font-weight: 800;
}
.cf-blood-badge {
  background: #fee2e2;
  color: #dc2626;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 800;
}

/* Card Front Sign & Validity Footer */
.cf-footer {
  width: 100%;
  margin-top: auto;
  padding-top: 8px;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  border-top: 1.5px dashed #e2e8f0;
}
.cf-valid-box {
  text-align: left;
}
.cf-valid-lbl {
  font-size: 8px;
  color: #94a3b8;
  font-weight: 800;
  text-transform: uppercase;
}
.cf-valid-val {
  font-size: 10px;
  font-weight: 800;
  color: #334155;
  font-family: monospace;
}
.cf-sign-box {
  text-align: right;
}
.cf-sign-img {
  font-family: 'Outfit', cursive;
  font-size: 13px;
  font-weight: 800;
  color: #0284c7;
  letter-spacing: 0.5px;
}
.cf-sign-lbl {
  font-size: 8px;
  color: #94a3b8;
  font-weight: 800;
  text-transform: uppercase;
  border-top: 1px solid #cbd5e1;
  padding-top: 2px;
}

/* ── Card Back Styles ── */
.card-back-header {
  background: #0f172a;
  color: #ffffff;
  padding: 12px 14px 10px;
  text-align: center;
  position: relative;
}
.cb-title {
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #38bdf8;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.card-back-body {
  padding: 14px 18px 12px;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

/* QR Code Container */
.cb-qr-frame {
  padding: 8px;
  background: #ffffff;
  border: 2px solid #0092df;
  border-radius: 12px;
  box-shadow: 0 4px 14px rgba(0, 146, 223, 0.2);
  margin-bottom: 8px;
}
.cb-qr-frame img {
  width: 120px;
  height: 120px;
  display: block;
}

.cb-qr-code-text {
  font-family: monospace;
  font-size: 11.5px;
  font-weight: 800;
  color: #0284c7;
  letter-spacing: 0.05em;
  margin-bottom: 4px;
}
.cb-qr-hint {
  font-size: 9px;
  color: #64748b;
  margin-bottom: 12px;
}

/* Instructions Box */
.cb-instructions {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 9px 12px;
  font-size: 9px;
  color: #475569;
  text-align: left;
  line-height: 1.4;
  margin-bottom: 12px;
  width: 100%;
}
.cb-instructions strong {
  display: block;
  color: #0f172a;
  margin-bottom: 4px;
  font-size: 9.5px;
}
.cb-instructions ol {
  margin: 0;
  padding-left: 14px;
}
.cb-instructions li {
  margin-bottom: 2px;
}

/* Institutional Footer */
.cb-footer {
  width: 100%;
  margin-top: auto;
  border-top: 1.5px dashed #e2e8f0;
  padding-top: 8px;
  font-size: 8px;
  color: #64748b;
  line-height: 1.35;
}
.cb-footer strong {
  color: #0f172a;
  font-size: 10px;
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

/* ── Print Setup for CR80 Standard ID Card ── */
@media print {
  body * {
    visibility: hidden;
  }
  .id-card-printable-area, .id-card-printable-area * {
    visibility: visible;
  }
  .id-card-printable-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    display: flex !important;
    justify-content: center;
    gap: 30px;
    padding: 20px;
    background: #ffffff !important;
  }
  .lanyard-clip, .card-label-badge {
    display: none !important;
  }
  .pvc-card {
    box-shadow: none !important;
    border: 1px solid #94a3b8 !important;
    break-inside: avoid;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .no-print {
    display: none !important;
  }
}

/* ══════════════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE VIEW ENHANCEMENTS FOR STUDENT ID
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

  /* Top Toolbar */
  .roaster-toolbar-card {
    padding: 12px 14px !important;
    margin-bottom: 16px !important;
    border-radius: 12px !important;
  }
  .roaster-toolbar-inner {
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 12px !important;
  }
  .student-id-toolbar-left {
    width: 100% !important;
  }
  .student-id-select-form {
    width: 100% !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 6px !important;
  }
  .student-id-select-dropdown {
    width: 100% !important;
    min-width: 0 !important;
  }
  .student-id-faculty-title {
    font-size: 0.98rem !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
  }
  .student-id-toolbar-actions {
    width: 100% !important;
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 8px !important;
  }
  .student-id-toolbar-actions .roaster-btn {
    width: 100% !important;
    height: 38px !important;
    justify-content: center !important;
    font-size: 0.78rem !important;
    padding: 0 8px !important;
    box-sizing: border-box !important;
    border-radius: 8px !important;
    white-space: nowrap !important;
  }
  .student-id-btn-print {
    grid-column: span 2 !important;
    width: 100% !important;
    height: 38px !important;
    justify-content: center !important;
    font-size: 0.80rem !important;
    border-radius: 8px !important;
    box-sizing: border-box !important;
  }

  /* ID Card Stage */
  .id-card-stage {
    gap: 24px !important;
    padding: 6px 0 20px !important;
  }
  .id-badge-holder {
    width: 100% !important;
    max-width: min(326px, 100%) !important;
  }
  .pvc-card {
    width: min(326px, 100%) !important;
    max-width: 100% !important;
    border-radius: 16px !important;
  }
}

/* Micro Devices (<= 360px) */
@media (max-width: 360px) {
  .student-id-toolbar-actions {
    grid-template-columns: 1fr !important;
  }
  .student-id-btn-print {
    grid-column: span 1 !important;
  }
  .pvc-card {
    border-radius: 14px !important;
  }
  .card-front-header {
    padding: 8px 12px 34px !important;
  }
  .card-front-body {
    padding: 6px 14px 12px !important;
  }
  .cf-student-name {
    font-size: 15px !important;
  }
  .cf-photo-frame {
    width: 86px !important;
    height: 86px !important;
  }
  .cf-smart-chip {
    width: 28px !important;
    height: 20px !important;
    font-size: 11px !important;
    left: 14px !important;
  }
  .cf-info-row {
    padding: 4px 8px !important;
    font-size: 10px !important;
  }
  .cb-qr-frame img {
    width: 105px !important;
    height: 105px !important;
  }
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR STUDENT ID CARD
   ══════════════════════════════════════════════════════════════════════ */
[data-theme="dark"] .child-selector-banner {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .child-selector-banner > span {
  color: #cbd5e1 !important;
}

[data-theme="dark"] .roaster-toolbar-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}

[data-theme="dark"] .student-id-faculty-title {
  color: #f8fafc !important;
}

[data-theme="dark"] .student-id-faculty-title span[style*="background: #f8fafc"] {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}

[data-theme="dark"] .student-id-faculty-title span[style*="background: #f0f9ff"] {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.35) !important;
  color: #38bdf8 !important;
}

[data-theme="dark"] .student-id-select-dropdown {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}

[data-theme="dark"] .card-label-badge {
  background: #1e293b !important;
  color: #94a3b8 !important;
  border: 1px solid #334155 !important;
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
        <a href="<?php echo PARENT_URL; ?>/student-id?id=<?php echo $pc['id']; ?>" 
           class="roaster-btn <?php echo $pc['id'] == $selected_id ? 'primary' : 'secondary'; ?>" 
           style="font-size: 0.76rem; padding: 5px 12px; border-radius: 6px; text-decoration: none;">
          <?php echo htmlspecialchars($pc['student_name']); ?> (<?php echo htmlspecialchars($pc['standard']); ?>)
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     STUDENT ID CARD TOP TOOLBAR (Exact replica of staff-id.php)
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-bottom: 22px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Selected Student Label or Switcher -->
    <div class="student-id-toolbar-left" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      <?php if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff'): ?>
        <form method="GET" action="<?php echo PARENT_URL; ?>/student-id" class="student-id-select-form" style="display: flex; align-items: center; gap: 10px; margin: 0;">
          <label style="font-size: 13px; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-id-badge" style="color: #0284c7;"></i> Select Student:
          </label>
          <select name="id" onchange="this.form.submit()" class="hrms-input student-id-select-dropdown" style="min-width: 260px; padding: 6px 12px; font-size: 0.84rem; border-radius: 6px;">
            <?php foreach ($students_list as $st_item): ?>
              <option value="<?php echo $st_item['id']; ?>" <?php echo $st_item['id'] == $selected_id ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($st_item['student_name']); ?> (<?php echo htmlspecialchars($st_item['roll_no']); ?> &bull; <?php echo htmlspecialchars($st_item['standard']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php else: ?>
        <h3 class="student-id-faculty-title" style="margin: 0; font-size: 1.08rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px;">
          <i class="fa-solid fa-id-card" style="color: var(--hrms-primary);"></i>
          <span><?php echo htmlspecialchars($student['student_name'] ?? 'Student'); ?></span>
          <span style="font-size: 0.78rem; font-weight: 800; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
            <?php echo htmlspecialchars($student['roll_no'] ?? ''); ?>
          </span>
          <span style="font-size: 0.78rem; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 9px; border-radius: 6px;">
            <?php echo htmlspecialchars($student['standard'] ?? 'Standard'); ?>
          </span>
        </h3>
      <?php endif; ?>
    </div>

    <!-- Right: Print and Download Actions -->
    <div class="student-id-toolbar-actions" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
      
      <!-- Download Front PNG -->
      <button type="button" onclick="downloadCardImage('card_front_element', 'Front');" class="roaster-btn secondary" style="gap: 6px;" title="Save Front Card as High-Resolution Image">
        <i class="fa-solid fa-download"></i>
        <span>Front (PNG)</span>
      </button>

      <!-- Download Back PNG -->
      <button type="button" onclick="downloadCardImage('card_back_element', 'Back');" class="roaster-btn secondary" style="gap: 6px;" title="Save Back Card with QR Code as High-Resolution Image">
        <i class="fa-solid fa-download"></i>
        <span>Back (PNG)</span>
      </button>

      <!-- Print Both Sides -->
      <button type="button" onclick="window.print();" class="roaster-add-btn student-id-btn-print" style="padding: 7px 16px; font-size: 0.82rem;" title="Print official ID card (Front &amp; Back)">
        <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-print"></i></div>
        <span>Print ID Card</span>
      </button>

    </div>

  </div>
</div>

<?php if ($student): ?>
  <!-- ══════════════════════════════════════════════════════
       ID CARD STAGE: FRONT & BACK SIDES WITH LANYARDS
       ══════════════════════════════════════════════════════ -->
  <div class="id-card-printable-area id-card-stage" id="id-card-stage">
    
    <!-- ── CARD FRONT ── -->
    <div class="id-badge-holder">
      <div class="lanyard-clip no-print"></div>
      
      <div class="pvc-card" id="card_front_element">
        
        <!-- Punch Slot -->
        <div class="card-lanyard-slot"></div>

        <!-- Header Banner -->
        <div class="card-front-header">
          <div class="cf-logo-row">
            <div class="cf-logo-wrap">
              <img src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="Logo" onerror="this.src='/assets/imgs/ksv-logo.jpg'">
            </div>
            <div>
              <div class="cf-brand-name"><?php echo htmlspecialchars($school_settings['school_name']); ?></div>
              <div class="cf-sub-tag">Coaching Institute &bull; Academic Center</div>
            </div>
          </div>
          <div class="cf-session-pill">
            <i class="fa-solid fa-graduation-cap"></i>
            <span>Student Identity Card &bull; Session <?php echo htmlspecialchars($active_session['session_name']); ?></span>
          </div>
        </div>

        <!-- Photo & Smart Chip Container -->
        <div class="cf-photo-container">
          <div class="cf-smart-chip" title="Biometric NFC Microchip Pass">
            <i class="fa-solid fa-microchip"></i>
          </div>

          <div class="cf-photo-frame">
            <?php if ($has_student_photo): ?>
              <img src="<?php echo htmlspecialchars($student_photo_url); ?>" alt="<?php echo htmlspecialchars($student['student_name']); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
              <div class="cf-photo-fallback" style="background: <?php echo $avatar_bg; ?>; display: none;">
                <?php echo $first_letter; ?>
              </div>
            <?php else: ?>
              <div class="cf-photo-fallback" style="background: <?php echo $avatar_bg; ?>;">
                <?php echo $first_letter; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Card Front Body Details -->
        <div class="card-front-body">
          <div class="cf-student-name"><?php echo htmlspecialchars($student['student_name']); ?></div>
          <div class="cf-standard-badge">
            <i class="fa-solid fa-book-open" style="font-size: 9px;"></i>
            <span><?php echo htmlspecialchars($student['standard']); ?> &bull; Sec <?php echo htmlspecialchars($student['section'] ?? 'A'); ?></span>
          </div>

          <!-- Key Details List -->
          <div class="cf-info-list">
            <div class="cf-info-row">
              <span class="cf-label">Roll Number</span>
              <span class="cf-val mono"><?php echo htmlspecialchars($student['roll_no']); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Admission No</span>
              <span class="cf-val"><?php echo htmlspecialchars($student['admission_no'] ?? 'ADM-' . $student['id']); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Guardian Name</span>
              <span class="cf-val"><?php echo htmlspecialchars($student['parent_name'] ?? '—'); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Emergency Phone</span>
              <span class="cf-val"><?php echo htmlspecialchars($student['parent_phone'] ?: ($student['phone'] ?? '—')); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Blood Group</span>
              <span class="cf-val">
                <span class="cf-blood-badge"><?php echo htmlspecialchars($student['blood_group'] ?? 'O+'); ?></span>
              </span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Date of Birth</span>
              <span class="cf-val"><?php echo !empty($student['dob']) ? format_date($student['dob']) : '15 Aug 2010'; ?></span>
            </div>
          </div>

          <!-- Signatures & Validity Footer -->
          <div class="cf-footer">
            <div class="cf-valid-box">
              <div class="cf-valid-lbl">VALID TILL</div>
              <div class="cf-valid-val">31 MAR <?php echo date('Y') + 1; ?></div>
            </div>
            <div class="cf-sign-box">
              <div class="cf-sign-img">Salam Khan</div>
              <div class="cf-sign-lbl">Principal Signature</div>
            </div>
          </div>

        </div>

      </div>

      <div class="card-label-badge no-print" style="margin-top: 10px; font-size: 0.76rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 3px 12px; border-radius: 20px;">
        <i class="fa-solid fa-id-badge" style="color: var(--hrms-primary);"></i> PVC Front Side
      </div>
    </div>

    <!-- ── CARD BACK (Biometric QR Pass) ── -->
    <div class="id-badge-holder">
      <div class="lanyard-clip no-print"></div>

      <div class="pvc-card" id="card_back_element">
        
        <!-- Punch Slot -->
        <div class="card-lanyard-slot"></div>

        <!-- Back Header -->
        <div class="card-back-header">
          <div class="cb-title">
            <i class="fa-solid fa-qrcode"></i>
            <span>Student Biometric Attendance QR Pass</span>
          </div>
        </div>

        <!-- Back Body with QR and Instructions -->
        <div class="card-back-body">
          
          <!-- QR Frame -->
          <div class="cb-qr-frame">
            <img src="<?php echo $qr_image_url; ?>" alt="Student Attendance QR Code">
          </div>
          
          <div class="cb-qr-code-text"><?php echo htmlspecialchars($qr_data); ?></div>
          <div class="cb-qr-hint">Scan via KSV Academy Biometric Camera to punch daily attendance.</div>

          <!-- Guidelines Box -->
          <div class="cb-instructions">
            <strong>Campus Rules &amp; Regulations:</strong>
            <ol>
              <li>This identity badge is mandatory on campus at all times.</li>
              <li>Present this card for library, lab, and examination hall entry.</li>
              <li>Non-transferable; valid only for <?php echo htmlspecialchars($active_session['session_name']); ?>.</li>
              <li>If lost or damaged, report to admin office immediately.</li>
            </ol>
          </div>

          <!-- Institution Contact Footer -->
          <div class="cb-footer">
            <strong><?php echo htmlspecialchars($school_settings['school_name']); ?></strong><br>
            <?php echo htmlspecialchars($school_settings['address']); ?><br>
            Emergency Student Desk: <?php echo htmlspecialchars($school_settings['phone']); ?> | <?php echo htmlspecialchars($school_settings['email']); ?>
          </div>

        </div>

      </div>

      <div class="card-label-badge no-print" style="margin-top: 10px; font-size: 0.76rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 3px 12px; border-radius: 20px;">
        <i class="fa-solid fa-qrcode" style="color: #0284c7;"></i> PVC Back Side (Biometric QR)
      </div>
    </div>

  </div>

<?php else: ?>
  <div class="hrms-card" style="text-align: center; padding: 60px 20px;">
    <div style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px;"><i class="fa-solid fa-id-badge"></i></div>
    <h3 style="margin: 0 0 8px; font-weight: 800; color: #0f172a;">No Student Record Found</h3>
    <p style="margin: 0; color: #64748b; font-size: 0.88rem;">Please contact the administrator to setup your student profile.</p>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     HIGH-RES PNG DOWNLOAD LOGIC (html2canvas)
     ══════════════════════════════════════════════════════ -->
<script>
function downloadCardImage(elementId, sideLabel) {
  const card = document.getElementById(elementId);
  if (!card) return;

  const studentName = <?php echo json_encode($student['student_name'] ?? 'Student'); ?>;
  const rollNo = <?php echo json_encode($student['roll_no'] ?? 'KSV'); ?>;
  const filename = `Student_ID_${sideLabel}_${studentName.replace(/\s+/g, '_')}_${rollNo}.png`;

  html2canvas(card, {
    scale: 2.5,
    useCORS: true,
    backgroundColor: null,
    logging: false
  }).then(canvas => {
    const link = document.createElement('a');
    link.download = filename;
    link.href = canvas.toDataURL('image/png');
    link.click();
  }).catch(err => {
    console.error('Download error:', err);
    alert('Unable to generate high-resolution image. Please try again.');
  });
}
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
