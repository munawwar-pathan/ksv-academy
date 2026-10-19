<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff', 'parent', 'student']);
$page_title = 'Student ID Card Generator & QR Code';
$page_heading = 'Student Identity Card & QR Generator';
$page_subheading = 'Official Academic Identification Card with Biometric QR Code';
$active_menu = 'student-id';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Get selected student ID
$selected_id = intval($_GET['id'] ?? 0);

// If student role logged in, lock to their student profile
if ($current_user['role'] === 'student' || $current_user['role'] === 'parent') {
    $my_stu = $db->query("SELECT id FROM students WHERE parent_user_id = {$current_user['id']} LIMIT 1")->fetch();
    if ($my_stu) {
        $selected_id = $my_stu['id'];
    }
}

// Fetch all students for selector dropdown
$all_students = $db->prepare("SELECT id, student_name, roll_no, standard FROM students WHERE session_id = ? ORDER BY standard ASC, student_name ASC");
$all_students->execute([$session_id]);
$students_list = $all_students->fetchAll();

if ($selected_id === 0 && !empty($students_list)) {
    $selected_id = $students_list[0]['id'];
}

// Fetch selected student details
$student = null;
if ($selected_id > 0) {
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$selected_id]);
    $student = $stmt->fetch();
}

// Generate QR Code data string (STU-{id}-{roll_no})
$qr_data = $student ? ($student['qr_code'] ?: 'STU-' . $student['roll_no']) : 'KSV-STUDENT';
$qr_image_url = get_qr_code_url($qr_data, '180x180');

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
/* Print Stylesheet for PVC ID Card */
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
    gap: 20px;
    padding: 20px;
  }
  .no-print {
    display: none !important;
  }
}

.id-card-pvc {
  width: 320px;
  height: 490px;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12), 0 3px 10px rgba(0, 0, 0, 0.06);
  border: 1px solid #e2e8f0;
  overflow: hidden;
  position: relative;
  display: flex;
  flex-direction: column;
}

.id-card-header {
  background: linear-gradient(135deg, #0092df 0%, #005a9c 100%);
  color: #ffffff;
  padding: 16px 14px 12px;
  text-align: center;
  position: relative;
}

.id-card-header::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 0;
  right: 0;
  height: 20px;
  background: #ffffff;
  clip-path: ellipse(55% 100% at 50% 100%);
}

.id-card-body {
  padding: 12px 18px;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.id-photo-frame {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  border: 4px solid #ffffff;
  box-shadow: 0 4px 12px rgba(0, 146, 223, 0.25);
  background: #f1f5f9;
  overflow: hidden;
  margin-top: -30px;
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
}

.id-info-row {
  display: flex;
  justify-content: space-between;
  width: 100%;
  font-size: 12px;
  padding: 4px 0;
  border-bottom: 1px dashed #f1f5f9;
  text-align: left;
}

.id-info-label {
  color: #64748b;
  font-weight: 600;
  width: 42%;
}

.id-info-val {
  color: #0f172a;
  font-weight: 700;
  width: 58%;
  text-align: right;
}
</style>

<!-- Top Toolbar (Student Selector & Print Trigger) -->
<div class="hrms-card no-print" style="margin-bottom: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    
    <?php if ($current_user['role'] === 'super_admin' || $current_user['role'] === 'staff'): ?>
      <form method="GET" action="<?php echo ADMIN_URL; ?>/student-id" style="display: flex; align-items: center; gap: 12px;">
        <label for="studentSelect" style="font-size: 13px; font-weight: 700; color: var(--neutral-700);">Select Student:</label>
        <select id="studentSelect" name="id" onchange="this.form.submit()" class="hrms-input" style="min-width: 280px; padding: 8px 12px; border-radius: 6px;">
          <?php foreach ($students_list as $s_item): ?>
            <option value="<?php echo $s_item['id']; ?>" <?php echo $s_item['id'] == $selected_id ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($s_item['student_name']); ?> (<?php echo htmlspecialchars($s_item['roll_no']); ?> &bull; <?php echo htmlspecialchars($s_item['standard']); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php else: ?>
      <div>
        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--neutral-800);">Student Digital ID Card</h3>
        <p style="margin: 2px 0 0; font-size: 12px; color: var(--neutral-500);">Official school identity badge &amp; attendance pass</p>
      </div>
    <?php endif; ?>

    <div style="display: flex; gap: 10px;">
      <button type="button" onclick="window.print();" class="action-primary-btn" style="background: #0092df; color: #ffffff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0, 146, 223, 0.25);">
        <i class="fa-solid fa-print"></i>
        <span>Print ID Card (Front &amp; Back)</span>
      </button>
    </div>

  </div>
</div>

<?php if ($student): ?>
  <!-- Printable Area (Front & Back View) -->
  <div class="id-card-printable-area" style="display: flex; gap: 32px; justify-content: center; flex-wrap: wrap; margin-bottom: 30px;">
    
    <!-- CARD FRONT -->
    <div class="id-card-pvc">
      
      <!-- Card Header -->
      <div class="id-card-header">
        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 4px;">
          <img src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="Logo" style="width: 32px; height: 32px; border-radius: 50%; background: #fff; padding: 2px;">
          <span style="font-size: 16px; font-weight: 800; letter-spacing: 0.5px;"><?php echo htmlspecialchars($school_settings['school_name']); ?></span>
        </div>
        <div style="font-size: 10px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">
          <?php echo htmlspecialchars($school_settings['affiliation']); ?>
        </div>
        <div style="display: inline-block; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 800; margin-top: 4px;">
          STUDENT IDENTITY CARD &bull; <?php echo htmlspecialchars($active_session['session_name']); ?>
        </div>
      </div>

      <!-- Photo Frame -->
      <div class="id-photo-frame" style="margin-left: auto; margin-right: auto;">
        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #0284c7, #0369a1); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 34px; font-weight: 800;">
          <?php echo strtoupper(substr($student['student_name'], 0, 1)); ?>
        </div>
      </div>

      <!-- Card Body Information -->
      <div class="id-card-body">
        <h3 style="margin: 8px 0 2px; font-size: 17px; font-weight: 800; color: #0f172a;">
          <?php echo htmlspecialchars($student['student_name']); ?>
        </h3>
        <span style="display: inline-block; background: #e0f2fe; color: #0284c7; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 800; margin-bottom: 12px;">
          <?php echo htmlspecialchars($student['standard']); ?> &bull; Section <?php echo htmlspecialchars($student['section'] ?? 'A'); ?>
        </span>

        <div style="width: 100%; margin-top: 4px;">
          <div class="id-info-row">
            <span class="id-info-label">Roll Number:</span>
            <span class="id-info-val" style="color: #007cbd; font-family: monospace;"><?php echo htmlspecialchars($student['roll_no']); ?></span>
          </div>
          <div class="id-info-row">
            <span class="id-info-label">Admission No:</span>
            <span class="id-info-val"><?php echo htmlspecialchars($student['admission_no'] ?? 'ADM-2025-081'); ?></span>
          </div>
          <div class="id-info-row">
            <span class="id-info-label">Guardian:</span>
            <span class="id-info-val"><?php echo htmlspecialchars($student['parent_name'] ?? '—'); ?></span>
          </div>
          <div class="id-info-row">
            <span class="id-info-label">Emergency Phone:</span>
            <span class="id-info-val"><?php echo htmlspecialchars($student['parent_phone'] ?? '—'); ?></span>
          </div>
          <div class="id-info-row">
            <span class="id-info-label">Blood Group:</span>
            <span class="id-info-val"><?php echo htmlspecialchars($student['blood_group'] ?? 'O+'); ?></span>
          </div>
          <div class="id-info-row">
            <span class="id-info-label">Date of Birth:</span>
            <span class="id-info-val"><?php echo format_date($student['dob']); ?></span>
          </div>
        </div>

        <!-- Authorized Signature & Seal Footer -->
        <div style="width: 100%; margin-top: auto; padding-top: 10px; display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #f1f5f9;">
          <div style="text-align: left;">
            <div style="font-size: 9px; color: #94a3b8; font-weight: 700;">VALID TILL</div>
            <div style="font-size: 10px; font-weight: 800; color: #334155;">31 MAR 2026</div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 11px; font-weight: 700; color: #0284c7; font-family: 'Outfit', cursive;">Salam Khan</div>
            <div style="font-size: 9px; color: #94a3b8; font-weight: 700; border-top: 1px solid #cbd5e1; padding-top: 2px;">PRINCIPAL</div>
          </div>
        </div>
      </div>

    </div>

    <!-- CARD BACK (Featuring Biometric QR Code) -->
    <div class="id-card-pvc">
      
      <!-- Card Back Header -->
      <div style="background: #0f172a; color: #ffffff; padding: 12px; text-align: center;">
        <span style="font-size: 11px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;">
          BIOMETRIC ATTENDANCE PASS &bull; SCAN CARD
        </span>
      </div>

      <div class="id-card-body" style="padding: 16px;">
        
        <!-- Scannable QR Code Box -->
        <div style="margin-top: 10px; padding: 8px; background: #ffffff; border: 2px solid #0092df; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 146, 223, 0.15);">
          <img src="<?php echo $qr_image_url; ?>" alt="Student Attendance QR" style="width: 150px; height: 150px; display: block;">
        </div>

        <div style="font-family: monospace; font-size: 13px; font-weight: 800; color: #007cbd; margin-top: 8px;">
          <?php echo htmlspecialchars($qr_data); ?>
        </div>
        <p style="font-size: 11px; color: #64748b; margin: 4px 0 12px;">
          Scan with KSV Academy Biometric Scanner to record attendance.
        </p>

        <!-- Terms and Instructions -->
        <div style="width: 100%; background: #f8fafc; border-radius: 8px; padding: 10px; font-size: 10px; color: #475569; text-align: left; line-height: 1.5;">
          <strong>INSTRUCTIONS:</strong>
          <ol style="margin: 4px 0 0; padding-left: 14px;">
            <li>This identity card is mandatory inside campus premises.</li>
            <li>If lost or misplaced, report immediately to academy office.</li>
            <li>Non-transferable. Valid only for academic year <?php echo htmlspecialchars($active_session['session_name']); ?>.</li>
          </ol>
        </div>

        <!-- Campus Contact Footer -->
        <div style="width: 100%; margin-top: auto; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #64748b;">
          <strong><?php echo htmlspecialchars($school_settings['school_name']); ?></strong><br>
          <?php echo htmlspecialchars($school_settings['address']); ?><br>
          Phone: <?php echo htmlspecialchars($school_settings['phone']); ?>
        </div>

      </div>

    </div>

  </div>
<?php else: ?>
  <div class="hrms-card" style="text-align: center; padding: 40px;">
    <p>Please select a student above to generate the Identity Card.</p>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
