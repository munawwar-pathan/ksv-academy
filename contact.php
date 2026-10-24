<?php
require_once __DIR__ . '/config.php';

$active_page = 'contact';
$page_title = 'Contact & Student Helpdesk | KSV Academy Ulwe Navi Mumbai';
$page_description = 'Get in touch with KSV Academy Ulwe. Address: MGM Residency, Shop No.02, Plot No.161, opp. Om Sai Hospital, Sector 19, Ulwe, Navi Mumbai. Phone: +91 79059 00350 / +91 99840 58406.';
$page_keywords = 'KSV Academy contact, KSV Academy phone number, KSV Academy address Ulwe, tuition near Om Sai hospital Ulwe, coaching in sector 19 Ulwe';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Contact Hero Header Banner (Attractive Design Matching Home & About) -->
<section class="about-hero-banner">
  <div class="container text-center">
    <div class="about-banner-content">
      <!-- Dual Eyebrows -->
      <div class="hero-eyebrows-wrap" style="justify-content: center; margin-bottom: 16px;">
        <div class="hero-eyebrow">
          <span class="hero-live-dot"></span>
          <span>Student Support &amp; Helpdesk • Session <?php echo CURRENT_SESSION; ?></span>
        </div>
        <div class="hero-trust-badge">
          <i class="fa-solid fa-headset"></i>
          <span>Open 7 Days a Week in Ulwe</span>
        </div>
      </div>

      <!-- Main Heading -->
      <h1 class="about-banner-title">
        Student Support &amp; <span class="hero-highlight">Counseling Helpdesk</span>
      </h1>

      <!-- Slogan / Motto Tagline -->
      <div class="hero-motto-row" style="justify-content: center; margin-bottom: 16px;">
        <span class="motto-lead"><i class="fa-solid fa-sparkles"></i> Direct Access to Academic Counselors</span>
        <span class="motto-divider">•</span>
        <span class="motto-sub">Admissions, Scholarships &amp; Free Demo Classes</span>
      </div>

      <!-- Lead Description -->
      <p class="about-banner-desc">
        We are here to assist parents and students with course guidance, batch timings, scholarships, and center admissions at our Ulwe Sector 19 campus.
      </p>

      <!-- Quick Stats Counter Strip -->
      <div class="hero-stats-row" style="max-width: 760px; margin: 0 auto;">
        <div class="stat-item">
          <span class="stat-number" data-target="15" data-suffix=" Min">15 Min</span>
          <span class="stat-label">Fast Callback</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="7" data-suffix=" Days">7 Days</span>
          <span class="stat-label">Helpdesk Open</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="100" data-suffix="%">100%</span>
          <span class="stat-label">Free Demo Pass</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="19" data-prefix="Sec " data-suffix="">Sec 19</span>
          <span class="stat-label">Prime Ulwe Campus</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. Contact Form & Campus Details Column -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-envelope-open-text"></i> Connect With Us</span>
      <h2 class="section-title">Get in Touch with <span class="hero-highlight">Our Counseling Team</span></h2>
      <p class="section-subtitle">Have queries regarding admissions, scholarships, or syllabus coverage? Reach out to us directly or visit our Ulwe center.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 36px; align-items: start;" class="about-top-grid">
      <!-- Contact Form Column -->
      <div style="background: var(--bg-card); padding: 36px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-sm); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0284c7, #0092df, #38bdf8);"></div>

        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
          <h3 style="font-size: 1.45rem; font-weight: 800; color: var(--text-main); margin: 0;">
            Send an <span class="hero-highlight">Inquiry</span>
          </h3>
          <span class="badge-tag" style="background: rgba(0, 146, 223, 0.08); color: var(--primary-700); border: 1px solid rgba(0, 146, 223, 0.18); font-size: 0.76rem; font-weight: 700; padding: 4px 10px; border-radius: 999px;">
            <i class="fa-solid fa-bolt"></i> Quick Response Desk
          </span>
        </div>
        <p style="color: var(--text-muted); font-size: 0.90rem; margin-bottom: 24px; line-height: 1.55;">
          Fill out the details below and our academic counseling desk will connect with you promptly.
        </p>

        <div id="contactSuccessAlert" class="form-success-message" style="display: none; padding: 26px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 16px; text-align: center; margin-bottom: 20px;">
          <div class="success-icon" style="font-size: 2.2rem; color: #10b981; margin-bottom: 10px;"><i class="fa-solid fa-circle-check"></i></div>
          <h3 style="color: var(--text-main); font-size: 1.25rem; font-weight: 800; margin-bottom: 6px;">Message Received!</h3>
          <p style="color: var(--text-muted); font-size: 0.90rem; margin: 0 0 16px;">Thank you for reaching out to KSV Academy. We will respond to your inquiry via phone/WhatsApp shortly.</p>
          <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-whatsapp btn-sm" target="_blank" rel="noopener noreferrer" style="background: #25d366; color: #ffffff; font-weight: 700;">
            <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp Now
          </a>
        </div>

        <form id="contactForm" class="interactive-form" method="POST" action="/submit-form" onsubmit="handleContactSubmit(event)">
          <input type="hidden" name="form_type" value="inquiry">
          <div class="form-row">
            <div class="form-group col-half">
              <label for="contactName"><i class="fa-solid fa-user"></i> Full Name <span class="required">*</span></label>
              <input type="text" id="contactName" name="full_name" class="form-control" placeholder="e.g. Rahul Sharma" required>
            </div>
            <div class="form-group col-half">
              <label for="contactPhone"><i class="fa-solid fa-phone"></i> Phone Number <span class="required">*</span></label>
              <input type="tel" id="contactPhone" name="phone" class="form-control" placeholder="10-digit mobile number" pattern="[0-9]{10}" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-half">
              <label for="contactEmail"><i class="fa-solid fa-envelope"></i> Email Address <span class="required">*</span></label>
              <input type="email" id="contactEmail" name="email" class="form-control" placeholder="name@example.com" required>
            </div>
            <div class="form-group col-half">
              <label for="contactQueryType"><i class="fa-solid fa-list-check"></i> Query Type <span class="required">*</span></label>
              <select id="contactQueryType" name="query_type" class="form-control" required>
                <option value="">-- Please Select --</option>
                <option value="Admission Enquiry">Admission Enquiry</option>
                <option value="Book Free 3-Days Demo Class">Book Free 3-Days Demo Class</option>
                <option value="Fees & Scholarship Assessment">Fees &amp; Scholarship Assessment</option>
                <option value="Course Syllabus & Batches">Course Syllabus &amp; Batches</option>
                <option value="Academic Counseling">Academic Counseling</option>
                <option value="General Support">General Support</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="contactMessage"><i class="fa-solid fa-comment-dots"></i> Message / Details</label>
            <textarea id="contactMessage" name="message" class="form-control" placeholder="Write your question, student standard, or any specific requirements here..."></textarea>
          </div>

          <button type="submit" id="contactSubmitBtn" class="btn btn-primary btn-block btn-lg" style="box-shadow: 0 6px 20px rgba(0, 146, 223, 0.35);">
            <i class="fa-solid fa-paper-plane"></i> Submit Inquiry
          </button>
        </form>
      </div>

      <!-- Campus Details & Contact Information Cards -->
      <div style="display: flex; flex-direction: column; gap: 18px;">
        <!-- Address Card -->
        <div class="contact-info-card">
          <div class="contact-sober-icon icon-blue"><i class="fa-solid fa-location-dot"></i></div>
          <div style="flex-grow: 1;">
            <h4 style="font-size: 1.12rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Academy <span class="hero-highlight">Location</span></h4>
            <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin: 0 0 8px 0;">
              <?php echo ADDRESS_STREET; ?>,<br>
              <?php echo ADDRESS_LOCALITY; ?>, <?php echo ADDRESS_PINCODE; ?>,<br>
              <?php echo ADDRESS_STATE; ?>
            </p>
            <span class="faculty-spec-tag" style="font-size: 0.74rem;">
              <i class="fa-solid fa-landmark"></i> Opposite Om Sai Hospital
            </span>
          </div>
        </div>

        <!-- Visiting Hours Card -->
        <div class="contact-info-card">
          <div class="contact-sober-icon icon-emerald"><i class="fa-solid fa-clock"></i></div>
          <div style="flex-grow: 1;">
            <h4 style="font-size: 1.12rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Visiting <span class="hero-highlight">Hours</span></h4>
            <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin: 0 0 8px 0;">
              <strong>Monday – Saturday:</strong> 8:00 AM – 9:00 PM<br>
              <strong>Sunday:</strong> 9:00 AM – 2:00 PM (Admission Desk Open)
            </p>
            <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.74rem; font-weight: 700; color: #059669; background: rgba(16, 185, 129, 0.08); padding: 3px 8px; border-radius: 6px;">
              <i class="fa-solid fa-circle-check"></i> Admissions Open 7 Days
            </span>
          </div>
        </div>

        <!-- Phone Card -->
        <div class="contact-info-card">
          <div class="contact-sober-icon icon-amber"><i class="fa-solid fa-phone-volume"></i></div>
          <div style="flex-grow: 1;">
            <h4 style="font-size: 1.12rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Call Us <span class="hero-highlight">Directly</span></h4>
            <p style="font-size: 0.90rem; font-weight: 700; color: var(--text-main); line-height: 1.6; margin: 0 0 6px 0;">
              <a href="tel:<?php echo PHONE_RAW_1; ?>" style="color: var(--primary-700);"><i class="fa-solid fa-phone"></i> <?php echo PHONE_PRIMARY; ?></a><br>
              <a href="tel:<?php echo PHONE_RAW_2; ?>" style="color: var(--primary-700);"><i class="fa-solid fa-phone"></i> <?php echo PHONE_SECONDARY; ?></a>
            </p>
            <small style="color: var(--text-muted); font-size: 0.78rem;">Available 7 days a week for admissions guidance</small>
          </div>
        </div>

        <!-- Email & WhatsApp Card -->
        <div class="contact-info-card">
          <div class="contact-sober-icon icon-purple"><i class="fa-solid fa-envelope-open-text"></i></div>
          <div style="flex-grow: 1;">
            <h4 style="font-size: 1.12rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Email &amp; <span class="hero-highlight">WhatsApp</span></h4>
            <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin: 0 0 6px 0;">
              <strong>Email:</strong> <a href="mailto:<?php echo EMAIL_PRIMARY; ?>" style="color: var(--primary-700);"><?php echo EMAIL_PRIMARY; ?></a><br>
              <strong>WhatsApp:</strong> <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-700);">+91 79059 00350</a>
            </p>
          </div>
        </div>

        <!-- Social Media Card -->
        <div style="background: linear-gradient(135deg, #023859 0%, #02457a 45%, #0077b6 80%, #0092df 100%); color: #ffffff; padding: 22px 26px; border-radius: var(--radius-xl); display: flex; align-items: center; justify-content: space-between; gap: 16px; border: 1px solid rgba(255, 255, 255, 0.18); box-shadow: 0 10px 24px -6px rgba(0, 146, 223, 0.25);">
          <div>
            <h4 style="color: #ffffff; font-size: 1.05rem; font-weight: 800; margin-bottom: 4px;">Follow <span style="color: #38bdf8;">KSV Academy</span></h4>
            <p style="font-size: 0.80rem; color: rgba(255, 255, 255, 0.85); margin: 0;">Lectures, updates &amp; student results</p>
          </div>
          <div style="display: flex; gap: 10px;">
            <a href="<?php echo URL_YOUTUBE; ?>" target="_blank" rel="noopener noreferrer" class="social-circle yt" aria-label="YouTube">
              <i class="fa-brands fa-youtube"></i>
            </a>
            <a href="<?php echo URL_INSTAGRAM; ?>" target="_blank" rel="noopener noreferrer" class="social-circle ig" aria-label="Instagram">
              <i class="fa-brands fa-instagram"></i>
            </a>
            <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener noreferrer" class="social-circle wa" aria-label="WhatsApp">
              <i class="fa-brands fa-whatsapp"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 3. Google Maps Location Embed -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-map-pin"></i> Find Us in Ulwe</span>
      <h2 class="section-title">Visit Our Academy <span class="hero-highlight">Center in Ulwe</span></h2>
      <p class="section-subtitle">Conveniently situated at MGM Residency, Sector 19, Ulwe — easily accessible across Navi Mumbai.</p>
    </div>

    <div style="border-radius: 24px; overflow: hidden; box-shadow: 0 16px 36px -8px rgba(15, 23, 42, 0.12); border: 1px solid var(--border-subtle); height: 420px; position: relative;">
      <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15090.222728956908!2d73.018!3d18.9734!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3be7c393844d18ff%3A0x6bfae8bf5628bb98!2sSector%2019%2C%20Ulwe%2C%20Navi%20Mumbai%2C%20Maharashtra%20410206!5e0!3m2!1sen!2sin!4v1700000000000!5m2!1sen!2sin"
        width="100%"
        height="100%"
        style="border:0;"
        allowfullscreen=""
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        title="KSV Academy Location Map">
      </iframe>
    </div>

    <!-- Quick Location Action Bar below Map -->
    <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 24px;">
      <a href="https://maps.google.com/?q=Sector+19+Ulwe+Navi+Mumbai" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-lg" style="box-shadow: 0 4px 16px rgba(0, 146, 223, 0.25);">
        <i class="fa-solid fa-diamond-turn-right"></i> Open in Google Maps
      </a>
      <button type="button" class="btn btn-outline-primary btn-lg" data-modal="demoModal">
        <i class="fa-regular fa-calendar-check"></i> Book Campus Tour / Demo
      </button>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
