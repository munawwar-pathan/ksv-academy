<?php
require_once __DIR__ . '/../config.php';

$current_user = require_login(['super_admin', 'staff']);
require_menu_permission('staff-id');
$page_title = 'Faculty & Staff ID Card Generator';
$page_heading = 'Faculty Identity Card & Biometric QR Pass';
$page_subheading = 'Official Faculty Identity Badge & Automated Biometric QR Pass';
$active_menu = 'staff-id';

$db = get_db_connection();
$active_session = get_active_session();
$session_id = $active_session['id'];
$school_settings = get_school_settings();

// Get selected staff ID
$selected_id = intval($_GET['id'] ?? 0);

// If staff role, lock to their profile
if ($current_user['role'] === 'staff') {
    $my_prof = $db->query("SELECT id FROM staff_profiles WHERE user_id = {$current_user['id']} LIMIT 1")->fetch();
    if ($my_prof) {
        $selected_id = $my_prof['id'];
    }
}

// Fetch staff dropdown
$all_staff = $db->prepare("SELECT sp.id, sp.employee_code, sp.department, sp.designation, u.full_name FROM staff_profiles sp JOIN users u ON sp.user_id = u.id WHERE sp.session_id = ? ORDER BY u.full_name ASC");
$all_staff->execute([$session_id]);
$staff_list = $all_staff->fetchAll();

if ($selected_id === 0 && !empty($staff_list)) {
    $selected_id = $staff_list[0]['id'];
}

// Fetch selected staff details
$staff = null;
if ($selected_id > 0) {
    $stmt = $db->prepare("
        SELECT sp.*, u.full_name, u.email as user_email, u.phone as user_phone
        FROM staff_profiles sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.id = ? LIMIT 1
    ");
    $stmt->execute([$selected_id]);
    $staff = $stmt->fetch();
}

$qr_data = $staff ? ($staff['qr_code'] ?: 'STAFF-' . $staff['employee_code']) : 'KSV-STAFF';
$qr_image_url = get_qr_code_url($qr_data, '180x180');

require_once __DIR__ . '/../includes/layout-header.php';
?>

<style>
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

.id-card-pvc-staff {
  width: 320px;
  height: 500px;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12), 0 3px 10px rgba(0, 0, 0, 0.06);
  border: 1px solid #e2e8f0;
  overflow: hidden;
  position: relative;
  display: flex;
  flex-direction: column;
}

.id-card-header-staff {
  background: linear-gradient(135deg, #7c3aed 0%, #4338ca 100%);
  color: #ffffff;
  padding: 16px 14px 12px;
  text-align: center;
  position: relative;
}

.id-card-header-staff::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 0;
  right: 0;
  height: 20px;
  background: #ffffff;
  clip-path: ellipse(55% 100% at 50% 100%);
}

.id-card-body-staff {
  padding: 12px 18px;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.id-photo-frame-staff {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  border: 4px solid #ffffff;
  box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
  background: #f1f5f9;
  overflow: hidden;
  margin-top: -30px;
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
}

.id-info-row-staff {
  display: flex;
  justify-content: space-between;
  width: 100%;
  font-size: 12px;
  padding: 4px 0;
  border-bottom: 1px dashed #f1f5f9;
  text-align: left;
}

.id-info-label-staff {
  color: #64748b;
  font-weight: 600;
  width: 42%;
}

.id-info-val-staff {
  color: #0f172a;
  font-weight: 700;
  width: 58%;
  text-align: right;
}
</style>

<!-- Top Toolbar -->
<div class="hrms-card no-print" style="margin-bottom: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    
    <?php if ($current_user['role'] === 'super_admin'): ?>
      <form method="GET" action="<?php echo ADMIN_URL; ?>/staff-id" style="display: flex; align-items: center; gap: 12px;">
        <label style="font-size: 13px; font-weight: 700; color: var(--neutral-700);">Select Faculty Member:</label>
        <select name="id" onchange="this.form.submit()" class="hrms-input" style="min-width: 280px; padding: 8px 12px; border-radius: 6px;">
          <?php foreach ($staff_list as $st_item): ?>
            <option value="<?php echo $st_item['id']; ?>" <?php echo $st_item['id'] == $selected_id ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($st_item['full_name']); ?> (<?php echo htmlspecialchars($st_item['employee_code']); ?> &bull; <?php echo htmlspecialchars($st_item['department']); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php else: ?>
      <div>
        <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--neutral-800);">Faculty Official Identity Card</h3>
        <p style="margin: 2px 0 0; font-size: 12px; color: var(--neutral-500);">Campus identification &amp; attendance QR pass</p>
      </div>
    <?php endif; ?>

    <div>
      <button type="button" onclick="window.print();" class="action-primary-btn" style="background: #7c3aed; color: #ffffff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);">
        <i class="fa-solid fa-print"></i>
        <span>Print Staff ID Card</span>
      </button>
    </div>

  </div>
</div>

<?php if ($staff): ?>
  <div class="id-card-printable-area" style="display: flex; gap: 32px; justify-content: center; flex-wrap: wrap; margin-bottom: 30px;">
    
    <!-- STAFF CARD FRONT -->
    <div class="id-card-pvc-staff">
      
      <!-- Header -->
      <div class="id-card-header-staff">
        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 4px;">
          <img src="<?php echo htmlspecialchars($school_settings['logo_url']); ?>" alt="Logo" style="width: 32px; height: 32px; border-radius: 50%; background: #fff; padding: 2px;">
          <span style="font-size: 16px; font-weight: 800; letter-spacing: 0.5px;"><?php echo htmlspecialchars($school_settings['school_name']); ?></span>
        </div>
        <div style="font-size: 10px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;">
          FACULTY &amp; STAFF IDENTITY CARD
        </div>
        <div style="display: inline-block; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 800; margin-top: 4px;">
          SESSION <?php echo htmlspecialchars($active_session['session_name']); ?>
        </div>
      </div>

      <!-- Photo Frame -->
      <div class="id-photo-frame-staff" style="margin-left: auto; margin-right: auto;">
        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #7c3aed, #4338ca); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 34px; font-weight: 800;">
          <?php echo strtoupper(substr($staff['full_name'], 0, 1)); ?>
        </div>
      </div>

      <!-- Body -->
      <div class="id-card-body-staff">
        <h3 style="margin: 8px 0 2px; font-size: 17px; font-weight: 800; color: #0f172a;">
          <?php echo htmlspecialchars($staff['full_name']); ?>
        </h3>
        <span style="display: inline-block; background: #f3e8ff; color: #7c3aed; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 800; margin-bottom: 12px;">
          <?php echo htmlspecialchars($staff['designation']); ?> &bull; Dept of <?php echo htmlspecialchars($staff['department']); ?>
        </span>

        <div style="width: 100%; margin-top: 4px;">
          <div class="id-info-row-staff">
            <span class="id-info-label-staff">Employee Code:</span>
            <span class="id-info-val-staff" style="color: #7c3aed; font-family: monospace;"><?php echo htmlspecialchars($staff['employee_code']); ?></span>
          </div>
          <div class="id-info-row-staff">
            <span class="id-info-label-staff">Qualification:</span>
            <span class="id-info-val-staff"><?php echo htmlspecialchars($staff['qualification']); ?></span>
          </div>
          <div class="id-info-row-staff">
            <span class="id-info-label-staff">Contact Phone:</span>
            <span class="id-info-val-staff"><?php echo htmlspecialchars($staff['phone'] ?: $staff['user_phone']); ?></span>
          </div>
          <div class="id-info-row-staff">
            <span class="id-info-label-staff">Email:</span>
            <span class="id-info-val-staff" style="font-size: 11px;"><?php echo htmlspecialchars($staff['email'] ?: $staff['user_email']); ?></span>
          </div>
          <div class="id-info-row-staff">
            <span class="id-info-label-staff">Blood Group:</span>
            <span class="id-info-val-staff"><?php echo htmlspecialchars($staff['blood_group'] ?? 'B+'); ?></span>
          </div>
          <div class="id-info-row-staff">
            <span class="id-info-label-staff">Joining Date:</span>
            <span class="id-info-val-staff"><?php echo format_date($staff['joining_date']); ?></span>
          </div>
        </div>

        <!-- Authorized Signature & Seal Footer -->
        <div style="width: 100%; margin-top: auto; padding-top: 10px; display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #f1f5f9;">
          <div style="text-align: left;">
            <div style="font-size: 9px; color: #94a3b8; font-weight: 700;">ISSUED BY</div>
            <div style="font-size: 10px; font-weight: 800; color: #334155;">KSV ACADEMY</div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 11px; font-weight: 700; color: #7c3aed; font-family: 'Outfit', cursive;">Salam Khan</div>
            <div style="font-size: 9px; color: #94a3b8; font-weight: 700; border-top: 1px solid #cbd5e1; padding-top: 2px;">MANAGING DIRECTOR</div>
          </div>
        </div>
      </div>

    </div>

    <!-- STAFF CARD BACK (Biometric Attendance QR) -->
    <div class="id-card-pvc-staff">
      
      <div style="background: #0f172a; color: #ffffff; padding: 12px; text-align: center;">
        <span style="font-size: 11px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;">
          FACULTY BIOMETRIC ATTENDANCE QR PASS
        </span>
      </div>

      <div class="id-card-body-staff" style="padding: 16px;">
        
        <div style="margin-top: 10px; padding: 8px; background: #ffffff; border: 2px solid #7c3aed; border-radius: 12px; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);">
          <img src="<?php echo $qr_image_url; ?>" alt="Staff Attendance QR" style="width: 150px; height: 150px; display: block;">
        </div>

        <div style="font-family: monospace; font-size: 13px; font-weight: 800; color: #7c3aed; margin-top: 8px;">
          <?php echo htmlspecialchars($qr_data); ?>
        </div>
        <p style="font-size: 11px; color: #64748b; margin: 4px 0 12px;">
          Scan at reception terminal for automated daily duty punch in/out.
        </p>

        <div style="width: 100%; background: #f8fafc; border-radius: 8px; padding: 10px; font-size: 10px; color: #475569; text-align: left; line-height: 1.5;">
          <strong>FACULTY GUIDELINES:</strong>
          <ol style="margin: 4px 0 0; padding-left: 14px;">
            <li>This card confirms active faculty accreditation at KSV Academy.</li>
            <li>Required for biometric room, academic labs &amp; administrative access.</li>
            <li>In case of loss or theft, notify HR department immediately.</li>
          </ol>
        </div>

        <div style="width: 100%; margin-top: auto; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #64748b;">
          <strong><?php echo htmlspecialchars($school_settings['school_name']); ?></strong><br>
          <?php echo htmlspecialchars($school_settings['address']); ?><br>
          Email: <?php echo htmlspecialchars($school_settings['email']); ?>
        </div>

      </div>

    </div>

  </div>
<?php else: ?>
  <div class="hrms-card" style="text-align: center; padding: 40px;">
    <p>Please select a faculty member above to generate the Identity Card.</p>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
