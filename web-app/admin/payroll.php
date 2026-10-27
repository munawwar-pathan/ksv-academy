<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
$page_title = 'Staff Salary Management & Payroll | KSV Academy HRMS';
$page_heading = 'Payroll Management';
$page_subheading = 'Monthly Payroll Generation, Earnings, Deductions & Official Printable Salary Slips';
$active_menu = 'payroll';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Filters: default to all months so existing records are immediately visible
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_month = isset($_GET['month']) ? trim($_GET['month']) : '';
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$filter_staff_id = intval($_GET['staff_id'] ?? 0);

// Pagination Settings (10, 20, 30, 40, 50)
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// If staff role, restrict to own profile
if ($current_user['role'] === 'staff') {
    $stmt_mprof = $db->prepare("SELECT id FROM staff_profiles WHERE user_id = ? LIMIT 1");
    $stmt_mprof->execute([$current_user['id']]);
    $my_prof = $stmt_mprof->fetch();
    if ($my_prof) {
        $filter_staff_id = $my_prof['id'];
    }
}

// Handle Pay Salary / Generate Payroll Record (Super Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_payroll' || $action === 'pay_salary') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $month = trim($_POST['month'] ?? date('F'));
        $year = intval($_POST['year'] ?? date('Y'));
        $basic_salary = floatval($_POST['basic_salary'] ?? 50000);
        $allowances = floatval($_POST['allowances'] ?? 5000);
        $deductions = floatval($_POST['deductions'] ?? 2000);
        $payment_status = $_POST['payment_status'] ?? 'Paid';
        $payment_method = $_POST['payment_method'] ?? 'Bank Transfer (NEFT)';
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');

        $net_salary = max(0, ($basic_salary + $allowances) - $deductions);

        if ($staff_id > 0) {
            // Check if payroll already exists for this staff/month/year/session
            $chk = $db->prepare("SELECT id FROM payroll WHERE staff_id = ? AND month = ? AND year = ? AND session_id = ? LIMIT 1");
            $chk->execute([$staff_id, $month, $year, $session_id]);
            $exist_id = $chk->fetchColumn();

            if ($exist_id) {
                $stmt = $db->prepare("
                    UPDATE payroll SET basic_salary = ?, allowances = ?, deductions = ?, net_salary = ?, payment_status = ?, payment_method = ?, payment_date = ?
                    WHERE id = ?
                ");
                $stmt->execute([$basic_salary, $allowances, $deductions, $net_salary, $payment_status, $payment_method, $payment_date, $exist_id]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO payroll (session_id, staff_id, month, year, basic_salary, allowances, deductions, net_salary, payment_status, payment_date, payment_method)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$session_id, $staff_id, $month, $year, $basic_salary, $allowances, $deductions, $net_salary, $payment_status, $payment_date, $payment_method]);
            }

            // Fetch faculty name for friendly confirmation (Prepared Statement)
            $stmt_fn = $db->prepare("SELECT u.full_name FROM staff_profiles sp JOIN users u ON sp.user_id = u.id WHERE sp.id = ?");
            $stmt_fn->execute([$staff_id]);
            $fac_name = $stmt_fn->fetchColumn() ?: "Faculty Member";

            set_flash('success', "Salary payment of " . format_inr($net_salary) . " for {$fac_name} ({$month} {$year}) processed successfully!");
            header("Location: " . ADMIN_URL . "/payroll?month={$month}&year={$year}");
            exit;
        }
    }
}

// Fetch Staff Profiles for Dropdown
$all_staff = $db->prepare("SELECT sp.id, sp.employee_code, sp.department, sp.designation, sp.monthly_salary, sp.photo, u.full_name, u.avatar FROM staff_profiles sp JOIN users u ON sp.user_id = u.id WHERE sp.session_id = ? ORDER BY u.full_name ASC");
$all_staff->execute([$session_id]);
$staff_dropdown = $all_staff->fetchAll();

// KPI Session Statistics
$stat_payroll_stmt = $db->prepare("
    SELECT COUNT(*) as total_slips,
           COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN net_salary ELSE 0 END), 0) as total_disbursed,
           COALESCE(AVG(net_salary), 0) as avg_payout
    FROM payroll
    WHERE session_id = ?
");
$stat_payroll_stmt->execute([$session_id]);
$stat_payroll = $stat_payroll_stmt->fetch();
$total_slips_issued = intval($stat_payroll['total_slips'] ?? 0);
$total_disbursed_amt = floatval($stat_payroll['total_disbursed'] ?? 0);
$avg_payout_amt = floatval($stat_payroll['avg_payout'] ?? 0);
$total_faculty_count = count($staff_dropdown);

// Query Payroll Records with Filtering & Pagination
$where_clauses = ["p.session_id = ?"];
$where_params = [$session_id];

if (!empty($search)) {
    $where_clauses[] = "(u.full_name LIKE ? OR sp.employee_code LIKE ? OR sp.department LIKE ? OR sp.designation LIKE ?)";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
}
if (!empty($filter_month)) {
    $where_clauses[] = "p.month = ?";
    $where_params[] = $filter_month;
}
if ($filter_year > 0) {
    $where_clauses[] = "p.year = ?";
    $where_params[] = $filter_year;
}
if ($filter_staff_id > 0) {
    $where_clauses[] = "p.staff_id = ?";
    $where_params[] = $filter_staff_id;
}

$where_sql = implode(' AND ', $where_clauses);

// Accurate SQL aggregate for total slip count and total disbursed sum for the current filter criteria
$agg_stmt = $db->prepare("
    SELECT COUNT(*) as total_slips,
           COALESCE(SUM(CASE WHEN p.payment_status = 'Paid' THEN p.net_salary ELSE 0 END), 0) as total_disbursed
    FROM payroll p
    JOIN staff_profiles sp ON p.staff_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE {$where_sql}
");
$agg_stmt->execute($where_params);
$agg_res = $agg_stmt->fetch();
$total_records = intval($agg_res['total_slips'] ?? 0);
$filtered_disbursed = floatval($agg_res['total_disbursed'] ?? 0);

// Calculate Pagination Bounds
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Fetch Paginated Records
$data_sql = "
    SELECT p.*, sp.employee_code, sp.department, sp.designation, sp.photo as staff_photo, u.full_name, u.username, u.avatar, u.phone, u.email
    FROM payroll p
    JOIN staff_profiles sp ON p.staff_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE {$where_sql}
    ORDER BY p.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($data_sql);
$param_idx = 1;
foreach ($where_params as $val) {
    $stmt->bindValue($param_idx++, $val);
}
$stmt->bindValue($param_idx++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($param_idx++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$payroll_records = $stmt->fetchAll();

// Helper to preserve active query parameters in pagination links
function get_payroll_pagination_url($target_page, $search, $filter_month, $filter_year, $filter_staff_id, $limit) {
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_month)) $params['month'] = $filter_month;
    if ($filter_year > 0) $params['year'] = $filter_year;
    if ($filter_staff_id > 0) $params['staff_id'] = $filter_staff_id;
    if ($limit !== 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    $query_str = !empty($params) ? '?' . http_build_query($params) : '';
    return ADMIN_URL . '/payroll' . $query_str;
}

$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

// Helper for staff avatar detection
function get_staff_photo_url($sm) {
    $photo_field = $sm['staff_photo'] ?? ($sm['photo'] ?? '');
    if (!empty($photo_field) && $photo_field !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $photo_field)) {
        return $photo_field;
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

// Avatar gradient fallback
$grad_palette = [
    'linear-gradient(135deg, #7c3aed, #6d28d9)',
    'linear-gradient(135deg, #0284c7, #0369a1)',
    'linear-gradient(135deg, #059669, #047857)',
    'linear-gradient(135deg, #d97706, #b45309)',
    'linear-gradient(135deg, #2563eb, #1d4ed8)',
];

require_once __DIR__ . '/../includes/layout-header.php';
?>

<!-- html2canvas Library for Payslip Image Generation -->
<script src="<?php echo HRMS_URL; ?>/assets/js/html2canvas.min.js"></script>

<style>
/* ── Academic & Coaching Institute Payslip Receipt Styles (Matching Student Fee Receipt) ── */
.pay-receipt {
  padding: 0;
  background: #ffffff;
  color: #0f172a;
  font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
  width: 100%;
  max-width: 440px;
  margin: 0 auto;
  border-radius: 12px;
  overflow: hidden;
  border: 1.5px solid #cbd5e1;
  box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
}
.pay-receipt .ri-top-accent {
  height: 6px;
  background: linear-gradient(90deg, #0f172a 0%, #0092df 50%, #0284c7 100%);
}
.pay-receipt .receipt-body {
  padding: 14px 18px 12px;
}
.pay-receipt .ri-brand-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding-bottom: 12px;
  border-bottom: 2px solid #e2e8f0;
}
.pay-receipt .ri-logo-box {
  width: 54px;
  height: 54px;
  min-width: 54px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  padding: 3px;
  background: #ffffff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.pay-receipt .ri-logo-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 6px;
}
.pay-receipt .ri-brand-info {
  flex: 1;
}
.pay-receipt .ri-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
  font-family: 'Outfit', sans-serif;
  letter-spacing: -0.01em;
  line-height: 1.2;
}
.pay-receipt .ri-sub {
  font-size: 10px;
  font-weight: 700;
  color: #0284c7;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-top: 2px;
}
.pay-receipt .ri-address {
  font-size: 9.5px;
  color: #64748b;
  margin-top: 2px;
  line-height: 1.3;
}

/* Document Title Bar */
.pay-receipt .receipt-doc-band {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 5px 10px;
  margin: 8px 0 6px;
}
.pay-receipt .receipt-doc-title {
  font-size: 11px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  font-family: 'Outfit', sans-serif;
}
.pay-receipt .receipt-doc-session {
  font-size: 10px;
  font-weight: 700;
  color: #0284c7;
  background: #e0f2fe;
  padding: 2px 7px;
  border-radius: 5px;
}

/* Meta Grid */
.pay-receipt .receipt-meta-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 6px 10px;
  margin-bottom: 8px;
}
.pay-receipt .rmg-cell {
  display: flex;
  flex-direction: column;
}
.pay-receipt .rmg-label {
  font-size: 9px;
  color: #64748b;
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.04em;
}
.pay-receipt .rmg-val {
  font-size: 11.5px;
  color: #0f172a;
  font-weight: 600;
  margin-top: 1px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pay-receipt .rmg-val.bold {
  font-weight: 800;
  font-family: 'Outfit', sans-serif;
}
.pay-receipt .rmg-val.mono-badge {
  font-family: monospace;
  font-weight: 800;
  color: #0284c7;
}

/* Particulars Table */
.pay-receipt .receipt-table-wrapper {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 10px;
}
.pay-receipt .receipt-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}
.pay-receipt .receipt-table th {
  background: #f1f5f9;
  color: #334155;
  font-weight: 800;
  font-size: 9.5px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 6px 10px;
  border-bottom: 1px solid #cbd5e1;
}
.pay-receipt .receipt-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.pay-receipt .receipt-table tr.total-row td {
  background: #f8fafc;
  border-top: 1.5px solid #cbd5e1;
  border-bottom: none;
  font-weight: 800;
  padding: 8px 10px;
}

/* Words Box */
.pay-receipt .receipt-words-box {
  background: #f0fdf4;
  border: 1px dashed #86efac;
  border-radius: 8px;
  padding: 7px 10px;
  margin-bottom: 12px;
  font-size: 10.5px;
}
.pay-receipt .receipt-words-box .words-label {
  font-weight: 800;
  color: #166534;
  text-transform: uppercase;
  font-size: 9px;
  letter-spacing: 0.04em;
  display: block;
  margin-bottom: 2px;
}
.pay-receipt .receipt-words-box .words-val {
  font-weight: 700;
  color: #14532d;
  font-style: italic;
}

/* Signatures & Seal Section */
.pay-receipt .receipt-auth-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin: 10px 0 8px;
  padding: 0 4px;
}
.pay-receipt .auth-sign-box {
  text-align: center;
  width: 115px;
}
.pay-receipt .auth-sign-line {
  border-bottom: 1.5px dashed #94a3b8;
  height: 24px;
  margin-bottom: 4px;
  font-size: 10.5px;
  font-weight: 700;
  color: #0f172a;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.pay-receipt .auth-sign-label {
  font-size: 9px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* Academy Stamp Badge */
.pay-receipt .auth-seal-badge {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  border: 2px dashed #0284c7;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 4px;
  background: rgba(2, 132, 199, 0.03);
  transform: rotate(-6deg);
  margin: 0 auto;
}
.pay-receipt .seal-text-top {
  font-size: 7px;
  font-weight: 900;
  color: #0284c7;
  letter-spacing: 0.05em;
  line-height: 1;
}
.pay-receipt .seal-text-mid {
  font-size: 7.5px;
  font-weight: 900;
  color: #059669;
  letter-spacing: 0.08em;
  margin: 2px 0;
  line-height: 1;
}
.pay-receipt .seal-text-bot {
  font-size: 6.5px;
  font-weight: 800;
  color: #0284c7;
  line-height: 1;
}

/* Bottom QR & Terms */
.pay-receipt .receipt-bottom-bar {
  display: flex;
  gap: 10px;
  align-items: center;
  border-top: 1px solid #e2e8f0;
  padding-top: 8px;
  margin-top: 8px;
}
.pay-receipt .rb-qr-wrap {
  width: 62px;
  min-width: 62px;
  height: 62px;
  padding: 3px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
}
.pay-receipt .rb-qr-wrap img {
  width: 100%;
  height: 100%;
  display: block;
}
.pay-receipt .rb-notes {
  flex: 1;
  font-size: 8.5px;
  color: #64748b;
  line-height: 1.35;
}
.pay-receipt .rb-notes strong {
  color: #334155;
}

/* ── Receipt Modal Custom Visible Scrollbar & Layout ── */
#payslipViewModal {
  overflow-y: auto !important;
}
#payslipViewModal .receipt-modal-card {
  max-width: 530px !important;
  width: 95% !important;
  max-height: 90vh !important;
  display: flex !important;
  flex-direction: column !important;
  border-radius: 20px !important;
  overflow: hidden !important;
  box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.4) !important;
  margin: auto !important;
}
#payslipModalBody {
  padding: 0 !important;
  background: #f8fafc !important;
  display: block !important;
  overflow-y: scroll !important; /* Force visible vertical scrollbar */
  overflow-x: hidden !important;
  flex: 1 1 auto !important;
  min-height: 220px !important;
  max-height: calc(90vh - 135px) !important;
  scroll-behavior: smooth !important;
  -webkit-overflow-scrolling: touch !important;
  position: relative !important;
  scrollbar-width: thin !important;
  scrollbar-color: #0284c7 #e2e8f0 !important;
}
#payslipModalBody::-webkit-scrollbar {
  width: 9px !important;
  display: block !important;
}
#payslipModalBody::-webkit-scrollbar-track {
  background: #f1f5f9 !important;
  border-left: 1px solid #e2e8f0 !important;
}
#payslipModalBody::-webkit-scrollbar-thumb {
  background: #0284c7 !important;
  border-radius: 10px !important;
  border: 2px solid #f1f5f9 !important;
}
#payslipModalBody::-webkit-scrollbar-thumb:hover {
  background: #0369a1 !important;
}

/* Print CSS */
@media print {
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  html, body {
    background: #ffffff !important;
    color: #000000 !important;
    margin: 0 !important;
    padding: 0 !important;
    height: auto !important;
  }
  .hrms-sidebar, .hrms-topbar, .roaster-toolbar-card, .table-card, .stat-grid, .no-print, .modal-close-btn {
    display: none !important;
  }
  .hrms-main-content {
    margin: 0 !important;
    padding: 0 !important;
  }
  #payslipViewModal {
    position: static !important;
    background: transparent !important;
    display: block !important;
    padding: 0 !important;
  }
  #payslipViewModal .hrms-modal-card {
    max-width: 100% !important;
    box-shadow: none !important;
    border: none !important;
    background: transparent !important;
    max-height: none !important;
    overflow: visible !important;
  }
  #payslipModalBody {
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
    padding: 0 !important;
    display: block !important;
  }
  #payslip_print_area {
    display: flex !important;
    justify-content: center !important;
    padding: 10px 0 !important;
  }
  .pay-receipt {
    box-shadow: none !important;
    border: 1.5px solid #cbd5e1 !important;
    max-width: 480px !important;
    margin: 10px auto !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }
}

/* ── Staff ID Style Table & Badge Styles (Simple & Sober - Matching staff-id) ── */
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
  white-space: nowrap;
}
.period-badge-chip {
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
.sid-status-paid {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  font-size: 0.74rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
  white-space: nowrap;
}
.sid-status-pending {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fde68a;
  font-size: 0.74rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
  white-space: nowrap;
}

/* Action Icon Buttons */
.sid-action-btn {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
  font-size: 0.76rem;
  padding: 0;
  text-decoration: none;
}
.sid-action-btn:hover {
  transform: translateY(-1px);
}
.sid-action-btn.pay {
  color: #059669;
  border-color: #bbf7d0;
  background: #f0fdf4;
}
.sid-action-btn.pay:hover {
  background: #059669;
  color: #ffffff;
  border-color: #059669;
}
.sid-action-btn.slip {
  color: #0284c7;
  border-color: #bae6fd;
  background: #f0f9ff;
}
.sid-action-btn.slip:hover {
  background: #0284c7;
  color: #ffffff;
  border-color: #0284c7;
}

/* ══════════════════════════════════════════════════════════════════════
   DARK THEME COMPREHENSIVE ADAPTATIONS FOR FACULTY PAYROLL
   ══════════════════════════════════════════════════════════════════════ */

/* 1. Stat Metric Cards */
[data-theme="dark"] .stat-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .stat-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .stat-number {
  color: #f8fafc !important;
}
[data-theme="dark"] .stat-card-footer {
  border-top-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .stat-card-footer span strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .stat-card-progress {
  background: #334155 !important;
}
[data-theme="dark"] .stat-card.green .stat-icon-wrapper {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
}
[data-theme="dark"] .stat-card.blue .stat-icon-wrapper {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
}
[data-theme="dark"] .stat-card.purple .stat-icon-wrapper {
  background: rgba(124, 58, 237, 0.15) !important;
  color: #a78bfa !important;
  border-color: rgba(124, 58, 237, 0.3) !important;
}
[data-theme="dark"] .stat-card.amber .stat-icon-wrapper {
  background: rgba(245, 158, 11, 0.15) !important;
  color: #fbbf24 !important;
  border-color: rgba(245, 158, 11, 0.3) !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #059669"],
[data-theme="dark"] .stat-card-footer span[style*="color:#059669"] {
  color: #34d399 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #0284c7"],
[data-theme="dark"] .stat-card-footer span[style*="color:#0284c7"] {
  color: #38bdf8 !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #7c3aed"],
[data-theme="dark"] .stat-card-footer span[style*="color:#7c3aed"] {
  color: #a78bfa !important;
}
[data-theme="dark"] .stat-card-footer span[style*="color: #d97706"],
[data-theme="dark"] .stat-card-footer span[style*="color:#d97706"] {
  color: #fbbf24 !important;
}

/* 2. Payroll Search & Filter Toolbar */
[data-theme="dark"] .roaster-toolbar-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .roaster-search-box {
  background: #0f172a !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .roaster-search-box:focus-within {
  background: #0f172a !important;
  border-color: var(--hrms-primary, #0092df) !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}
[data-theme="dark"] .roaster-search-box i {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-search-box input {
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-search-box input::placeholder {
  color: #64748b !important;
}
[data-theme="dark"] .roaster-search-box .clear-search-btn {
  background: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-search-box .clear-search-btn:hover {
  background: #475569 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-select-box > i:first-child,
[data-theme="dark"] .roaster-select-box .select-chevron {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-select-box select {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-select-box select:focus {
  background: #0f172a !important;
  border-color: var(--hrms-primary, #0092df) !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.2) !important;
}
[data-theme="dark"] .roaster-select-box select option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .roaster-btn.secondary {
  background: #334155 !important;
  color: #f1f5f9 !important;
  border: 1px solid #475569 !important;
}
[data-theme="dark"] .roaster-btn.secondary:hover {
  background: #475569 !important;
  color: #ffffff !important;
}
[data-theme="dark"] .roaster-btn.ghost {
  color: #94a3b8 !important;
}
[data-theme="dark"] .roaster-btn.ghost:hover {
  background: rgba(239, 68, 68, 0.15) !important;
  color: #f87171 !important;
}
[data-theme="dark"] .scholars-count-badge {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .scholars-count-badge strong {
  color: #f8fafc !important;
}

/* 3. Payroll Directory Card & Table */
[data-theme="dark"] .payroll-directory-card {
  background: #1e293b !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .payroll-card-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .payroll-title-block .card-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .payroll-session-badge {
  background: #0f172a !important;
  color: #cbd5e1 !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .payroll-records-count {
  color: #94a3b8 !important;
}
[data-theme="dark"] .payroll-records-count strong {
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
[data-theme="dark"] .staff-id-table tbody tr:hover {
  background-color: rgba(255, 255, 255, 0.02) !important;
}

/* User cell & Avatar */
[data-theme="dark"] .staff-id-table tbody td .user-cell img {
  border-color: #334155 !important;
}
[data-theme="dark"] .staff-id-table tbody td .user-cell > div:first-child[style*="background: #f1f5f9"],
[data-theme="dark"] .staff-id-table tbody td .user-cell > div:first-child[style*="background:#f1f5f9"] {
  background: #334155 !important;
  color: #f8fafc !important;
  border-color: #475569 !important;
}
[data-theme="dark"] .staff-id-table tbody td .user-cell > div > div:first-child {
  color: #f8fafc !important;
}
[data-theme="dark"] .staff-id-table tbody td .user-cell > div > div:last-child {
  color: #94a3b8 !important;
}

/* ID, Department & Period Pills */
[data-theme="dark"] .sid-code-pill {
  background: rgba(2, 132, 199, 0.15) !important;
  border-color: rgba(2, 132, 199, 0.3) !important;
  color: #38bdf8 !important;
}
[data-theme="dark"] .sid-dept-pill {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .sid-dept-pill i {
  color: #94a3b8 !important;
}
[data-theme="dark"] .period-badge-chip {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .period-badge-chip i {
  color: #38bdf8 !important;
}

/* Table Text & Salary Values */
[data-theme="dark"] .staff-id-table tbody td span[style*="color: #334155"],
[data-theme="dark"] .staff-id-table tbody td span[style*="color:#334155"] {
  color: #cbd5e1 !important;
}
[data-theme="dark"] .staff-id-table tbody td span[style*="color: #059669"],
[data-theme="dark"] .staff-id-table tbody td span[style*="color:#059669"] {
  color: #34d399 !important;
}
[data-theme="dark"] .staff-id-table tbody td span[style*="color: #dc2626"],
[data-theme="dark"] .staff-id-table tbody td span[style*="color:#dc2626"] {
  color: #f87171 !important;
}
[data-theme="dark"] .staff-id-table tbody td span[style*="color: #0f172a"],
[data-theme="dark"] .staff-id-table tbody td span[style*="color:#0f172a"] {
  color: #34d399 !important;
}
[data-theme="dark"] .staff-id-table tbody td div[style*="color: #64748b"],
[data-theme="dark"] .staff-id-table tbody td div[style*="color:#64748b"] {
  color: #94a3b8 !important;
}

/* Status Badges */
[data-theme="dark"] .sid-status-paid {
  background: rgba(22, 101, 52, 0.2) !important;
  color: #4ade80 !important;
  border-color: rgba(22, 101, 52, 0.4) !important;
}
[data-theme="dark"] .sid-status-pending {
  background: rgba(202, 138, 4, 0.2) !important;
  color: #facc15 !important;
  border-color: rgba(202, 138, 4, 0.4) !important;
}

/* Action Buttons */
[data-theme="dark"] .sid-action-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #94a3b8 !important;
}
[data-theme="dark"] .sid-action-btn.pay {
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.4) !important;
  background: rgba(16, 185, 129, 0.1) !important;
}
[data-theme="dark"] .sid-action-btn.pay:hover {
  background: #059669 !important;
  color: #ffffff !important;
  border-color: #059669 !important;
}
[data-theme="dark"] .sid-action-btn.slip {
  color: #38bdf8 !important;
  border-color: rgba(2, 132, 199, 0.4) !important;
  background: rgba(2, 132, 199, 0.1) !important;
}
[data-theme="dark"] .sid-action-btn.slip:hover {
  background: #0284c7 !important;
  color: #ffffff !important;
  border-color: #0284c7 !important;
}

/* Empty State */
[data-theme="dark"] .staff-id-table tbody td div[style*="background: #f1f5f9"],
[data-theme="dark"] .staff-id-table tbody td div[style*="background:#f1f5f9"] {
  background: #334155 !important;
  color: #94a3b8 !important;
  border-color: #475569 !important;
}
[data-theme="dark"] .staff-id-table tbody td h4 {
  color: #f8fafc !important;
}

/* 4. Pagination & Controls */
[data-theme="dark"] .pagination-controls-wrap {
  background: #1e293b !important;
  border-top-color: #334155 !important;
}
[data-theme="dark"] .pagination-meta-group span {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pagination-meta-group span strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .pagination-limit-box label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pagination-limit-select {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .pagination-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .pagination-btn:hover:not(.disabled) {
  background: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .pagination-btn.active {
  background: var(--hrms-primary, #0092df) !important;
  border-color: var(--hrms-primary, #0092df) !important;
  color: #ffffff !important;
}
[data-theme="dark"] .pagination-btn.disabled {
  background: #0f172a !important;
  border-color: #1e293b !important;
  color: #475569 !important;
  opacity: 0.5 !important;
}
[data-theme="dark"] .pagination-ellipsis {
  color: #64748b !important;
}
[data-theme="dark"] .pagination-controls-wrap div[style*="color: #64748b"],
[data-theme="dark"] .pagination-controls-wrap div[style*="color:#64748b"] {
  color: #94a3b8 !important;
}

/* 5. Modal 1: #paySalaryModal */
[data-theme="dark"] #paySalaryModal .hrms-modal-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
  box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6) !important;
}
[data-theme="dark"] #paySalaryModal .hrms-modal-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] #paySalaryModal .hrms-modal-header > div > div:first-child {
  background: rgba(16, 185, 129, 0.15) !important;
  color: #34d399 !important;
}
[data-theme="dark"] #paySalaryModal .hrms-modal-header h4 {
  color: #f8fafc !important;
}
[data-theme="dark"] #paySalaryModal .hrms-modal-header span {
  color: #94a3b8 !important;
}
[data-theme="dark"] #paySalaryModal .modal-close-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] #paySalaryModal .modal-close-btn:hover {
  background: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #paySalaryModal .hrms-modal-body {
  background: #1e293b !important;
}
[data-theme="dark"] #paySalaryModal label {
  color: #cbd5e1 !important;
}
[data-theme="dark"] #paySalaryModal input.hrms-input,
[data-theme="dark"] #paySalaryModal select.hrms-input {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #paySalaryModal input.hrms-input:focus,
[data-theme="dark"] #paySalaryModal select.hrms-input:focus {
  background: #0f172a !important;
  border-color: var(--hrms-primary, #0092df) !important;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.25) !important;
}
[data-theme="dark"] #paySalaryModal select.hrms-input option {
  background: #0f172a !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #paySalaryModal input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(1) brightness(0.9);
}
[data-theme="dark"] #payrollAllowances {
  color: #34d399 !important;
}
[data-theme="dark"] #payrollDeductions {
  color: #f87171 !important;
}
/* Net Salary Preview Ribbon */
[data-theme="dark"] #paySalaryModal div[style*="background: #f0fdf4"],
[data-theme="dark"] #paySalaryModal div[style*="background:#f0fdf4"] {
  background: rgba(16, 185, 129, 0.15) !important;
  border-color: rgba(16, 185, 129, 0.35) !important;
}
[data-theme="dark"] #paySalaryModal div[style*="background: #f0fdf4"] span:first-child,
[data-theme="dark"] #paySalaryModal div[style*="background:#f0fdf4"] span:first-child {
  color: #34d399 !important;
}
[data-theme="dark"] #calcNetPreview {
  color: #4ade80 !important;
}
[data-theme="dark"] #paySalaryModal .hrms-modal-footer {
  background: #0f172a !important;
  border-top-color: #334155 !important;
}
[data-theme="dark"] #paySalaryModal .hrms-modal-footer .roaster-btn.secondary {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] #paySalaryModal .hrms-modal-footer .roaster-btn.secondary:hover {
  background: #334155 !important;
  color: #f8fafc !important;
}

/* 6. Modal 2: #payslipViewModal & Payslip Screen View */
[data-theme="dark"] #payslipViewModal .receipt-modal-card {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
}
[data-theme="dark"] #payslipViewModal .hrms-modal-header {
  background: #1e293b !important;
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] #payslipViewModal .hrms-modal-header > div > div:first-child {
  background: rgba(2, 132, 199, 0.15) !important;
  color: #38bdf8 !important;
  border: 1px solid rgba(2, 132, 199, 0.3) !important;
}
[data-theme="dark"] #payslipViewModal .hrms-modal-header h4 {
  color: #f8fafc !important;
}
[data-theme="dark"] #payslipViewModal #modalPayslipSub {
  color: #94a3b8 !important;
}
[data-theme="dark"] #payslipViewModal .modal-close-btn {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] #payslipViewModal .modal-close-btn:hover {
  background: #334155 !important;
  border-color: #475569 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] #payslipModalBody {
  background: #0f172a !important;
  scrollbar-color: #0284c7 #1e293b !important;
}
[data-theme="dark"] #payslipModalBody::-webkit-scrollbar-track {
  background: #0f172a !important;
  border-left-color: #334155 !important;
}
[data-theme="dark"] #payslipModalBody::-webkit-scrollbar-thumb {
  background: #0284c7 !important;
  border-color: #0f172a !important;
}
[data-theme="dark"] .payslip-modal-footer {
  background: #1e293b !important;
  border-top-color: #334155 !important;
}
[data-theme="dark"] .payslip-modal-footer .btn-hrms-outline {
  background: #0f172a !important;
  border-color: #334155 !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .payslip-modal-footer .btn-hrms-outline:hover {
  background: #334155 !important;
  border-color: #475569 !important;
  color: #f8fafc !important;
}

/* ── Dark Theme for Pay Receipt Card (Screen View inside Payslip Modal) ── */
[data-theme="dark"] .pay-receipt {
  background: #0f172a !important;
  border: 1.5px solid #334155 !important;
  color: #f8fafc !important;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
}
[data-theme="dark"] .pay-receipt .ri-brand-row {
  border-bottom-color: #334155 !important;
}
[data-theme="dark"] .pay-receipt .ri-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt .ri-sub {
  color: #38bdf8 !important;
}
[data-theme="dark"] .pay-receipt .ri-address {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pay-receipt .ri-logo-box {
  background: #1e293b !important;
  border-color: #334155 !important;
}

/* Document Type & Session Banner */
[data-theme="dark"] .pay-receipt .receipt-doc-band {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .pay-receipt .receipt-doc-title {
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt .receipt-doc-title i {
  color: #38bdf8 !important;
}
[data-theme="dark"] .pay-receipt .receipt-doc-session {
  background: rgba(2, 132, 199, 0.2) !important;
  color: #38bdf8 !important;
  border: 1px solid rgba(2, 132, 199, 0.3) !important;
}

/* Faculty & Payslip Meta Grid */
[data-theme="dark"] .pay-receipt .receipt-meta-grid {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .pay-receipt .rmg-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pay-receipt .rmg-val {
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt .rmg-val.bold {
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt .rmg-val.mono-badge {
  color: #38bdf8 !important;
}
[data-theme="dark"] .pay-receipt .rmg-val[style*="color: #0f172a"],
[data-theme="dark"] .pay-receipt .rmg-val[style*="color:#0f172a"] {
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt .rmg-val[style*="color: #0284c7"],
[data-theme="dark"] .pay-receipt .rmg-val[style*="color:#0284c7"] {
  color: #38bdf8 !important;
}

/* Particulars Table */
[data-theme="dark"] .pay-receipt .receipt-table-wrapper {
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .pay-receipt .receipt-table th {
  background: #1e293b !important;
  color: #94a3b8 !important;
  border-bottom: 1px solid #334155 !important;
}
[data-theme="dark"] .pay-receipt .receipt-table td {
  border-bottom: 1px solid #1e293b !important;
  color: #cbd5e1 !important;
}
[data-theme="dark"] .pay-receipt .receipt-table td span[style*="color: #0f172a"],
[data-theme="dark"] .pay-receipt .receipt-table td span[style*="color:#0f172a"] {
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt .receipt-table td[style*="color: #0f172a"],
[data-theme="dark"] .pay-receipt .receipt-table td[style*="color:#0f172a"] {
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt .receipt-table td div[style*="color: #64748b"],
[data-theme="dark"] .pay-receipt .receipt-table td div[style*="color:#64748b"] {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pay-receipt .receipt-table td span[style*="color: #059669"],
[data-theme="dark"] .pay-receipt .receipt-table td span[style*="color:#059669"] {
  color: #34d399 !important;
}
[data-theme="dark"] .pay-receipt .receipt-table td span[style*="color: #dc2626"],
[data-theme="dark"] .pay-receipt .receipt-table td span[style*="color:#dc2626"] {
  color: #f87171 !important;
}

/* Table Total Row (fixes washed-out white bar) */
[data-theme="dark"] .pay-receipt .receipt-table tr.total-row td {
  background: #1e293b !important;
  border-top: 1.5px solid #334155 !important;
  border-bottom: none !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt .receipt-table tr.total-row td[style*="color: #0f172a"],
[data-theme="dark"] .pay-receipt .receipt-table tr.total-row td[style*="color:#0f172a"] {
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt .receipt-table tr.total-row td:last-child {
  color: #34d399 !important;
}

/* Gross & Net Salary Strip */
[data-theme="dark"] .pay-receipt div[style*="background: #f8fafc"],
[data-theme="dark"] .pay-receipt div[style*="background:#f8fafc"] {
  background: #1e293b !important;
  border: 1px solid #334155 !important;
}
[data-theme="dark"] .pay-receipt div[style*="background: #f8fafc"] span[style*="color: #64748b"],
[data-theme="dark"] .pay-receipt div[style*="background:#f8fafc"] span[style*="color:#64748b"],
[data-theme="dark"] .pay-receipt div[style*="background: #f8fafc"] span,
[data-theme="dark"] .pay-receipt div[style*="background:#f8fafc"] span {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pay-receipt div[style*="background: #f8fafc"] strong,
[data-theme="dark"] .pay-receipt div[style*="background:#f8fafc"] strong {
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt div[style*="background: #f8fafc"] strong[style*="color: #0f172a"],
[data-theme="dark"] .pay-receipt div[style*="background:#f8fafc"] strong[style*="color:#0f172a"] {
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt div[style*="background: #f8fafc"] strong[style*="color: #059669"],
[data-theme="dark"] .pay-receipt div[style*="background:#f8fafc"] strong[style*="color:#059669"] {
  color: #34d399 !important;
}

/* Words Box */
[data-theme="dark"] .pay-receipt .receipt-words-box {
  background: rgba(16, 185, 129, 0.1) !important;
  border: 1px dashed rgba(16, 185, 129, 0.3) !important;
}
[data-theme="dark"] .pay-receipt .receipt-words-box .words-label {
  color: #34d399 !important;
}
[data-theme="dark"] .pay-receipt .receipt-words-box .words-val,
[data-theme="dark"] .pay-receipt .words-val {
  color: #a7f3d0 !important;
}

/* Signatures & Seal Section */
[data-theme="dark"] .pay-receipt .auth-sign-line {
  border-bottom: 1.5px dashed #475569 !important;
  color: #f8fafc !important;
}
[data-theme="dark"] .pay-receipt .auth-sign-line[style*="color: #0284c7"],
[data-theme="dark"] .pay-receipt .auth-sign-line[style*="color:#0284c7"] {
  color: #38bdf8 !important;
}
[data-theme="dark"] .pay-receipt .auth-sign-label {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pay-receipt .auth-seal-badge {
  border-color: #38bdf8 !important;
  background: rgba(56, 189, 248, 0.08) !important;
}
[data-theme="dark"] .pay-receipt .seal-text-top {
  color: #38bdf8 !important;
}
[data-theme="dark"] .pay-receipt .seal-text-mid {
  color: #34d399 !important;
}
[data-theme="dark"] .pay-receipt .seal-text-bot {
  color: #38bdf8 !important;
}

/* Security QR & Terms Bottom Bar */
[data-theme="dark"] .pay-receipt .receipt-bottom-bar {
  border-top-color: #334155 !important;
}
[data-theme="dark"] .pay-receipt .rb-qr-wrap {
  background: #ffffff !important;
  border-color: #334155 !important;
}
[data-theme="dark"] .pay-receipt .rb-notes {
  color: #94a3b8 !important;
}
[data-theme="dark"] .pay-receipt .rb-notes strong {
  color: #cbd5e1 !important;
}
</style>

<!-- ══════════════════════════════════════════════════════
     PAYROLL KPI OVERVIEW CARDS (Exact match to HRMS stat-grid)
     ══════════════════════════════════════════════════════ -->
<div class="stat-grid no-print" style="margin-bottom: 24px;">
  
  <!-- Total Disbursed -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Payroll Disbursed</span>
          <span class="stat-number"><?php echo format_inr($total_disbursed_amt); ?></span>
        </div>
        <div class="stat-icon-wrapper" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
          <i class="fa-solid fa-money-check-dollar"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #10b981;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Filtered Total: <strong><?php echo format_inr($filtered_disbursed); ?></strong></span>
      <span style="color: #059669; font-weight: 700;"><i class="fa-solid fa-circle-check"></i> Paid</span>
    </div>
  </div>

  <!-- Slips Issued -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Salary Slips Issued</span>
          <span class="stat-number"><?php echo $total_slips_issued; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Showing <strong><?php echo count($payroll_records); ?></strong> in view</span>
      <span style="color: #0284c7; font-weight: 700;"><i class="fa-solid fa-award"></i> Verified</span>
    </div>
  </div>

  <!-- Faculty on Payroll -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Faculty on Payroll</span>
          <span class="stat-number"><?php echo $total_faculty_count; ?></span>
        </div>
        <div class="stat-icon-wrapper" style="background: rgba(124, 58, 237, 0.12); color: #7c3aed;">
          <i class="fa-solid fa-users"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #7c3aed;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Academic Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong></span>
      <span style="color: #7c3aed; font-weight: 700;">Active Roster</span>
    </div>
  </div>

  <!-- Average Compensation -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Average Net Payout</span>
          <span class="stat-number"><?php echo format_inr($avg_payout_amt); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-chart-line"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%; background: #f59e0b;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Per faculty compensation</span>
      <span style="color: #d97706; font-weight: 700;">Standard</span>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     PAYROLL FILTER TOOLBAR (Exact match to HRMS Roaster Theme)
     ══════════════════════════════════════════════════════ -->
<!-- ══════════════════════════════════════════════════════
     PAYROLL FILTER TOOLBAR (Exact match to HRMS Roaster Theme)
     ══════════════════════════════════════════════════════ -->
<div class="roaster-toolbar-card no-print" style="margin-bottom: 22px;">
  <div class="roaster-toolbar-inner">
    
    <!-- Filter form -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/payroll" class="roaster-filter-form">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      
      <!-- Search Input -->
      <div class="roaster-search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search faculty name, ID, dept..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php 
            $params = $_GET; 
            unset($params['search']); 
            unset($params['page']);
            echo ADMIN_URL . '/payroll' . (!empty($params) ? '?' . http_build_query($params) : ''); 
          ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <!-- Month Filter -->
      <div class="roaster-select-box">
        <i class="fa-solid fa-calendar-days"></i>
        <select name="month" onchange="this.form.submit()">
          <option value="">All Months</option>
          <?php foreach ($months as $m): ?>
            <option value="<?php echo $m; ?>" <?php echo $filter_month === $m ? 'selected' : ''; ?>>
              <?php echo $m; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <!-- Year Filter -->
      <div class="roaster-select-box">
        <i class="fa-solid fa-calendar"></i>
        <select name="year" onchange="this.form.submit()">
          <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
            <option value="<?php echo $y; ?>" <?php echo $filter_year == $y ? 'selected' : ''; ?>>
              <?php echo $y; ?>
            </option>
          <?php endfor; ?>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron"></i>
      </div>

      <!-- Faculty Member Filter -->
      <?php if ($current_user['role'] === 'super_admin'): ?>
        <div class="roaster-select-box">
          <i class="fa-solid fa-user-tie"></i>
          <select name="staff_id" onchange="this.form.submit()" style="max-width: 190px;">
            <option value="">All Faculty</option>
            <?php foreach ($staff_dropdown as $st): ?>
              <option value="<?php echo $st['id']; ?>" <?php echo $filter_staff_id == $st['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($st['full_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <i class="fa-solid fa-chevron-down select-chevron"></i>
        </div>
      <?php endif; ?>

      <button type="submit" class="roaster-btn secondary">
        <i class="fa-solid fa-sliders"></i> Filter
      </button>

      <?php if (!empty($search) || !empty($filter_month) || $filter_staff_id > 0 || $filter_year != date('Y')): ?>
        <a href="<?php echo ADMIN_URL; ?>/payroll<?php echo $limit !== 10 ? '?limit=' . $limit : ''; ?>" class="roaster-btn ghost" title="Reset all filters">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </a>
      <?php endif; ?>
    </form>

    <!-- Right: Records Count Badge & Pay Salary Button -->
    <div class="roaster-toolbar-actions">
      <div class="scholars-count-badge">
        <span class="count-dot" style="background: #0284c7;"></span>
        <span><strong><?php echo $total_records; ?></strong> <?php echo $total_records === 1 ? 'Slip' : 'Slips'; ?></span>
      </div>

      <?php if ($current_user['role'] === 'super_admin'): ?>
        <button type="button" class="roaster-add-btn" onclick="openPaySalaryModal()">
          <div class="btn-icon-circle"><i class="fa-solid fa-plus"></i></div>
          <span>Pay Salary</span>
        </button>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     FACULTY SALARY LEDGER TABLE
     ══════════════════════════════════════════════════════ -->
<div class="hrms-card payroll-directory-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">
  <div class="hrms-card-header payroll-card-header" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
    <div class="payroll-title-block">
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-file-invoice-dollar" style="color: #0284c7;"></i>
        Faculty Salary &amp; Payroll Ledger
        <span class="payroll-session-badge" style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Official statement of monthly salary disbursements, earnings, deductions, and downloadable payslips.
      </p>
    </div>
    <div class="payroll-records-count" style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
      <span>Total Records: <strong style="color: #0f172a;"><?php echo $total_records; ?></strong></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="hrms-table staff-id-table">
      <thead>
        <tr>
          <th style="min-width: 190px;">Faculty Member</th>
          <th style="min-width: 110px;">Employee ID</th>
          <th style="min-width: 135px;">Department</th>
          <th style="min-width: 120px;">Period</th>
          <th style="min-width: 100px;">Basic Salary</th>
          <th style="min-width: 90px;">Allowances</th>
          <th style="min-width: 90px;">Deductions</th>
          <th style="min-width: 110px;">Net Payable</th>
          <th style="min-width: 125px;">Disbursement</th>
          <th style="min-width: 85px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payroll_records)): ?>
          <tr>
            <td colspan="10" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-file-invoice-dollar"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Payroll Records Found</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                No salary slips found for the selected criteria in Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.
              </p>
              <a href="<?php echo ADMIN_URL; ?>/payroll" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                <i class="fa-solid fa-rotate-left"></i> Reset Filters
              </a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($payroll_records as $pr): ?>
            <?php 
              $photo_url = get_staff_photo_url($pr);
              $first_letter = strtoupper(substr($pr['full_name'], 0, 1));
            ?>
            <tr>
              
              <!-- 1. Faculty Profile -->
              <td>
                <div class="user-cell" style="display: flex; align-items: center; gap: 12px;">
                  <?php if (!empty($photo_url)): ?>
                    <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo htmlspecialchars($pr['full_name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 15px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php endif; ?>
                  
                  <div>
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.90rem; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                      <?php echo htmlspecialchars($pr['full_name']); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 0.73rem; color: #64748b; margin-top: 2px;">
                      <span style="font-family: monospace; color: #0284c7; font-weight: 600;">@<?php echo htmlspecialchars($pr['username']); ?></span>
                      <span>&bull;</span>
                      <span><?php echo htmlspecialchars($pr['designation']); ?></span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 2. Employee ID -->
              <td>
                <span class="sid-code-pill">
                  <i class="fa-solid fa-id-badge" style="font-size: 0.65rem;"></i>
                  <span><?php echo htmlspecialchars($pr['employee_code']); ?></span>
                </span>
              </td>

              <!-- 3. Department -->
              <td>
                <span class="sid-dept-pill">
                  <i class="fa-solid fa-layer-group" style="font-size: 0.65rem; color: #64748b;"></i>
                  <span><?php echo htmlspecialchars($pr['department']); ?></span>
                </span>
              </td>

              <!-- 4. Payroll Period -->
              <td>
                <span class="period-badge-chip">
                  <i class="fa-regular fa-calendar" style="font-size: 0.68rem; color: #64748b;"></i>
                  <span><?php echo htmlspecialchars($pr['month']); ?> <?php echo htmlspecialchars($pr['year']); ?></span>
                </span>
              </td>

              <!-- 5. Basic Salary -->
              <td>
                <span style="font-size: 0.86rem; font-weight: 600; color: #334155; font-family: 'Outfit', sans-serif;">
                  <?php echo format_inr($pr['basic_salary']); ?>
                </span>
              </td>

              <!-- 6. Allowances -->
              <td>
                <span style="font-size: 0.82rem; font-weight: 600; color: #059669; font-family: 'Outfit', sans-serif;">
                  +<?php echo format_inr($pr['allowances']); ?>
                </span>
              </td>

              <!-- 7. Deductions -->
              <td>
                <span style="font-size: 0.82rem; font-weight: 600; color: #dc2626; font-family: 'Outfit', sans-serif;">
                  -<?php echo format_inr($pr['deductions']); ?>
                </span>
              </td>

              <!-- 8. Net Payable -->
              <td>
                <span style="font-size: 0.92rem; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
                  <?php echo format_inr($pr['net_salary']); ?>
                </span>
              </td>

              <!-- 9. Disbursement Status & Mode -->
              <td>
                <div>
                  <?php if ($pr['payment_status'] === 'Paid'): ?>
                    <span class="sid-status-paid">
                      <i class="fa-solid fa-circle-check" style="font-size: 0.68rem;"></i> Paid
                    </span>
                  <?php else: ?>
                    <span class="sid-status-pending">
                      <i class="fa-solid fa-clock" style="font-size: 0.68rem;"></i> Pending
                    </span>
                  <?php endif; ?>
                </div>
                <div style="font-size: 0.72rem; color: #64748b; margin-top: 3px; display: flex; align-items: center; gap: 4px;" title="<?php echo htmlspecialchars($pr['payment_method']); ?>">
                  <i class="fa-solid fa-building-columns" style="font-size: 0.62rem; color: #94a3b8;"></i>
                  <span><?php echo htmlspecialchars($pr['payment_method']); ?></span>
                </div>
              </td>

              <!-- 10. Actions: Pay Salary & Payslip -->
              <td style="text-align: right; white-space: nowrap;">
                <div style="display: inline-flex; gap: 5px; justify-content: flex-end; align-items: center;">
                  
                  <?php if ($current_user['role'] === 'super_admin'): ?>
                    <button type="button" onclick="openPaySalaryModal(<?php echo (int)$pr['staff_id']; ?>, '<?php echo htmlspecialchars($pr['month'], ENT_QUOTES); ?>', <?php echo (int)$pr['year']; ?>, <?php echo (float)$pr['basic_salary']; ?>, <?php echo (float)$pr['allowances']; ?>, <?php echo (float)$pr['deductions']; ?>, '<?php echo htmlspecialchars($pr['payment_status'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($pr['payment_method'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($pr['payment_date'] ?? date('Y-m-d'), ENT_QUOTES); ?>');" class="sid-action-btn pay" title="Pay or Edit Salary">
                      <i class="fa-solid fa-wallet"></i>
                    </button>
                  <?php endif; ?>

                  <button type="button" onclick="previewPayslip(<?php echo htmlspecialchars(json_encode($pr)); ?>);" class="sid-action-btn slip" title="View, Print &amp; Download Payslip">
                    <i class="fa-solid fa-file-invoice"></i>
                  </button>

                </div>
              </td>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Table Footer Pagination & Per-Page Controls (Matches HRMS standard) -->
  <div class="pagination-controls-wrap">
    
    <!-- Left: Showing summary & Limit Selector -->
    <div class="pagination-meta-group">
      <span style="font-size: 0.82rem; color: #475569;">
        <?php if ($total_records > 0): ?>
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> salary <?php echo $total_records === 1 ? 'slip' : 'slips'; ?>
        <?php else: ?>
          Showing <strong>0</strong> salary slips
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_payroll_pagination_url(1, $search, $filter_month, $filter_year, $filter_staff_id, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Faculty Payroll Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_payroll_pagination_url($page - 1, $search, $filter_month, $filter_year, $filter_staff_id, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
           aria-label="Previous Page"
           title="Previous Page">
          <i class="fa-solid fa-chevron-left" style="font-size: 0.72rem;"></i>
        </a>

        <!-- Page Numbers -->
        <?php
          $range = 2;
          for ($p = 1; $p <= $total_pages; $p++):
            if ($p == 1 || $p == $total_pages || ($p >= $page - $range && $p <= $page + $range)):
        ?>
          <a href="<?php echo htmlspecialchars(get_payroll_pagination_url($p, $search, $filter_month, $filter_year, $filter_staff_id, $limit)); ?>" 
             class="pagination-btn <?php echo $p === $page ? 'active' : ''; ?>">
            <?php echo $p; ?>
          </a>
        <?php 
            elseif (($p == 2 && $page - $range > 2) || ($p == $total_pages - 1 && $page + $range < $total_pages - 1)):
        ?>
          <span class="pagination-ellipsis">&hellip;</span>
        <?php 
            endif;
          endfor;
        ?>

        <!-- Next Page -->
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_payroll_pagination_url($page + 1, $search, $filter_month, $filter_year, $filter_staff_id, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Status Legend -->
    <div style="display: flex; align-items: center; gap: 16px; font-size: 0.78rem; color: #64748b; margin-left: auto;">
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
        Paid &amp; Settled
      </span>
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span>
        Pending Disbursal
      </span>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: PAY FACULTY SALARY & ISSUE SLIP (Interactive Popup)
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="paySalaryModal" onclick="if (event.target === this) closePaySalaryModal();">
  <div class="hrms-modal-card" style="max-width: 580px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; border-radius: 18px; overflow: hidden; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.35);">
    
    <!-- Modal Header -->
    <div class="hrms-modal-header" style="padding: 16px 22px; background: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(5, 150, 105, 0.1); color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.05rem;">
          <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
        <div>
          <h4 style="margin: 0; font-size: 0.98rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
            Pay Faculty Salary
          </h4>
          <span style="font-size: 0.72rem; color: #64748b;">Disburse monthly earnings &amp; issue slip</span>
        </div>
      </div>

      <button type="button" class="modal-close-btn" onclick="closePaySalaryModal();" aria-label="Close modal" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Form Body -->
    <form method="POST" action="<?php echo ADMIN_URL; ?>/payroll" id="paySalaryForm" style="display: flex; flex-direction: column; overflow-y: auto; margin: 0;">
      <input type="hidden" name="action" value="pay_salary">

      <div class="hrms-modal-body" style="padding: 22px 24px; display: flex; flex-direction: column; gap: 16px;">
        
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Faculty Member *</label>
          <select name="staff_id" id="payrollStaffSelect" onchange="autoFillSalary(this);" class="hrms-input" required style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
            <?php foreach ($staff_dropdown as $st): ?>
              <option value="<?php echo $st['id']; ?>" data-salary="<?php echo $st['monthly_salary']; ?>">
                <?php echo htmlspecialchars($st['full_name']); ?> (<?php echo htmlspecialchars($st['employee_code']); ?> • <?php echo format_inr($st['monthly_salary']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Salary Month</label>
            <select name="month" id="payrollMonth" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
              <?php foreach ($months as $m): ?>
                <option value="<?php echo $m; ?>" <?php echo (date('F') === $m || $filter_month === $m) ? 'selected' : ''; ?>><?php echo $m; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Financial Year</label>
            <input type="number" name="year" id="payrollYear" value="<?php echo $filter_year; ?>" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Basic Pay (₹)</label>
            <input type="number" step="0.01" name="basic_salary" id="payrollBasic" value="55000" class="hrms-input" oninput="calcNetSalary();" required style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 700;">
          </div>
          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Allowances (₹)</label>
            <input type="number" step="0.01" name="allowances" id="payrollAllowances" value="3000" class="hrms-input" oninput="calcNetSalary();" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; color: #059669; font-weight: 700;">
          </div>
          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Deductions (₹)</label>
            <input type="number" step="0.01" name="deductions" id="payrollDeductions" value="200" class="hrms-input" oninput="calcNetSalary();" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; color: #dc2626; font-weight: 700;">
          </div>
        </div>

        <!-- Net Salary Preview Ribbon -->
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 12px; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.04em;">NET SALARY PAYABLE:</span>
          <span style="font-size: 19px; font-weight: 900; color: #15803d; font-family: 'Outfit', sans-serif;" id="calcNetPreview">₹57,800.00</span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Payment Status</label>
            <select name="payment_status" id="payrollPaymentStatus" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
              <option value="Paid">Paid</option>
              <option value="Pending">Pending</option>
            </select>
          </div>
          <div>
            <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Disbursement Mode</label>
            <select name="payment_method" id="payrollPaymentMethod" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
              <option value="Bank Transfer (NEFT)">Bank Transfer (NEFT)</option>
              <option value="Direct Deposit">Direct Deposit</option>
              <option value="Cheque">Cheque</option>
              <option value="Cash">Cash</option>
              <option value="UPI / Online">UPI / Online</option>
            </select>
          </div>
        </div>

        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; color: #334155;">Disbursement Date</label>
          <input type="date" name="payment_date" id="payrollPaymentDate" value="<?php echo date('Y-m-d'); ?>" class="hrms-input" style="width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 600;">
        </div>

      </div>

      <!-- Footer Actions -->
      <div class="hrms-modal-footer" style="padding: 14px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" onclick="closePaySalaryModal();" class="roaster-btn secondary" style="padding: 8px 16px; font-size: 0.82rem; font-weight: 600;">Cancel</button>
        <button type="submit" class="roaster-add-btn" style="padding: 8px 20px; font-size: 0.82rem;">
          <i class="fa-solid fa-check"></i>
          <span>Confirm Payment</span>
        </button>
      </div>

    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: View & Print Salary Slip (Interactive Preview)
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="payslipViewModal" onclick="if (event.target === this) closePayslipModal();">
  <div class="hrms-modal-card receipt-modal-card" style="max-width: 520px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.4);">
    
    <!-- Modal Header (no-print) -->
    <div class="hrms-modal-header no-print" style="padding: 14px 20px; background: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(0, 146, 223, 0.1); color: var(--hrms-primary); display: flex; align-items: center; justify-content: center; font-size: 1.05rem;">
          <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
        <div>
          <h4 style="margin: 0; font-size: 0.98rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
            Official Salary Payslip
          </h4>
          <span id="modalPayslipSub" style="font-size: 0.72rem; color: #64748b;">Verified Faculty Salary Voucher</span>
        </div>
      </div>

      <button type="button" class="modal-close-btn" onclick="closePayslipModal();" aria-label="Close modal" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Body (Payslip display container with visible scrollbar) -->
    <div class="hrms-modal-body" id="payslipModalBody">
      <div id="payslip_print_area" style="display: flex; justify-content: center; width: 100%; padding: 14px 10px 20px;">
        <!-- Built dynamically via buildPayslip(payroll) -->
      </div>
    </div>

    <!-- Hidden container used for off-screen clean html2canvas rendering -->
    <div id="payslipRenderContainer" style="position: fixed; left: -9999px; top: 0; width: 480px;"></div>

    <!-- Modal Footer Action Buttons (Print, Download, Share) -->
    <div class="payslip-modal-footer no-print" style="padding: 12px 18px; background: #ffffff; border-top: 1px solid #e2e8f0; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
      
      <!-- Print Button -->
      <button type="button" onclick="printPayslip();" class="btn-hrms btn-hrms-outline btn-hrms-sm" style="font-weight: 700; gap: 6px; justify-content: center; padding: 9px 12px; font-size: 0.82rem; border-color: #cbd5e1;" title="Print on printer or save as PDF">
        <i class="fa-solid fa-print"></i>
        <span>Print</span>
      </button>

      <!-- Download Button -->
      <button type="button" id="btn_download_payslip" onclick="downloadPayslipPNG();" class="roaster-add-btn" style="justify-content: center; padding: 9px 12px; font-size: 0.82rem; gap: 6px; box-shadow: 0 4px 12px rgba(0, 146, 223, 0.25);" title="Download as high-resolution PNG image">
        <i class="fa-solid fa-file-arrow-down"></i>
        <span>Download</span>
      </button>

      <!-- Share Button -->
      <button type="button" id="btn_share_payslip" onclick="sharePayslip();" class="btn-hrms btn-hrms-sm" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; border: none; font-weight: 700; gap: 6px; justify-content: center; padding: 9px 12px; font-size: 0.82rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);" title="Share via WhatsApp or Device Share">
        <i class="fa-solid fa-share-nodes"></i>
        <span>Share</span>
      </button>

    </div>

  </div>
</div>

<script>
// ── State for Active Payroll Record in Modal ──
let currentActivePayroll = null;

function openPaySalaryModal(staffId = null, month = null, year = null, basic = null, allowances = null, deductions = null, status = 'Paid', method = 'Bank Transfer (NEFT)', paymentDate = null) {
  const modal = document.getElementById('paySalaryModal');
  if (!modal) return;
  
  if (staffId) {
    const sel = document.getElementById('payrollStaffSelect');
    if (sel) {
      sel.value = staffId;
      autoFillSalary(sel);
    }
  } else {
    const sel = document.getElementById('payrollStaffSelect');
    if (sel) autoFillSalary(sel);
  }

  if (month) {
    const mSel = document.getElementById('payrollMonth');
    if (mSel) mSel.value = month;
  }
  if (year) {
    const yInput = document.getElementById('payrollYear');
    if (yInput) yInput.value = year;
  }
  if (basic !== null && basic !== undefined && !isNaN(basic)) {
    const bInput = document.getElementById('payrollBasic');
    if (bInput) bInput.value = basic;
  }
  if (allowances !== null && allowances !== undefined && !isNaN(allowances)) {
    const aInput = document.getElementById('payrollAllowances');
    if (aInput) aInput.value = allowances;
  }
  if (deductions !== null && deductions !== undefined && !isNaN(deductions)) {
    const dInput = document.getElementById('payrollDeductions');
    if (dInput) dInput.value = deductions;
  }
  if (status) {
    const sInput = document.getElementById('payrollPaymentStatus');
    if (sInput) sInput.value = status;
  }
  if (method) {
    const mInput = document.getElementById('payrollPaymentMethod');
    if (mInput) mInput.value = method;
  }
  if (paymentDate) {
    const pDate = document.getElementById('payrollPaymentDate');
    if (pDate) pDate.value = paymentDate;
  }

  calcNetSalary();
  modal.classList.add('show');
}

function closePaySalaryModal() {
  const modal = document.getElementById('paySalaryModal');
  if (modal) modal.classList.remove('show');
}

// Backward-compatible aliases
function openGeneratePayrollModal() {
  openPaySalaryModal();
}
function closeGeneratePayrollModal() {
  closePaySalaryModal();
}

function autoFillSalary(selectElem) {
  const opt = selectElem.options[selectElem.selectedIndex];
  if (opt) {
    const salary = opt.getAttribute('data-salary') || 50000;
    document.getElementById('payrollBasic').value = salary;
    calcNetSalary();
  }
}

function calcNetSalary() {
  const basic = parseFloat(document.getElementById('payrollBasic').value) || 0;
  const allow = parseFloat(document.getElementById('payrollAllowances').value) || 0;
  const ded = parseFloat(document.getElementById('payrollDeductions').value) || 0;
  const net = Math.max(0, (basic + allow) - ded);
  document.getElementById('calcNetPreview').innerText = '₹' + net.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function closePayslipModal() {
  const modal = document.getElementById('payslipViewModal');
  if (modal) modal.classList.remove('show');
}

// ── Number to Indian Currency Words (e.g. 50000 -> Fifty Thousand Rupees Only) ──
function numberToIndianWords(num) {
  const a = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
  const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

  num = Math.floor(parseFloat(num) || 0);
  if (num === 0) return '(Zero Rupees Only)';

  function convertSection(n) {
    if (n < 20) return a[n];
    const digit = n % 10;
    return b[Math.floor(n / 10)] + (digit ? ' ' + a[digit] : '');
  }

  let words = '';
  const crore = Math.floor(num / 10000000);
  num %= 10000000;
  const lakh = Math.floor(num / 100000);
  num %= 100000;
  const thousand = Math.floor(num / 1000);
  num %= 1000;
  const hundred = Math.floor(num / 100);
  const remaining = num % 100;

  if (crore > 0) words += convertSection(crore) + ' Crore ';
  if (lakh > 0) words += convertSection(lakh) + ' Lakh ';
  if (thousand > 0) words += convertSection(thousand) + ' Thousand ';
  if (hundred > 0) words += convertSection(hundred) + ' Hundred ';
  if (remaining > 0) {
    if (words !== '') words += 'and ';
    words += convertSection(remaining) + ' ';
  }

  return '(' + words.trim() + ' Rupees Only)';
}

// ── Build Official Payslip Card (Matching Student Fee Receipt Design) ──
function buildPayslip(payroll) {
  const basic = parseFloat(payroll.basic_salary) || 0;
  const allow = parseFloat(payroll.allowances) || 0;
  const ded = parseFloat(payroll.deductions) || 0;
  const net = parseFloat(payroll.net_salary) || 0;
  const gross = basic + allow;

  const slipNo = 'PAY-' + (payroll.year || '2026') + '-' + String(payroll.id).padStart(5, '0');

  let payDateFmt = '--';
  if (payroll.payment_date) {
    const d = new Date(payroll.payment_date + 'T12:00:00');
    payDateFmt = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'long', year: 'numeric' });
  }

  const amountWords = numberToIndianWords(net);

  const esc = (str) => {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  const schoolName = <?php echo json_encode($school_settings['school_name'] ?? 'KSV Academy'); ?>;
  const schoolAddress = <?php echo json_encode(($school_settings['address'] ?? 'KSV Campus, Near City Center') . ' • Contact: ' . ($school_settings['phone'] ?? '+91 98765 43210')); ?>;
  const sessionName = <?php echo json_encode($active_session['session_name'] ?? date('Y')); ?>;
  const schoolLogo = <?php echo json_encode($school_settings['logo_url'] ?? '/assets/imgs/ksv-logo.jpg'); ?>;

  // Verification QR string
  const qrData = "VERIFIED-FACULTY-PAYSLIP:" + slipNo + "|FACULTY:" + (payroll.full_name || '') + "|NET_SALARY:" + net + "|MONTH:" + (payroll.month || '') + " " + (payroll.year || '');

  return `
    <div class="pay-receipt" id="renderedPayslipCard">
      <div class="ri-top-accent"></div>
      <div class="receipt-body">
        
        <!-- Academy Institutional Header -->
        <div class="ri-brand-row">
          <div class="ri-logo-box">
            <img src="${schoolLogo}" alt="Logo" onerror="this.src='/assets/imgs/ksv-logo.jpg'">
          </div>
          <div class="ri-brand-info">
            <h2 class="ri-title">${esc(schoolName)}</h2>
            <div class="ri-sub">Coaching Institute &amp; Faculty Payroll</div>
            <div class="ri-address">${esc(schoolAddress)}</div>
          </div>
        </div>

        <!-- Document Type & Period Banner -->
        <div class="receipt-doc-band">
          <span class="receipt-doc-title"><i class="fa-solid fa-file-invoice-dollar" style="color: #0284c7; margin-right: 5px;"></i> SALARY DISBURSEMENT SLIP</span>
          <span class="receipt-doc-session">${esc(payroll.month)} ${esc(payroll.year)}</span>
        </div>

        <!-- Faculty & Payslip Meta Grid -->
        <div class="receipt-meta-grid">
          <div class="rmg-cell">
            <span class="rmg-label">Payslip Ref No</span>
            <span class="rmg-val mono-badge">${esc(slipNo)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Disbursement Date</span>
            <span class="rmg-val">${esc(payDateFmt)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Faculty Member</span>
            <span class="rmg-val bold">${esc(payroll.full_name)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Employee ID</span>
            <span class="rmg-val mono-badge" style="color: #0f172a;">${esc(payroll.employee_code || 'EMP-' + payroll.staff_id)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Designation &amp; Dept</span>
            <span class="rmg-val">${esc(payroll.designation || 'Faculty')} &bull; ${esc(payroll.department || 'Academic')}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Payment Mode</span>
            <span class="rmg-val" style="color: #0284c7; font-weight: 700;">${esc(payroll.payment_method || 'Bank Transfer (NEFT)')}</span>
          </div>
        </div>

        <!-- Particulars / Breakdown Table -->
        <div class="receipt-table-wrapper">
          <table class="receipt-table">
            <thead>
              <tr>
                <th style="width: 24px; text-align: center;">#</th>
                <th>Salary Heads &amp; Allowances</th>
                <th style="text-align: right; width: 110px;">Amount (₹)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="text-align: center; color: #64748b; font-weight: 700; font-size: 10px;">1</td>
                <td style="padding: 7px 10px;">
                  <span style="font-weight: 800; color: #0f172a; font-size: 10.5px; display: block;">Basic Monthly Salary</span>
                  <span style="font-size: 8.5px; color: #64748b;">Fixed contractual component for ${esc(payroll.month)}</span>
                </td>
                <td style="text-align: right; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; font-size: 11px;">
                  ₹${basic.toLocaleString('en-IN', { minimumFractionDigits: 2 })}/-
                </td>
              </tr>
              <tr>
                <td style="text-align: center; color: #64748b; font-weight: 700; font-size: 10px;">2</td>
                <td style="padding: 7px 10px;">
                  <span style="font-weight: 800; color: #059669; font-size: 10.5px; display: block;">Allowances (HRA / DA / Special)</span>
                  <span style="font-size: 8.5px; color: #64748b;">Monthly academic &amp; living allowances</span>
                </td>
                <td style="text-align: right; font-weight: 800; color: #059669; font-family: 'Outfit', sans-serif; font-size: 11px;">
                  +₹${allow.toLocaleString('en-IN', { minimumFractionDigits: 2 })}/-
                </td>
              </tr>
              <tr style="background: #f8fafc; font-weight: 800; border-top: 1px dashed #cbd5e1; border-bottom: 1px dashed #cbd5e1;">
                <td style="text-align: center; color: #64748b; font-size: 9px;">•</td>
                <td style="padding: 5px 10px; font-size: 9.5px; color: #475569; text-transform: uppercase; font-weight: 800;">
                  Gross Monthly Earnings
                </td>
                <td style="text-align: right; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; font-size: 10.5px;">
                  ₹${gross.toLocaleString('en-IN', { minimumFractionDigits: 2 })}/-
                </td>
              </tr>
              <tr>
                <td style="text-align: center; color: #64748b; font-weight: 700; font-size: 10px;">3</td>
                <td style="padding: 7px 10px;">
                  <span style="font-weight: 800; color: #dc2626; font-size: 10.5px; display: block;">Statutory Deductions (PF / PT / Leaves)</span>
                  <span style="font-size: 8.5px; color: #64748b;">Provident fund, tax and leave adjustments</span>
                </td>
                <td style="text-align: right; font-weight: 800; color: #dc2626; font-family: 'Outfit', sans-serif; font-size: 11px;">
                  -₹${ded.toLocaleString('en-IN', { minimumFractionDigits: 2 })}/-
                </td>
              </tr>
              <tr class="total-row">
                <td colspan="2" style="text-align: right; font-weight: 800; font-size: 10.5px; color: #0f172a; text-transform: uppercase;">
                  Net Disbursed Payout
                </td>
                <td style="text-align: right; font-size: 13px; font-weight: 900; color: #059669; font-family: 'Outfit', sans-serif;">
                  ₹${net.toLocaleString('en-IN', { minimumFractionDigits: 2 })}/-
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Payout Status & Mode Strip -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 7px 10px; font-size: 9.5px;">
          <div>
            <span style="color: #64748b; display: block; font-size: 8.5px; text-transform: uppercase; font-weight: 700;">Disbursement Status</span>
            <strong style="color: #059669; font-family: 'Outfit', sans-serif; font-size: 11px;">
              <i class="fa-solid fa-circle-check"></i> ${esc(payroll.payment_status || 'Paid')} &amp; Disbursed
            </strong>
          </div>
          <div style="text-align: right;">
            <span style="color: #64748b; display: block; font-size: 8.5px; text-transform: uppercase; font-weight: 700;">Academic Session</span>
            <strong style="color: #0284c7; font-family: 'Outfit', sans-serif; font-size: 11px;">
              Session ${esc(sessionName)}
            </strong>
          </div>
        </div>

        <!-- Amount in Words Box -->
        <div class="receipt-words-box">
          <span class="words-label">Net Salary in Words:</span>
          <span class="words-val">${amountWords}</span>
        </div>

        <!-- Signatures & Official Institute Stamp -->
        <div class="receipt-auth-row">
          <div class="auth-sign-box">
            <div class="auth-sign-line">${esc(payroll.full_name)}</div>
            <div class="auth-sign-label">Faculty Member</div>
          </div>

          <div class="auth-seal-badge">
            <div class="seal-text-top">KSV ACADEMY</div>
            <div class="seal-text-mid">★ VERIFIED ★</div>
            <div class="seal-text-bot">PAYROLL DEPT</div>
          </div>

          <div class="auth-sign-box">
            <div class="auth-sign-line" style="color: #0284c7;">Authorized Signatory</div>
            <div class="auth-sign-label">Accounts Officer</div>
          </div>
        </div>

        <!-- Security QR & Institutional Terms -->
        <div class="receipt-bottom-bar">
          <div class="rb-qr-wrap">
            <img id="dl_qr_image" src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=0&data=${encodeURIComponent(qrData)}" alt="QR Code">
          </div>
          <div class="rb-notes">
            <div><strong>Terms &amp; Instructions:</strong></div>
            <div>• Computer-generated monthly salary acknowledgement slip issued by KSV Academy HRMS.</div>
            <div>• Deductions computed as per institute guidelines; report any queries within 7 days.</div>
            <div>• Confidential document intended strictly for the designated faculty member.</div>
          </div>
        </div>

      </div>
    </div>`;
}

// ── Open / Preview Payslip Modal ──
function previewPayslip(payroll) {
  currentActivePayroll = payroll;
  const printArea = document.getElementById('payslip_print_area');
  printArea.innerHTML = buildPayslip(payroll);

  const sub = document.getElementById('modalPayslipSub');
  if (sub) {
    sub.innerText = (payroll.full_name || 'Faculty') + ' • ' + (payroll.month || '') + ' ' + (payroll.year || '');
  }

  const modal = document.getElementById('payslipViewModal');
  if (modal) modal.classList.add('show');

  // Reset scroll position to top
  const body = document.getElementById('payslipModalBody');
  if (body) {
    body.scrollTop = 0;
  }
}

// ── Print Action (Isolated Clean Iframe Print) ──
function printPayslip() {
  if (!currentActivePayroll) return;
  const p = currentActivePayroll;
  const printArea = document.getElementById('payslip_print_area');
  if (!printArea) return;

  const payslipContent = printArea.innerHTML;

  // Create or reuse hidden printing iframe for 100% reliable print isolation
  let printIframe = document.getElementById('ksvPayslipPrintFrame');
  if (!printIframe) {
    printIframe = document.createElement('iframe');
    printIframe.id = 'ksvPayslipPrintFrame';
    printIframe.style.position = 'fixed';
    printIframe.style.right = '0';
    printIframe.style.bottom = '0';
    printIframe.style.width = '0';
    printIframe.style.height = '0';
    printIframe.style.border = '0';
    document.body.appendChild(printIframe);
  }

  // Collect external link stylesheets (FontAwesome, Fonts)
  const externalLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
    .map(el => el.outerHTML)
    .join('\n');

  // Dedicated, pristine styles for payslip print
  const payReceiptStyles = `
    * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    html, body {
      margin: 0;
      padding: 0;
      background: #ffffff !important;
      color: #0f172a;
      font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
    }
    body {
      display: flex;
      justify-content: center;
      padding: 8mm 0;
    }
    .pay-receipt {
      padding: 0;
      background: #ffffff !important;
      color: #0f172a;
      width: 100%;
      max-width: 480px;
      margin: 0 auto !important;
      border-radius: 12px;
      overflow: hidden;
      border: 1.5px solid #cbd5e1 !important;
      box-shadow: none !important;
      page-break-inside: avoid !important;
      break-inside: avoid !important;
    }
    .pay-receipt .ri-top-accent {
      height: 6px;
      background: linear-gradient(90deg, #0f172a 0%, #0092df 50%, #0284c7 100%);
    }
    .pay-receipt .receipt-body {
      padding: 16px 20px 14px;
    }
    .pay-receipt .ri-brand-row {
      display: flex;
      align-items: center;
      gap: 14px;
      padding-bottom: 12px;
      border-bottom: 2px solid #e2e8f0;
    }
    .pay-receipt .ri-logo-box {
      width: 54px;
      height: 54px;
      min-width: 54px;
      border-radius: 10px;
      border: 1.5px solid #e2e8f0;
      padding: 3px;
      background: #ffffff;
    }
    .pay-receipt .ri-logo-box img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      border-radius: 6px;
    }
    .pay-receipt .ri-brand-info { flex: 1; }
    .pay-receipt .ri-title {
      margin: 0;
      font-size: 16px;
      font-weight: 800;
      color: #0f172a;
      font-family: 'Outfit', sans-serif;
      line-height: 1.2;
    }
    .pay-receipt .ri-sub {
      font-size: 10px;
      font-weight: 700;
      color: #0284c7;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-top: 2px;
    }
    .pay-receipt .ri-address {
      font-size: 9.5px;
      color: #64748b;
      margin-top: 2px;
      line-height: 1.3;
    }
    .pay-receipt .receipt-doc-band {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 5px 10px;
      margin: 8px 0 6px;
    }
    .pay-receipt .receipt-doc-title {
      font-size: 11px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      font-family: 'Outfit', sans-serif;
    }
    .pay-receipt .receipt-doc-session {
      font-size: 10px;
      font-weight: 700;
      color: #0284c7;
      background: #e0f2fe;
      padding: 2px 7px;
      border-radius: 5px;
    }
    .pay-receipt .receipt-meta-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 5px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 6px 10px;
      margin-bottom: 8px;
    }
    .pay-receipt .rmg-cell { display: flex; flex-direction: column; }
    .pay-receipt .rmg-label {
      font-size: 8.5px;
      color: #64748b;
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.03em;
    }
    .pay-receipt .rmg-val {
      font-size: 10.5px;
      color: #0f172a;
      font-weight: 600;
      margin-top: 1px;
    }
    .pay-receipt .rmg-val.bold {
      font-weight: 800;
      font-family: 'Outfit', sans-serif;
      font-size: 11.5px;
    }
    .pay-receipt .rmg-val.mono-badge {
      font-family: monospace;
      color: #0284c7;
      font-weight: 800;
      letter-spacing: 0.02em;
    }
    .pay-receipt .receipt-table-wrapper {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      overflow: hidden;
      margin-bottom: 8px;
    }
    .pay-receipt .receipt-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 10.5px;
    }
    .pay-receipt .receipt-table th {
      background: #f1f5f9;
      color: #334155;
      font-weight: 800;
      text-transform: uppercase;
      font-size: 8.5px;
      padding: 6px 10px;
      letter-spacing: 0.04em;
    }
    .pay-receipt .receipt-table td {
      padding: 7px 10px;
      border-bottom: 1px solid #f1f5f9;
      color: #334155;
    }
    .pay-receipt .receipt-table tr.total-row td {
      background: #f8fafc;
      border-top: 1.5px solid #cbd5e1;
      border-bottom: none;
      padding: 8px 10px;
    }
    .pay-receipt .receipt-words-box {
      background: #f0fdf4;
      border: 1px dashed #86efac;
      border-radius: 6px;
      padding: 6px 10px;
      margin-bottom: 10px;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .pay-receipt .receipt-words-box .words-label {
      font-size: 8px;
      font-weight: 800;
      color: #166534;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .pay-receipt .receipt-words-box .words-val {
      font-size: 9.5px;
      font-weight: 700;
      color: #15803d;
      font-style: italic;
    }
    .pay-receipt .receipt-auth-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin: 10px 0 8px;
      padding: 0 4px;
    }
    .pay-receipt .auth-sign-box { text-align: center; width: 115px; }
    .pay-receipt .auth-sign-line {
      border-bottom: 1.5px dashed #94a3b8;
      height: 24px;
      margin-bottom: 4px;
      font-size: 10.5px;
      font-weight: 700;
      color: #0f172a;
      display: flex;
      align-items: flex-end;
      justify-content: center;
    }
    .pay-receipt .auth-sign-label {
      font-size: 9px;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .pay-receipt .auth-seal-badge {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      border: 2px dashed #0284c7;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 4px;
      background: rgba(2, 132, 199, 0.03);
      transform: rotate(-6deg);
      margin: 0 auto;
    }
    .pay-receipt .seal-text-top { font-size: 7px; font-weight: 900; color: #0284c7; letter-spacing: 0.05em; line-height: 1; }
    .pay-receipt .seal-text-mid { font-size: 7.5px; font-weight: 900; color: #059669; letter-spacing: 0.08em; margin: 2px 0; line-height: 1; }
    .pay-receipt .seal-text-bot { font-size: 6.5px; font-weight: 800; color: #0284c7; line-height: 1; }
    .pay-receipt .receipt-bottom-bar {
      display: flex;
      gap: 10px;
      align-items: center;
      border-top: 1px solid #e2e8f0;
      padding-top: 8px;
      margin-top: 8px;
    }
    .pay-receipt .rb-qr-wrap {
      width: 62px;
      min-width: 62px;
      height: 62px;
      padding: 3px;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
    }
    .pay-receipt .rb-qr-wrap img { width: 100%; height: 100%; display: block; }
    .pay-receipt .rb-notes { flex: 1; font-size: 8.5px; color: #64748b; line-height: 1.35; }
    .pay-receipt .rb-notes strong { color: #334155; }
    .no-print { display: none !important; }
    @page { size: auto; margin: 8mm; }
  `;

  const doc = printIframe.contentWindow.document;
  doc.open();
  doc.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8">
      <title>Salary Payslip - ${p.full_name || 'Faculty'} - ${p.month} ${p.year}</title>
      ${externalLinks}
      <style>
        ${payReceiptStyles}
      </style>
    </head>
    <body>
      ${payslipContent}
    </body>
    </html>
  `);
  doc.close();

  setTimeout(() => {
    try {
      printIframe.contentWindow.focus();
      printIframe.contentWindow.print();
    } catch (e) {
      console.warn('Iframe print fallback to window.print():', e);
      window.print();
    }
  }, 400);
}

// ── Download PNG Action (html2canvas) ──
function downloadPayslipPNG() {
  if (!currentActivePayroll) return;
  const p = currentActivePayroll;
  const btn = document.getElementById('btn_download_payslip');
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Rendering...';

  const renderContainer = document.getElementById('payslipRenderContainer');
  renderContainer.innerHTML = buildPayslip(p);
  const cardEl = renderContainer.firstElementChild;
  const cleanName = (p.full_name || 'Faculty').replace(/\s+/g, '_');
  const filename = `Salary_Payslip-${cleanName}-${p.month}_${p.year}.png`;

  // Pre-load image blob to avoid CORS issues in canvas
  const imgEl = renderContainer.querySelector('#dl_qr_image');
  const runExport = () => {
    html2canvas(cardEl, {
      scale: 2,
      useCORS: true,
      backgroundColor: '#ffffff'
    }).then(canvas => {
      const link = document.createElement('a');
      link.download = filename;
      link.href = canvas.toDataURL('image/png');
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      btn.disabled = false;
      btn.innerHTML = origHtml;
      renderContainer.innerHTML = '';
    }).catch(err => {
      console.error('html2canvas error:', err);
      alert('Unable to generate PNG payslip. You can use the Print button to save as PDF.');
      btn.disabled = false;
      btn.innerHTML = origHtml;
      renderContainer.innerHTML = '';
    });
  };

  if (imgEl && imgEl.src.startsWith('http')) {
    fetch(imgEl.src)
      .then(res => res.blob())
      .then(blob => {
        const reader = new FileReader();
        reader.onloadend = () => {
          imgEl.src = reader.result;
          runExport();
        };
        reader.readAsDataURL(blob);
      })
      .catch(() => runExport());
  } else {
    runExport();
  }
}

// ── Share Action (WhatsApp & Web Share) ──
function sharePayslip() {
  if (!currentActivePayroll) return;
  const p = currentActivePayroll;
  const btn = document.getElementById('btn_share_payslip');
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sharing...';

  const basic = parseFloat(p.basic_salary) || 0;
  const allow = parseFloat(p.allowances) || 0;
  const ded = parseFloat(p.deductions) || 0;
  const net = parseFloat(p.net_salary) || 0;
  const gross = basic + allow;

  let shareText = `*KSV ACADEMY - SALARY DISBURSEMENT SLIP*\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `• Faculty: ${p.full_name} (${p.employee_code || 'EMP-' + p.staff_id})\n` +
    `• Designation: ${p.designation || 'Faculty'} • ${p.department || 'Academic'}\n` +
    `• Pay Period: ${p.month} ${p.year}\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `• Basic Monthly Salary: ₹${basic.toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n` +
    `• Allowances (HRA/DA): +₹${allow.toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n` +
    `• Gross Earnings: ₹${gross.toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n` +
    `• Deductions (PF/PT): -₹${ded.toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `• NET SALARY DISBURSED: ₹${net.toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n` +
    `• Disbursement Mode: ${p.payment_method || 'Bank Transfer (NEFT)'}\n` +
    `• Status: ${p.payment_status || 'Paid'} & Cleared\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `Official HRMS Voucher issued by KSV Academy`;

  const renderContainer = document.getElementById('payslipRenderContainer');
  renderContainer.innerHTML = buildPayslip(p);
  const cardEl = renderContainer.firstElementChild;
  const cleanName = (p.full_name || 'Faculty').replace(/\s+/g, '_');
  const filename = `Salary_Payslip-${cleanName}-${p.month}_${p.year}.png`;

  html2canvas(cardEl, { scale: 2, useCORS: true, backgroundColor: '#ffffff' })
    .then(canvas => {
      canvas.toBlob(blob => {
        const file = new File([blob], filename, { type: 'image/png' });

        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          navigator.share({
            title: `Salary Payslip - ${p.full_name}`,
            text: shareText,
            files: [file]
          }).then(() => {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            renderContainer.innerHTML = '';
          }).catch(err => {
            console.log('Share dismissed:', err);
            btn.disabled = false;
            btn.innerHTML = origHtml;
            renderContainer.innerHTML = '';
          });
        } else {
          // Fallback: Copy image to clipboard and open WhatsApp with formatted text
          try {
            const item = new ClipboardItem({ 'image/png': blob });
            navigator.clipboard.write([item]).then(() => {
              btn.disabled = false;
              btn.innerHTML = origHtml;
              renderContainer.innerHTML = '';
              const waUrl = 'https://wa.me/?text=' + encodeURIComponent(shareText);
              window.open(waUrl, '_blank');
            }).catch(() => {
              btn.disabled = false;
              btn.innerHTML = origHtml;
              renderContainer.innerHTML = '';
              const waUrl = 'https://wa.me/?text=' + encodeURIComponent(shareText);
              window.open(waUrl, '_blank');
            });
          } catch (e) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            renderContainer.innerHTML = '';
            const waUrl = 'https://wa.me/?text=' + encodeURIComponent(shareText);
            window.open(waUrl, '_blank');
          }
        }
      });
    }).catch(err => {
      console.error('Share generation error:', err);
      btn.disabled = false;
      btn.innerHTML = origHtml;
      renderContainer.innerHTML = '';
      const waUrl = 'https://wa.me/?text=' + encodeURIComponent(shareText);
      window.open(waUrl, '_blank');
    });
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closePayslipModal();
    closePaySalaryModal();
  }
});

// Run initial salary calculation
document.addEventListener('DOMContentLoaded', function() {
  const sel = document.getElementById('payrollStaffSelect');
  if (sel) autoFillSalary(sel);
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
