<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
$page_title = 'Student Marksheet & Report Card Generator';
$page_heading = 'Student Academic Marksheet Generator';
$page_subheading = 'Official Statement of Marks, Examination Evaluations & Printable Report Cards';
$active_menu = 'marksheet';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Selected student & exam
$selected_student_id = intval($_GET['student_id'] ?? 0);
$selected_marksheet_id = intval($_GET['marksheet_id'] ?? 0);

// If logged in as student or parent, restrict to their student
if ($current_user['role'] === 'student' || $current_user['role'] === 'parent') {
    $my_stu = $db->query("SELECT id FROM students WHERE parent_user_id = {$current_user['id']} LIMIT 1")->fetch();
    if ($my_stu) {
        $selected_student_id = $my_stu['id'];
    }
}

// Fetch all students for selector dropdown
$stus = $db->prepare("SELECT id, student_name, roll_no, standard FROM students WHERE session_id = ? ORDER BY standard ASC, student_name ASC");
$stus->execute([$session_id]);
$students_list = $stus->fetchAll();

if ($selected_student_id === 0 && !empty($students_list)) {
    $selected_student_id = $students_list[0]['id'];
}

// Handle Add/Generate Marksheet Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_marksheet') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $exam_name = trim($_POST['exam_name'] ?? 'Annual Examination');
        $exam_date = $_POST['exam_date'] ?? date('Y-m-d');
        $remarks = trim($_POST['remarks'] ?? 'Good performance');

        $subjects = $_POST['subjects'] ?? [];
        $obtained_list = $_POST['obtained'] ?? [];
        $max_list = $_POST['max_marks'] ?? [];

        $subjects_data = [];
        $total_obtained = 0;
        $total_max = 0;

        for ($i = 0; $i < count($subjects); $i++) {
            $sub_name = trim($subjects[$i]);
            $obt = floatval($obtained_list[$i] ?? 0);
            $max_m = floatval($max_list[$i] ?? 100);
            $pass_m = 35;

            $pct_sub = $max_m > 0 ? ($obt / $max_m) * 100 : 0;
            if ($pct_sub >= 90) $sub_grade = 'A+';
            elseif ($pct_sub >= 80) $sub_grade = 'A';
            elseif ($pct_sub >= 70) $sub_grade = 'B+';
            elseif ($pct_sub >= 60) $sub_grade = 'B';
            elseif ($pct_sub >= 50) $sub_grade = 'C';
            elseif ($pct_sub >= 35) $sub_grade = 'D';
            else $sub_grade = 'F';

            $subjects_data[] = [
                'name' => $sub_name,
                'max' => $max_m,
                'pass' => $pass_m,
                'obtained' => $obt,
                'grade' => $sub_grade
            ];

            $total_obtained += $obt;
            $total_max += $max_m;
        }

        $overall_percentage = $total_max > 0 ? round(($total_obtained / $total_max) * 100, 2) : 0;
        
        if ($overall_percentage >= 90) $overall_grade = 'A+';
        elseif ($overall_percentage >= 80) $overall_grade = 'A';
        elseif ($overall_percentage >= 70) $overall_grade = 'B+';
        elseif ($overall_percentage >= 60) $overall_grade = 'B';
        elseif ($overall_percentage >= 50) $overall_grade = 'C';
        elseif ($overall_percentage >= 35) $overall_grade = 'D';
        else $overall_grade = 'F';

        $result_status = $overall_percentage >= 35 ? 'PASS' : 'FAIL';

        $stmt = $db->prepare("
            INSERT INTO marksheets (session_id, student_id, exam_name, exam_date, subjects_json, total_obtained, total_max, percentage, overall_grade, result_status, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $session_id, $student_id, $exam_name, $exam_date, json_encode($subjects_data),
            $total_obtained, $total_max, $overall_percentage, $overall_grade, $result_status, $remarks
        ]);
        $new_ms_id = $db->lastInsertId();

        set_flash('success', "Marksheet generated successfully for student #{$student_id}!");
        header("Location: " . ADMIN_URL . "/marksheet?student_id={$student_id}&marksheet_id={$new_ms_id}");
        exit;
    }
}

// Fetch marksheets for this student
$marksheets_stmt = $db->prepare("SELECT * FROM marksheets WHERE student_id = ? AND session_id = ? ORDER BY id DESC");
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

// Fetch student profile
$student_profile = null;
if ($selected_student_id > 0) {
    $st_stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $st_stmt->execute([$selected_student_id]);
    $student_profile = $st_stmt->fetch();
}

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* ── Strict A4 Single-Page Print Setup ── */
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
  .modal-backdrop,
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
}

.report-card-container {
  max-width: 820px;
  margin: 0 auto;
  background: #ffffff;
  border-radius: 12px;
  border: 4px double #0092df;
  padding: 24px 34px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.08);
  position: relative;
}

/* Tier Overrides for Parent/Student Portal */
.report-card-container.tier-few { padding: 32px 40px; }
.report-card-container.tier-medium { padding: 22px 32px; }
.report-card-container.tier-compact { padding: 18px 26px; }
.report-card-container.tier-dense { padding: 14px 20px; }

@media print {
  .report-card-container.tier-few { padding: 22px 28px !important; }
  .report-card-container.tier-medium { padding: 14px 22px !important; }
  .report-card-container.tier-compact { padding: 10px 18px !important; }
  .report-card-container.tier-dense { padding: 8px 14px !important; }

  .report-card-container.tier-medium .report-table th,
  .report-card-container.tier-medium .report-table td {
    padding: 6px 10px !important;
    font-size: 11px !important;
  }
  .report-card-container.tier-compact .report-table th,
  .report-card-container.tier-compact .report-table td {
    padding: 4.5px 8px !important;
    font-size: 10px !important;
  }
  .report-card-container.tier-dense .report-table th,
  .report-card-container.tier-dense .report-table td {
    padding: 3.5px 6px !important;
    font-size: 9.5px !important;
  }
}
  box-shadow: 0 10px 30px rgba(0,0,0,0.08);
  position: relative;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  margin: 20px 0;
}

.report-table th, .report-table td {
  border: 1px solid #cbd5e1;
  padding: 10px 14px;
  font-size: 13px;
}

.report-table th {
  background: #f1f5f9;
  color: #1e293b;
  font-weight: 700;
  text-align: center;
}
</style>

<!-- Top Toolbar (Student Selector & Actions) -->
<div class="hrms-card no-print" style="margin-bottom: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    
    <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
      <?php if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff'): ?>
        <form method="GET" action="<?php echo ADMIN_URL; ?>/marksheet" style="display: flex; align-items: center; gap: 8px;">
          <label style="font-size: 13px; font-weight: 700; color: var(--neutral-700);">Student:</label>
          <select name="student_id" onchange="this.form.submit()" class="hrms-input" style="min-width: 260px; padding: 8px 12px; border-radius: 6px;">
            <?php foreach ($students_list as $s_item): ?>
              <option value="<?php echo $s_item['id']; ?>" <?php echo $s_item['id'] == $selected_student_id ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($s_item['student_name']); ?> (<?php echo htmlspecialchars($s_item['roll_no']); ?> &bull; <?php echo htmlspecialchars($s_item['standard']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>

      <?php if (!empty($student_marksheets)): ?>
        <form method="GET" action="<?php echo ADMIN_URL; ?>/marksheet" style="display: flex; align-items: center; gap: 8px;">
          <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
          <label style="font-size: 13px; font-weight: 700; color: var(--neutral-700);">Exam Marksheet:</label>
          <select name="marksheet_id" onchange="this.form.submit()" class="hrms-input" style="padding: 8px 12px; border-radius: 6px;">
            <?php foreach ($student_marksheets as $m_item): ?>
              <option value="<?php echo $m_item['id']; ?>" <?php echo ($active_marksheet && $m_item['id'] == $active_marksheet['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($m_item['exam_name']); ?> (<?php echo $m_item['percentage']; ?>% - <?php echo $m_item['overall_grade']; ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
    </div>

    <div style="display: flex; gap: 10px;">
      <?php if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff'): ?>
        <button type="button" onclick="document.getElementById('createMarksheetModal').style.display='flex';" class="action-btn" style="background: rgba(16, 185, 129, 0.12); color: #047857; border: 1px solid rgba(16, 185, 129, 0.3); padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;">
          <i class="fa-solid fa-plus-circle"></i>
          <span>Create New Marksheet</span>
        </button>
      <?php endif; ?>

      <?php if ($active_marksheet): ?>
        <button type="button" onclick="window.print();" class="action-primary-btn" style="background: #0092df; color: #ffffff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0, 146, 223, 0.25);">
          <i class="fa-solid fa-print"></i>
          <span>Print Marksheet</span>
        </button>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php if ($active_marksheet && $student_profile): ?>
  <?php 
    $subjects = json_decode($active_marksheet['subjects_json'], true) ?: [];
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
  <!-- OFFICIAL PRINTABLE REPORT CARD -->
  <div class="marksheet-print-canvas report-card-container <?php echo $tier_class; ?>" data-sub-count="<?php echo $sub_count; ?>">
    
    <!-- Watermark Logo -->
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0.05; pointer-events: none; z-index: 0;">
      <img src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="" style="width: 380px; height: 380px;">
    </div>

    <!-- Header Section -->
    <div style="position: relative; z-index: 1; text-align: center; border-bottom: 2px solid #0092df; padding-bottom: 16px; margin-bottom: 20px;">
      <div style="display: flex; align-items: center; justify-content: center; gap: 14px; margin-bottom: 8px;">
        <img src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="KSV Logo" style="width: 58px; height: 58px; border-radius: 50%;">
        <div>
          <h1 style="margin: 0; font-size: 26px; font-weight: 900; color: #007cbd; letter-spacing: 0.5px;">
            <?php echo htmlspecialchars($school_settings['school_name']); ?>
          </h1>
          <div style="font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase;">
            <?php echo htmlspecialchars($school_settings['tagline']); ?>
          </div>
        </div>
      </div>
      
      <div style="font-size: 11px; color: #64748b;">
        <?php echo htmlspecialchars($school_settings['address']); ?> &bull; Phone: <?php echo htmlspecialchars($school_settings['phone']); ?>
      </div>
      
      <div style="margin-top: 10px; display: inline-block; background: #0092df; color: #ffffff; padding: 4px 20px; border-radius: 20px; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">
        ACADEMIC REPORT CARD &bull; SESSION <?php echo htmlspecialchars($active_session['session_name']); ?>
      </div>
    </div>

    <!-- Student & Exam Details Box -->
    <div style="position: relative; z-index: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; font-size: 13px;">
      <div>
        <div style="margin-bottom: 6px;"><strong>Student Name:</strong> <span style="font-size: 15px; font-weight: 800; color: #0f172a;"><?php echo htmlspecialchars($student_profile['student_name']); ?></span></div>
        <div style="margin-bottom: 6px;"><strong>Roll Number:</strong> <span style="font-family: monospace; font-weight: 700; color: #007cbd;"><?php echo htmlspecialchars($student_profile['roll_no']); ?></span></div>
        <div><strong>Admission No:</strong> <?php echo htmlspecialchars($student_profile['admission_no'] ?? 'ADM-2025-081'); ?></div>
      </div>
      <div>
        <div style="margin-bottom: 6px;"><strong>Class &amp; Section:</strong> <span style="font-weight: 700;"><?php echo htmlspecialchars($student_profile['standard']); ?> - Section <?php echo htmlspecialchars($student_profile['section'] ?? 'A'); ?></span></div>
        <div style="margin-bottom: 6px;"><strong>Father's Name:</strong> <?php echo htmlspecialchars($student_profile['parent_name'] ?? '—'); ?></div>
        <div><strong>Examination:</strong> <span style="color: #007cbd; font-weight: 700;"><?php echo htmlspecialchars($active_marksheet['exam_name']); ?> (<?php echo date('M Y', strtotime($active_marksheet['exam_date'])); ?>)</span></div>
      </div>
    </div>

    <!-- Statement of Marks Table -->
    <div style="position: relative; z-index: 1;">
      <table class="report-table">
        <thead>
          <tr>
            <th style="text-align: left; width: 40%;">SUBJECT NAME</th>
            <th style="width: 15%;">MAX MARKS</th>
            <th style="width: 15%;">PASS MARKS</th>
            <th style="width: 15%;">OBTAINED</th>
            <th style="width: 15%;">GRADE</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($subjects as $sub): ?>
            <tr>
              <td style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($sub['name']); ?></td>
              <td style="text-align: center; color: #64748b;"><?php echo $sub['max']; ?></td>
              <td style="text-align: center; color: #64748b;"><?php echo $sub['pass']; ?></td>
              <td style="text-align: center; font-weight: 800; font-size: 14px; color: <?php echo $sub['obtained'] >= $sub['pass'] ? '#059669' : '#dc2626'; ?>;">
                <?php echo $sub['obtained']; ?>
              </td>
              <td style="text-align: center; font-weight: 800; color: #0284c7;"><?php echo $sub['grade']; ?></td>
            </tr>
          <?php endforeach; ?>
          <!-- Grand Total Row -->
          <tr style="background: #f8fafc; font-weight: 800; font-size: 14px;">
            <td>GRAND TOTAL</td>
            <td style="text-align: center;"><?php echo $active_marksheet['total_max']; ?></td>
            <td style="text-align: center;">—</td>
            <td style="text-align: center; color: #007cbd;"><?php echo $active_marksheet['total_obtained']; ?></td>
            <td style="text-align: center; color: #007cbd;"><?php echo $active_marksheet['overall_grade']; ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Performance Summary Card -->
    <div style="position: relative; z-index: 1; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 24px;">
      <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px; border-radius: 8px; text-align: center;">
        <div style="font-size: 11px; font-weight: 700; color: #15803d; text-transform: uppercase;">PERCENTAGE</div>
        <div style="font-size: 22px; font-weight: 900; color: #166534;"><?php echo $active_marksheet['percentage']; ?>%</div>
      </div>
      <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 12px; border-radius: 8px; text-align: center;">
        <div style="font-size: 11px; font-weight: 700; color: #1d4ed8; text-transform: uppercase;">OVERALL GRADE</div>
        <div style="font-size: 22px; font-weight: 900; color: #1e40af;"><?php echo $active_marksheet['overall_grade']; ?></div>
      </div>
      <div style="background: <?php echo $active_marksheet['result_status'] === 'PASS' ? '#f0fdf4' : '#fef2f2'; ?>; border: 1px solid <?php echo $active_marksheet['result_status'] === 'PASS' ? '#86efac' : '#fca5a5'; ?>; padding: 12px; border-radius: 8px; text-align: center;">
        <div style="font-size: 11px; font-weight: 700; color: <?php echo $active_marksheet['result_status'] === 'PASS' ? '#15803d' : '#991b1b'; ?>; text-transform: uppercase;">RESULT STATUS</div>
        <div style="font-size: 22px; font-weight: 900; color: <?php echo $active_marksheet['result_status'] === 'PASS' ? '#166534' : '#b91c1c'; ?>;"><?php echo $active_marksheet['result_status']; ?></div>
      </div>
    </div>

    <!-- Remarks -->
    <div style="position: relative; z-index: 1; margin-bottom: 40px; font-size: 13px; color: #334155; background: #f8fafc; padding: 10px 14px; border-radius: 6px; border-left: 4px solid #0092df;">
      <strong>Faculty Remarks:</strong> <?php echo htmlspecialchars($active_marksheet['remarks'] ?? 'Consistent academic effort and discipline.'); ?>
    </div>

    <!-- Signatures and Official Seals -->
    <div style="position: relative; z-index: 1; display: flex; justify-content: space-between; align-items: flex-end; padding-top: 20px; border-top: 1px solid #cbd5e1; font-size: 12px; font-weight: 700; color: #475569;">
      <div style="text-align: center; width: 140px;">
        <div style="height: 35px;"></div>
        <div style="border-top: 1px solid #94a3b8; padding-top: 4px;">Class Teacher</div>
      </div>
      
      <div style="text-align: center; width: 140px;">
        <div style="font-size: 11px; color: #94a3b8; margin-bottom: 2px;">OFFICIAL SEAL</div>
        <div style="width: 50px; height: 50px; border: 2px dashed #0092df; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 9px; color: #0092df; font-weight: 800;">
          KSV
        </div>
      </div>

      <div style="text-align: center; width: 140px;">
        <div style="font-family: 'Outfit', cursive; font-size: 14px; color: #007cbd; height: 25px;">Salam Khan</div>
        <div style="border-top: 1px solid #94a3b8; padding-top: 4px;">Principal Signature</div>
      </div>
    </div>

  </div>
<?php else: ?>
  <div class="hrms-card" style="text-align: center; padding: 40px;">
    <p>No marksheet found for this student in session <?php echo htmlspecialchars($active_session['session_name']); ?>.</p>
    <?php if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff'): ?>
      <button type="button" onclick="document.getElementById('createMarksheetModal').style.display='flex';" class="action-primary-btn" style="background: #0092df; color: #fff; padding: 10px 20px; border-radius: 6px; font-weight: 700; border: none; cursor: pointer; margin-top: 10px;">
        <i class="fa-solid fa-plus"></i> Generate Marksheet
      </button>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- MODAL: Create New Marksheet Form -->
<div id="createMarksheetModal" class="hrms-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 20px;">
  <div class="hrms-modal-content" style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 680px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
      <h3 style="margin: 0; font-size: 19px; font-weight: 800; color: var(--neutral-800);">
        <i class="fa-solid fa-file-circle-plus" style="color: var(--primary-600);"></i> Generate Student Marksheet
      </h3>
      <button type="button" onclick="document.getElementById('createMarksheetModal').style.display='none';" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748b;">&times;</button>
    </div>

    <form method="POST" action="<?php echo ADMIN_URL; ?>/marksheet">
      <input type="hidden" name="action" value="create_marksheet">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
        <div class="form-group">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px;">Student *</label>
          <select name="student_id" class="hrms-input" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
            <?php foreach ($students_list as $s): ?>
              <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $selected_student_id ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($s['student_name']); ?> (<?php echo htmlspecialchars($s['roll_no']); ?> &bull; <?php echo htmlspecialchars($s['standard']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px;">Examination Name *</label>
          <select name="exam_name" class="hrms-input" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
            <option value="Unit Test 1">Unit Test 1</option>
            <option value="First Term Mid-Term Examination">First Term Mid-Term Examination</option>
            <option value="Unit Test 2">Unit Test 2</option>
            <option value="Annual Board Examination">Annual Board Examination</option>
            <option value="Pre-Board Mock Evaluation">Pre-Board Mock Evaluation</option>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px;">Exam Date</label>
        <input type="date" name="exam_date" value="<?php echo date('Y-m-d'); ?>" class="hrms-input" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
      </div>

      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 8px; color: var(--primary-700);">
          <i class="fa-solid fa-book-open"></i> Subject Wise Statement of Marks:
        </label>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
          <thead>
            <tr style="background: #f8fafc; border-bottom: 1px solid #cbd5e1;">
              <th style="padding: 8px; text-align: left;">Subject</th>
              <th style="padding: 8px; text-align: center; width: 25%;">Max Marks</th>
              <th style="padding: 8px; text-align: center; width: 25%;">Marks Obtained</th>
            </tr>
          </thead>
          <tbody>
            <?php 
              $default_subjects = ['Mathematics', 'Physics / Science', 'Chemistry / Social Studies', 'English Core', 'Computer Science / IT', 'Hindi / Language'];
              foreach ($default_subjects as $idx => $subj):
            ?>
              <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 6px 8px;">
                  <input type="text" name="subjects[]" value="<?php echo $subj; ?>" class="hrms-input" style="width: 100%; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12px;">
                </td>
                <td style="padding: 6px 8px;">
                  <input type="number" name="max_marks[]" value="100" class="hrms-input" style="width: 100%; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center; font-size: 12px;">
                </td>
                <td style="padding: 6px 8px;">
                  <input type="number" step="0.5" name="obtained[]" value="<?php echo rand(75, 96); ?>" class="hrms-input" style="width: 100%; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center; font-weight: 700; font-size: 13px; color: #0284c7;">
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px;">Faculty Remarks &amp; Feedback</label>
        <textarea name="remarks" rows="2" class="hrms-input" placeholder="e.g. Excellent grasp of concepts and outstanding participation." style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" onclick="document.getElementById('createMarksheetModal').style.display='none';" class="action-btn" style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancel</button>
        <button type="submit" class="action-primary-btn" style="background: #0092df; color: #ffffff; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 700; cursor: pointer;">Save &amp; Generate Marksheet</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
