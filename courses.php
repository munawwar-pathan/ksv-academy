<?php
require_once __DIR__ . '/config.php';

$active_page = 'courses';
$page_title = 'Courses & Batches | KSV Academy Ulwe | School, College, JEE, NEET, CET, Degree';
$page_description = 'Explore full course programs at KSV Academy Ulwe. Classes 5th-10th (CBSE/ICSE/State), IIT-JEE Main & Adv, NEET, MH-CET, 11th-12th Science & Commerce, B.Com, BMS, BCA & M.Com.';
$page_keywords = 'KSV Academy courses, IIT JEE coaching Ulwe, NEET tuition Ulwe, 10th CBSE classes, 12th science commerce coaching, BCom BMS BCA coaching Ulwe';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Courses Header Hero Banner (Attractive Design Matching Home & About) -->
<section class="about-hero-banner">
  <div class="container text-center">
    <div class="about-banner-content">
      <!-- Dual Eyebrows -->
      <div class="hero-eyebrows-wrap" style="justify-content: center; margin-bottom: 16px;">
        <div class="hero-eyebrow">
          <span class="hero-live-dot"></span>
          <span>Admissions Open • Session <?php echo CURRENT_SESSION; ?></span>
        </div>
        <div class="hero-trust-badge">
          <i class="fa-solid fa-graduation-cap"></i>
          <span>Complete Academic Spectrum (5th to Degree)</span>
        </div>
      </div>

      <!-- Main Heading -->
      <h1 class="about-banner-title">
        Courses &amp; <span class="hero-highlight">Academic Batches</span>
      </h1>

      <!-- Slogan / Motto Tagline -->
      <div class="hero-motto-row" style="justify-content: center; margin-bottom: 16px;">
        <span class="motto-lead"><i class="fa-solid fa-sparkles"></i> Strong Foundation, Sure Success</span>
        <span class="motto-divider">•</span>
        <span class="motto-sub">Small Batches &amp; Concept-First Mentorship</span>
      </div>

      <!-- Lead Description -->
      <p class="about-banner-desc">
        Structured, concept-driven coaching engineered for board rankers, medical and engineering aspirants, and university commerce &amp; technology graduates in Ulwe.
      </p>

      <!-- Quick Stats Counter Strip -->
      <div class="hero-stats-row" style="max-width: 760px; margin: 0 auto;">
        <div class="stat-item">
          <span class="stat-number" data-target="10" data-suffix="+">10+</span>
          <span class="stat-label">Years in Ulwe</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="6" data-suffix="+">6+</span>
          <span class="stat-label">Academic Streams</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="98" data-suffix="%">98%</span>
          <span class="stat-label">Success Rate</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="60" data-suffix="%">60%</span>
          <span class="stat-label">Max Scholarship</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. Filterable Explorer Section -->
<section class="section-py" id="coursesSection">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-layer-group"></i> Academic Programs</span>
      <h2 class="section-title">Explore Our <span class="hero-highlight">Curriculum &amp; Batches</span></h2>
      <p class="section-subtitle">Structured, concept-driven coaching engineered for school toppers, competitive aspirants, and degree students in Ulwe.</p>
    </div>

    <!-- Filter Tabs with Icons -->
    <div class="courses-tab-nav">
      <button type="button" class="course-tab-btn active" data-filter="all">
        <i class="fa-solid fa-border-all"></i> All Programs
      </button>
      <button type="button" class="course-tab-btn" data-filter="school">
        <i class="fa-solid fa-school"></i> School (5th - 10th)
      </button>
      <button type="button" class="course-tab-btn" data-filter="competitive">
        <i class="fa-solid fa-atom"></i> IIT-JEE / NEET / MH-CET
      </button>
      <button type="button" class="course-tab-btn" data-filter="college">
        <i class="fa-solid fa-graduation-cap"></i> Junior College (11th &amp; 12th)
      </button>
      <button type="button" class="course-tab-btn" data-filter="degree">
        <i class="fa-solid fa-briefcase"></i> Undergraduate Degree
      </button>
      <button type="button" class="course-tab-btn" data-filter="postgraduate">
        <i class="fa-solid fa-award"></i> Postgraduate
      </button>
    </div>

    <!-- Courses Detailed Grid -->
    <div class="courses-grid">
      <?php foreach ($courses as $c): 
        $themeClass = 'course-theme-' . htmlspecialchars($c['category']);
      ?>
      <div class="course-card <?php echo $themeClass; ?>" data-category="<?php echo htmlspecialchars($c['category']); ?>" id="<?php echo htmlspecialchars($c['id']); ?>">
        <div class="course-card-topbar"></div>

        <div class="course-card-top">
          <span class="course-cat-tag">
            <i class="fa-solid fa-book-open"></i>
            <span><?php echo htmlspecialchars($c['category_name']); ?></span>
          </span>
          <span class="course-status-pill">
            <i class="fa-solid fa-star"></i>
            <span><?php echo htmlspecialchars($c['badge']); ?></span>
          </span>
        </div>

        <div class="course-card-main">
          <h3 class="course-title"><?php echo htmlspecialchars($c['title']); ?></h3>
          <div class="course-standards-badge">
            <i class="fa-solid fa-graduation-cap"></i>
            <span><?php echo htmlspecialchars($c['standards']); ?></span>
          </div>
          <p class="course-desc"><?php echo htmlspecialchars($c['description']); ?></p>
        </div>

        <div class="course-card-specs">
          <div class="course-spec-item">
            <div class="spec-icon-badge"><i class="fa-solid fa-layer-group"></i></div>
            <div class="spec-content">
              <span class="spec-label">Target Boards</span>
              <span class="spec-val"><?php echo htmlspecialchars($c['boards']); ?></span>
            </div>
          </div>
          <div class="course-spec-item">
            <div class="spec-icon-badge"><i class="fa-solid fa-flask-vial"></i></div>
            <div class="spec-content">
              <span class="spec-label">Key Subjects</span>
              <span class="spec-val"><?php echo htmlspecialchars($c['subjects']); ?></span>
            </div>
          </div>
          <div class="course-spec-item">
            <div class="spec-icon-badge"><i class="fa-solid fa-clock"></i></div>
            <div class="spec-content">
              <span class="spec-label">Batch Options</span>
              <span class="spec-val">Morning (8:00 AM) &amp; Evening (4:00 PM)</span>
            </div>
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
              <span class="feat-check"><i class="fa-solid fa-check"></i></span>
              <span><?php echo htmlspecialchars($f); ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="course-actions">
          <button type="button" class="btn-demo-action" data-modal="demoModal">
            <span>Book Free Demo</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>
          <a href="tel:<?php echo PHONE_RAW_1; ?>" class="btn-details-action" title="Call for Details">
            <i class="fa-solid fa-phone"></i>
            <span>Inquire</span>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 3. Subject Specialization Focus Cards -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-microscope"></i> Subject Mastery</span>
      <h2 class="section-title">Deep Conceptual Focus by <span class="hero-highlight">Subject</span></h2>
      <p class="section-subtitle">How our senior faculties teach the core pillars of science, mathematics, commerce, and language.</p>
    </div>

    <div class="features-grid">
      <!-- 1. Physics -->
      <div class="subject-focus-card">
        <div class="subject-focus-topbar bar-physics"></div>
        <div class="subject-focus-icon icon-physics"><i class="fa-solid fa-bolt"></i></div>
        <div class="subject-mentor-pill"><i class="fa-solid fa-user-tie"></i> Guided by Mr. Salam Khan (10+ Yrs Exp)</div>
        <h3>Physics Mastery</h3>
        <span class="subject-scope-tag">Mechanics, Optics &amp; Electrodynamics</span>
        <p>
          Physics is taught with real-world physical models and intuitive problem breakdowns, turning fear into scoring confidence for NEET, JEE &amp; Board examinations.
        </p>
        <div class="subject-tags-row">
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> Visual Concept Models</span>
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> Numerical Speed Drills</span>
        </div>
      </div>

      <!-- 2. Chemistry -->
      <div class="subject-focus-card">
        <div class="subject-focus-topbar bar-chem"></div>
        <div class="subject-focus-icon icon-chem"><i class="fa-solid fa-flask"></i></div>
        <div class="subject-mentor-pill"><i class="fa-solid fa-user-tie"></i> Mr. Rameshwar Mishra &amp; Aman Soni</div>
        <h3>Chemistry Mastery</h3>
        <span class="subject-scope-tag">Organic, Physical &amp; Inorganic</span>
        <p>
          Demystifying complex reaction mechanisms, periodic trends, and chemical thermodynamics through systematic practice and formula mastery.
        </p>
        <div class="subject-tags-row">
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> Reaction Mechanisms</span>
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> NCERT Line-by-Line</span>
        </div>
      </div>

      <!-- 3. Mathematics -->
      <div class="subject-focus-card">
        <div class="subject-focus-topbar bar-math"></div>
        <div class="subject-focus-icon icon-math"><i class="fa-solid fa-calculator"></i></div>
        <div class="subject-mentor-pill"><i class="fa-solid fa-user-tie"></i> Hafiza Palikar &amp; Rafeek Khan</div>
        <h3>Mathematics Mastery</h3>
        <span class="subject-scope-tag">Calculus, Algebra &amp; Geometry</span>
        <p>
          Step-by-step proofs, shortcut speed techniques, and rigorous NCERT &amp; exemplar drilling to build lasting mathematical aptitude and examination speed.
        </p>
        <div class="subject-tags-row">
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> Shortcut Speed Tricks</span>
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> Exemplar Drilling</span>
        </div>
      </div>

      <!-- 4. Commerce -->
      <div class="subject-focus-card">
        <div class="subject-focus-topbar bar-commerce"></div>
        <div class="subject-focus-icon icon-commerce"><i class="fa-solid fa-chart-pie"></i></div>
        <div class="subject-mentor-pill"><i class="fa-solid fa-briefcase"></i> 11th, 12th &amp; Degree (B.Com / BAF / BMS)</div>
        <h3>Commerce &amp; Accounts</h3>
        <span class="subject-scope-tag">Double Entry, Tax &amp; Economics</span>
        <p>
          Comprehensive coverage for Junior College &amp; Degree programs covering Double Entry Bookkeeping, Corporate Accounting, Direct/Indirect Tax, and Financial Analysis.
        </p>
        <div class="subject-tags-row">
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> Corporate Accounting</span>
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> GST &amp; Direct Tax</span>
        </div>
      </div>

      <!-- 5. Computer Science -->
      <div class="subject-focus-card">
        <div class="subject-focus-topbar bar-cs"></div>
        <div class="subject-focus-icon icon-cs"><i class="fa-solid fa-laptop-code"></i></div>
        <div class="subject-mentor-pill"><i class="fa-solid fa-user-tie"></i> Led by Rafeek Khan (M.Sc Comp Sci)</div>
        <h3>Computer Science &amp; IT</h3>
        <span class="subject-scope-tag">BCA &amp; Higher Computing Programs</span>
        <p>
          Practical programming in C, Java, Python, discrete maths, database management, and university semester project guidance with full lab support.
        </p>
        <div class="subject-tags-row">
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> Python &amp; Java Coding</span>
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> Semester Projects</span>
        </div>
      </div>

      <!-- 6. English & SST -->
      <div class="subject-focus-card">
        <div class="subject-focus-topbar bar-humanities"></div>
        <div class="subject-focus-icon icon-humanities"><i class="fa-solid fa-book-open"></i></div>
        <div class="subject-mentor-pill"><i class="fa-solid fa-user-tie"></i> Guided by Mrs. Lubna Khan (BMS)</div>
        <h3>Languages &amp; Social Studies</h3>
        <span class="subject-scope-tag">English, SST &amp; Management Studies</span>
        <p>
          Precision answer-writing frameworks for Social Studies, English Grammar, literature comprehension, and management subjects for top board marks.
        </p>
        <div class="subject-tags-row">
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> Board Answer Writing</span>
          <span class="subject-mini-tag"><i class="fa-solid fa-check"></i> Grammar &amp; Literature</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 4. Scholarship CTA Banner -->
<section class="section-py">
  <div class="container">
    <div class="scholarship-banner-card">
      <div>
        <span class="scholarship-eyebrow">
          <i class="fa-solid fa-award"></i> Merit Scholarship Opportunity • Session <?php echo CURRENT_SESSION; ?>
        </span>
        <h2 style="font-size: 2.3rem; font-weight: 800; margin-bottom: 14px; color: #ffffff; line-height: 1.25;">
          Merit Scholarship Up to <span style="color: #38bdf8;">60% Concession</span>
        </h2>
        <p style="font-size: 1.02rem; color: rgba(255, 255, 255, 0.9); line-height: 1.65; margin: 0 0 20px; max-width: 640px;">
          Don't let financial constraints hinder your ambition. Appear for the KSV Academy Assessment Test or present your previous board marksheet to qualify for generous fee concessions in Ulwe.
        </p>
        
        <div class="scholarship-check-tags">
          <div class="scholarship-check-tag">
            <i class="fa-solid fa-circle-check"></i>
            <span>Based on Class 10th / 12th Board Examination Score</span>
          </div>
          <div class="scholarship-check-tag">
            <i class="fa-solid fa-circle-check"></i>
            <span>Free KSV Diagnostic Aptitude &amp; Subject Assessment</span>
          </div>
          <div class="scholarship-check-tag">
            <i class="fa-solid fa-circle-check"></i>
            <span>Transparent Merit Concession applied directly to fees</span>
          </div>
        </div>

        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
          <a href="<?php echo url('admissions'); ?>" class="btn btn-warning btn-lg" style="background: #f59e0b; color: #08212e; border: none; font-weight: 800; box-shadow: 0 4px 16px rgba(245,158,11,0.4);">
            <i class="fa-solid fa-award"></i> Apply for Scholarship
          </a>
          <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-outline-white btn-lg" target="_blank" rel="noopener noreferrer" style="border: 2px solid rgba(255,255,255,0.6); color: #ffffff;">
            <i class="fa-brands fa-whatsapp"></i> Inquire via WhatsApp
          </a>
        </div>
      </div>

      <div class="scholarship-glass-box">
        <div class="scholarship-big-percent">UP TO 60%</div>
        <div class="scholarship-box-title">Merit Fee Waiver</div>
        <div class="scholarship-box-sub">Applicable for School, Junior College &amp; Entrance Batches in Ulwe</div>
        
        <div style="color: #fbbf24; font-size: 1rem; margin-bottom: 16px;">
          <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
          <span style="font-size: 0.82rem; color: #ffffff; margin-left: 6px; font-weight: 700;">150+ Happy Parents</span>
        </div>

        <button type="button" class="btn btn-sm btn-white" data-modal="demoModal" style="width: 100%; font-weight: 800; color: var(--primary-700); background: #ffffff;">
          <i class="fa-regular fa-calendar-check"></i> Book 3-Days Free Demo
        </button>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
