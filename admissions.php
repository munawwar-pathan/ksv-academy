<?php
require_once __DIR__ . '/config.php';

$active_page = 'admissions';
$page_title = 'Admissions & Scholarships ' . CURRENT_SESSION . ' | KSV Academy Ulwe';
$page_description = 'Enroll at KSV Academy Ulwe for Academic Session ' . CURRENT_SESSION . '. Avail up to 60% merit scholarships, 3-Days Free Demo Classes, and flexible installment plans.';
$page_keywords = 'KSV Academy admission, tuition admission Ulwe, scholarship coaching Ulwe, coaching fees Ulwe, 10th 12th coaching admission';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Admissions Hero Banner -->
<section class="section-py-sm page-header-banner">
  <div class="container text-center">
    <span class="section-eyebrow" style="background: rgba(255, 255, 255, 0.1); color: #bae6fd; border: 1px solid rgba(255, 255, 255, 0.18); margin-bottom: 14px;">
      <i class="fa-solid fa-bullhorn"></i> Academic Session <?php echo CURRENT_SESSION; ?>
    </span>
    <h1 style="color: #ffffff; font-size: 2.8rem; font-weight: 800; margin-bottom: 14px; letter-spacing: -0.02em;">
      Admissions &amp; <span class="hero-highlight">Scholarships</span>
    </h1>
    <p style="color: #cbd5e1; max-width: 680px; margin: 0 auto; font-size: 1.08rem; line-height: 1.6;">
      Take the decisive step toward academic excellence. Apply online, claim up to 60% merit scholarships, and attend a 3-Day Free Demo Class without upfront fees.
    </p>
  </div>
</section>

<!-- 4-Step Admission Roadmap -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-road"></i> Streamlined Process</span>
      <h2 class="section-title">4 Simple Steps to <span class="hero-highlight">Join KSV Academy</span></h2>
      <p class="section-subtitle">We make the enrollment experience transparent, supportive, and completely hassle-free for parents and students.</p>
    </div>

    <div class="admission-steps-grid">
      <div class="admission-step-card">
        <div class="admission-step-num">01</div>
        <h3>Free Demo Class</h3>
        <p>Register online or visit our Ulwe center to attend a complimentary 3-day classroom demo without upfront commitment.</p>
      </div>

      <div class="admission-step-card">
        <div class="admission-step-num">02</div>
        <h3>Academic Counseling</h3>
        <p>Meet with Mr. Salam Khan and senior faculty for a diagnostic discussion tailored to your child's learning goals.</p>
      </div>

      <div class="admission-step-card">
        <div class="admission-step-num">03</div>
        <h3>Scholarship Test</h3>
        <p>Take our quick aptitude assessment test to qualify for up to 60% tuition fee scholarships based on merit and performance.</p>
      </div>

      <div class="admission-step-card">
        <div class="admission-step-num">04</div>
        <h3>Batch Confirmation</h3>
        <p>Select morning or evening shifts, receive the complete curriculum study kit, and commence structured classes.</p>
      </div>
    </div>
  </div>
</section>

<!-- Online Admission Application Form & Scholarship Details -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-file-signature"></i> Quick Application</span>
      <h2 class="section-title">Apply Online for <span class="hero-highlight">Academic Session <?php echo CURRENT_SESSION; ?></span></h2>
      <p class="section-subtitle">Submit your registration below to secure early-bird batch allotment and scholarship eligibility.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 36px; align-items: start;" class="about-top-grid">
      <!-- Form Column -->
      <div style="background: var(--bg-surface); padding: 36px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-sm);">
        <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; color: var(--text-main);">
          Candidate <span class="hero-highlight">Registration Form</span>
        </h3>
        <p style="color: var(--text-muted); font-size: 0.92rem; margin-bottom: 24px;">
          Submit your application to secure early-bird batch allotment and scholarship eligibility for Session <?php echo CURRENT_SESSION; ?>.
        </p>

        <div id="admissionSuccessState" class="form-success-message" style="display: none;">
          <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
          <h3>Application Submitted Successfully!</h3>
          <p>Thank you for choosing KSV Academy. Our admission desk will call you within 24 hours to confirm your registration and test date.</p>
          <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-whatsapp" target="_blank" rel="noopener noreferrer">
            <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp Now
          </a>
        </div>

        <form id="onlineAdmissionForm" class="interactive-form" onsubmit="handleAdmissionSubmit(event)">
          <div class="form-row">
            <div class="form-group col-half">
              <label for="admStudentName"><i class="fa-solid fa-user"></i> Student Full Name <span class="required">*</span></label>
              <input type="text" id="admStudentName" class="form-control" placeholder="Student name" required>
            </div>
            <div class="form-group col-half">
              <label for="admParentName"><i class="fa-solid fa-user-tie"></i> Parent / Guardian Name</label>
              <input type="text" id="admParentName" class="form-control" placeholder="Parent name">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-half">
              <label for="admPhone"><i class="fa-solid fa-phone"></i> Mobile / WhatsApp <span class="required">*</span></label>
              <input type="tel" id="admPhone" class="form-control" placeholder="10-digit number" pattern="[0-9]{10}" required>
            </div>
            <div class="form-group col-half">
              <label for="admEmail"><i class="fa-solid fa-envelope"></i> Email Address</label>
              <input type="email" id="admEmail" class="form-control" placeholder="email@example.com">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-half">
              <label for="admCourse"><i class="fa-solid fa-book-open"></i> Course Applying For <span class="required">*</span></label>
              <select id="admCourse" class="form-control" required>
                <option value="">-- Select Course --</option>
                <option value="Class 5th - 8th Foundation">Class 5th - 8th Foundation</option>
                <option value="Class 9th & 10th Boards (CBSE/ICSE/State)">Class 9th & 10th Boards (CBSE/ICSE/State)</option>
                <option value="IIT-JEE Main + Advanced">IIT-JEE Main + Advanced</option>
                <option value="NEET-UG Medical">NEET-UG Medical</option>
                <option value="MH-CET (Engineering / Pharmacy)">MH-CET (Engineering / Pharmacy)</option>
                <option value="11th & 12th Science (HSC/CBSE)">11th & 12th Science (HSC/CBSE)</option>
                <option value="11th & 12th Commerce Section">11th & 12th Commerce Section</option>
                <option value="Degree: B.Com / BAF / BMS / BCA">Degree: B.Com / BAF / BMS / BCA</option>
                <option value="Postgraduate: M.Com / MBA">Postgraduate: M.Com / MBA</option>
              </select>
            </div>

            <div class="form-group col-half">
              <label for="admBatch"><i class="fa-solid fa-clock"></i> Preferred Shift</label>
              <select id="admBatch" class="form-control">
                <option value="Morning Batch (7:30 AM - 11:30 AM)">Morning Shift (7:30 AM - 11:30 AM)</option>
                <option value="Evening Batch (4:00 PM - 8:30 PM)">Evening Shift (4:00 PM - 8:30 PM)</option>
                <option value="Weekend Special">Weekend Special</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="admSchool"><i class="fa-solid fa-school"></i> Current School / Junior College</label>
            <input type="text" id="admSchool" class="form-control" placeholder="e.g. DPS Nerul / Ramsheth Thakur / College in Ulwe">
          </div>

          <div class="form-group">
            <label for="admQuery"><i class="fa-solid fa-message"></i> Remarks or Questions</label>
            <textarea id="admQuery" class="form-control" placeholder="Any specific requirements or questions for our academic team..."></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="fa-solid fa-paper-plane"></i> Submit Admission Application
          </button>
        </form>
      </div>

      <!-- Scholarship & Guidance Column -->
      <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Scholarship Card -->
        <div class="admission-scholarship-card">
          <span class="section-eyebrow" style="background: rgba(255, 255, 255, 0.12); color: #bae6fd; border: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 12px;">
            <i class="fa-solid fa-award"></i> Merit Reward
          </span>
          <h3 style="color: #ffffff; font-size: 1.55rem; font-weight: 800; margin-bottom: 12px;">
            Up to 60% <span style="color: #38bdf8;">Merit Scholarship</span>
          </h3>
          <p style="color: #cbd5e1; font-size: 0.92rem; line-height: 1.6; margin-bottom: 18px;">
            KSV Academy offers scholarships based on merit and performance in our Diagnostic Aptitude Test to ensure every motivated learner accesses top-tier coaching.
          </p>
          <ul style="color: #f1f5f9; font-size: 0.88rem; display: flex; flex-direction: column; gap: 12px; list-style: none; padding: 0; margin: 0;">
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
        <div style="background: var(--bg-surface); padding: 26px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-xs);">
          <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 14px;">
            <i class="fa-solid fa-folder-open" style="color: var(--primary-600); margin-right: 8px;"></i> Required <span class="hero-highlight">Documents</span>
          </h4>
          <ul style="font-size: 0.88rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 10px; list-style: none; padding: 0; margin: 0;">
            <li style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-check" style="color: var(--primary-600); font-size: 0.85rem;"></i>
              <span>Copy of previous academic examination marksheet</span>
            </li>
            <li style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-check" style="color: var(--primary-600); font-size: 0.85rem;"></i>
              <span>2 Passport-sized recent student photographs</span>
            </li>
            <li style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-check" style="color: var(--primary-600); font-size: 0.85rem;"></i>
              <span>Photo ID / Aadhaar card photocopy</span>
            </li>
            <li style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-check" style="color: var(--primary-600); font-size: 0.85rem;"></i>
              <span>Duly completed registration / application form</span>
            </li>
          </ul>
        </div>

        <!-- Counselor Contact Card -->
        <div style="background: var(--bg-surface-soft); padding: 24px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); text-align: center;">
          <h4 style="font-size: 1.12rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">
            Speak with an <span class="hero-highlight">Admission Counselor</span>
          </h4>
          <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
            Need help selecting the best batch or subjects? Talk directly to our academic team.
          </p>
          <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <a href="tel:<?php echo PHONE_RAW_1; ?>" class="btn btn-primary btn-sm" style="flex: 1; min-width: 140px;">
              <i class="fa-solid fa-phone"></i> <?php echo PHONE_PRIMARY; ?>
            </a>
            <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer" style="flex: 1; min-width: 140px;">
              <i class="fa-brands fa-whatsapp"></i> WhatsApp
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Admission FAQs -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-circle-question"></i> Common Inquiries</span>
      <h2 class="section-title">Admission &amp; Scholarship <span class="hero-highlight">FAQs</span></h2>
      <p class="section-subtitle">Clear answers to frequent questions about enrollment, fee installments, scholarships, and batch timings.</p>
    </div>

    <div class="faq-accordion">
      <div class="faq-item active">
        <button type="button" class="faq-header">
          <span class="faq-title">How do I claim the 3-Days Free Demo Class?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            You can register through our online form above, call us at <strong><?php echo PHONE_PRIMARY; ?></strong>, or visit our Sector 19 Ulwe center. You will receive an immediate pass to attend 3 actual classroom lectures before making any financial commitment.
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

<!-- Admission Visit CTA -->
<section class="section-py bg-soft" style="text-align: center;">
  <div class="container">
    <div class="section-header" style="max-width: 720px; margin: 0 auto 30px auto;">
      <span class="section-eyebrow"><i class="fa-solid fa-calendar-check"></i> Center Walk-in</span>
      <h2 class="section-title">Ready to Experience <span class="hero-highlight">KSV Academy?</span></h2>
      <p class="section-subtitle">Walk into our Ulwe Sector 19 campus, meet our educators in person, and reserve your seat for the upcoming batch.</p>
    </div>
    <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
      <button type="button" class="btn btn-primary btn-lg" data-modal="demoModal">
        <i class="fa-regular fa-calendar-check"></i> Claim 3-Days Free Demo Pass
      </button>
      <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-outline-primary btn-lg" target="_blank" rel="noopener noreferrer">
        <i class="fa-brands fa-whatsapp"></i> Chat with Counselor
      </a>
    </div>
  </div>
</section>

<script>
function handleAdmissionSubmit(e) {
  e.preventDefault();
  const form = document.getElementById('onlineAdmissionForm');
  const successState = document.getElementById('admissionSuccessState');
  if (form && successState) {
    form.style.display = 'none';
    successState.style.display = 'block';
    successState.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
