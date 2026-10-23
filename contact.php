<?php
require_once __DIR__ . '/config.php';

$active_page = 'contact';
$page_title = 'Contact & Student Helpdesk | KSV Academy Ulwe Navi Mumbai';
$page_description = 'Get in touch with KSV Academy Ulwe. Address: MGM Residency, Shop No.02, Plot No.161, opp. Om Sai Hospital, Sector 19, Ulwe, Navi Mumbai. Phone: +91 79059 00350 / +91 99840 58406.';
$page_keywords = 'KSV Academy contact, KSV Academy phone number, KSV Academy address Ulwe, tuition near Om Sai hospital Ulwe, coaching in sector 19 Ulwe';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Contact Hero Banner (Student Support and Helpdesk) -->
<section class="section-py-sm page-header-banner">
  <div class="container text-center">
    <span class="section-eyebrow" style="background: rgba(255, 255, 255, 0.1); color: #bae6fd; border: 1px solid rgba(255, 255, 255, 0.18); margin-bottom: 14px;">
      <i class="fa-solid fa-headset"></i> Dedicated Assistance
    </span>
    <h1 style="color: #ffffff; font-size: 2.8rem; font-weight: 800; margin-bottom: 14px; letter-spacing: -0.02em;">
      Student Support &amp; <span class="hero-highlight">Helpdesk</span>
    </h1>
    <p style="color: #cbd5e1; max-width: 680px; margin: 0 auto; font-size: 1.08rem; line-height: 1.6;">
      We are here to assist parents and students with course guidance, batch timings, scholarships, and center admissions.
    </p>
  </div>
</section>

<!-- Contact Form & Info Cards -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-envelope-open-text"></i> Connect With Us</span>
      <h2 class="section-title">Get in Touch with <span class="hero-highlight">Our Counseling Team</span></h2>
      <p class="section-subtitle">Have queries regarding admissions, scholarships, or syllabus coverage? Reach out to us directly or visit our Ulwe center.</p>
    </div>

    <div style="display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 36px; align-items: start;" class="about-top-grid">
      <!-- Contact Form Column -->
      <div style="background: var(--bg-surface); padding: 36px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-sm);">
        <h3 style="font-size: 1.55rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">
          Send an <span class="hero-highlight">Inquiry</span>
        </h3>
        <p style="color: var(--text-muted); font-size: 0.92rem; margin-bottom: 24px;">
          Fill out the details below and our academic counseling desk will connect with you promptly.
        </p>

        <div id="contactSuccessAlert" class="form-success-message" style="display: none; background: var(--bg-surface-soft); border-radius: var(--radius-lg); margin-bottom: 20px;">
          <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
          <h3>Message Received!</h3>
          <p>Thank you for reaching out to KSV Academy. We will respond to your inquiry via phone/WhatsApp shortly.</p>
        </div>

        <form id="contactForm" class="interactive-form" method="POST" action="/submit-form" onsubmit="handleContactSubmit(event)">
          <input type="hidden" name="form_type" value="inquiry">
          <div class="form-row">
            <div class="form-group col-half">
              <label for="contactName"><i class="fa-solid fa-user"></i> Full Name <span class="required">*</span></label>
              <input type="text" id="contactName" name="full_name" class="form-control" placeholder="Your full name" required>
            </div>
            <div class="form-group col-half">
              <label for="contactPhone"><i class="fa-solid fa-phone"></i> Phone Number <span class="required">*</span></label>
              <input type="tel" id="contactPhone" name="phone" class="form-control" placeholder="10-digit mobile number" pattern="[0-9]{10}" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-half">
              <label for="contactEmail"><i class="fa-solid fa-envelope"></i> Email Address <span class="required">*</span></label>
              <input type="email" id="contactEmail" name="email" class="form-control" placeholder="yourname@gmail.com" required>
            </div>
            <div class="form-group col-half">
              <label for="contactQueryType"><i class="fa-solid fa-list-check"></i> Query Type <span class="required">*</span></label>
              <select id="contactQueryType" name="query_type" class="form-control" required>
                <option value="">Please Select</option>
                <option value="Admission Enquiry">Admission Enquiry</option>
                <option value="Book Free 3-Days Demo Class">Book Free 3-Days Demo Class</option>
                <option value="Fees & Scholarship Assessment">Fees & Scholarship Assessment</option>
                <option value="Course Syllabus & Batches">Course Syllabus & Batches</option>
                <option value="Academic Counseling">Academic Counseling</option>
                <option value="General Support">General Support</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="contactMessage"><i class="fa-solid fa-comment-dots"></i> Message / Details</label>
            <textarea id="contactMessage" name="message" class="form-control" placeholder="Write your question, student standard, or any specific requirements here..."></textarea>
          </div>

          <button type="submit" id="contactSubmitBtn" class="btn btn-primary btn-block btn-lg">
            <i class="fa-solid fa-paper-plane"></i> Submit Inquiry
          </button>
        </form>
      </div>

      <!-- Campus Details & Contact Information Cards -->
      <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Address Card -->
        <div style="background: var(--bg-surface); padding: 24px 26px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-xs);">
          <div style="display: flex; gap: 16px; align-items: flex-start;">
            <div class="contact-sober-icon"><i class="fa-solid fa-location-dot"></i></div>
            <div>
              <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">Academy <span class="hero-highlight">Location</span></h4>
              <p style="font-size: 0.90rem; color: var(--text-muted); line-height: 1.55; margin: 0 0 6px 0;">
                <?php echo ADDRESS_STREET; ?>,<br>
                <?php echo ADDRESS_LOCALITY; ?>, <?php echo ADDRESS_PINCODE; ?>,<br>
                <?php echo ADDRESS_STATE; ?>
              </p>
              <span class="faculty-spec-tag" style="margin-top: 4px;">
                <i class="fa-solid fa-landmark"></i> Opposite Om Sai Hospital
              </span>
            </div>
          </div>
        </div>

        <!-- Visiting Hours Card -->
        <div style="background: var(--bg-surface); padding: 24px 26px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-xs);">
          <div style="display: flex; gap: 16px; align-items: flex-start;">
            <div class="contact-sober-icon"><i class="fa-solid fa-clock"></i></div>
            <div>
              <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">Visiting <span class="hero-highlight">Hours</span></h4>
              <p style="font-size: 0.90rem; color: var(--text-muted); line-height: 1.55; margin: 0;">
                <strong>Monday – Saturday:</strong> 8:00 AM – 9:00 PM<br>
                <strong>Sunday:</strong> 9:00 AM – 2:00 PM (Admission Desk Open)
              </p>
            </div>
          </div>
        </div>

        <!-- Phone Card -->
        <div style="background: var(--bg-surface); padding: 24px 26px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-xs);">
          <div style="display: flex; gap: 16px; align-items: flex-start;">
            <div class="contact-sober-icon"><i class="fa-solid fa-phone-volume"></i></div>
            <div>
              <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">Call Us <span class="hero-highlight">Directly</span></h4>
              <p style="font-size: 0.92rem; font-weight: 700; color: var(--text-main); line-height: 1.6; margin: 0 0 4px 0;">
                <a href="tel:<?php echo PHONE_RAW_1; ?>" style="color: var(--primary-700);"><?php echo PHONE_PRIMARY; ?></a><br>
                <a href="tel:<?php echo PHONE_RAW_2; ?>" style="color: var(--primary-700);"><?php echo PHONE_SECONDARY; ?></a>
              </p>
              <small style="color: var(--text-muted);">Available 7 days a week for admissions</small>
            </div>
          </div>
        </div>

        <!-- Email & WhatsApp Card -->
        <div style="background: var(--bg-surface); padding: 24px 26px; border-radius: var(--radius-xl); border: 1px solid var(--border-subtle); box-shadow: var(--shadow-xs);">
          <div style="display: flex; gap: 16px; align-items: flex-start;">
            <div class="contact-sober-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
            <div>
              <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">Email &amp; <span class="hero-highlight">WhatsApp</span></h4>
              <p style="font-size: 0.90rem; color: var(--text-muted); line-height: 1.55; margin: 0;">
                <strong>Email:</strong> <a href="mailto:<?php echo EMAIL_PRIMARY; ?>" style="color: var(--primary-700);"><?php echo EMAIL_PRIMARY; ?></a><br>
                <strong>WhatsApp:</strong> <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary-700);">+91 79059 00350</a>
              </p>
            </div>
          </div>
        </div>

        <!-- Social Media Card -->
        <div style="background: var(--gradient-hero); color: #ffffff; padding: 24px 26px; border-radius: var(--radius-xl); display: flex; align-items: center; justify-content: space-between; gap: 16px; border: 1px solid rgba(208, 234, 247, 0.15); box-shadow: var(--shadow-xs);">
          <div>
            <h4 style="color: #ffffff; font-size: 1.10rem; font-weight: 700; margin-bottom: 4px;">Follow <span style="color: #38bdf8;">KSV Academy</span></h4>
            <p style="font-size: 0.82rem; color: #cbd5e1; margin: 0;">Lectures, tips &amp; student achievements</p>
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

<!-- Google Maps Location Embed -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-map-pin"></i> Find Us in Ulwe</span>
      <h2 class="section-title">Visit Our Academy <span class="hero-highlight">Center in Ulwe</span></h2>
      <p class="section-subtitle">Conveniently situated at MGM Residency, Sector 19, Ulwe — easily accessible across Navi Mumbai.</p>
    </div>

    <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md); border: 1px solid var(--border-subtle); height: 420px; position: relative;">
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
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
