<?php
require_once __DIR__ . '/config.php';

// Send official 404 HTTP Status Code
http_response_code(404);

$active_page = '404';
$page_title = '404 - Page Not Found | KSV Academy Ulwe';
$page_description = 'The page you are looking for does not exist or has moved. Explore KSV Academy coaching courses, JEE/NEET entrance batches, star faculty, or return to our homepage.';
$page_keywords = '404 error, page not found, KSV Academy, Ulwe coaching';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 404 Error Section -->
<section class="section-py error-404-section">
  <div class="container text-center">
    <div class="error-404-card">
      <!-- Ambient Glow Behind 404 -->
      <div class="error-glow" aria-hidden="true"></div>

      <!-- Error Eyebrow Pill -->
      <div class="error-eyebrow-wrap">
        <span class="error-pill-badge">
          <i class="fa-solid fa-triangle-exclamation"></i> Error 404 • Page Not Found
        </span>
      </div>

      <!-- Visual 404 Display -->
      <div class="error-big-code">
        <span class="error-num">4</span>
        <div class="error-icon-box">
          <i class="fa-solid fa-compass-drafting"></i>
          <span class="error-pulse-ring"></span>
        </div>
        <span class="error-num">4</span>
      </div>

      <!-- Main Headline & Description -->
      <h1 class="error-title">
        Looks Like This Concept Is <span class="hero-highlight">Off The Syllabus!</span>
      </h1>
      <p class="error-desc">
        We couldn’t find the page you were looking for. It might have been moved, renamed, or is temporarily unavailable. Let’s get you back on track toward academic excellence.
      </p>

      <!-- Primary Action Buttons -->
      <div class="error-actions-row">
        <a href="<?php echo url(); ?>" class="btn btn-primary btn-lg">
          <i class="fa-solid fa-house"></i>
          <span>Back to Homepage</span>
        </a>
        <a href="<?php echo url('courses'); ?>" class="btn btn-outline-primary btn-lg">
          <i class="fa-solid fa-book-open"></i>
          <span>Explore All Courses</span>
        </a>
        <button type="button" class="btn btn-gold btn-lg" data-modal="demoModal">
          <i class="fa-solid fa-calendar-check"></i>
          <span>Book Free Demo</span>
        </button>
      </div>

      <!-- Helpful Quick Navigation Grid -->
      <div class="error-quick-links-box">
        <div class="quick-links-header">
          <i class="fa-solid fa-bolt"></i>
          <span>Popular Academic Destinations</span>
        </div>
        
        <div class="error-quick-grid">
          <a href="<?php echo url('courses?filter=school'); ?>" class="error-quick-card">
            <div class="eq-icon em-blue"><i class="fa-solid fa-school"></i></div>
            <div class="eq-text">
              <strong>School Section</strong>
              <span>Classes 5th – 10th (CBSE / ICSE / State)</span>
            </div>
            <i class="fa-solid fa-chevron-right eq-arrow"></i>
          </a>

          <a href="<?php echo url('courses?filter=competitive'); ?>" class="error-quick-card">
            <div class="eq-icon em-purple"><i class="fa-solid fa-atom"></i></div>
            <div class="eq-text">
              <strong>IIT-JEE &amp; NEET-UG</strong>
              <span>Medical &amp; Engineering Entrances</span>
            </div>
            <i class="fa-solid fa-chevron-right eq-arrow"></i>
          </a>

          <a href="<?php echo url('faculty'); ?>" class="error-quick-card">
            <div class="eq-icon em-gold"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div class="eq-text">
              <strong>Master Mentors</strong>
              <span>Learn from Director Mr. Salam Khan</span>
            </div>
            <i class="fa-solid fa-chevron-right eq-arrow"></i>
          </a>

          <a href="<?php echo url('gallery'); ?>" class="error-quick-card">
            <div class="eq-icon em-emerald"><i class="fa-solid fa-camera"></i></div>
            <div class="eq-text">
              <strong>Campus &amp; Classrooms</strong>
              <span>AC Smart Classrooms &amp; Study Library</span>
            </div>
            <i class="fa-solid fa-chevron-right eq-arrow"></i>
          </a>

          <a href="<?php echo url('admissions'); ?>" class="error-quick-card">
            <div class="eq-icon em-cyan"><i class="fa-solid fa-award"></i></div>
            <div class="eq-text">
              <strong>Admissions &amp; Scholarship</strong>
              <span>Up to 60% K-SAT Merit Scholarship</span>
            </div>
            <i class="fa-solid fa-chevron-right eq-arrow"></i>
          </a>

          <a href="<?php echo url('contact'); ?>" class="error-quick-card">
            <div class="eq-icon em-rose"><i class="fa-solid fa-headset"></i></div>
            <div class="eq-text">
              <strong>Student Helpdesk</strong>
              <span>Ulwe Sector 19 Campus &amp; Support</span>
            </div>
            <i class="fa-solid fa-chevron-right eq-arrow"></i>
          </a>
        </div>
      </div>

      <!-- Direct Counselor WhatsApp Assistance -->
      <div class="error-support-strip">
        <i class="fa-brands fa-whatsapp support-wa-icon"></i>
        <span>Need urgent assistance? Chat with our admission counselor on WhatsApp:</span>
        <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener noreferrer" class="support-wa-btn">
          <span>+91 79059 00350</span>
          <i class="fa-solid fa-arrow-up-right-from-square"></i>
        </a>
      </div>
    </div>
  </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

