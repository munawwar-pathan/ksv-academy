<?php
require_once __DIR__ . '/config.php';

$active_page = 'courses';
$page_title = 'Courses & Batches | KSV Academy Ulwe | School, College, JEE, NEET, CET, Degree';
$page_description = 'Explore full course programs at KSV Academy Ulwe. Classes 5th-10th (CBSE/ICSE/State), IIT-JEE Main & Adv, NEET, MH-CET, 11th-12th Science & Commerce, B.Com, BMS, BCA & M.Com.';
$page_keywords = 'KSV Academy courses, IIT JEE coaching Ulwe, NEET tuition Ulwe, 10th CBSE classes, 12th science commerce coaching, BCom BMS BCA coaching Ulwe';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Courses Header Hero -->
<section class="section-py-sm page-header-banner">
  <div class="container text-center">
    <span class="section-eyebrow" style="background: rgba(255, 255, 255, 0.1); color: #bae6fd; border: 1px solid rgba(255, 255, 255, 0.18); margin-bottom: 14px;">
      <i class="fa-solid fa-graduation-cap"></i> Complete Academic Spectrum
    </span>
    <h1 style="color: #ffffff; font-size: 2.8rem; font-weight: 800; margin-bottom: 14px; letter-spacing: -0.02em;">
      Courses &amp; <span class="hero-highlight">Batches</span>
    </h1>
    <p style="color: #cbd5e1; max-width: 700px; margin: 0 auto; font-size: 1.08rem; line-height: 1.6;">
      Structured, concept-driven coaching engineered for board rankers, medical and engineering aspirants, and university commerce &amp; technology graduates in Ulwe.
    </p>
  </div>
</section>

<!-- Filterable Explorer -->
<section class="section-py" id="coursesSection">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-layer-group"></i> Academic Programs</span>
      <h2 class="section-title">Explore Our <span class="hero-highlight">Curriculum &amp; Batches</span></h2>
      <p class="section-subtitle">Structured, concept-driven coaching engineered for school toppers, competitive aspirants, and degree students in Ulwe.</p>
    </div>

    <!-- Filter Tabs -->
    <div class="courses-tab-nav">
      <button type="button" class="course-tab-btn active" data-filter="all">All Courses</button>
      <button type="button" class="course-tab-btn" data-filter="school">School Section (5th - 10th)</button>
      <button type="button" class="course-tab-btn" data-filter="competitive">IIT-JEE / NEET / MH-CET</button>
      <button type="button" class="course-tab-btn" data-filter="college">Junior College (11th &amp; 12th)</button>
      <button type="button" class="course-tab-btn" data-filter="degree">Undergraduate &amp; Degree</button>
      <button type="button" class="course-tab-btn" data-filter="postgraduate">Postgraduate</button>
    </div>

    <!-- Courses Detailed Grid -->
    <div class="courses-grid">
      <?php foreach ($courses as $c): ?>
      <div class="course-card" data-category="<?php echo htmlspecialchars($c['category']); ?>" id="<?php echo htmlspecialchars($c['id']); ?>">
        <div class="course-card-top">
          <span class="course-cat-tag"><?php echo htmlspecialchars($c['category_name']); ?></span>
          <span class="course-status-pill"><?php echo htmlspecialchars($c['badge']); ?></span>
        </div>

        <div class="course-card-main">
          <h3 class="course-title"><?php echo htmlspecialchars($c['title']); ?></h3>
          <div class="course-standards">
            <i class="fa-solid fa-graduation-cap"></i>
            <span><?php echo htmlspecialchars($c['standards']); ?></span>
          </div>
          <p class="course-desc"><?php echo htmlspecialchars($c['description']); ?></p>
        </div>

        <div class="course-card-specs">
          <div class="course-spec-item">
            <i class="fa-solid fa-layer-group"></i>
            <span><strong>Boards:</strong> <?php echo htmlspecialchars($c['boards']); ?></span>
          </div>
          <div class="course-spec-item">
            <i class="fa-solid fa-flask-vial"></i>
            <span><strong>Subjects:</strong> <?php echo htmlspecialchars($c['subjects']); ?></span>
          </div>
          <div class="course-spec-item">
            <i class="fa-solid fa-clock"></i>
            <span><strong>Batch Timings:</strong> Morning (8:00 AM) &amp; Evening (4:00 PM)</span>
          </div>
        </div>

        <div class="course-highlights-box">
          <div class="course-highlights-heading">
            <i class="fa-solid fa-circle-check"></i>
            <span>Program Highlights</span>
          </div>
          <ul class="course-checklist">
            <?php foreach ($c['features'] as $f): ?>
            <li>
              <i class="fa-solid fa-check"></i>
              <span><?php echo htmlspecialchars($f); ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="course-card-footer">
          <button type="button" class="btn btn-primary btn-block" data-modal="demoModal">
            <i class="fa-solid fa-ticket"></i> Book Free Demo / Enroll
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Subject Specialization Focus Cards -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-microscope"></i> Subject Mastery</span>
      <h2 class="section-title">Deep Conceptual Focus by <span class="hero-highlight">Subject</span></h2>
      <p class="section-subtitle">How our senior faculties teach the core pillars of science, mathematics, commerce, and language.</p>
    </div>

    <div class="features-grid">
      <div class="subject-focus-card">
        <div class="subject-focus-icon"><i class="fa-solid fa-bolt"></i></div>
        <h3>Physics (Mechanics, Optics &amp; Electrodynamics)</h3>
        <p>
          Guided by <strong>Mr. Salam Khan (10+ Yrs Exp)</strong>. Physics is taught with real-world physical models and intuitive problem breakdowns, turning fear into scoring confidence.
        </p>
      </div>

      <div class="subject-focus-card">
        <div class="subject-focus-icon"><i class="fa-solid fa-flask"></i></div>
        <h3>Chemistry (Organic, Physical &amp; Inorganic)</h3>
        <p>
          Guided by <strong>Mr. Rameshwar Mishra (15+ Yrs Exp)</strong> &amp; <strong>Mr. Aman Kumar Soni</strong>. Demystifying reaction mechanisms, periodic trends, and chemical thermodynamics.
        </p>
      </div>

      <div class="subject-focus-card">
        <div class="subject-focus-icon"><i class="fa-solid fa-calculator"></i></div>
        <h3>Mathematics (Calculus, Algebra &amp; Geometry)</h3>
        <p>
          Mentored by <strong>Hafiza Palikar (M.Sc Mumbai Univ)</strong> &amp; <strong>Rafeek Khan</strong>. Step-by-step proofs, shortcut speed techniques, and rigorous NCERT &amp; exemplar drilling.
        </p>
      </div>

      <div class="subject-focus-card">
        <div class="subject-focus-icon"><i class="fa-solid fa-chart-pie"></i></div>
        <h3>Commerce, Accounts &amp; Economics</h3>
        <p>
          Comprehensive coverage for 11th, 12th, and Degree (B.Com, BAF, BMS) covering Double Entry Bookkeeping, Corporate Accounting, Direct/Indirect Tax, and Financial Analysis.
        </p>
      </div>

      <div class="subject-focus-card">
        <div class="subject-focus-icon"><i class="fa-solid fa-laptop-code"></i></div>
        <h3>Computer Science &amp; Applications (BCA)</h3>
        <p>
          Led by <strong>Rafeek Khan (M.Sc Computer Science)</strong>. Practical programming, discrete maths, database management, and university semester project guidance.
        </p>
      </div>

      <div class="subject-focus-card">
        <div class="subject-focus-icon"><i class="fa-solid fa-book"></i></div>
        <h3>English, SST &amp; Management Studies</h3>
        <p>
          Guided by <strong>Mrs. Lubna Khan (BMS)</strong>. Precision answer-writing frameworks for Social Studies, English Grammar, Literature comprehension, and Management subjects.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Scholarship CTA Banner -->
<section class="section-py">
  <div class="container">
    <div class="director-feature-box scholarship-cta-box" style="text-align: center; padding: 50px 32px;">
      <div style="max-width: 780px; margin: 0 auto;">
        <span class="section-eyebrow" style="background: rgba(255, 255, 255, 0.1); color: #bae6fd; border: 1px solid rgba(255, 255, 255, 0.18); margin-bottom: 16px;">
          <i class="fa-solid fa-hand-holding-dollar"></i> Scholarship Opportunity
        </span>
        <h2 style="font-size: 2.3rem; font-weight: 800; margin-bottom: 16px; color: #ffffff; line-height: 1.25;">
          Merit Scholarship Up to <span style="color: #38bdf8;">60% for Session <?php echo CURRENT_SESSION; ?></span>
        </h2>
        <p style="font-size: 1.08rem; color: #cbd5e1; margin-bottom: 28px; line-height: 1.65; max-width: 680px; margin-left: auto; margin-right: auto;">
          Don't let financial constraints hinder your ambition. Appear for the KSV Academy Assessment Test or present your previous board marksheet to qualify for generous fee concessions.
        </p>
        <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
          <a href="<?php echo url('admissions'); ?>" class="btn btn-white btn-lg">
            <i class="fa-solid fa-award"></i> Apply for Scholarship
          </a>
          <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-outline-white btn-lg" target="_blank" rel="noopener noreferrer">
            <i class="fa-brands fa-whatsapp"></i> Inquire via WhatsApp
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
