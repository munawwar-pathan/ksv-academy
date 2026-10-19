<?php
require_once __DIR__ . '/config.php';

$active_page = 'faculty';
$page_title = 'Our Expert Faculty | KSV Academy Ulwe | Dedicated Mentors & Subject Specialists';
$page_description = 'Meet the distinguished faculty team at KSV Academy Ulwe. Learn with Mr. Salam Khan (Physics, 10 Yrs), Mr. Rameshwar Mishra (Chemistry, 15 Yrs), Mr. Aman Kumar Soni, Hafiza Palikar, Rafeek Khan & Mrs. Lubna Khan.';
$page_keywords = 'KSV Academy faculty, Salam Khan Physics Ulwe, Rameshwar Mishra Chemistry, Aman Kumar Soni, Hafiza Palikar Mumbai University math, Rafeek Khan CS, Lubna Khan English SST, Ulwe tuition teachers';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Faculty Page Hero Header -->
<section class="section-py-sm page-header-banner">
  <div class="container text-center">
    <span class="section-eyebrow" style="background: rgba(255, 255, 255, 0.1); color: #bae6fd; border: 1px solid rgba(255, 255, 255, 0.18); margin-bottom: 14px;">
      <i class="fa-solid fa-users-viewfinder"></i> Academic Leadership
    </span>
    <h1 style="color: #ffffff; font-size: 2.8rem; font-weight: 800; margin-bottom: 14px; letter-spacing: -0.02em;">
      Our Expert <span class="hero-highlight">Faculty</span>
    </h1>
    <p style="color: #cbd5e1; max-width: 680px; margin: 0 auto; font-size: 1.08rem; line-height: 1.6;">
      "Many dedicated professionals and academics comprise our leadership." — Learn from experienced educators with proven expertise across school boards, engineering, medical, and degree curricula.
    </p>
  </div>
</section>

<!-- Faculty Grid Section -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-chalkboard-user"></i> Master Mentors</span>
      <h2 class="section-title">Meet Our Distinguished <span class="hero-highlight">Educators</span></h2>
      <p class="section-subtitle">Dedicated subject specialists with 10–15+ years of classroom experience guiding students in Ulwe.</p>
    </div>

    <div class="faculty-grid">
      <?php foreach ($faculties as $fac): ?>
      <div class="faculty-card" id="<?php echo htmlspecialchars($fac['id']); ?>">
        <div class="faculty-photo-wrapper">
          <img src="<?php echo url($fac['image']); ?>" alt="<?php echo htmlspecialchars($fac['name']); ?>" class="faculty-photo" loading="lazy">
          <span class="faculty-exp-badge"><i class="fa-solid fa-medal"></i> <?php echo htmlspecialchars($fac['experience']); ?></span>
        </div>
        <div class="faculty-info-block">
          <h3 class="faculty-name"><?php echo htmlspecialchars($fac['name']); ?></h3>
          <div class="faculty-role"><?php echo htmlspecialchars($fac['title']); ?></div>
          <div class="faculty-qualification"><i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($fac['degrees']); ?></div>
          
          <div class="faculty-spec-tag">
            <i class="fa-solid fa-atom"></i> Specialization: <?php echo htmlspecialchars($fac['specialization']); ?>
          </div>

          <p class="faculty-bio"><?php echo htmlspecialchars($fac['bio']); ?></p>

          <div class="faculty-card-footer">
            <span class="faculty-verified-badge">
              <i class="fa-solid fa-circle-check"></i> Verified Specialist
            </span>
            <button type="button" class="btn btn-outline-primary btn-sm" data-modal="demoModal">
              Attend Demo Lecture
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Teaching Methodology -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-lightbulb"></i> Mentorship Style</span>
      <h2 class="section-title">Why Students Love <span class="hero-highlight">Our Faculty</span></h2>
      <p class="section-subtitle">Our teachers don't just deliver lectures; they foster an environment of inquisitive thinking and exam mastery.</p>
    </div>

    <div class="features-grid">
      <div class="methodology-card">
        <div class="methodology-icon"><i class="fa-solid fa-hands-holding-circle"></i></div>
        <h3>Accessible Doubt Clearing</h3>
        <p>Teachers stay back after every lecture to resolve individual doubts. No student leaves the academy with unresolved queries.</p>
      </div>

      <div class="methodology-card">
        <div class="methodology-icon"><i class="fa-solid fa-chart-pie"></i></div>
        <h3>Exam-Oriented Insights</h3>
        <p>Our educators analyze past 10 years exam patterns (CBSE, HSC, NEET, JEE) to highlight high-weightage topics and recurring questions.</p>
      </div>

      <div class="methodology-card">
        <div class="methodology-icon"><i class="fa-solid fa-heart-pulse"></i></div>
        <h3>Continuous Motivation</h3>
        <p>Frequent motivational sessions and stress-management guidance by Director Mr. Salam Khan keep students inspired and disciplined.</p>
      </div>
    </div>
  </div>
</section>

<!-- Demo Class CTA -->
<section class="section-py" style="text-align: center;">
  <div class="container">
    <div class="section-header" style="max-width: 720px; margin: 0 auto 30px auto;">
      <span class="section-eyebrow"><i class="fa-solid fa-graduation-cap"></i> Attend A Free Class</span>
      <h2 class="section-title">Experience Learning from <span class="hero-highlight">Our Star Educators</span></h2>
      <p class="section-subtitle">Register for a 3-Days Free Demo Class and interact directly with our teachers at our Ulwe Sector 19 center.</p>
    </div>
    <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
      <button type="button" class="btn btn-primary btn-lg" data-modal="demoModal">
        <i class="fa-regular fa-calendar-check"></i> Claim 3-Days Free Demo Pass
      </button>
      <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-outline-primary btn-lg" target="_blank" rel="noopener noreferrer">
        <i class="fa-brands fa-whatsapp"></i> Inquire via WhatsApp
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
