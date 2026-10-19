<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
$page_title = 'Staff ID Card | KSV Academy HRMS';
$page_heading = 'Staff ID Card';
$page_subheading = 'Official Academic Identification Badge & Biometric QR Pass';
$active_menu = 'staff-id';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Filters: search & department
$search = trim($_GET['search'] ?? '');
$filter_dept = trim($_GET['department'] ?? '');

// Fetch distinct departments for the filter dropdown
$dept_stmt = $db->prepare("SELECT DISTINCT department FROM staff_profiles WHERE session_id = ? AND department IS NOT NULL AND department != '' ORDER BY department ASC");
$dept_stmt->execute([$session_id]);
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// Selected Staff ID
$selected_id = intval($_GET['id'] ?? 0);

// If staff role, restrict strictly to their own profile
if ($current_user['role'] === 'staff') {
    $my_prof = $db->query("SELECT id FROM staff_profiles WHERE user_id = {$current_user['id']} LIMIT 1")->fetch();
    if ($my_prof) {
        $selected_id = $my_prof['id'];
    }
}

// Fetch all staff members in active session
$all_staff_stmt = $db->prepare("
    SELECT sp.*, u.full_name, u.email as user_email, u.phone as user_phone, u.avatar, u.username
    FROM staff_profiles sp 
    JOIN users u ON sp.user_id = u.id 
    WHERE sp.session_id = ? 
    ORDER BY sp.id ASC
");
$all_staff_stmt->execute([$session_id]);
$all_staff_list = $all_staff_stmt->fetchAll();

// Query filtered staff for the table directory
$query = "
    SELECT sp.*, u.full_name, u.email as user_email, u.phone as user_phone, u.avatar, u.username
    FROM staff_profiles sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.session_id = ?
";
$params = [$session_id];

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR sp.employee_code LIKE ? OR sp.department LIKE ? OR sp.designation LIKE ? OR sp.phone LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_dept)) {
    $query .= " AND sp.department = ?";
    $params[] = $filter_dept;
}

$query .= " ORDER BY sp.id ASC";
$stmt_table = $db->prepare($query);
$stmt_table->execute($params);
$table_staff = $stmt_table->fetchAll();

// Default selected staff:
// 1. If ID requested and exists, keep it
// 2. Otherwise default to first staff in the filtered table list
// 3. If filtered list is empty, default to first staff in session
if ($selected_id === 0) {
    if (!empty($table_staff)) {
        $selected_id = $table_staff[0]['id'];
    } elseif (!empty($all_staff_list)) {
        $selected_id = $all_staff_list[0]['id'];
    }
}

// Active navigation list: use filtered table staff if available, else all staff
$nav_list = !empty($table_staff) ? $table_staff : $all_staff_list;

// Find current staff index for Previous / Next navigation
$current_index = 0;
foreach ($nav_list as $idx => $s_item) {
    if ($s_item['id'] == $selected_id) {
        $current_index = $idx;
        break;
    }
}
$prev_staff_id = ($current_index > 0) ? $nav_list[$current_index - 1]['id'] : null;
$next_staff_id = ($current_index < count($nav_list) - 1) ? $nav_list[$current_index + 1]['id'] : null;

// Fetch selected staff details
$staff = null;
if ($selected_id > 0) {
    $stmt = $db->prepare("
        SELECT sp.*, u.full_name, u.email as user_email, u.phone as user_phone, u.avatar, u.username
        FROM staff_profiles sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.id = ? LIMIT 1
    ");
    $stmt->execute([$selected_id]);
    $staff = $stmt->fetch();
}

// Helper for staff avatar detection
function get_staff_photo_url($sm) {
    if (!empty($sm['photo']) && $sm['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $sm['photo'])) {
        return $sm['photo'];
    }
    if (!empty($sm['avatar'])) {
        if (file_exists(__DIR__ . '/../../assets/imgs/faculties/' . $sm['avatar'])) {
            return '/assets/imgs/faculties/' . $sm['avatar'];
        }
        if (file_exists(__DIR__ . '/../../assets/imgs/' . $sm['avatar'])) {
            return '/assets/imgs/' . $sm['avatar'];
        }
    }
    $lower = strtolower($sm['full_name'] ?? '');
    if (strpos($lower, 'salam') !== false || strpos($lower, 'director') !== false) {
        return '/assets/imgs/faculties/founder-director.png';
    }
    if (strpos($lower, 'mishra') !== false || strpos($lower, 'rameshwar') !== false) {
        return '/assets/imgs/faculties/mr-rameshwar-mishra.png';
    }
    if (strpos($lower, 'aman') !== false) {
        return '/assets/imgs/faculties/mr-aman-kumar-soni.png';
    }
    if (strpos($lower, 'lubna') !== false) {
        return '/assets/imgs/faculties/mrs-lubna-khan.png';
    }
    return '';
}

// Staff photo & QR data
$staff_photo_url = $staff ? get_staff_photo_url($staff) : '';
$has_staff_photo = !empty($staff_photo_url);

$qr_data = $staff ? ($staff['qr_code'] ?: 'STAFF-' . $staff['id'] . '-' . $staff['employee_code']) : 'KSV-STAFF';
$qr_image_url = get_qr_code_url($qr_data, '220x220');

// Avatar gradient fallback
$first_letter = $staff ? strtoupper(substr($staff['full_name'], 0, 1)) : 'F';
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
/* ── Premium PVC Staff ID Card Styles (Identical CR80 Standard like student-id.php) ── */
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
  margin-top: 4px;
}
.cb-qr-frame img {
  width: 140px;
  height: 140px;
  display: block;
  border-radius: 4px;
}
.cb-qr-code-text {
  font-family: monospace;
  font-size: 12px;
  font-weight: 800;
  color: #0284c7;
  margin-top: 6px;
  letter-spacing: 0.05em;
}
.cb-qr-hint {
  font-size: 9.5px;
  color: #64748b;
  margin: 3px 0 10px;
  font-weight: 600;
}

/* Instructions Box */
.cb-instructions {
  width: 100%;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 9.5px;
  color: #475569;
  text-align: left;
  line-height: 1.45;
  margin-bottom: 8px;
}
.cb-instructions strong {
  color: #0f172a;
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  display: block;
  margin-bottom: 3px;
}
.cb-instructions ol {
  margin: 0;
  padding-left: 14px;
}

/* Card Back Contact Footer */
.cb-footer {
  width: 100%;
  margin-top: auto;
  padding-top: 8px;
  border-top: 1px solid #e2e8f0;
  font-size: 9.5px;
  color: #64748b;
  line-height: 1.35;
}
.cb-footer strong {
  color: #0f172a;
  font-size: 10px;
}

/* ── Staff ID Directory Table & Badge Styles (Simple & Sober) ── */
.staff-id-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.staff-id-table thead th {
  background: #f8fafc;
  color: #475569;
  font-size: 0.73rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 11px 12px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.staff-id-table tbody td {
  padding: 11px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.84rem;
}
.staff-id-table tbody tr {
  transition: background-color 0.15s ease;
}
.staff-id-table tbody tr:hover {
  background-color: #f8fafc;
}
.staff-id-table tbody tr.active-staff-row {
  background-color: #f0f9ff !important;
}
.staff-id-table tbody tr.active-staff-row td:first-child {
  border-left: 3px solid #0284c7;
}

/* Badge & Tag Elements */
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
}
.sid-blood-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  background: #fff1f2;
  border: 1px solid #fecdd3;
  color: #be123c;
  font-size: 0.70rem;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
  white-space: nowrap;
}
.sid-qr-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 3px 8px;
  border-radius: 5px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.73rem;
  font-weight: 600;
  white-space: nowrap;
}
.sid-status-active {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f0f9ff;
  color: #0284c7;
  border: 1px solid #bae6fd;
  font-size: 0.74rem;
  font-weight: 600;
  padding: 2px 9px;
  border-radius: 20px;
  white-space: nowrap;
}
.sid-status-active .pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #0284c7;
  box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7);
  animation: pulse-ring 1.8s infinite;
}
@keyframes pulse-ring {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(2, 132, 199, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
}
.sid-status-verified {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  font-size: 0.74rem;
  font-weight: 600;
  padding: 2px 9px;
  border-radius: 20px;
  white-space: nowrap;
}
.sid-btn-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  font-size: 0.76rem;
  font-weight: 600;
  border-radius: 6px;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}
.sid-btn-secondary {
  color: #334155;
  background: #ffffff;
  border: 1px solid #cbd5e1;
}
.sid-btn-secondary:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}
.sid-btn-primary {
  color: #ffffff;
  background: #0f172a;
  border: 1px solid #0f172a;
}
.sid-btn-primary:hover {
  background: #1e293b;
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
</style>

<!-- ══════════════════════════════════════════════════════
     STAFF ID CARD TOP TOOLBAR & ACTION BAR
     ══════════════════════════════════════════════════════ -->

<div class="roaster-toolbar-card no-print" style="margin-bottom: 22px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    
    <!-- Left: Selected Faculty Member Label -->
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      <h3 style="margin: 0; font-size: 1.08rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-id-card" style="color: var(--hrms-primary);"></i>
        <span><?php echo htmlspecialchars($staff['full_name'] ?? 'Faculty Member'); ?></span>
        <span style="font-size: 0.78rem; font-weight: 800; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
          <?php echo htmlspecialchars($staff['employee_code'] ?? ''); ?>
        </span>
        <span style="font-size: 0.78rem; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 9px; border-radius: 6px;">
          <?php echo htmlspecialchars($staff['department'] ?? 'Faculty'); ?>
        </span>
      </h3>
    </div>

    <!-- Right: Print and Download Actions -->
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
      
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
      <button type="button" onclick="window.print();" class="roaster-add-btn" style="padding: 7px 16px; font-size: 0.82rem;" title="Print official ID card (Front &amp; Back)">
        <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-print"></i></div>
        <span>Print ID Card</span>
      </button>

    </div>

  </div>
</div>

<?php if ($staff): ?>
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
              <div class="cf-sub-tag">Academic Excellence &amp; HRMS</div>
            </div>
          </div>
          <div class="cf-session-pill">
            <i class="fa-solid fa-chalkboard-user"></i>
            <span>Faculty ID Card &bull; Session <?php echo htmlspecialchars($active_session['session_name']); ?></span>
          </div>
        </div>

        <!-- Photo & Smart Chip Container -->
        <div class="cf-photo-container">
          <div class="cf-smart-chip" title="Biometric NFC Microchip Pass">
            <i class="fa-solid fa-microchip"></i>
          </div>

          <div class="cf-photo-frame">
            <?php if ($has_staff_photo): ?>
              <img src="<?php echo htmlspecialchars($staff_photo_url); ?>" alt="<?php echo htmlspecialchars($staff['full_name']); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
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
          <div class="cf-student-name"><?php echo htmlspecialchars($staff['full_name']); ?></div>
          <div class="cf-standard-badge">
            <i class="fa-solid fa-briefcase"></i>
            <span><?php echo htmlspecialchars($staff['designation']); ?> &bull; Dept of <?php echo htmlspecialchars($staff['department']); ?></span>
          </div>

          <!-- Key Details List -->
          <div class="cf-info-list">
            <div class="cf-info-row">
              <span class="cf-label">Employee Code</span>
              <span class="cf-val mono"><?php echo htmlspecialchars($staff['employee_code']); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Qualification</span>
              <span class="cf-val"><?php echo htmlspecialchars($staff['qualification']); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Phone Contact</span>
              <span class="cf-val"><?php echo htmlspecialchars($staff['phone'] ?: $staff['user_phone']); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Official Email</span>
              <span class="cf-val" style="font-size: 10px;"><?php echo htmlspecialchars($staff['email'] ?: $staff['user_email']); ?></span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Blood Group</span>
              <span class="cf-val">
                <span class="cf-blood-badge"><?php echo htmlspecialchars($staff['blood_group'] ?? 'B+'); ?></span>
              </span>
            </div>
            <div class="cf-info-row">
              <span class="cf-label">Date of Joining</span>
              <span class="cf-val"><?php echo format_date($staff['joining_date']); ?></span>
            </div>
          </div>

          <!-- Signatures & Validity Footer -->
          <div class="cf-footer">
            <div class="cf-valid-box">
              <div class="cf-valid-lbl">ISSUED BY</div>
              <div class="cf-valid-val">KSV ACADEMY</div>
            </div>
            <div class="cf-sign-box">
              <div class="cf-sign-img">Salam Khan</div>
              <div class="cf-sign-lbl">Managing Director</div>
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
            <span>Faculty Biometric Attendance QR Pass</span>
          </div>
        </div>

        <!-- Back Body with QR and Instructions -->
        <div class="card-back-body">
          
          <!-- QR Frame -->
          <div class="cb-qr-frame">
            <img src="<?php echo $qr_image_url; ?>" alt="Faculty Attendance QR Code">
          </div>
          
          <div class="cb-qr-code-text"><?php echo htmlspecialchars($qr_data); ?></div>
          <div class="cb-qr-hint">Scan at reception terminal or mobile scanner for daily attendance punch.</div>

          <!-- Guidelines Box -->
          <div class="cb-instructions">
            <strong>Faculty Accreditation Terms:</strong>
            <ol>
              <li>This card confirms active faculty appointment at KSV Academy.</li>
              <li>Mandatory for biometric duty check-in, academic labs &amp; administrative access.</li>
              <li>Non-transferable; report loss immediately to HR &amp; Administration.</li>
            </ol>
          </div>

          <!-- Institution Contact Footer -->
          <div class="cb-footer">
            <strong><?php echo htmlspecialchars($school_settings['school_name']); ?></strong><br>
            <?php echo htmlspecialchars($school_settings['address']); ?><br>
            Emergency HR Desk: <?php echo htmlspecialchars($school_settings['phone']); ?> | <?php echo htmlspecialchars($school_settings['email']); ?>
          </div>

        </div>

      </div>

      <div class="card-label-badge no-print" style="margin-top: 10px; font-size: 0.76rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 3px 12px; border-radius: 20px;">
        <i class="fa-solid fa-qrcode" style="color: #0284c7;"></i> PVC Back Side (Biometric QR)
      </div>
    </div>

  </div>

  <!-- ══════════════════════════════════════════════════════
       FILTER & SEARCH ROASTER TOOLBAR (Same design as student-id.php)
       ══════════════════════════════════════════════════════ -->
  <div class="roaster-toolbar-card no-print" style="margin-top: 20px; margin-bottom: 22px;">
    <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
      
      <!-- Left: Search & Department Filter Form -->
      <form method="GET" action="<?php echo ADMIN_URL; ?>/staff-id" class="roaster-filter-form">
        <input type="hidden" name="id" value="<?php echo $selected_id; ?>">

        <!-- Search Input -->
        <div class="roaster-search-box">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search faculty name, code, dept...">
        </div>

        <!-- Department Filter Dropdown -->
        <div class="roaster-select-box">
          <i class="fa-solid fa-filter"></i>
          <select name="department" onchange="this.form.submit()">
            <option value="">All Departments</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?php echo $dept; ?>" <?php echo $filter_dept === $dept ? 'selected' : ''; ?>>
                <?php echo $dept; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <i class="fa-solid fa-chevron-down select-chevron"></i>
        </div>

        <button type="submit" class="roaster-btn secondary">
          <i class="fa-solid fa-sliders"></i> Filter
        </button>

        <?php if (!empty($search) || !empty($filter_dept)): ?>
          <a href="<?php echo ADMIN_URL; ?>/staff-id<?php echo $selected_id > 0 ? '?id=' . $selected_id : ''; ?>" class="roaster-btn ghost" title="Reset all filters">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </a>
        <?php endif; ?>
      </form>

      <!-- Right: Faculty Live Count & Link to Roster -->
      <div class="roaster-toolbar-actions">
        <div class="scholars-count-badge">
          <span class="count-dot" style="background: #7e22ce;"></span>
          <span><strong><?php echo count($table_staff); ?></strong> Faculty Members</span>
        </div>
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       FACULTY DATA TABLE (Simple & Sober)
       ══════════════════════════════════════════════════════ -->
  <div class="hrms-card no-print" style="margin-bottom: 30px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
    <div class="hrms-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
      <div>
        <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
          <i class="fa-solid fa-id-card-clip" style="color: #0284c7;"></i>
          Faculty ID Card Directory
          <span style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
            Session <?php echo htmlspecialchars($active_session['session_name']); ?>
          </span>
        </h3>
        <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
          Click <strong>View ID Card</strong> on any faculty row below to load their official PVC badge with biometric QR pass.
        </p>
      </div>
      <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
        <span>Total Records: <strong style="color: #0f172a;"><?php echo count($table_staff); ?></strong></span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="hrms-table staff-id-table">
        <thead>
          <tr>
            <th style="min-width: 165px;">Faculty Member</th>
            <th style="min-width: 120px;">Employee ID &amp; Post</th>
            <th style="min-width: 125px;">Department</th>
            <th style="min-width: 120px;">Contact &amp; Blood</th>
            <th style="min-width: 125px;">Biometric QR Pass</th>
            <th style="min-width: 95px;">Card Status</th>
            <th style="min-width: 120px; text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($table_staff)): ?>
            <tr>
              <td colspan="7" style="text-align: center; padding: 50px 20px; color: #64748b;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                  <i class="fa-solid fa-user-slash"></i>
                </div>
                <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Faculty Members Matching Filters</h4>
                <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                  No faculty records matched your query in Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.
                </p>
                <a href="<?php echo ADMIN_URL; ?>/staff-id<?php echo $selected_id > 0 ? '?id=' . $selected_id : ''; ?>" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                  <i class="fa-solid fa-rotate-left"></i> Reset All Filters
                </a>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($table_staff as $st): ?>
              <?php 
                $is_cur_viewing = ($st['id'] == $selected_id);
                $st_photo = get_staff_photo_url($st);
                $has_st_photo = !empty($st_photo);
                $f_letter = strtoupper(substr($st['full_name'], 0, 1));

                $row_class = $is_cur_viewing ? 'active-staff-row' : '';

                // Query preservation for switch url
                $qp = [];
                if (!empty($search)) $qp['search'] = $search;
                if (!empty($filter_dept)) $qp['department'] = $filter_dept;
                $qp['id'] = $st['id'];
                $switch_url = ADMIN_URL . '/staff-id?' . http_build_query($qp) . '#id-card-stage';
              ?>
              <tr class="<?php echo $row_class; ?>">
                
                <!-- 1. Faculty Profile -->
                <td>
                  <div class="user-cell" style="display: flex; align-items: center; gap: 12px;">
                    <?php if ($has_st_photo): ?>
                      <img src="<?php echo htmlspecialchars($st_photo); ?>" alt="<?php echo htmlspecialchars($st['full_name']); ?>" class="staff-avatar-img" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                      <div class="staff-avatar-init" style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                        <?php echo $f_letter; ?>
                      </div>
                    <?php else: ?>
                      <div class="staff-avatar-init" style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <?php echo $f_letter; ?>
                      </div>
                    <?php endif; ?>

                    <div>
                      <div style="font-weight: 700; color: #0f172a; font-size: 0.90rem; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                        <?php echo htmlspecialchars($st['full_name']); ?>
                      </div>
                      <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">
                        <span><?php echo htmlspecialchars($st['email'] ?: $st['user_email']); ?></span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- 2. Employee ID & Post -->
                <td>
                  <span class="sid-code-pill">
                    <i class="fa-solid fa-hashtag" style="font-size: 0.62rem;"></i>
                    <span><?php echo htmlspecialchars($st['employee_code']); ?></span>
                  </span>
                  <div style="font-size: 0.76rem; font-weight: 600; color: #475569; margin-top: 4px;">
                    <?php echo htmlspecialchars($st['designation']); ?>
                  </div>
                </td>

                <!-- 3. Department -->
                <td>
                  <span class="sid-dept-pill">
                    <i class="fa-solid fa-building" style="font-size: 0.65rem; color: #64748b;"></i>
                    <span><?php echo htmlspecialchars($st['department']); ?></span>
                  </span>
                  <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 4px;">
                    Joined: <?php echo !empty($st['joining_date']) ? date('M Y', strtotime($st['joining_date'])) : 'Active'; ?>
                  </div>
                </td>

                <!-- 4. Contact & Blood Group -->
                <td>
                  <div style="font-size: 0.78rem; font-weight: 600; color: #334155; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-phone" style="font-size: 0.68rem; color: #64748b;"></i>
                    <span><?php echo htmlspecialchars($st['phone'] ?: $st['user_phone']); ?></span>
                  </div>
                  <div style="margin-top: 3px;">
                    <span class="sid-blood-badge">
                      Blood: <?php echo htmlspecialchars($st['blood_group'] ?? 'B+'); ?>
                    </span>
                  </div>
                </td>

                <!-- 5. Biometric QR Pass -->
                <td>
                  <span class="sid-qr-chip" title="Biometric Terminal QR Code ID">
                    <i class="fa-solid fa-qrcode" style="color: #0284c7; font-size: 0.72rem;"></i>
                    <span><?php echo htmlspecialchars($st['qr_code'] ?: ('STAFF-' . $st['id'])); ?></span>
                  </span>
                </td>

                <!-- 6. Card Status -->
                <td>
                  <?php if ($is_cur_viewing): ?>
                    <span class="sid-status-active">
                      <span class="pulse-dot"></span> In Preview
                    </span>
                  <?php else: ?>
                    <span class="sid-status-verified">
                      <i class="fa-solid fa-check" style="font-size: 0.68rem;"></i> Verified
                    </span>
                  <?php endif; ?>
                </td>

                <!-- 7. Actions -->
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                    <?php if ($is_cur_viewing): ?>
                      <button type="button" onclick="document.getElementById('id-card-stage').scrollIntoView({behavior: 'smooth'});" class="sid-btn-action sid-btn-secondary" style="background: #f0f9ff; border-color: #bae6fd; color: #0284c7;" title="Currently loaded into preview">
                        <i class="fa-solid fa-eye" style="font-size: 0.72rem;"></i>
                        <span>Viewing</span>
                      </button>
                      <button type="button" onclick="window.print();" class="sid-btn-action sid-btn-secondary" title="Print this Card">
                        <i class="fa-solid fa-print" style="color: #0284c7; font-size: 0.72rem;"></i>
                        <span>Print</span>
                      </button>
                    <?php else: ?>
                      <a href="<?php echo $switch_url; ?>" class="sid-btn-action sid-btn-secondary" title="Load faculty card into preview">
                        <i class="fa-solid fa-id-card" style="color: #0284c7; font-size: 0.72rem;"></i>
                        <span>View ID Card</span>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <div class="hrms-card" style="text-align: center; padding: 60px 20px;">
    <div style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 12px;"><i class="fa-solid fa-id-badge"></i></div>
    <h3 style="margin: 0 0 8px; font-weight: 800; color: #0f172a;">No Faculty Records in Active Session</h3>
    <p style="margin: 0; color: #64748b; font-size: 0.88rem;">Please add staff members in the Faculty Directory to generate official identity cards.</p>
  </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     HIGH-RES PNG DOWNLOAD LOGIC (html2canvas)
     ══════════════════════════════════════════════════════ -->
<script>
function downloadCardImage(elementId, sideLabel) {
  const card = document.getElementById(elementId);
  if (!card) return;

  const staffName = <?php echo json_encode($staff['full_name'] ?? 'Faculty'); ?>;
  const empCode = <?php echo json_encode($staff['employee_code'] ?? 'KSV'); ?>;
  const filename = `Staff_ID_${sideLabel}_${staffName.replace(/\s+/g, '_')}_${empCode}.png`;

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
