<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'parent', 'student']);
$page_title = 'Fee Management & Accounts';
$page_heading = 'Student Fee Management & Accounts';
$page_subheading = 'Tuition Invoices, Payment Receipts, Outstanding Balances & Ledger Records';
$active_menu = 'fees';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Filters
$search = trim($_GET['search'] ?? '');
$filter_standard = trim($_GET['standard'] ?? '');
$filter_method = trim($_GET['method'] ?? '');
$filter_student_id = intval($_GET['student_id'] ?? 0);

// Pagination & Limits Setup (10, 20, 30, 40, 50)
$allowed_limits = [10, 20, 30, 40, 50];
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));

// Standards list
$standards = ['8th Standard', '9th Standard', '10th Standard', '11th Science', '11th Commerce', '12th Science', '12th Commerce'];

// If parent or student role, limit to their student
if ($current_user['role'] === 'student' || $current_user['role'] === 'parent') {
    $my_stu = $db->query("SELECT id FROM students WHERE parent_user_id = {$current_user['id']} LIMIT 1")->fetch();
    if ($my_stu) {
        $filter_student_id = $my_stu['id'];
    }
}

// Handle Record Fee Collection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user['role'] === 'super_admin') {
    $action = $_POST['action'] ?? '';

    if ($action === 'collect_fee') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 0);
        $payment_method = trim($_POST['payment_method'] ?? 'Cash');
        $fee_type = trim($_POST['fee_type'] ?? 'Tuition Fee');
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $remarks = trim($_POST['remarks'] ?? 'Fee payment received');

        if ($student_id > 0 && $amount > 0) {
            $receipt_no = 'RCPT-' . strtoupper(substr(uniqid(), 7));

            $stmt = $db->prepare("
                INSERT INTO fee_payments (session_id, student_id, receipt_no, amount, payment_method, payment_date, fee_type, discount, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$session_id, $student_id, $receipt_no, $amount, $payment_method, $payment_date, $fee_type, $discount, $remarks]);

            // Update student paid_fees
            $db->prepare("UPDATE students SET paid_fees = paid_fees + ? WHERE id = ?")->execute([$amount, $student_id]);

            set_flash('success', "Fee payment of ₹" . number_format($amount, 2) . " recorded with Receipt #{$receipt_no}!");
            header('Location: ' . ADMIN_URL . '/fees');
            exit;
        }
    }
}

// Fetch all students for the dropdown
$all_students = $db->prepare("SELECT id, student_name, roll_no, standard, total_fees, paid_fees FROM students WHERE session_id = ? ORDER BY student_name ASC");
$all_students->execute([$session_id]);
$students_list = $all_students->fetchAll();

// Build WHERE filter conditions
$where_clauses = ["fp.session_id = ?"];
$where_params = [$session_id];

if (!empty($search)) {
    $where_clauses[] = "(s.student_name LIKE ? OR s.roll_no LIKE ? OR fp.receipt_no LIKE ? OR s.admission_no LIKE ? OR s.parent_phone LIKE ? OR fp.remarks LIKE ?)";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
    $where_params[] = "%$search%";
}

if (!empty($filter_standard)) {
    $where_clauses[] = "s.standard = ?";
    $where_params[] = $filter_standard;
}

if (!empty($filter_method)) {
    if ($filter_method === 'UPI') {
        $where_clauses[] = "fp.payment_method LIKE '%UPI%'";
    } else {
        $where_clauses[] = "fp.payment_method LIKE ?";
        $where_params[] = "%$filter_method%";
    }
}

if ($filter_student_id > 0) {
    $where_clauses[] = "fp.student_id = ?";
    $where_params[] = $filter_student_id;
}

$where_sql = implode(' AND ', $where_clauses);

// Count total matching payment records
$count_query = "
    SELECT COUNT(*)
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    WHERE {$where_sql}
";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($where_params);
$total_records = intval($count_stmt->fetchColumn());

// Calculate total pages & offset
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Query Payments with Search, Filters and Pagination
$query = "
    SELECT fp.*, s.student_name, s.roll_no, s.standard, s.total_fees, s.paid_fees, s.photo, s.admission_no, s.parent_phone, s.gender, s.blood_group, s.section
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    WHERE {$where_sql}
    ORDER BY fp.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($query);
$bind_idx = 1;
foreach ($where_params as $val) {
    $stmt->bindValue($bind_idx++, $val);
}
$stmt->bindValue($bind_idx++, $limit, PDO::PARAM_INT);
$stmt->bindValue($bind_idx++, $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination indicators
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $limit, $total_records);

// Helper for generating fee pagination links
function get_fee_pagination_url($target_page, $search, $filter_standard, $filter_method, $filter_student_id, $limit) {
    $params = [];
    if (!empty($search)) $params['search'] = $search;
    if (!empty($filter_standard)) $params['standard'] = $filter_standard;
    if (!empty($filter_method)) $params['method'] = $filter_method;
    if ($filter_student_id > 0) $params['student_id'] = $filter_student_id;
    if ($limit != 10) $params['limit'] = $limit;
    if ($target_page > 1) $params['page'] = $target_page;
    return ADMIN_URL . '/fees' . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Fetch all payments mapped by student_id to show installment history
$all_stu_payments = $db->query("
    SELECT fp.*, s.student_name, s.roll_no, s.standard, s.total_fees, s.paid_fees
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    WHERE fp.session_id = {$session_id}
    ORDER BY fp.payment_date ASC, fp.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$student_payments_map = [];
foreach ($all_stu_payments as $sp) {
    $student_payments_map[$sp['student_id']][] = $sp;
}

foreach ($payments as &$p) {
    $p['all_payments'] = $student_payments_map[$p['student_id']] ?? [$p];
}
unset($p);

// Total collections in this session
$total_collected = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM fee_payments WHERE session_id = {$session_id}")->fetchColumn());
$total_receivable = floatval($db->query("SELECT COALESCE(SUM(total_fees), 0) FROM students WHERE session_id = {$session_id}")->fetchColumn());
$total_dues = max(0, $total_receivable - $total_collected);
$receipts_count = intval($db->query("SELECT COUNT(*) FROM fee_payments WHERE session_id = {$session_id}")->fetchColumn());

$collection_rate = $total_receivable > 0 ? round(($total_collected / $total_receivable) * 100, 1) : 0;
$dues_rate = $total_receivable > 0 ? round(($total_dues / $total_receivable) * 100, 1) : 0;

require_once __DIR__ . '/../includes/layout-header.php';
?>

<!-- html2canvas Library for Receipt Image Generation -->
<script src="<?php echo HRMS_URL; ?>/assets/js/html2canvas.min.js"></script>

<style>
/* ── Academic & Coaching Institute Fee Receipt Styles ── */
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
#receiptPrintModal {
  overflow-y: auto !important;
}
#receiptPrintModal .receipt-modal-card {
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
#receiptModalBody {
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
#receiptModalBody::-webkit-scrollbar {
  width: 9px !important;
  display: block !important;
}
#receiptModalBody::-webkit-scrollbar-track {
  background: #f1f5f9 !important;
  border-left: 1px solid #e2e8f0 !important;
}
#receiptModalBody::-webkit-scrollbar-thumb {
  background: #0284c7 !important;
  border-radius: 10px !important;
  border: 2px solid #f1f5f9 !important;
}
#receiptModalBody::-webkit-scrollbar-thumb:hover {
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
  #receiptPrintModal {
    position: static !important;
    background: transparent !important;
    display: block !important;
    padding: 0 !important;
  }
  #receiptPrintModal .hrms-modal-card {
    max-width: 100% !important;
    box-shadow: none !important;
    border: none !important;
    background: transparent !important;
    max-height: none !important;
    overflow: visible !important;
  }
  #receiptModalBody {
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
    padding: 0 !important;
    display: block !important;
  }
  #receipt_print_area {
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

/* ── Fee Ledger Table & Badge Styles (Simple & Sober - Matching staff-id & student-id) ── */
.staff-id-table, .student-id-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}
.staff-id-table thead th, .student-id-table thead th {
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
.staff-id-table tbody td, .student-id-table tbody td {
  padding: 11px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  font-size: 0.84rem;
}
.staff-id-table tbody tr, .student-id-table tbody tr {
  transition: background-color 0.15s ease;
}
.staff-id-table tbody tr:hover, .student-id-table tbody tr:hover {
  background-color: #f8fafc;
}

/* Badge & Tag Elements */
.sid-receipt-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
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
.sid-roll-pill {
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
.sid-std-pill {
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
.sid-feehead-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 2px 8px;
  border-radius: 5px;
  font-size: 0.74rem;
  font-weight: 600;
  white-space: nowrap;
}
.sid-mode-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 2px 8px;
  border-radius: 5px;
  font-size: 0.74rem;
  font-weight: 600;
  white-space: nowrap;
}
.sid-mode-pill.upi {
  background: #f0f9ff;
  border-color: #bae6fd;
  color: #0284c7;
}
.sid-mode-pill.cash {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #166534;
}
.sid-mode-pill.netbanking, .sid-mode-pill.bank {
  background: #f5f3ff;
  border-color: #ddd6fe;
  color: #7c3aed;
}
.sid-mode-pill.cheque {
  background: #fffbeb;
  border-color: #fde68a;
  color: #b45309;
}

/* Sober Action Buttons */
.sid-btn-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  font-size: 0.74rem;
  font-weight: 600;
  border-radius: 6px;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}
.sid-btn-primary {
  color: #0284c7;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
}
.sid-btn-primary:hover {
  background: #0284c7;
  color: #ffffff;
  border-color: #0284c7;
}
.sid-btn-secondary {
  color: #166534;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
}
.sid-btn-secondary:hover {
  background: #16a34a;
  color: #ffffff;
  border-color: #16a34a;
}
</style>

<!-- Financial KPI Stat Metric Cards -->
<div class="stat-grid" style="margin-bottom: 24px;">

  <!-- Total Collected -->
  <div class="stat-card green">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Collected</span>
          <span class="stat-number"><?php echo format_inr($total_collected); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo min(100, $collection_rate); ?>%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $collection_rate; ?>%</strong> Collection Realized</span>
      <span style="color: #059669; font-weight: 700;"><i class="fa-solid fa-arrow-trend-up"></i> In Vault</span>
    </div>
  </div>

  <!-- Total Receivable Demand -->
  <div class="stat-card blue">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Receivable</span>
          <span class="stat-number"><?php echo format_inr($total_receivable); ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-file-invoice"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Annual Enrolled Fee Demand</span>
      <span style="color: #0284c7; font-weight: 700;"><i class="fa-solid fa-building-columns"></i> Session <?php echo htmlspecialchars($active_session['session_name']); ?></span>
    </div>
  </div>

  <!-- Outstanding Dues -->
  <div class="stat-card amber">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Outstanding Dues</span>
          <span class="stat-number" style="color: #dc2626;"><?php echo format_inr($total_dues); ?></span>
        </div>
        <div class="stat-icon-wrapper" style="background: rgba(220, 38, 38, 0.1); color: #dc2626;">
          <i class="fa-solid fa-hourglass-half"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: <?php echo min(100, $dues_rate); ?>%; background: #dc2626;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span><strong><?php echo $dues_rate; ?>%</strong> Pending Collection</span>
      <span style="color: #dc2626; font-weight: 700;">Reminders Due</span>
    </div>
  </div>

  <!-- Receipts Issued -->
  <div class="stat-card purple">
    <div>
      <div class="stat-card-top">
        <div class="stat-info">
          <span class="stat-label">Receipts Issued</span>
          <span class="stat-number"><?php echo $receipts_count; ?></span>
        </div>
        <div class="stat-icon-wrapper">
          <i class="fa-solid fa-receipt"></i>
        </div>
      </div>
      <div class="stat-card-progress">
        <div class="stat-card-progress-bar" style="width: 100%;"></div>
      </div>
    </div>
    <div class="stat-card-footer">
      <span>Verified Ledger Transactions</span>
      <span style="color: #9333ea; font-weight: 700;"><i class="fa-solid fa-circle-check"></i> Audit Ready</span>
    </div>
  </div>

</div>

<!-- Modern Enhanced Filter Toolbar & Collect Fee Button (Like student.php) -->
<div class="roaster-toolbar-card" style="padding: 12px 18px;">
  <div class="roaster-toolbar-inner" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
    
    <!-- Filter form -->
    <form method="GET" action="<?php echo ADMIN_URL; ?>/fees" class="roaster-filter-form" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; flex: 1;">
      <input type="hidden" name="limit" value="<?php echo $limit; ?>">
      <?php if ($filter_student_id > 0): ?>
        <input type="hidden" name="student_id" value="<?php echo $filter_student_id; ?>">
      <?php endif; ?>

      <div class="roaster-search-box" style="min-width: 200px; max-width: 250px; padding: 7px 12px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, receipt, roll..." autocomplete="off">
        <?php if (!empty($search)): ?>
          <a href="<?php echo htmlspecialchars(get_fee_pagination_url(1, '', $filter_standard, $filter_method, $filter_student_id, $limit)); ?>" class="clear-search-btn" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>

      <div class="roaster-select-box">
        <i class="fa-solid fa-school"></i>
        <select name="standard" onchange="this.form.submit()" style="min-width: 155px; padding: 7px 28px 7px 30px; font-size: 0.82rem;">
          <option value="">All Standards</option>
          <?php foreach ($standards as $std): ?>
            <option value="<?php echo $std; ?>" <?php echo $filter_standard === $std ? 'selected' : ''; ?>>
              <?php echo $std; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron" style="right: 10px;"></i>
      </div>

      <div class="roaster-select-box">
        <i class="fa-solid fa-wallet"></i>
        <select name="method" onchange="this.form.submit()" style="min-width: 145px; padding: 7px 28px 7px 30px; font-size: 0.82rem;">
          <option value="">All Modes</option>
          <option value="Cash" <?php echo $filter_method === 'Cash' ? 'selected' : ''; ?>>Cash</option>
          <option value="UPI" <?php echo $filter_method === 'UPI' ? 'selected' : ''; ?>>UPI / QR</option>
          <option value="Net Banking" <?php echo $filter_method === 'Net Banking' ? 'selected' : ''; ?>>Net Banking</option>
          <option value="Cheque" <?php echo $filter_method === 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
          <option value="Credit Card" <?php echo $filter_method === 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
        </select>
        <i class="fa-solid fa-chevron-down select-chevron" style="right: 10px;"></i>
      </div>

      <button type="submit" class="roaster-btn secondary" style="padding: 7px 14px; font-size: 0.82rem;">
        <i class="fa-solid fa-sliders"></i> Filter
      </button>

      <?php if (!empty($search) || !empty($filter_standard) || !empty($filter_method) || $filter_student_id > 0): ?>
        <a href="<?php echo ADMIN_URL; ?>/fees<?php echo $limit !== 10 ? '?limit=' . $limit : ''; ?>" class="roaster-btn ghost" style="padding: 7px 12px; font-size: 0.82rem;" title="Reset all filters">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </a>
      <?php endif; ?>
    </form>

    <!-- Right: Receipts Live Count & Collect Fee Button -->
    <div class="roaster-toolbar-actions" style="display: flex; align-items: center; gap: 10px; margin-left: auto;">
      <div class="scholars-count-badge" style="padding: 6px 12px; font-size: 0.8rem;">
        <span class="count-dot"></span>
        <span><strong><?php echo $total_records; ?></strong> Receipts</span>
      </div>

      <?php if ($current_user['role'] === 'super_admin'): ?>
        <button type="button" class="roaster-add-btn" onclick="openCollectModal()" style="padding: 7px 16px; font-size: 0.82rem;">
          <div class="btn-icon-circle" style="width: 22px; height: 22px; font-size: 0.72rem;"><i class="fa-solid fa-plus"></i></div>
          <span>Collect Fee</span>
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Main Fee Payment Ledger Card (Simple & Sober - Like student-id) -->
<div class="hrms-card" style="margin-bottom: 24px; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden;">

  <!-- Card Header -->
  <div class="hrms-card-header no-print" style="border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px 20px;">
    <div>
      <h3 class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: #0f172a; font-family: 'Outfit', sans-serif;">
        <i class="fa-solid fa-file-invoice-dollar" style="color: #0284c7;"></i>
        Fee Payment Ledger &amp; Receipts
        <span style="font-size: 0.74rem; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;">
          Session <?php echo htmlspecialchars($active_session['session_name']); ?>
        </span>
      </h3>
      <p style="margin: 3px 0 0; font-size: 0.78rem; color: #64748b;">
        Verified student fee vouchers, ledger transactions &amp; official receipt generation.
      </p>
    </div>
    <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
      <span>Total Records: <strong style="color: #0f172a;"><?php echo $total_records; ?></strong></span>
    </div>
  </div>

  <!-- Fee Transactions Table -->
  <div class="table-responsive">
    <table class="hrms-table staff-id-table student-id-table" id="feeLedgerTable">
      <thead>
        <tr>
          <th style="min-width: 120px;">Receipt No</th>
          <th style="min-width: 170px;">Student Profile</th>
          <th style="min-width: 110px;">Roll &amp; Admission</th>
          <th style="min-width: 130px;">Standard &amp; Section</th>
          <th style="min-width: 115px;">Fee Head</th>
          <th style="min-width: 115px;">Amount Paid</th>
          <th style="min-width: 110px;">Payment Mode</th>
          <th style="min-width: 100px;">Date</th>
          <th style="min-width: 135px; text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 50px 20px; color: #64748b;">
              <div style="width: 52px; height: 52px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.4rem; color: #94a3b8;">
                <i class="fa-solid fa-receipt"></i>
              </div>
              <h4 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">No Fee Transactions Recorded</h4>
              <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 14px;">
                No fee collection receipts match the selected filters in Session <strong><?php echo htmlspecialchars($active_session['session_name']); ?></strong>.
              </p>
              <a href="<?php echo ADMIN_URL; ?>/fees" class="roaster-btn secondary" style="display: inline-flex; text-decoration: none;">
                <i class="fa-solid fa-rotate-left"></i> Reset All Filters
              </a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($payments as $p): ?>
            <?php 
              $has_photo = !empty($p['photo']) && $p['photo'] !== '/assets/imgs/ksv-logo.jpg' && file_exists(__DIR__ . '/../..' . $p['photo']);
              $first_letter = strtoupper(substr($p['student_name'], 0, 1));

              // Mode icon
              $mode_icon = 'fa-money-bill-wave';
              if (stripos($p['payment_method'], 'upi') !== false) {
                $mode_icon = 'fa-qrcode';
              } elseif (stripos($p['payment_method'], 'net') !== false || stripos($p['payment_method'], 'bank') !== false) {
                $mode_icon = 'fa-building-columns';
              } elseif (stripos($p['payment_method'], 'cheque') !== false) {
                $mode_icon = 'fa-money-check';
              }
            ?>
            <tr class="ledger-row">
              <!-- 1. Receipt No -->
              <td>
                <span class="sid-receipt-pill">
                  <i class="fa-solid fa-receipt" style="font-size: 0.65rem;"></i>
                  <span><?php echo htmlspecialchars($p['receipt_no']); ?></span>
                </span>
              </td>

              <!-- 2. Student Profile -->
              <td>
                <div class="user-cell" style="display: flex; align-items: center; gap: 12px;">
                  <?php if ($has_photo): ?>
                    <img src="<?php echo htmlspecialchars($p['photo']); ?>" alt="<?php echo htmlspecialchars($p['student_name']); ?>" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0; flex-shrink: 0;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 14px; border: 1px solid #e2e8f0; display: none; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php else: ?>
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 14px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                      <?php echo $first_letter; ?>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div style="font-weight: 700; color: #0f172a; font-size: 0.88rem; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                      <?php echo htmlspecialchars($p['student_name']); ?>
                    </div>
                    <div style="font-size: 0.73rem; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 6px;">
                      <span><?php echo htmlspecialchars($p['gender'] ?? 'Scholar'); ?></span>
                      <?php if (!empty($p['blood_group'])): ?>
                        <span>&bull;</span>
                        <span class="sid-blood-badge">Blood: <?php echo htmlspecialchars($p['blood_group']); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 3. Roll & Admission -->
              <td>
                <span class="sid-roll-pill">
                  <i class="fa-solid fa-hashtag" style="font-size: 0.62rem;"></i>
                  <span><?php echo htmlspecialchars($p['roll_no']); ?></span>
                </span>
                <div style="font-size: 0.74rem; color: #64748b; margin-top: 4px;">
                  Adm: <?php echo htmlspecialchars($p['admission_no'] ?? '—'); ?>
                </div>
              </td>

              <!-- 4. Standard & Section -->
              <td>
                <span class="sid-std-pill">
                  <i class="fa-solid fa-graduation-cap" style="font-size: 0.65rem; color: #64748b;"></i>
                  <span><?php echo htmlspecialchars($p['standard']); ?></span>
                </span>
                <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 4px;">
                  Section <?php echo htmlspecialchars($p['section'] ?? 'A'); ?>
                </div>
              </td>

              <!-- 5. Fee Type -->
              <td>
                <span class="sid-feehead-pill">
                  <i class="fa-solid fa-tag" style="font-size: 0.62rem; color: #0284c7;"></i>
                  <span><?php echo htmlspecialchars($p['fee_type'] ?? 'Tuition Fee'); ?></span>
                </span>
              </td>

              <!-- 6. Amount Paid -->
              <td>
                <div style="font-weight: 700; color: #059669; font-size: 0.88rem; font-family: 'Outfit', sans-serif;">
                  <?php echo format_inr($p['amount']); ?>
                </div>
                <?php if (!empty($p['discount']) && $p['discount'] > 0): ?>
                  <div style="font-size: 0.70rem; color: #dc2626; margin-top: 1px;">
                    Disc: <?php echo format_inr($p['discount']); ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- 7. Payment Mode -->
              <td>
                <span class="sid-mode-pill <?php echo strtolower(preg_replace('/[^a-zA-Z]/', '', $p['payment_method'])); ?>">
                  <i class="fa-solid <?php echo $mode_icon; ?>" style="font-size: 0.65rem;"></i>
                  <span><?php echo htmlspecialchars($p['payment_method']); ?></span>
                </span>
              </td>

              <!-- 8. Payment Date -->
              <td>
                <span style="font-size: 0.76rem; font-weight: 600; color: #64748b; white-space: nowrap;">
                  <?php echo format_date($p['payment_date']); ?>
                </span>
              </td>

              <!-- 9. Actions (Receipt & Collect) -->
              <td style="text-align: right; white-space: nowrap;">
                <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 6px;">
                  <?php if ($current_user['role'] === 'super_admin'): ?>
                    <button type="button" onclick="openCollectModal(<?php echo (int)$p['student_id']; ?>);" class="sid-btn-action sid-btn-secondary" title="Collect next fee installment for <?php echo htmlspecialchars($p['student_name']); ?>">
                      <i class="fa-solid fa-circle-dollar-to-slot" style="color: #059669; font-size: 0.70rem;"></i>
                      <span>Collect</span>
                    </button>
                  <?php endif; ?>
                  <button type="button" onclick='openReceiptModal(<?php echo json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);' class="sid-btn-action sid-btn-primary" title="View &amp; Print Official Receipt">
                    <i class="fa-solid fa-receipt" style="font-size: 0.70rem;"></i>
                    <span>Receipt</span>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Table Pagination & Per-Page Controls -->
  <div class="pagination-controls-wrap">
    
    <!-- Left: Showing summary & Limit Selector -->
    <div class="pagination-meta-group">
      <span style="font-size: 0.82rem; color: #475569;">
        <?php if ($total_records > 0): ?>
          Showing <strong><?php echo $start_record; ?></strong>–<strong><?php echo $end_record; ?></strong> of <strong><?php echo $total_records; ?></strong> receipts
        <?php else: ?>
          Showing <strong>0</strong> receipts
        <?php endif; ?>
      </span>

      <div class="pagination-limit-box">
        <label for="perPageLimitSelect" style="color: #64748b; font-size: 0.8rem; margin: 0; font-weight: 500;">Show Per Page</label>
        <select id="perPageLimitSelect" class="pagination-limit-select" onchange="window.location.href = this.value;">
          <?php foreach ($allowed_limits as $l): ?>
            <option value="<?php echo htmlspecialchars(get_fee_pagination_url(1, $search, $filter_standard, $filter_method, $filter_student_id, $l)); ?>" <?php echo $limit === $l ? 'selected' : ''; ?>>
              <?php echo $l; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Center/Right: Navigation page buttons -->
    <?php if ($total_pages > 1): ?>
      <nav class="pagination-nav" aria-label="Fee Ledger Pagination">
        <!-- Prev Page -->
        <a href="<?php echo $page > 1 ? htmlspecialchars(get_fee_pagination_url($page - 1, $search, $filter_standard, $filter_method, $filter_student_id, $limit)) : '#'; ?>" 
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
          <a href="<?php echo htmlspecialchars(get_fee_pagination_url($p, $search, $filter_standard, $filter_method, $filter_student_id, $limit)); ?>" 
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
        <a href="<?php echo $page < $total_pages ? htmlspecialchars(get_fee_pagination_url($page + 1, $search, $filter_standard, $filter_method, $filter_student_id, $limit)) : '#'; ?>" 
           class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
           aria-label="Next Page"
           title="Next Page">
          <i class="fa-solid fa-chevron-right" style="font-size: 0.72rem;"></i>
        </a>
      </nav>
    <?php endif; ?>

    <!-- Ledger Footer Summary & Session Info -->
    <div style="font-size: 0.78rem; color: #64748b; display: flex; align-items: center; gap: 12px; margin-left: auto;">
      <span>Total Collected: <strong style="color: #059669; font-family: 'Outfit', sans-serif;"><?php echo format_inr($total_collected); ?></strong></span>
      <span style="color: #cbd5e1;">|</span>
      <span>Session: <strong style="color: #334155;"><?php echo htmlspecialchars($active_session['session_name']); ?></strong></span>
    </div>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: MOSQUE-INSPIRED OFFICIAL RECEIPT (VIEW, PRINT, DOWNLOAD, SHARE)
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="receiptPrintModal">
  <div class="hrms-modal-card receipt-modal-card" style="max-width: 520px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.4);">
    
    <!-- Modal Header (no-print) -->
    <div class="hrms-modal-header no-print" style="padding: 14px 20px; background: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(0, 146, 223, 0.1); color: var(--hrms-primary); display: flex; align-items: center; justify-content: center; font-size: 1.05rem;">
          <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
        <div>
          <h4 style="margin: 0; font-size: 0.98rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
            Official Fee Receipt
          </h4>
          <span id="modalReceiptSub" style="font-size: 0.72rem; color: #64748b;">Verified School Voucher</span>
        </div>
      </div>

      <button type="button" class="modal-close-btn" onclick="closeReceiptModal();" aria-label="Close modal" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Body (Receipt display container with visible scrollbar) -->
    <div class="hrms-modal-body" id="receiptModalBody">
      <div id="receipt_print_area" style="display: flex; justify-content: center; width: 100%; padding: 14px 10px 20px;">
        <!-- Built dynamically via buildPaymentReceipt(payment) -->
      </div>
    </div>

    <!-- Hidden container used for off-screen clean html2canvas rendering -->
    <div id="receiptRenderContainer" style="position: fixed; left: -9999px; top: 0; width: 480px;"></div>

    <!-- Modal Footer Action Buttons (Print, Download, Share) -->
    <div class="no-print" style="padding: 12px 18px; background: #ffffff; border-top: 1px solid #e2e8f0; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
      
      <!-- Print Button -->
      <button type="button" onclick="printReceipt();" class="btn-hrms btn-hrms-outline btn-hrms-sm" style="font-weight: 700; gap: 6px; justify-content: center; padding: 9px 12px; font-size: 0.82rem; border-color: #cbd5e1;" title="Print on printer or save as PDF">
        <i class="fa-solid fa-print"></i>
        <span>Print</span>
      </button>

      <!-- Download Button -->
      <button type="button" id="btn_download_receipt" onclick="downloadReceiptPNG();" class="roaster-add-btn" style="justify-content: center; padding: 9px 12px; font-size: 0.82rem; gap: 6px; box-shadow: 0 4px 12px rgba(0, 146, 223, 0.25);" title="Download as high-resolution PNG image">
        <i class="fa-solid fa-file-arrow-down"></i>
        <span>Download</span>
      </button>

      <!-- Share Button -->
      <button type="button" id="btn_share_receipt" onclick="shareReceipt();" class="btn-hrms btn-hrms-sm" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; border: none; font-weight: 700; gap: 6px; justify-content: center; padding: 9px 12px; font-size: 0.82rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);" title="Share via WhatsApp or Device Share">
        <i class="fa-solid fa-share-nodes"></i>
        <span>Share</span>
      </button>

    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: COLLECT FEE PAYMENT FORM
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="collectFeeModal">
  <div class="hrms-modal-card" style="max-width: 540px; border-radius: 18px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);">
    
    <!-- Modal Header -->
    <div class="hrms-modal-header" style="border-bottom: 1px solid #e2e8f0; padding: 18px 24px; background: #ffffff; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(0, 146, 223, 0.1); color: var(--hrms-primary); display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
          <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
            Record Fee Collection
          </h3>
          <p style="margin: 2px 0 0; font-size: 0.76rem; color: #64748b;">Issue verified payment receipt</p>
        </div>
      </div>

      <button type="button" class="modal-close-btn" onclick="closeCollectModal();" aria-label="Close modal" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Body -->
    <div class="hrms-modal-body" style="padding: 22px;">
      <form method="POST" action="<?php echo ADMIN_URL; ?>/fees" onsubmit="return validateCollectFeeForm();">
        <input type="hidden" name="action" value="collect_fee">

        <!-- Student Selector with Due Indicator -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
            Select Scholar / Student <span style="color: #dc2626;">*</span>
          </label>
          <select name="student_id" id="collectStudentSelect" class="hrms-input" required style="width: 100%; padding: 9px 12px; font-size: 0.85rem;" onchange="updateStudentDueDisplay(this)">
            <option value="">-- Choose Enrolled Student --</option>
            <?php foreach ($students_list as $s): ?>
              <?php $due = max(0, $s['total_fees'] - $s['paid_fees']); ?>
              <option value="<?php echo $s['id']; ?>" data-due="<?php echo $due; ?>" data-total="<?php echo $s['total_fees']; ?>" data-paid="<?php echo $s['paid_fees']; ?>">
                <?php echo htmlspecialchars($s['student_name']); ?> (Roll: <?php echo htmlspecialchars($s['roll_no']); ?> &bull; Class: <?php echo htmlspecialchars($s['standard']); ?> &bull; Due: ₹<?php echo number_format($due); ?>)
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Dynamic Due Banner -->
          <div id="studentDueBanner" style="display: none; margin-top: 8px; padding: 8px 12px; border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; font-size: 0.78rem; color: #991b1b; display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fa-solid fa-clock-rotate-left"></i> Current Outstanding Due:</span>
            <strong id="bannerDueAmount" style="font-size: 0.9rem; font-family: 'Outfit', sans-serif;">₹0.00</strong>
          </div>
        </div>

        <!-- Fee Type & Amount Paid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
          <div class="form-group">
            <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
              Fee Head / Category <span style="color: #dc2626;">*</span>
            </label>
            <select name="fee_type" class="hrms-input" style="width: 100%; padding: 9px 12px; font-size: 0.85rem;">
              <option value="Tuition Fee">Tuition Fee</option>
              <option value="Admission Fee">Admission Fee</option>
              <option value="Examination Fee">Examination Fee</option>
              <option value="Laboratory Charges">Laboratory Charges</option>
              <option value="Library Fee">Library Fee</option>
              <option value="Transport Fee">Transport Fee</option>
              <option value="Annual Charges">Annual Charges</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
              Amount Paid (₹) <span style="color: #dc2626;">*</span>
            </label>
            <div style="position: relative;">
              <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 800; color: #059669;">₹</span>
              <input type="number" step="1" name="amount" id="collectAmountInput" placeholder="0" class="hrms-input" required style="width: 100%; padding: 9px 12px 9px 28px; font-weight: 800; font-size: 1rem; color: #059669;">
            </div>
          </div>
        </div>

        <!-- Payment Mode & Payment Date -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
          <div class="form-group">
            <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
              Payment Mode <span style="color: #dc2626;">*</span>
            </label>
            <select name="payment_method" class="hrms-input" style="width: 100%; padding: 9px 12px; font-size: 0.85rem;">
              <option value="Cash">Cash</option>
              <option value="UPI">UPI / PhonePe / GPay</option>
              <option value="Net Banking">Net Banking / NEFT</option>
              <option value="Cheque">Cheque</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
              Payment Date <span style="color: #dc2626;">*</span>
            </label>
            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" class="hrms-input" required style="width: 100%; padding: 9px 12px; font-size: 0.85rem;">
          </div>
        </div>

        <!-- Remarks / Ref ID -->
        <div class="form-group" style="margin-bottom: 22px;">
          <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
            Transaction Reference / Remarks
          </label>
          <input type="text" name="remarks" placeholder="e.g. UPI Ref #4928192019 or Cheque #91823" class="hrms-input" style="width: 100%; padding: 9px 12px; font-size: 0.85rem;">
        </div>

        <!-- Modal Submit & Cancel Buttons -->
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" onclick="closeCollectModal();" class="btn-hrms btn-hrms-outline" style="padding: 9px 18px; font-weight: 600;">
            Cancel
          </button>
          <button type="submit" class="roaster-add-btn" style="padding: 9px 22px; font-size: 0.85rem; gap: 8px;">
            <i class="fa-solid fa-check"></i>
            <span>Record &amp; Issue Receipt</span>
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<script>
// ── State for Active Payment in Modal ──
let currentActivePayment = null;

// ── Number to Indian Currency Words (e.g. 10000 -> Ten Thousand Rupees Only) ──
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

// ── Build Mosque-Style Official Receipt Card ──
function buildPaymentReceipt(record) {
  const paymentsList = (record.all_payments && Array.isArray(record.all_payments) && record.all_payments.length > 0)
    ? record.all_payments
    : [record];

  let totalReceived = 0;
  paymentsList.forEach(p => {
    totalReceived += parseFloat(p.amount) || 0;
  });

  const totalCourseFee = parseFloat(record.total_fees || 0);
  const remainingDue = Math.max(0, totalCourseFee - totalReceived);

  const receiptNo = record.receipt_no || ('RCPT-' + String(record.id).padStart(5, '0'));

  let payDateFmt = '--';
  if (record.payment_date) {
    const d = new Date(record.payment_date + 'T12:00:00');
    payDateFmt = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'long', year: 'numeric' });
  }

  const totalReceivedFmt = '₹' + totalReceived.toLocaleString('en-IN', { minimumFractionDigits: 2 }) + '/-';
  const amountWords = numberToIndianWords(totalReceived);

  const esc = (str) => {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  // Verification QR string
  const qrData = "VERIFIED-INSTITUTE-FEE-RECEIPT:" + receiptNo + "|STUDENT:" + (record.student_name || '') + "|TOTAL_AMT:" + totalReceived + "|PAYMENTS:" + paymentsList.length;

  const schoolName = <?php echo json_encode($school_settings['school_name'] ?? 'KSV Academy'); ?>;
  const schoolAddress = <?php echo json_encode(($school_settings['address'] ?? 'KSV Campus, Near City Center') . ' • Contact: ' . ($school_settings['phone'] ?? '+91 98765 43210')); ?>;
  const sessionName = <?php echo json_encode($active_session['session_name'] ?? date('Y')); ?>;
  const schoolLogo = <?php echo json_encode($school_settings['logo_url'] ?? '/assets/imgs/ksv-logo.jpg'); ?>;

  return `
    <div class="pay-receipt" id="renderedReceiptCard">
      <div class="ri-top-accent"></div>
      <div class="receipt-body">
        
        <!-- Academy Institutional Header -->
        <div class="ri-brand-row">
          <div class="ri-logo-box">
            <img src="${schoolLogo}" alt="Logo" onerror="this.src='/assets/imgs/ksv-logo.jpg'">
          </div>
          <div class="ri-brand-info">
            <h2 class="ri-title">${esc(schoolName)}</h2>
            <div class="ri-sub">Coaching Institute &amp; Academic Center</div>
            <div class="ri-address">${esc(schoolAddress)}</div>
          </div>
        </div>

        <!-- Document Type & Session Banner -->
        <div class="receipt-doc-band">
          <span class="receipt-doc-title"><i class="fa-solid fa-receipt" style="color: #0284c7; margin-right: 5px;"></i> FEE PAYMENT RECEIPT</span>
          <span class="receipt-doc-session">Session: ${esc(sessionName)}</span>
        </div>

        <!-- Student & Voucher Meta Grid -->
        <div class="receipt-meta-grid">
          <div class="rmg-cell">
            <span class="rmg-label">Receipt No</span>
            <span class="rmg-val mono-badge">${esc(receiptNo)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Payment Date</span>
            <span class="rmg-val">${esc(payDateFmt)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Student Name</span>
            <span class="rmg-val bold">${esc(record.student_name)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Roll No / ID</span>
            <span class="rmg-val mono-badge" style="color: #0f172a;">${esc(record.roll_no)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Class / Course</span>
            <span class="rmg-val">${esc(record.standard)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">${paymentsList.length > 1 ? 'Installments' : 'Payment Mode'}</span>
            <span class="rmg-val" style="color: #0284c7; font-weight: 700;">
              ${paymentsList.length > 1 ? `${paymentsList.length} Payments Recorded` : esc(record.payment_method || 'Cash')}
            </span>
          </div>
        </div>

        <!-- Particulars / Breakdown Table -->
        <div class="receipt-table-wrapper">
          <table class="receipt-table">
            <thead>
              <tr>
                <th style="width: 24px; text-align: center;">#</th>
                <th>Fee Head / Particulars</th>
                <th style="text-align: right; width: 105px;">Amount (₹)</th>
              </tr>
            </thead>
            <tbody>
              ${paymentsList.map((item, idx) => {
                const itemAmt = parseFloat(item.amount) || 0;
                let itemDate = '--';
                if (item.payment_date) {
                  const d = new Date(item.payment_date + 'T12:00:00');
                  itemDate = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
                }
                const isSelected = (item.id === record.id || item.receipt_no === record.receipt_no);
                const itemRemarks = item.remarks ? item.remarks.trim() : '';

                return `
                  <tr style="${isSelected && paymentsList.length > 1 ? 'background: #f0f9ff;' : ''}">
                    <td style="text-align: center; color: #64748b; font-weight: 700; font-size: 10px; vertical-align: top; padding-top: 7px;">
                      ${idx + 1}
                    </td>
                    <td style="padding: 6px 8px;">
                      <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                        <span style="font-weight: 800; color: #0f172a; font-size: 10.5px;">
                          ${esc(item.fee_type || 'Tuition Fee')}
                          ${paymentsList.length > 1 ? `<span style="font-size: 8.5px; color: #0284c7; font-weight: 700; margin-left: 3px;">(Installment ${idx + 1})</span>` : ''}
                        </span>
                        <span style="font-size: 8.5px; background: #e0f2fe; color: #0369a1; padding: 1px 5px; border-radius: 4px; font-weight: 700; font-family: monospace;">
                          ${esc(item.receipt_no)}
                        </span>
                      </div>
                      <div style="font-size: 9px; color: #64748b; margin-top: 2px;">
                        <span><i class="fa-regular fa-calendar" style="font-size: 8px;"></i> ${esc(itemDate)}</span>
                        &bull; <span><i class="fa-solid fa-wallet" style="font-size: 8px;"></i> ${esc(item.payment_method || 'Cash')}</span>
                        ${isSelected && paymentsList.length > 1 ? '<span style="color: #0284c7; font-weight: 800; margin-left: 3px;">(Selected)</span>' : ''}
                      </div>
                      ${itemRemarks ? `<div style="font-size: 8.5px; color: #0284c7; margin-top: 2px; font-style: italic;">Note: ${esc(itemRemarks)}</div>` : ''}
                    </td>
                    <td style="text-align: right; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; font-size: 11px; vertical-align: top; padding-top: 7px; white-space: nowrap;">
                      ₹${itemAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 })}/-
                    </td>
                  </tr>
                `;
              }).join('')}

              <tr class="total-row">
                <td colspan="2" style="text-align: right; font-weight: 800; font-size: 10.5px; color: #0f172a; text-transform: uppercase;">
                  Total Amount Received
                </td>
                <td style="text-align: right; font-size: 13px; font-weight: 900; color: #059669; font-family: 'Outfit', sans-serif;">
                  ${totalReceivedFmt}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Course Fee & Due Balance Strip -->
        ${totalCourseFee > 0 ? `
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 7px 10px; font-size: 9.5px;">
            <div>
              <span style="color: #64748b; display: block; font-size: 8.5px; text-transform: uppercase; font-weight: 700;">Total Course Fee</span>
              <strong style="color: #0f172a; font-family: 'Outfit', sans-serif; font-size: 11px;">₹${totalCourseFee.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong>
            </div>
            <div style="text-align: right;">
              <span style="color: #64748b; display: block; font-size: 8.5px; text-transform: uppercase; font-weight: 700;">Outstanding Balance Due</span>
              <strong style="color: ${remainingDue > 0 ? '#dc2626' : '#059669'}; font-family: 'Outfit', sans-serif; font-size: 11px;">
                ${remainingDue > 0 ? `₹${remainingDue.toLocaleString('en-IN', { minimumFractionDigits: 2 })}` : 'Fully Paid ✓'}
              </strong>
            </div>
          </div>
        ` : ''}

        <!-- Amount In Words Box -->
        <div class="receipt-words-box">
          <span class="words-label">Total Amount in Words:</span>
          <span class="words-val">${amountWords}</span>
        </div>

        <!-- Signatures & Official Institute Stamp -->
        <div class="receipt-auth-row">
          <div class="auth-sign-box">
            <div class="auth-sign-line">${esc(record.student_name)}</div>
            <div class="auth-sign-label">Student / Depositor</div>
          </div>

          <div class="auth-seal-badge">
            <div class="seal-text-top">KSV ACADEMY</div>
            <div class="seal-text-mid">★ VERIFIED ★</div>
            <div class="seal-text-bot">ACCOUNTS DEPT</div>
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
            <div>• Computer-generated fee acknowledgement voucher issued by KSV Academy.</div>
            <div>• Fees deposited are non-refundable and non-transferable under any condition.</div>
            <div>• Please preserve this receipt for academic clearance and hall ticket.</div>
          </div>
        </div>

      </div>
    </div>`;
}

// ── Open Receipt Modal ──
function openReceiptModal(payment) {
  currentActivePayment = payment;
  const printArea = document.getElementById('receipt_print_area');
  printArea.innerHTML = buildPaymentReceipt(payment);

  const sub = document.getElementById('modalReceiptSub');
  if (sub) {
    const pCount = (payment.all_payments && payment.all_payments.length) ? payment.all_payments.length : 1;
    sub.innerText = payment.student_name + ' • ' + (pCount > 1 ? `${pCount} Payments Recorded` : payment.receipt_no);
  }

  const modal = document.getElementById('receiptPrintModal');
  if (modal) modal.classList.add('show');

  // Reset scroll position to top
  const body = document.getElementById('receiptModalBody');
  if (body) {
    body.scrollTop = 0;
  }
}

// ── Close Receipt Modal ──
function closeReceiptModal() {
  const modal = document.getElementById('receiptPrintModal');
  if (modal) modal.classList.remove('show');
}

// ── Print Action (Isolated Clean Print) ──
function printReceipt() {
  if (!currentActivePayment) return;
  const p = currentActivePayment;
  const printArea = document.getElementById('receipt_print_area');
  if (!printArea) return;

  const receiptContent = printArea.innerHTML;

  // Create or reuse hidden printing iframe for 100% reliable print isolation
  let printIframe = document.getElementById('ksvReceiptPrintFrame');
  if (!printIframe) {
    printIframe = document.createElement('iframe');
    printIframe.id = 'ksvReceiptPrintFrame';
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

  // Dedicated, pristine styles for receipt print (no visibility:hidden or layout clashes)
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
      <title>Fee Receipt - ${p.student_name || 'KSV Academy'} - ${p.receipt_no || ''}</title>
      ${externalLinks}
      <style>
        ${payReceiptStyles}
      </style>
    </head>
    <body>
      ${receiptContent}
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
function downloadReceiptPNG() {
  if (!currentActivePayment) return;
  const p = currentActivePayment;
  const btn = document.getElementById('btn_download_receipt');
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Rendering...';

  const renderContainer = document.getElementById('receiptRenderContainer');
  renderContainer.innerHTML = buildPaymentReceipt(p);
  const cardEl = renderContainer.firstElementChild;
  const filename = `Fee_Receipt-${(p.student_name || 'Student').replace(/\s+/g, '_')}-${p.receipt_no}.png`;

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
      alert('Unable to generate PNG receipt. You can use the Print button to save as PDF.');
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
function shareReceipt() {
  if (!currentActivePayment) return;
  const p = currentActivePayment;
  const btn = document.getElementById('btn_share_receipt');
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sharing...';

  const paymentsList = (p.all_payments && p.all_payments.length > 0) ? p.all_payments : [p];
  const totalPaidSum = paymentsList.reduce((acc, curr) => acc + (parseFloat(curr.amount) || 0), 0);

  let shareText = `*KSV ACADEMY - OFFICIAL FEE PAYMENT RECEIPT*\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `• Student: ${p.student_name} (${p.roll_no || '-'})\n` +
    `• Class: ${p.standard || '-'}\n` +
    `• Payments Recorded: ${paymentsList.length}\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n`;

  paymentsList.forEach((pmt, idx) => {
    const pDate = pmt.payment_date ? new Date(pmt.payment_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
    shareText += `[${idx + 1}] ${pmt.fee_type || 'Tuition Fee'} (${pmt.receipt_no}): ₹${parseFloat(pmt.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })} on ${pDate} [${pmt.payment_method}]\n`;
  });

  shareText += `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `• Total Amount Received: ₹${totalPaidSum.toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n`;

  if (p.total_fee !== undefined && p.total_fee !== null && parseFloat(p.total_fee) > 0) {
    const balDue = Math.max(0, parseFloat(p.total_fee) - totalPaidSum);
    shareText += `• Total Course Fee: ₹${parseFloat(p.total_fee).toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n` +
      `• Outstanding Due: ${balDue <= 0 ? 'Fully Paid (Nil)' : '₹' + balDue.toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n`;
  }

  shareText += `• Status: Paid & Cleared\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `Verified by KSV Academy Accounts Portal`;

  const renderContainer = document.getElementById('receiptRenderContainer');
  renderContainer.innerHTML = buildPaymentReceipt(p);
  const cardEl = renderContainer.firstElementChild;
  const filename = `Fee_Receipt-${(p.student_name || 'Student').replace(/\s+/g, '_')}-${p.receipt_no}.png`;

  html2canvas(cardEl, { scale: 2, useCORS: true, backgroundColor: '#ffffff' })
    .then(canvas => {
      canvas.toBlob(blob => {
        const file = new File([blob], filename, { type: 'image/png' });

        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          navigator.share({
            title: `Fee Receipt - ${p.student_name}`,
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
          // Fallback: Copy image to clipboard, and open WhatsApp with formatted text
          try {
            const item = new ClipboardItem({ 'image/png': blob });
            navigator.clipboard.write([item]).then(() => {
              btn.disabled = false;
              btn.innerHTML = origHtml;
              renderContainer.innerHTML = '';
              if (confirm('Receipt image copied to clipboard!\n\nClick OK to open WhatsApp and paste/send.')) {
                window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`, '_blank');
              }
            }).catch(() => {
              const link = document.createElement('a');
              link.download = filename;
              link.href = canvas.toDataURL('image/png');
              link.click();
              btn.disabled = false;
              btn.innerHTML = origHtml;
              renderContainer.innerHTML = '';
              window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`, '_blank');
            });
          } catch (e) {
            const link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL('image/png');
            link.click();
            btn.disabled = false;
            btn.innerHTML = origHtml;
            renderContainer.innerHTML = '';
            window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`, '_blank');
          }
        }
      }, 'image/png');
    })
    .catch(err => {
      console.error(err);
      btn.disabled = false;
      btn.innerHTML = origHtml;
      renderContainer.innerHTML = '';
      window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`, '_blank');
    });
}

// ── Collect Fee Modal Controls ──
function openCollectModal(preselectStudentId) {
  const modal = document.getElementById('collectFeeModal');
  if (modal) modal.classList.add('show');
  if (preselectStudentId) {
    const sel = document.getElementById('collectStudentSelect');
    if (sel) {
      sel.value = preselectStudentId;
      updateStudentDueDisplay(sel);
    }
  }
}

function closeCollectModal() {
  const modal = document.getElementById('collectFeeModal');
  if (modal) modal.classList.remove('show');
}

function updateStudentDueDisplay(select) {
  const opt = select.options[select.selectedIndex];
  const due = parseFloat(opt.getAttribute('data-due') || 0);
  const banner = document.getElementById('studentDueBanner');
  const amountInput = document.getElementById('collectAmountInput');

  if (opt.value && due > 0) {
    banner.style.display = 'flex';
    document.getElementById('bannerDueAmount').innerText = '₹' + due.toLocaleString('en-IN');
    if (!amountInput.value || parseFloat(amountInput.value) <= 0) {
      amountInput.value = due;
    }
  } else if (opt.value) {
    banner.style.display = 'flex';
    banner.style.background = '#ecfdf5';
    banner.style.borderColor = '#a7f3d0';
    banner.style.color = '#065f46';
    document.getElementById('bannerDueAmount').innerText = '₹0.00 (Cleared)';
  } else {
    banner.style.display = 'none';
  }
}

function validateCollectFeeForm() {
  const amt = parseFloat(document.getElementById('collectAmountInput').value || 0);
  if (amt <= 0) {
    alert('Please enter a valid positive payment amount.');
    return false;
  }
  return true;
}

// ── Instant Table Search ──
function filterLedgerTable() {
  const q = document.getElementById('ledgerSearchInput').value.toLowerCase().trim();
  const rows = document.querySelectorAll('#feeLedgerTable tbody tr.ledger-row');

  rows.forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = (!q || text.includes(q)) ? '' : 'none';
  });
}

// ── Close on Escape or Backdrop Click ──
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeReceiptModal();
    closeCollectModal();
  }
});

document.addEventListener('DOMContentLoaded', function() {
  ['receiptPrintModal', 'collectFeeModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('click', function(e) {
        if (e.target === this) {
          el.classList.remove('show');
        }
      });
    }
  });
});
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
