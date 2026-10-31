<?php
require_once __DIR__ . '/config.php';

$active_page = 'admissions';
$page_title = 'Admissions Open ' . CURRENT_SESSION . ' | KSV Academy Ulwe | 60% Scholarship & 3-Days Free Demo';
$page_description = 'Enroll at KSV Academy Ulwe for Academic Session ' . CURRENT_SESSION . '. Avail up to 60% merit scholarships, 3-Days Free Demo Classes, zero-stress EMI installment plans, and personal mentoring for Classes 5th-12th, IIT-JEE, NEET & Degree.';
$page_keywords = 'KSV Academy admissions, coaching admission Ulwe 2026-27, scholarship test Ulwe, tuition admission sector 19 Ulwe, IIT JEE admission Navi Mumbai, NEET coaching enrollment, 10th CBSE tuition admission, 12th science commerce coaching admission, free demo class Ulwe, education loan EMI tuition Ulwe, Salam Khan Physics admission, Rameshwar Mishra Chemistry';
$canonical_url = 'https://ksvacademy.org/admissions';
$page_image = 'assets/imgs/students/classroom-learning.jpg';

// Strict input validation & allowlist mapping for optional ?course= query parameter
// Defends against SQL injection, XSS, and parameter pollution
$raw_course_param = isset($_GET['course']) ? strtolower(trim((string)$_GET['course'])) : '';
$course_param_clean = preg_replace('/[^a-z0-9_-]/', '', $raw_course_param);

$course_whitelist = [
    'foundation'   => 'Class 5th - 8th Foundation',
    'boards'       => 'Class 9th & 10th Boards (CBSE/ICSE/State)',
    'school'       => 'Class 9th & 10th Boards (CBSE/ICSE/State)',
    'iit-jee'      => 'IIT-JEE Main + Advanced',
    'jee'          => 'IIT-JEE Main + Advanced',
    'neet'         => 'NEET-UG Medical',
    'medical'      => 'NEET-UG Medical',
    'cet'          => 'MH-CET (Engineering / Pharmacy)',
    'mh-cet'       => 'MH-CET (Engineering / Pharmacy)',
    'science'      => '11th & 12th Science (HSC/CBSE)',
    'commerce'     => '11th & 12th Commerce Section',
    'degree'       => 'Degree: B.Com / BAF / BMS / BCA',
    'postgraduate' => 'Postgraduate: M.Com / MBA'
];
$selected_course = $course_whitelist[$course_param_clean] ?? '';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Admissions Hero Banner (Attractive Design Matching Home & About) -->
<section class="about-hero-banner">
  <div class="container text-center">
    <div class="about-banner-content">
      <!-- Dual Eyebrows -->
      <div class="hero-eyebrows-wrap" style="justify-content: center; margin-bottom: 16px;">
        <div class="hero-eyebrow">
          <span class="hero-live-dot"></span>
          <span>Admissions Open • Academic Session <?php echo htmlspecialchars(CURRENT_SESSION, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="hero-trust-badge">
          <i class="fa-solid fa-award"></i>
          <span>Up to 60% Merit Scholarship Available</span>
        </div>
      </div>

      <!-- Main Heading -->
      <h1 class="about-banner-title">
        Admissions &amp; <span class="hero-highlight">Scholarships</span>
      </h1>

      <!-- Slogan / Motto Tagline -->
      <div class="hero-motto-row" style="justify-content: center; margin-bottom: 16px;">
        <span class="motto-lead"><i class="fa-solid fa-sparkles"></i> 3-Days Free Demo Trial</span>
        <span class="motto-divider">•</span>
        <span class="motto-sub">Zero Upfront Commitment • Transparent Installments</span>
      </div>

      <!-- Lead Description -->
      <p class="about-banner-desc">
        Take the decisive step toward academic excellence. Apply online, claim up to 60% merit scholarships, and attend a 3-Day Free Demo Class without upfront fees in Ulwe.
      </p>

      <!-- Quick Stats Counter Strip -->
      <div class="hero-stats-row" style="max-width: 760px; margin: 0 auto;">
        <div class="stat-item">
          <span class="stat-number" data-target="60" data-suffix="%">60%</span>
          <span class="stat-label">Max Scholarship</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="3" data-suffix=" Days">3 Days</span>
          <span class="stat-label">Free Demo Trial</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="0" data-suffix="%">0%</span>
          <span class="stat-label">Interest EMI Plans</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="150" data-suffix="+">150+</span>
          <span class="stat-label">Verified Reviews</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. 4-Step Admission Roadmap -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-road"></i> Streamlined Process</span>
      <h2 class="section-title">4 Simple Steps to <span class="hero-highlight">Join KSV Academy</span></h2>
      <p class="section-subtitle">We make the enrollment experience transparent, supportive, and completely hassle-free for parents and students.</p>
    </div>

    <div class="admission-steps-grid">
      <!-- Step 01 -->
      <div class="admission-step-card">
        <div class="admission-step-topbar bar-step-1"></div>
        <div class="admission-step-header">
          <span class="step-num-pill">Step 01</span>
          <i class="fa-solid fa-arrow-right" style="color: var(--text-muted); font-size: 0.8rem;"></i>
        </div>
        <div class="admission-step-icon icon-blue">
          <i class="fa-solid fa-calendar-check"></i>
        </div>
        <h3>Free Demo Class</h3>
        <p>Register online or visit our Ulwe center to attend a complimentary 3-day classroom demo without upfront commitment.</p>
        <ul class="admission-step-checklist">
          <li><i class="fa-solid fa-check"></i> Sit in live classroom lectures</li>
          <li><i class="fa-solid fa-check"></i> Experience concept-first teaching</li>
        </ul>
      </div>

      <!-- Step 02 -->
      <div class="admission-step-card">
        <div class="admission-step-topbar bar-step-2"></div>
        <div class="admission-step-header">
          <span class="step-num-pill">Step 02</span>
          <i class="fa-solid fa-arrow-right" style="color: var(--text-muted); font-size: 0.8rem;"></i>
        </div>
        <div class="admission-step-icon icon-emerald">
          <i class="fa-solid fa-comments"></i>
        </div>
        <h3>Academic Counseling</h3>
        <p>Meet with Mr. Salam Khan and senior faculty for a diagnostic discussion tailored to your child's learning goals.</p>
        <ul class="admission-step-checklist">
          <li><i class="fa-solid fa-check"></i> 1-on-1 career &amp; subject evaluation</li>
          <li><i class="fa-solid fa-check"></i> Customized study roadmaps</li>
        </ul>
      </div>

      <!-- Step 03 -->
      <div class="admission-step-card">
        <div class="admission-step-topbar bar-step-3"></div>
        <div class="admission-step-header">
          <span class="step-num-pill">Step 03</span>
          <i class="fa-solid fa-arrow-right" style="color: var(--text-muted); font-size: 0.8rem;"></i>
        </div>
        <div class="admission-step-icon icon-amber">
          <i class="fa-solid fa-award"></i>
        </div>
        <h3>Scholarship Test</h3>
        <p>Take our quick aptitude assessment test to qualify for up to 60% tuition fee scholarships based on merit and performance.</p>
        <ul class="admission-step-checklist">
          <li><i class="fa-solid fa-check"></i> Merit-based direct fee concession</li>
          <li><i class="fa-solid fa-check"></i> Marksheet score consideration</li>
        </ul>
      </div>

      <!-- Step 04 -->
      <div class="admission-step-card">
        <div class="admission-step-topbar bar-step-4"></div>
        <div class="admission-step-header">
          <span class="step-num-pill">Step 04</span>
          <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 0.9rem;"></i>
        </div>
        <div class="admission-step-icon icon-purple">
          <i class="fa-solid fa-graduation-cap"></i>
        </div>
        <h3>Batch Confirmation</h3>
        <p>Select morning or evening shifts, receive the complete curriculum study kit, and commence structured classes.</p>
        <ul class="admission-step-checklist">
          <li><i class="fa-solid fa-check"></i> Morning &amp; evening shift choice</li>
          <li><i class="fa-solid fa-check"></i> Complete chapter study kit issued</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- 3. Online Admission Application Form & Scholarship Details -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-file-signature"></i> Quick Application</span>
      <h2 class="section-title">Apply Online for <span class="hero-highlight">Academic Session <?php echo htmlspecialchars(CURRENT_SESSION, ENT_QUOTES, 'UTF-8'); ?></span></h2>
      <p class="section-subtitle">Submit your registration below to secure early-bird batch allotment and scholarship eligibility.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1.25fr 0.75fr; gap: 36px; align-items: start;" class="about-top-grid">
      <!-- Form Column -->
      <div style="background: var(--bg-card); padding: 36px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-sm); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0284c7, #0092df, #38bdf8);"></div>
        
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
          <h3 style="font-size: 1.45rem; font-weight: 800; color: var(--text-main); margin: 0;">
            Candidate <span class="hero-highlight">Registration Form</span>
          </h3>
          <span class="badge-tag" style="background: rgba(0, 146, 223, 0.08); color: var(--primary-700); border: 1px solid rgba(0, 146, 223, 0.18); font-size: 0.76rem; font-weight: 700; padding: 4px 10px; border-radius: 999px;">
            <i class="fa-solid fa-bolt"></i> Fast 2-Min Form
          </span>
        </div>
        <p style="color: var(--text-muted); font-size: 0.90rem; margin-bottom: 24px; line-height: 1.55;">
          Fill out this form to reserve your free demo seat and lock your scholarship eligibility for Session <?php echo htmlspecialchars(CURRENT_SESSION, ENT_QUOTES, 'UTF-8'); ?>.
        </p>

        <div id="admissionSuccessState" class="form-success-message" style="display: none; padding: 28px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 16px; text-align: center; margin-bottom: 20px;">
          <div class="success-icon" style="font-size: 2.4rem; color: #10b981; margin-bottom: 12px;"><i class="fa-solid fa-circle-check"></i></div>
          <h3 style="color: var(--text-main); font-size: 1.3rem; font-weight: 800; margin-bottom: 8px;">Application Submitted Successfully!</h3>
          <p style="color: var(--text-muted); font-size: 0.92rem; line-height: 1.6; margin-bottom: 18px;">Thank you for choosing KSV Academy. Our admission desk will call you within 24 hours to confirm your registration and diagnostic test date.</p>
          <a href="<?php echo htmlspecialchars(WHATSAPP_LINK, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-whatsapp" target="_blank" rel="noopener noreferrer" style="background: #25d366; color: #ffffff; font-weight: 700;">
            <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp Now
          </a>
        </div>

        <form id="onlineAdmissionForm" class="interactive-form" method="POST" action="<?php echo htmlspecialchars(url('submit-form')); ?>" onsubmit="handleAdmissionSubmit(event)">
          <input type="hidden" name="form_type" value="admission">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
          <!-- Anti-bot honeypot field -->
          <div style="display:none !important;" aria-hidden="true">
            <label for="adm_hp_check">Leave blank</label>
            <input type="text" id="adm_hp_check" name="website_hp" tabindex="-1" autocomplete="off">
          </div>
          <div class="form-row">
            <div class="form-group col-half">
              <label for="admStudentName"><i class="fa-solid fa-user"></i> Student Full Name <span class="required">*</span></label>
              <input type="text" id="admStudentName" name="student_name" class="form-control" placeholder="e.g. Aryan Sharma" maxlength="100" autocomplete="name" required>
            </div>
            <div class="form-group col-half">
              <label for="admParentName"><i class="fa-solid fa-user-tie"></i> Parent / Guardian Name</label>
              <input type="text" id="admParentName" name="parent_name" class="form-control" placeholder="e.g. Rajesh Sharma" maxlength="100" autocomplete="name">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-half">
              <label for="admPhone"><i class="fa-solid fa-phone"></i> Mobile / WhatsApp <span class="required">*</span></label>
              <input type="tel" id="admPhone" name="phone" class="form-control" placeholder="10-digit mobile number" pattern="[0-9]{10}" maxlength="15" autocomplete="tel" required>
            </div>
            <div class="form-group col-half">
              <label for="admEmail"><i class="fa-solid fa-envelope"></i> Email Address</label>
              <input type="email" id="admEmail" name="email" class="form-control" placeholder="name@example.com" maxlength="100" autocomplete="email">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-half">
              <label for="admCourse"><i class="fa-solid fa-book-open"></i> Course Applying For <span class="required">*</span></label>
              <select id="admCourse" name="course" class="form-control" required>
                <option value="">-- Select Course --</option>
                <option value="Class 5th - 8th Foundation" <?php echo ($selected_course === 'Class 5th - 8th Foundation') ? 'selected' : ''; ?>>Class 5th - 8th Foundation</option>
                <option value="Class 9th & 10th Boards (CBSE/ICSE/State)" <?php echo ($selected_course === 'Class 9th & 10th Boards (CBSE/ICSE/State)') ? 'selected' : ''; ?>>Class 9th & 10th Boards (CBSE/ICSE/State)</option>
                <option value="IIT-JEE Main + Advanced" <?php echo ($selected_course === 'IIT-JEE Main + Advanced') ? 'selected' : ''; ?>>IIT-JEE Main + Advanced</option>
                <option value="NEET-UG Medical" <?php echo ($selected_course === 'NEET-UG Medical') ? 'selected' : ''; ?>>NEET-UG Medical</option>
                <option value="MH-CET (Engineering / Pharmacy)" <?php echo ($selected_course === 'MH-CET (Engineering / Pharmacy)') ? 'selected' : ''; ?>>MH-CET (Engineering / Pharmacy)</option>
                <option value="11th & 12th Science (HSC/CBSE)" <?php echo ($selected_course === '11th & 12th Science (HSC/CBSE)') ? 'selected' : ''; ?>>11th & 12th Science (HSC/CBSE)</option>
                <option value="11th & 12th Commerce Section" <?php echo ($selected_course === '11th & 12th Commerce Section') ? 'selected' : ''; ?>>11th & 12th Commerce Section</option>
                <option value="Degree: B.Com / BAF / BMS / BCA" <?php echo ($selected_course === 'Degree: B.Com / BAF / BMS / BCA') ? 'selected' : ''; ?>>Degree: B.Com / BAF / BMS / BCA</option>
                <option value="Postgraduate: M.Com / MBA" <?php echo ($selected_course === 'Postgraduate: M.Com / MBA') ? 'selected' : ''; ?>>Postgraduate: M.Com / MBA</option>
              </select>
            </div>

            <div class="form-group col-half">
              <label for="admBatch"><i class="fa-solid fa-clock"></i> Preferred Shift</label>
              <select id="admBatch" name="batch_timing" class="form-control">
                <option value="Morning Batch (7:30 AM - 11:30 AM)">Morning Shift (7:30 AM - 11:30 AM)</option>
                <option value="Evening Batch (4:00 PM - 8:30 PM)">Evening Shift (4:00 PM - 8:30 PM)</option>
                <option value="Weekend Special">Weekend Special</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="admSchool"><i class="fa-solid fa-school"></i> Current School / Junior College</label>
            <input type="text" id="admSchool" name="school_name" class="form-control" placeholder="e.g. DPS Nerul / Ramsheth Thakur / College in Ulwe" maxlength="150">
          </div>

          <div class="form-group">
            <label for="admQuery"><i class="fa-solid fa-message"></i> Remarks or Questions</label>
            <textarea id="admQuery" name="message" class="form-control" placeholder="Any specific requirements or questions for our academic team..." maxlength="1000"></textarea>
          </div>

          <button type="submit" id="admSubmitBtn" class="btn btn-primary btn-block btn-lg" style="box-shadow: 0 6px 20px rgba(0, 146, 223, 0.35);">
            <i class="fa-solid fa-paper-plane"></i> Submit Admission Application
          </button>
        </form>
      </div>

      <!-- Scholarship & Guidance Column -->
      <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Scholarship Card -->
        <div class="admission-scholarship-card">
          <span class="section-eyebrow" style="background: rgba(255, 255, 255, 0.14); color: #bae6fd; border: 1px solid rgba(255, 255, 255, 0.22); margin-bottom: 12px; font-size: 0.76rem; font-weight: 700;">
            <i class="fa-solid fa-award"></i> Merit Reward
          </span>
          <h3 style="color: #ffffff; font-size: 1.55rem; font-weight: 800; margin-bottom: 12px; line-height: 1.25;">
            Up to 60% <span style="color: #38bdf8;">Merit Scholarship</span>
          </h3>
          <p style="color: rgba(255, 255, 255, 0.88); font-size: 0.90rem; line-height: 1.6; margin-bottom: 18px;">
            KSV Academy offers scholarships based on academic merit and performance in our Diagnostic Aptitude Test to ensure every motivated learner accesses top-tier coaching.
          </p>
          <ul style="color: #f1f5f9; font-size: 0.86rem; display: flex; flex-direction: column; gap: 12px; list-style: none; padding: 0; margin: 0;">
            <li style="display: flex; align-items: flex-start; gap: 10px;">
              <i class="fa-solid fa-circle-check" style="color: #38bdf8; margin-top: 3px; font-size: 0.95rem;"></i>
              <span><strong>90%+ in Previous Exams:</strong> Direct 40%–60% tuition fee concession</span>
            </li>
            <li style="display: flex; align-items: flex-start; gap: 10px;">
              <i class="fa-solid fa-circle-check" style="color: #38bdf8; margin-top: 3px; font-size: 0.95rem;"></i>
              <span><strong>Sibling Benefit:</strong> Additional 10% fee rebate for families</span>
            </li>
            <li style="display: flex; align-items: flex-start; gap: 10px;">
              <i class="fa-solid fa-circle-check" style="color: #38bdf8; margin-top: 3px; font-size: 0.95rem;"></i>
              <span><strong>Installment Options:</strong> Zero-stress, flexible monthly installment schedules</span>
            </li>
          </ul>
        </div>

        <!-- Required Documents Card -->
        <div style="background: var(--bg-card); padding: 26px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-xs);">
          <h4 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-folder-open" style="color: var(--primary-600);"></i> Required <span class="hero-highlight">Documents</span>
          </h4>
          <ul style="font-size: 0.86rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 10px; list-style: none; padding: 0; margin: 0;">
            <li style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-check" style="color: #10b981; font-size: 0.85rem;"></i>
              <span>Copy of previous academic examination marksheet</span>
            </li>
            <li style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-check" style="color: #10b981; font-size: 0.85rem;"></i>
              <span>2 Passport-sized recent student photographs</span>
            </li>
            <li style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-check" style="color: #10b981; font-size: 0.85rem;"></i>
              <span>Photo ID / Aadhaar card photocopy</span>
            </li>
            <li style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-check" style="color: #10b981; font-size: 0.85rem;"></i>
              <span>Duly completed registration / application form</span>
            </li>
          </ul>
        </div>

        <!-- Counselor Contact Card -->
        <div style="background: var(--bg-surface-soft); padding: 26px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); text-align: center;">
          <h4 style="font-size: 1.12rem; font-weight: 800; color: var(--text-main); margin-bottom: 8px;">
            Speak with an <span class="hero-highlight">Admission Counselor</span>
          </h4>
          <p style="font-size: 0.86rem; color: var(--text-muted); margin-bottom: 16px; line-height: 1.5;">
            Need help selecting the best batch or subjects? Talk directly to our academic desk.
          </p>
          <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <a href="tel:<?php echo htmlspecialchars(PHONE_RAW_1, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-sm" style="flex: 1; min-width: 140px; font-weight: 700;">
              <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars(PHONE_PRIMARY, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href="<?php echo htmlspecialchars(WHATSAPP_LINK, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer" style="flex: 1; min-width: 140px; font-weight: 700;">
              <i class="fa-brands fa-whatsapp"></i> WhatsApp
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 4. Admission FAQs -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-circle-question"></i> Common Inquiries</span>
      <h2 class="section-title">Admission &amp; Scholarship <span class="hero-highlight">FAQs</span></h2>
      <p class="section-subtitle">Clear answers to frequent questions about enrollment, fee installments, scholarships, and batch timings.</p>
    </div>

    <div class="faq-accordion" style="max-width: 860px; margin: 0 auto;">
      <div class="faq-item active">
        <button type="button" class="faq-header">
          <span class="faq-title">How do I claim the 3-Days Free Demo Class?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            You can register through our online form above, call us at <strong><?php echo htmlspecialchars(PHONE_PRIMARY, ENT_QUOTES, 'UTF-8'); ?></strong>, or visit our Sector 19 Ulwe center. You will receive an immediate pass to attend 3 actual classroom lectures before making any financial commitment.
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">What is the criteria for the scholarship up to 60%?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            Merit scholarships up to 60% are determined through the KSV Diagnostic Aptitude Test and past board examination results (CBSE, ICSE, or SSC). Sibling discounts and early-registration privileges are also offered.
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">Are fees payable in flexible installments?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            Yes. We provide parent-friendly, interest-free monthly and quarterly installment options so quality coaching remains completely affordable and stress-free for families.
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">Can students switch between Morning and Evening shifts?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            Yes. If school or college timings change during the term, students can request a batch transfer between our morning and evening shifts with prior coordination with the center head.
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">What study material is included upon admission?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            Every enrolled student receives comprehensive chapter-wise theory booklets, question banks covering past 10 years board/entrance exams, formula reference sheets, and weekly assessment papers.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 5. Admission Visit CTA (Attractive Banner Card Matching Home & About) -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="demo-banner-card" style="background: linear-gradient(135deg, #02457a 0%, #0077b6 55%, #0092df 100%); border-radius: 28px; padding: 48px 40px; color: #ffffff; display: grid; grid-template-columns: 1.25fr 0.75fr; gap: 32px; align-items: center; box-shadow: 0 20px 48px -10px rgba(0, 146, 223, 0.35); position: relative; overflow: hidden;">
      <div style="position: absolute; right: -50px; bottom: -50px; width: 260px; height: 260px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); pointer-events: none;"></div>
      
      <div>
        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); padding: 5px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.04em;">
          <i class="fa-solid fa-sparkles"></i> Walk-In Campus Visit
        </div>
        <h2 style="font-size: 2.2rem; font-weight: 800; line-height: 1.2; margin-bottom: 12px; color: #ffffff;">
          Ready to Experience <span style="color: #38bdf8;">KSV Academy?</span>
        </h2>
        <p style="font-size: 1.02rem; color: rgba(255,255,255,0.9); line-height: 1.6; margin: 0 0 22px;">
          Walk into our Ulwe Sector 19 campus, meet Director Mr. Salam Khan and our educators in person, and reserve your seat for the upcoming batch.
        </p>
        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
          <button type="button" class="btn btn-warning btn-lg" data-modal="demoModal" style="background: #f59e0b; color: #08212e; border: none; font-weight: 800; box-shadow: 0 4px 16px rgba(245,158,11,0.4);">
            <i class="fa-regular fa-calendar-check"></i> Claim 3-Days Free Demo Pass
          </button>
          <a href="<?php echo htmlspecialchars(WHATSAPP_LINK, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-white btn-lg" target="_blank" rel="noopener noreferrer" style="border: 2px solid rgba(255,255,255,0.6); color: #ffffff;">
            <i class="fa-brands fa-whatsapp"></i> Chat with Counselor
          </a>
        </div>
      </div>

      <div style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 26px; backdrop-filter: blur(8px); text-align: center;">
        <div style="font-size: 2.2rem; font-weight: 800; color: #ffffff; line-height: 1;">4.9 / 5.0</div>
        <div style="color: #fbbf24; font-size: 1.1rem; margin: 8px 0;"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
        <div style="font-size: 0.9rem; font-weight: 700; color: #ffffff;">Top-Rated Coaching in Ulwe</div>
        <p style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.8); margin: 6px 0 16px;">150+ Verified Google &amp; Parent Reviews</p>
        <a href="tel:<?php echo htmlspecialchars(PHONE_RAW_1, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm" style="background: #ffffff; color: var(--primary-700); font-weight: 700; width: 100%;">
          <i class="fa-solid fa-phone"></i> Call: <?php echo htmlspecialchars(PHONE_PRIMARY, ENT_QUOTES, 'UTF-8'); ?>
        </a>
      </div>
    </div>
  </div>
</section>

<script>
function handleAdmissionSubmit(e) {
  e.preventDefault();
  const form = document.getElementById('onlineAdmissionForm');
  const successState = document.getElementById('admissionSuccessState');
  const submitBtn = document.getElementById('admSubmitBtn');
  const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';

  const studentName = document.getElementById('admStudentName')?.value.trim();
  const phone = document.getElementById('admPhone')?.value.trim();
  const course = document.getElementById('admCourse')?.value;

  if (!studentName || !phone || !course) {
    alert('Please fill in Student Name, Phone Number, and Course.');
    return;
  }

  // Validate phone format: 10 digits
  const cleanPhone = phone.replace(/[^0-9]/g, '');
  if (cleanPhone.length < 10) {
    alert('Please enter a valid 10-digit mobile number.');
    return;
  }

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting Application...';
  }

  const formData = new FormData(form);
  formData.append('ajax', '1');

  const submitUrl = form.getAttribute('action') || '/submit-form';

  fetch(submitUrl, {
    method: 'POST',
    body: formData,
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(res => {
    if (!res.ok) {
      throw new Error('Server returned HTTP ' + res.status);
    }
    return res.json();
  })
  .then(data => {
    if (data.success) {
      if (form) form.style.display = 'none';
      if (successState) {
        successState.style.display = 'block';
        successState.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      form.reset();
    } else {
      alert(data.message || 'Error saving admission application. Please try again.');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origBtnHtml;
      }
    }
  })
  .catch(err => {
    console.error('Submission error:', err);
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = origBtnHtml;
    }
    alert('Unable to submit the application right now. Please check your internet connection or reach out to us directly on WhatsApp.');
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
