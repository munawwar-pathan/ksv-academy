<?php
require_once __DIR__ . '/config.php';

$active_page = 'home';
$page_title = 'KSV Academy | Best Coaching Classes in Ulwe | 5th-12th, IIT-JEE, NEET, MH-CET, Degree';
$page_description = 'KSV Academy Ulwe Navi Mumbai offers top-notch coaching for Classes 5th to 12th (CBSE, ICSE, State), IIT-JEE, NEET, MH-CET and Degree Courses (B.Com, BMS, BCA). Book your 3-Days Free Demo today!';
$page_keywords = 'KSV Academy Ulwe, coaching classes Ulwe, tuition in Ulwe sector 19, IIT JEE coaching Navi Mumbai, NEET classes Ulwe, Salam Khan physics, Rameshwar Mishra chemistry, best tuition classes Ulwe';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Hero Section -->
<section class="hero-section">
  <div class="container hero-grid">
    <div class="hero-content">
      <!-- Dual Eyebrow Badges -->
      <div class="hero-eyebrows-wrap">
        <div class="hero-eyebrow">
          <span class="hero-live-dot"></span>
          <span>Admissions Open 2026–27</span>
        </div>
        <div class="hero-trust-badge">
          <i class="fa-solid fa-award"></i>
          <span>#1 Coaching in Ulwe Sector 19</span>
        </div>
      </div>

      <!-- Main Heading -->
      <h1 class="hero-title">
        Where Learning Meets <span class="hero-highlight">Excellence.</span>
      </h1>

      <!-- Slogan / Motto Tagline -->
      <div class="hero-motto-row">
        <span class="motto-lead"><i class="fa-solid fa-sparkles"></i> Ab Sapnon Ki Coaching Ulwe Mein!</span>
        <span class="motto-divider">•</span>
        <span class="motto-sub">Strong Foundation, Sure Success</span>
      </div>

      <!-- Academic Lead Description -->
      <p class="hero-description">
        Ulwe’s trusted coaching institute for Classes 5th to 12th (CBSE, ICSE, State), IIT-JEE, NEET, and Degree courses with concept-first mentoring and small, disciplined batches.
      </p>

      <!-- Key Course Streams Marquee -->
      <div class="hero-marquee-wrapper" aria-label="Key Course Streams">
        <div class="marquee-track">
          <div class="marquee-group">
            <span class="stream-pill"><i class="fa-solid fa-graduation-cap"></i> 5th–10th (CBSE / ICSE / State)</span>
            <span class="stream-pill"><i class="fa-solid fa-flask"></i> 11th &amp; 12th Science (PCB/PCM)</span>
            <span class="stream-pill"><i class="fa-solid fa-chart-pie"></i> 11th &amp; 12th Commerce</span>
            <span class="stream-pill"><i class="fa-solid fa-trophy"></i> IIT-JEE (Main &amp; Advanced)</span>
            <span class="stream-pill"><i class="fa-solid fa-user-doctor"></i> NEET-UG Medical</span>
            <span class="stream-pill"><i class="fa-solid fa-atom"></i> MHT-CET Engineering &amp; Pharma</span>
            <span class="stream-pill"><i class="fa-solid fa-building-columns"></i> Degree (B.Com, BMS, BCA, BAF)</span>
          </div>
          <div class="marquee-group" aria-hidden="true">
            <span class="stream-pill"><i class="fa-solid fa-graduation-cap"></i> 5th–10th (CBSE / ICSE / State)</span>
            <span class="stream-pill"><i class="fa-solid fa-flask"></i> 11th &amp; 12th Science (PCB/PCM)</span>
            <span class="stream-pill"><i class="fa-solid fa-chart-pie"></i> 11th &amp; 12th Commerce</span>
            <span class="stream-pill"><i class="fa-solid fa-trophy"></i> IIT-JEE (Main &amp; Advanced)</span>
            <span class="stream-pill"><i class="fa-solid fa-user-doctor"></i> NEET-UG Medical</span>
            <span class="stream-pill"><i class="fa-solid fa-atom"></i> MHT-CET Engineering &amp; Pharma</span>
            <span class="stream-pill"><i class="fa-solid fa-building-columns"></i> Degree (B.Com, BMS, BCA, BAF)</span>
          </div>
        </div>
      </div>

      <!-- Action Buttons Row -->
      <div class="hero-actions-row">
        <button type="button" class="btn btn-primary btn-lg" data-modal="demoModal">
          <span>Book 3-Days Free Demo</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>
        <a href="tel:<?php echo PHONE_RAW_1; ?>" class="btn btn-outline-primary btn-lg hero-btn-phone">
          <i class="fa-solid fa-phone"></i>
          <span><?php echo PHONE_PRIMARY; ?></span>
        </a>
      </div>

      <!-- Quick Stats Counter -->
      <div class="hero-stats-row">
        <div class="stat-item">
          <span class="stat-number" data-target="10" data-suffix="+">10+</span>
          <span class="stat-label">Years in Ulwe</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="1500" data-suffix="+">1500+</span>
          <span class="stat-label">Students Guided</span>
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

    <!-- Right Side: Executive Leadership & Academy Showcase Card -->
    <div class="hero-media-wrapper">
      <div class="hero-sober-card">
        <div class="hero-card-accent-bar"></div>

        <!-- Faculty Spotlight Header -->
        <div class="sober-card-header">
          <div class="sober-avatar-wrap">
            <img src="<?php echo url('assets/imgs/faculties/founder-director.png'); ?>" alt="Mr. Salam Khan - Founder &amp; Director" class="sober-avatar-img">
            <span class="sober-verified-check" title="Verified Director"><i class="fa-solid fa-circle-check"></i></span>
          </div>
          <div class="sober-profile-info">
            <span class="sober-lead-badge"><i class="fa-solid fa-shield-halved"></i> Academic Leadership</span>
            <h3 class="sober-name">Mr. Salam Khan</h3>
            <p class="sober-qualification">Founder &amp; Director • M.Sc, B.Ed</p>
            <span class="sober-experience">10+ Years Mentoring Physics &amp; Mathematics</span>
          </div>
        </div>

        <!-- 4 Upgraded Feature Cards -->
        <div class="hero-pillars-grid">
          <div class="hero-pillar-card">
            <div class="hero-pillar-icon icon-blue"><i class="fa-solid fa-brain"></i></div>
            <div class="hero-pillar-info">
              <strong>Concept-First Learning</strong>
              <span>Complete syllabus clarity for CBSE, ICSE, SSC &amp; HSC boards.</span>
            </div>
          </div>

          <div class="hero-pillar-card">
            <div class="hero-pillar-icon icon-emerald"><i class="fa-solid fa-rocket"></i></div>
            <div class="hero-pillar-info">
              <strong>Competitive Coaching</strong>
              <span>Integrated preparation for IIT-JEE, NEET-UG &amp; MHT-CET.</span>
            </div>
          </div>

          <div class="hero-pillar-card">
            <div class="hero-pillar-icon icon-purple"><i class="fa-solid fa-chart-line"></i></div>
            <div class="hero-pillar-info">
              <strong>Weekly Assessments</strong>
              <span>Regular chapter-wise tests with timely parent progress updates.</span>
            </div>
          </div>

          <div class="hero-pillar-card">
            <div class="hero-pillar-icon icon-amber"><i class="fa-solid fa-user-check"></i></div>
            <div class="hero-pillar-info">
              <strong>Individual Attention</strong>
              <span>Small batch sizes with dedicated daily 1-on-1 doubt solving.</span>
            </div>
          </div>
        </div>

        <!-- Reassurance Footer -->
        <div class="sober-card-footer">
          <div class="sober-rating">
            <div class="sober-stars">
              <i class="fa-solid fa-star"></i>
              <i class="fa-solid fa-star"></i>
              <i class="fa-solid fa-star"></i>
              <i class="fa-solid fa-star"></i>
              <i class="fa-solid fa-star"></i>
            </div>
            <strong>4.9 / 5</strong>
            <span>Rating (150+ Reviews)</span>
          </div>
          <span class="sober-location-tag">
            <i class="fa-solid fa-location-dot"></i> Sector 19, Ulwe
          </span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. The 3 Core Pillars -->
<section class="section-py pillars-section">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-compass"></i> Why Choose KSV Academy</span>
      <h2 class="section-title">Designed for Consistent <span class="hero-highlight">Academic Success</span></h2>
      <p class="section-subtitle">Our proven pedagogy, disciplined small batches, and dedicated mentorship set us apart in Ulwe.</p>
    </div>

    <div class="pillars-grid">
      <!-- Pillar 1: Result-Oriented Coaching -->
      <div class="pillar-sober-card pillar-theme-blue">
        <div class="pillar-card-topbar"></div>
        <div class="pillar-card-watermark">01</div>
        <div class="pillar-card-header">
          <div class="pillar-sober-icon pillar-icon-blue">
            <i class="fa-solid fa-graduation-cap"></i>
          </div>
          <span class="pillar-pill pill-blue"><i class="fa-solid fa-bolt"></i> Proven Pedagogy</span>
        </div>
        <h3 class="pillar-sober-title">Result-Oriented Coaching</h3>
        <p class="pillar-sober-desc">
          Structured preparation engineered to help students maximize scores in school boards (CBSE, ICSE, SSC), HSC, and premier competitive examinations.
        </p>
        <div class="pillar-stat-chip chip-blue">
          <i class="fa-solid fa-trophy"></i>
          <span><strong>98.4%</strong> Top Board Score &bull; 100% Pass Record</span>
        </div>
        <ul class="pillar-sober-list">
          <li>
            <span class="pillar-check check-blue"><i class="fa-solid fa-check"></i></span>
            <span><strong>Concept-First Pedagogy</strong> for complete syllabus clarity</span>
          </li>
          <li>
            <span class="pillar-check check-blue"><i class="fa-solid fa-check"></i></span>
            <span><strong>Integrated Mapping</strong> of Board + IIT-JEE &amp; NEET syllabus</span>
          </li>
          <li>
            <span class="pillar-check check-blue"><i class="fa-solid fa-check"></i></span>
            <span><strong>Past 10-Year Question Bank</strong> solving &amp; test blueprints</span>
          </li>
        </ul>
      </div>

      <!-- Pillar 2: Weekly Tests & Monitoring -->
      <div class="pillar-sober-card pillar-theme-purple">
        <div class="pillar-card-topbar"></div>
        <div class="pillar-card-watermark">02</div>
        <div class="pillar-card-header">
          <div class="pillar-sober-icon pillar-icon-purple">
            <i class="fa-solid fa-clipboard-check"></i>
          </div>
          <span class="pillar-pill pill-purple"><i class="fa-solid fa-chart-line"></i> Rigorous Testing</span>
        </div>
        <h3 class="pillar-sober-title">Weekly Tests &amp; Monitoring</h3>
        <p class="pillar-sober-desc">
          Continuous chapter-wise and cumulative assessments track progress systematically, identifying weak spots early and building ironclad exam confidence.
        </p>
        <div class="pillar-stat-chip chip-purple">
          <i class="fa-solid fa-file-circle-check"></i>
          <span><strong>120+ Tests / Year</strong> &bull; Chapter Tests &amp; Full Mocks</span>
        </div>
        <ul class="pillar-sober-list">
          <li>
            <span class="pillar-check check-purple"><i class="fa-solid fa-check"></i></span>
            <span><strong>Sunday Chapter Tests</strong> and computerized mock test series</span>
          </li>
          <li>
            <span class="pillar-check check-purple"><i class="fa-solid fa-check"></i></span>
            <span><strong>Regular Parent-Teacher</strong> progress review meetings &amp; reports</span>
          </li>
          <li>
            <span class="pillar-check check-purple"><i class="fa-solid fa-check"></i></span>
            <span><strong>Speed &amp; Accuracy Training</strong> with negative-marking control</span>
          </li>
        </ul>
      </div>

      <!-- Pillar 3: Individual Attention & Care -->
      <div class="pillar-sober-card pillar-theme-emerald">
        <div class="pillar-card-topbar"></div>
        <div class="pillar-card-watermark">03</div>
        <div class="pillar-card-header">
          <div class="pillar-sober-icon pillar-icon-emerald">
            <i class="fa-solid fa-user-shield"></i>
          </div>
          <span class="pillar-pill pill-emerald"><i class="fa-solid fa-shield-heart"></i> Personal Focus</span>
        </div>
        <h3 class="pillar-sober-title">Individual Attention &amp; Care</h3>
        <p class="pillar-sober-desc">
          We maintain disciplined, small batch sizes so senior educators can mentor each student personally, fostering absolute confidence without hesitation.
        </p>
        <div class="pillar-stat-chip chip-emerald">
          <i class="fa-solid fa-users"></i>
          <span><strong>Max 25 Per Batch</strong> &bull; Daily 1-on-1 Doubt Desks</span>
        </div>
        <ul class="pillar-sober-list">
          <li>
            <span class="pillar-check check-emerald"><i class="fa-solid fa-check"></i></span>
            <span><strong>Small Batch Sizes</strong> ensuring every learner is attended to</span>
          </li>
          <li>
            <span class="pillar-check check-emerald"><i class="fa-solid fa-check"></i></span>
            <span><strong>Daily 1-on-1 Doubt Counters</strong> with dedicated faculty</span>
          </li>
          <li>
            <span class="pillar-check check-emerald"><i class="fa-solid fa-check"></i></span>
            <span><strong>Personal Mentorship</strong> led by Director Salam Khan</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Enhanced Executive Trust & Highlight Banner -->
    <div class="pillars-sober-trust">
      <div class="sober-trust-item trust-item-amber">
        <div class="sober-trust-icon icon-amber"><i class="fa-solid fa-award"></i></div>
        <div class="sober-trust-info">
          <div class="trust-stat-badge-row">
            <span class="trust-counter-tag tag-amber">10+ Years</span>
            <span class="trust-pill-label">Ulwe Pioneer</span>
          </div>
          <strong class="trust-stat-title">Proven Academic Legacy</strong>
          <span class="trust-stat-subtitle">1,500+ successful alumni placed across top colleges</span>
        </div>
      </div>
      <div class="sober-trust-divider"></div>
      <div class="sober-trust-item trust-item-blue">
        <div class="sober-trust-icon icon-blue"><i class="fa-solid fa-book-open-reader"></i></div>
        <div class="sober-trust-info">
          <div class="trust-stat-badge-row">
            <span class="trust-counter-tag tag-blue">All Included</span>
            <span class="trust-pill-label">No Extra Fee</span>
          </div>
          <strong class="trust-stat-title">Comprehensive Study Kits</strong>
          <span class="trust-stat-subtitle">Handcrafted notes, mind maps, formula sheets &amp; question banks</span>
        </div>
      </div>
      <div class="sober-trust-divider"></div>
      <div class="sober-trust-item trust-item-emerald">
        <div class="sober-trust-icon icon-emerald"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="sober-trust-info">
          <div class="trust-stat-badge-row">
            <span class="trust-counter-tag tag-emerald">100% Free</span>
            <span class="trust-pill-label">No Obligation</span>
          </div>
          <strong class="trust-stat-title">3-Days Free Trial Lectures</strong>
          <span class="trust-stat-subtitle">Experience real classroom coaching before taking admission</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 3. Course Catalog Explorer (Interactive Tabbed View like Khan Global Studies) -->
<section class="section-py courses-section" id="coursesSection">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-book-bookmark"></i> Programs We Offer</span>
      <h2 class="section-title">Explore Our <span class="hero-highlight">Academic Batches</span></h2>
      <p class="section-subtitle">From foundational schooling to university degrees and competitive exams, discover customized learning pathways.</p>
    </div>

    <!-- Indian Students Batch Trust Strip -->
    <div class="batches-trust-strip">
      <div class="bts-left">
        <div class="bts-avatars-wrap">
          <img src="<?php echo url('assets/imgs/students/topper-girl.jpg'); ?>" alt="Indian Student at KSV Academy" class="bts-avatar">
          <img src="<?php echo url('assets/imgs/students/topper-boy.jpg'); ?>" alt="Indian Student at KSV Academy" class="bts-avatar">
          <img src="<?php echo url('assets/imgs/students/classroom-learning.jpg'); ?>" alt="Indian Student at KSV Academy" class="bts-avatar">
          <span class="bts-avatar-count">500+</span>
        </div>
        <div class="bts-text">
          <strong>500+ Students Learning Across Ulwe Batches</strong>
          <span>Strict limit of 20–25 students per batch for personalized, 1-on-1 faculty attention</span>
        </div>
      </div>
      <div class="bts-badges">
        <span class="bts-pill"><i class="fa-solid fa-circle-check"></i> Free 3-Day Demo Pass</span>
        <span class="bts-pill"><i class="fa-solid fa-circle-check"></i> Concept-First Training</span>
        <span class="bts-pill"><i class="fa-solid fa-circle-check"></i> Biometric Attendance</span>
      </div>
    </div>

    <!-- Filter Buttons -->
    <div class="courses-tab-nav">
      <button type="button" class="course-tab-btn active" data-filter="all"><i class="fa-solid fa-border-all"></i> <span>All Programs</span></button>
      <button type="button" class="course-tab-btn" data-filter="school"><i class="fa-solid fa-school"></i> <span>School Section (5th - 10th)</span></button>
      <button type="button" class="course-tab-btn" data-filter="competitive"><i class="fa-solid fa-atom"></i> <span>IIT-JEE / NEET / MH-CET</span></button>
      <button type="button" class="course-tab-btn" data-filter="college"><i class="fa-solid fa-graduation-cap"></i> <span>Junior College (11th &amp; 12th)</span></button>
      <button type="button" class="course-tab-btn" data-filter="degree"><i class="fa-solid fa-building-columns"></i> <span>Undergraduate &amp; Degrees</span></button>
      <button type="button" class="course-tab-btn" data-filter="postgraduate"><i class="fa-solid fa-award"></i> <span>Postgraduate</span></button>
    </div>

    <!-- Course Cards Grid -->
    <div class="courses-grid">
      <?php foreach ($courses as $c): 
        $cat = $c['category'];
        $theme_class = 'course-theme-school';
        $cat_icon = 'fa-school';
        $course_img = 'assets/imgs/students/classroom-learning.jpg';

        if ($cat === 'competitive') {
          $theme_class = 'course-theme-competitive';
          $cat_icon = 'fa-atom';
          $course_img = 'assets/imgs/students/topper-boy.jpg';
        } elseif ($cat === 'college') {
          $theme_class = 'course-theme-college';
          $cat_icon = 'fa-graduation-cap';
          $course_img = 'assets/imgs/students/topper-girl.jpg';
        } elseif ($cat === 'degree') {
          $theme_class = 'course-theme-degree';
          $cat_icon = 'fa-building-columns';
          $course_img = 'assets/imgs/gallers/gallery-9.png';
        } elseif ($cat === 'postgraduate') {
          $theme_class = 'course-theme-postgrad';
          $cat_icon = 'fa-award';
          $course_img = 'assets/imgs/gallers/gallery-4.png';
        } elseif (stripos($c['title'], 'olympiad') !== false) {
          $theme_class = 'course-theme-olympiad';
          $cat_icon = 'fa-trophy';
          $course_img = 'assets/imgs/gallers/gallery-3.png';
        }
      ?>
      <div class="course-card <?php echo $theme_class; ?>" data-category="<?php echo htmlspecialchars($c['category']); ?>">
        <div class="course-card-topbar"></div>

        <!-- Indian Student Batch Media Header -->
        <div class="course-card-media">
          <img src="<?php echo url($course_img); ?>" alt="<?php echo htmlspecialchars($c['title']); ?> - Indian Students Batch at KSV Academy" class="course-card-img" loading="lazy">
          <div class="course-card-media-overlay"></div>
          <span class="course-cat-tag">
            <i class="fa-solid <?php echo $cat_icon; ?>"></i>
            <span><?php echo htmlspecialchars($c['category_name']); ?></span>
          </span>
          <span class="course-status-pill">
            <i class="fa-solid fa-sparkles"></i>
            <span><?php echo htmlspecialchars($c['badge']); ?></span>
          </span>
        </div>

        <div class="course-card-main">
          <h3 class="course-title"><?php echo htmlspecialchars($c['title']); ?></h3>
          <div class="course-standards-badge">
            <i class="fa-solid fa-layer-group"></i>
            <span><?php echo htmlspecialchars($c['standards']); ?></span>
          </div>
          <p class="course-desc"><?php echo htmlspecialchars($c['description']); ?></p>
        </div>

        <div class="course-card-specs">
          <div class="course-spec-item">
            <div class="spec-icon-badge"><i class="fa-solid fa-landmark"></i></div>
            <div class="spec-content">
              <span class="spec-label">Boards</span>
              <span class="spec-val"><?php echo htmlspecialchars($c['boards']); ?></span>
            </div>
          </div>
          <div class="course-spec-item">
            <div class="spec-icon-badge"><i class="fa-solid fa-book-open"></i></div>
            <div class="spec-content">
              <span class="spec-label">Curriculum</span>
              <span class="spec-val"><?php echo htmlspecialchars($c['subjects']); ?></span>
            </div>
          </div>
        </div>

        <div class="course-highlights-box">
          <div class="course-highlights-heading">
            <i class="fa-solid fa-circle-check"></i>
            <span>Key Batch Highlights</span>
          </div>
          <ul class="course-checklist">
            <?php foreach (array_slice($c['features'], 0, 3) as $f): ?>
            <li>
              <span class="feat-check"><i class="fa-solid fa-check"></i></span>
              <span><?php echo htmlspecialchars($f); ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="course-actions">
          <button type="button" class="btn-demo-action" data-modal="demoModal">
            <span>Free Demo Pass</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>
          <a href="<?php echo url('courses'); ?>" class="btn-details-action">
            <span>View Details</span>
            <i class="fa-solid fa-chevron-right"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 3.5 Academic Achievers & Toppers Spotlight -->
<section class="section-py achievers-section-wrapper" id="achieversSection">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-trophy"></i> Hall of Fame</span>
      <h2 class="section-title">Celebrating Our <span class="hero-highlight">Academic Achievers</span></h2>
      <p class="section-subtitle">Year after year, KSV Academy students in Ulwe achieve top percentiles and 100% board distinction through systematic concept mentorship.</p>
    </div>

    <!-- Metrics Summary Strip -->
    <div class="achievers-metrics-strip">
      <div class="achievers-metric-card">
        <div class="amc-number">98.6%</div>
        <div class="amc-label">Highest Board Score</div>
        <div class="amc-desc">CBSE Class 10th Ulwe Center Topper</div>
      </div>
      <div class="achievers-metric-card">
        <div class="amc-number">99.2%ile</div>
        <div class="amc-label">Top JEE Main Score</div>
        <div class="amc-desc">All India Top 1% Percentile in PCM</div>
      </div>
      <div class="achievers-metric-card">
        <div class="amc-number">48+</div>
        <div class="amc-label">Scored 90%+ Marks</div>
        <div class="amc-desc">Across CBSE, ICSE &amp; Maharashtra State</div>
      </div>
      <div class="achievers-metric-card">
        <div class="amc-number">100%</div>
        <div class="amc-label">Board Passing Rate</div>
        <div class="amc-desc">Consecutive 100% Success Record</div>
      </div>
    </div>

    <!-- Achievers Cards Grid -->
    <div class="achievers-grid">
      <!-- Card 1: Girl Topper -->
      <div class="topper-card">
        <div class="topper-card-top">
          <div class="topper-photo-wrap">
            <img src="<?php echo url('assets/imgs/students/topper-girl.jpg'); ?>" alt="Ananya Deshmukh - CBSE 10th Board Topper KSV Academy" class="topper-photo" loading="lazy">
            <div class="topper-rank-badge">Ulwe Topper</div>
          </div>
          <div class="topper-header-info">
            <span class="topper-badge-pill"><i class="fa-solid fa-award"></i> CBSE Class 10 Board Ranker</span>
            <h3 class="topper-name">Ananya Deshmukh</h3>
            <div class="topper-score-highlight">98.6% Overall</div>
            <div class="topper-school"><i class="fa-solid fa-school"></i> DAV Public School, Ulwe Navi Mumbai</div>
          </div>
        </div>
        <div class="topper-quote">
          &ldquo;The teachers at KSV Academy cleared every fundamental concept with incredible clarity. Regular mock test series and personal doubt sessions with Salam Sir and Mishra Sir made board exams completely stress-free!&rdquo;
        </div>
        <div class="topper-scores-strip">
          <span class="topper-score-chip"><i class="fa-solid fa-circle-check"></i> Mathematics: 100/100</span>
          <span class="topper-score-chip"><i class="fa-solid fa-circle-check"></i> Science: 99/100</span>
          <span class="topper-score-chip"><i class="fa-solid fa-circle-check"></i> English: 98/100</span>
        </div>
      </div>

      <!-- Card 2: Boy Topper -->
      <div class="topper-card">
        <div class="topper-card-top">
          <div class="topper-photo-wrap">
            <img src="<?php echo url('assets/imgs/students/topper-boy.jpg'); ?>" alt="Aarav Mehta - IIT-JEE &amp; 12th Board Achiever KSV Academy" class="topper-photo" loading="lazy">
            <div class="topper-rank-badge">JEE Achiever</div>
          </div>
          <div class="topper-header-info">
            <span class="topper-badge-pill"><i class="fa-solid fa-medal"></i> IIT-JEE Main &amp; 12th Pinnacle</span>
            <h3 class="topper-name">Aarav Mehta</h3>
            <div class="topper-score-highlight">99.2 Percentile</div>
            <div class="topper-school"><i class="fa-solid fa-graduation-cap"></i> 96.4% in 12th Science Board</div>
          </div>
        </div>
        <div class="topper-quote">
          &ldquo;Learning formulas is easy, but understanding the physics behind every equation is what KSV Academy taught me. The small batch size meant I could ask questions freely at any time.&rdquo;
        </div>
        <div class="topper-scores-strip">
          <span class="topper-score-chip"><i class="fa-solid fa-circle-check"></i> Physics: 99.4 %ile</span>
          <span class="topper-score-chip"><i class="fa-solid fa-circle-check"></i> Chemistry: 99.1 %ile</span>
          <span class="topper-score-chip"><i class="fa-solid fa-circle-check"></i> Mathematics: 98.8 %ile</span>
        </div>
      </div>
    </div>

    <!-- Bottom CTA Strip -->
    <div class="achievers-cta-strip">
      <div class="acs-text">
        <h4>Aiming for Top Ranks in 2026–27?</h4>
        <p>Give your child the unfair advantage of expert mentorship, disciplined test series, and small batches.</p>
      </div>
      <div class="acs-actions">
        <button type="button" class="btn btn-gold" data-modal="demoModal">
          <i class="fa-solid fa-ticket"></i> Book Free 3-Day Demo
        </button>
        <a href="<?php echo url('courses'); ?>" class="btn btn-outline-white">
          <i class="fa-solid fa-compass"></i> View Programs
        </a>
      </div>
    </div>
  </div>
</section>

<!-- 4. Star Faculty Showcase (The Educators) -->
<section class="section-py bg-soft" id="facultySection">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-chalkboard-user"></i> Master Mentors</span>
      <h2 class="section-title">Learn from <span class="hero-highlight">Distinguished Educators</span></h2>
      <p class="section-subtitle">Our faculty brings decades of combined expertise, passion for teaching, and proven student success across top entrance exams.</p>
    </div>

    <div class="faculty-grid">
      <?php foreach ($faculties as $fac): 
        $id = $fac['id'];
        $theme_class = 'fac-theme-blue';
        $subj_icon = 'fa-atom';
        $subj_label = 'Physics';

        if ($id === 'rameshwar-mishra') {
          $theme_class = 'fac-theme-emerald';
          $subj_icon = 'fa-flask-vial';
          $subj_label = 'Chemistry';
        } elseif ($id === 'aman-kumar-soni') {
          $theme_class = 'fac-theme-amber';
          $subj_icon = 'fa-square-root-variable';
          $subj_label = 'Chem &amp; Math';
        } elseif ($id === 'hafiza-palikar') {
          $theme_class = 'fac-theme-purple';
          $subj_icon = 'fa-infinity';
          $subj_label = 'Mathematics';
        } elseif ($id === 'rafeek-khan') {
          $theme_class = 'fac-theme-cyan';
          $subj_icon = 'fa-laptop-code';
          $subj_label = 'CS &amp; Math';
        } elseif ($id === 'lubna-khan') {
          $theme_class = 'fac-theme-rose';
          $subj_icon = 'fa-book-open-reader';
          $subj_label = 'English &amp; SST';
        }
      ?>
      <div class="faculty-card <?php echo $theme_class; ?>" id="<?php echo htmlspecialchars($fac['id']); ?>">
        <div class="faculty-card-topbar"></div>

        <div class="faculty-photo-wrapper">
          <div class="faculty-ambient-glow"></div>
          <img src="<?php echo url($fac['image']); ?>" alt="<?php echo htmlspecialchars($fac['name']); ?>" class="faculty-photo" loading="lazy">
          
          <div class="faculty-photo-badges">
            <span class="faculty-subj-badge">
              <i class="fa-solid <?php echo $subj_icon; ?>"></i>
              <span><?php echo $subj_label; ?></span>
            </span>
            <span class="faculty-exp-badge">
              <i class="fa-solid fa-award"></i>
              <span><?php echo htmlspecialchars($fac['experience']); ?></span>
            </span>
          </div>
        </div>

        <div class="faculty-info-block">
          <div class="faculty-header-meta">
            <h3 class="faculty-name"><?php echo htmlspecialchars($fac['name']); ?></h3>
            <div class="faculty-role-pill"><?php echo htmlspecialchars($fac['title']); ?></div>
          </div>

          <div class="faculty-qualification-row">
            <div class="faculty-qual-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div class="faculty-qual-text"><?php echo htmlspecialchars($fac['degrees']); ?></div>
          </div>

          <div class="faculty-spec-tag">
            <i class="fa-solid fa-sparkles"></i>
            <span><strong>Specialization:</strong> <?php echo htmlspecialchars($fac['specialization']); ?></span>
          </div>

          <?php if (!empty($fac['highlight'])): ?>
          <div class="faculty-highlight-strip">
            <i class="fa-solid fa-circle-check"></i>
            <span><?php echo htmlspecialchars($fac['highlight']); ?></span>
          </div>
          <?php endif; ?>

          <p class="faculty-bio"><?php echo htmlspecialchars($fac['bio']); ?></p>

          <div class="faculty-actions-row">
            <button type="button" class="btn-faculty-action" data-modal="demoModal">
              <span>Book Demo Lecture</span>
              <i class="fa-solid fa-arrow-right"></i>
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align: center; margin-top: 40px;">
      <a href="<?php echo url('faculty'); ?>" class="btn-faculty-all">
        <i class="fa-solid fa-users"></i>
        <span>View All Faculty Profiles &amp; Full Credentials</span>
        <i class="fa-solid fa-chevron-right"></i>
      </a>
    </div>
  </div>
</section>

<!-- 5. Founder & Director Message Section -->
<section class="section-py director-section-wrapper" id="directorSection">
  <div class="container">
    <div class="director-executive-card">
      <div class="director-card-topbar"></div>
      
      <!-- Ambient Decorative Watermark & Light Gradients -->
      <div class="director-watermark-icon" aria-hidden="true"><i class="fa-solid fa-quote-right"></i></div>
      <div class="director-bg-glow-top" aria-hidden="true"></div>
      <div class="director-bg-glow-bottom" aria-hidden="true"></div>

      <div class="director-executive-grid">
        <!-- Left: Director Portrait & Signature Showcase -->
        <div class="director-showcase-col">
          <div class="director-portrait-box">
            <div class="director-portrait-halo" aria-hidden="true"></div>
            
            <!-- Floating Portrait Badges -->
            <div class="director-floating-badge top-left">
              <i class="fa-solid fa-crown"></i>
              <span>Founder &amp; Director</span>
            </div>
            <div class="director-floating-badge top-right">
              <i class="fa-solid fa-award"></i>
              <span>10+ Yrs Exp</span>
            </div>

            <img src="<?php echo url('assets/imgs/faculties/founder-director.png'); ?>" alt="Mr. Salam Khan - Founder &amp; Director" class="director-portrait-img" loading="lazy">
          </div>

          <div class="director-identity-card">
            <h3 class="director-name">Mr. Salam Khan</h3>
            <div class="director-role-tag">
              <i class="fa-solid fa-circle-check"></i>
              <span>Founder &amp; Director, KSV Academy</span>
            </div>
            
            <div class="director-degrees-pill">
              <i class="fa-solid fa-graduation-cap"></i>
              <span>M.Sc, B.Ed (Physics)</span>
            </div>

            <!-- Leadership Stats Mini Strip -->
            <div class="director-stats-row">
              <div class="director-stat-mini">
                <span class="stat-num">10+</span>
                <span class="stat-lbl">Years Exp</span>
              </div>
              <div class="director-stat-divider"></div>
              <div class="director-stat-mini">
                <span class="stat-num">500+</span>
                <span class="stat-lbl">Aspirants</span>
              </div>
              <div class="director-stat-divider"></div>
              <div class="director-stat-mini">
                <span class="stat-num">100%</span>
                <span class="stat-lbl">Concept-First</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Vision, Message & Action Plan -->
        <div class="director-content-col">
          <div class="director-eyebrow-wrapper">
            <span class="director-eyebrow-pill">
              <i class="fa-solid fa-quote-left"></i>
              <span>Founder &amp; Director's Desk</span>
            </span>
            <span class="director-verified-status">
              <span class="pulse-indicator"></span> Direct Mentorship
            </span>
          </div>

          <h2 class="director-headline">
            Empowering Minds, <span class="hero-highlight">Shaping Futures</span>
          </h2>

          <div class="director-quote-box">
            <div class="quote-icon-corner" aria-hidden="true"><i class="fa-solid fa-quote-left"></i></div>
            <blockquote class="director-quote-text">
              "At KSV Academy, we are dedicated to providing a dynamic learning environment that nurtures intellectual curiosity, creativity, and personal growth. Our mission is to empower students with the knowledge, skills, and confidence to excel academically and thrive in life."
            </blockquote>
            <div class="quote-signature-bar">
              <span class="quote-author-sign">— Mr. Salam Khan</span>
              <span class="quote-author-title">Founder &amp; Director's Note</span>
            </div>
          </div>

          <p class="director-bio-text">
            With over 10 years of passionate teaching in Physics &amp; Mathematics, Mr. Salam Khan has helped hundreds of students build clear concept fundamentals. His mentorship has guided aspirants into premier engineering and medical institutions through NEET, IIT-JEE, and MH-CET.
          </p>

          <!-- Executive Pillars / Highlights Grid -->
          <div class="director-pillars-grid">
            <div class="director-pillar-item">
              <div class="pillar-icon-box"><i class="fa-solid fa-chalkboard-user"></i></div>
              <div class="pillar-info">
                <strong>10+ Years Dedicated Teaching</strong>
                <span>Personal mentorship in Physics &amp; Mathematics</span>
              </div>
            </div>

            <div class="director-pillar-item">
              <div class="pillar-icon-box"><i class="fa-solid fa-atom"></i></div>
              <div class="pillar-info">
                <strong>Concept-First Physics Pedagogy</strong>
                <span>Eliminating rote learning with deep analytical clarity</span>
              </div>
            </div>

            <div class="director-pillar-item">
              <div class="pillar-icon-box"><i class="fa-solid fa-trophy"></i></div>
              <div class="pillar-info">
                <strong>IIT-JEE, NEET &amp; MH-CET Mentor</strong>
                <span>Consistent top percentiles &amp; board distinction scores</span>
              </div>
            </div>
          </div>

          <!-- CTAs -->
          <div class="director-actions-cluster">
            <button type="button" class="btn-director-primary" data-modal="demoModal">
              <i class="fa-solid fa-calendar-check"></i>
              <span>Book 1-on-1 Guidance with Director</span>
              <i class="fa-solid fa-arrow-right"></i>
            </button>
            <a href="<?php echo WHATSAPP_LINK; ?>" class="btn-director-whatsapp" target="_blank" rel="noopener noreferrer">
              <i class="fa-brands fa-whatsapp"></i>
              <span>Chat on WhatsApp</span>
            </a>
          </div>

          <div class="director-reassurance-note">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Personal 1-on-1 Academic Counseling • Free Career &amp; Stream Guidance</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 5.5 The KSV Classroom Difference: Learning Culture -->
<section class="section-py learning-culture-section" id="learningCultureSection">
  <div class="container">
    <div class="culture-grid">
      <!-- Left Visual Card with Real Indian Students Photo -->
      <div class="culture-visual-wrap">
        <div class="culture-main-img-card">
          <img src="<?php echo url('assets/imgs/students/classroom-learning.jpg'); ?>" alt="Indian Students Interactive Classroom Learning at KSV Academy Ulwe" class="culture-img" loading="lazy">
          <div class="culture-img-overlay"></div>
        </div>

        <!-- Floating Badge 1 (Top Left) -->
        <div class="culture-floating-chip top-left">
          <div class="culture-chip-icon"><i class="fa-solid fa-users"></i></div>
          <div class="culture-chip-text">
            <strong>Max 25 Students</strong>
            <span>Individual Attention Guaranteed</span>
          </div>
        </div>

        <!-- Floating Badge 2 (Top Right) -->
        <div class="culture-floating-chip top-right">
          <div class="culture-chip-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;"><i class="fa-solid fa-shield-check"></i></div>
          <div class="culture-chip-text">
            <strong>Concept-First Approach</strong>
            <span>Zero Rote Learning</span>
          </div>
        </div>

        <!-- Bottom Integrated Card -->
        <div class="culture-bottom-card">
          <div class="culture-chip-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="fa-solid fa-hand-holding-heart"></i></div>
          <div>
            <strong style="display:block; font-size: 0.88rem; color: #0f172a;">Daily Open Doubt Desk</strong>
            <span style="font-size: 0.76rem; color: #64748b;">Students clear doubts with faculty immediately after every session</span>
          </div>
        </div>
      </div>

      <!-- Right Column: Features & Philosophy -->
      <div class="culture-content-wrap">
        <span class="section-eyebrow"><i class="fa-solid fa-lightbulb"></i> The KSV Difference</span>
        <h2 class="section-title">Where Curiosity Meets <span class="hero-highlight">Focused Mentorship</span></h2>
        <p class="section-subtitle" style="text-align: left; margin: 0 0 20px 0;">Unlike overcrowded 80+ student coaching factories, KSV Academy believes true academic excellence happens when teachers personally know each student's strengths and learning pace.</p>

        <div class="culture-features-list">
          <!-- Feature 1 -->
          <div class="culture-feature-card">
            <div class="cfc-icon"><i class="fa-solid fa-atom"></i></div>
            <div class="cfc-content">
              <h4>Foundational Concept Mastery</h4>
              <p>We teach students <em>why</em> formulas work, building intuitive problem-solving abilities that excel in school boards and entrance exams alike.</p>
            </div>
          </div>

          <!-- Feature 2 -->
          <div class="culture-feature-card">
            <div class="cfc-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;"><i class="fa-solid fa-chart-line"></i></div>
            <div class="cfc-content">
              <h4>Weekly Diagnostic OMR &amp; Subjective Tests</h4>
              <p>Chapter-wise assessments provide immediate ranking, error logs, and detailed progress reports accessible to parents 24/7 on our portal.</p>
            </div>
          </div>

          <!-- Feature 3 -->
          <div class="culture-feature-card">
            <div class="cfc-icon" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;"><i class="fa-solid fa-fingerprint"></i></div>
            <div class="cfc-content">
              <h4>Smart Biometric Attendance &amp; Instant SMS</h4>
              <p>Real-time automated check-in and check-out logs keep parents completely assured of their ward's punctuality and safety in Ulwe.</p>
            </div>
          </div>

          <!-- Feature 4 -->
          <div class="culture-feature-card">
            <div class="cfc-icon" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6;"><i class="fa-solid fa-book-open-reader"></i></div>
            <div class="cfc-content">
              <h4>Dedicated AC Reading Library</h4>
              <p>A quiet, distraction-free environment equipped with complete reference textbooks, past 10-year question banks, and faculty support.</p>
            </div>
          </div>
        </div>

        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
          <button type="button" class="btn btn-primary" data-modal="demoModal">
            <i class="fa-solid fa-calendar-check"></i> Experience a Live Class (Free)
          </button>
          <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp">
            <i class="fa-brands fa-whatsapp"></i> Speak to Counsellor
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 6. Free 3-Day Demo Class & QR Code Section -->
<section class="section-py bg-soft demo-section-wrapper" id="demoSection">
  <div class="container">
    <div class="demo-vip-card">
      <div class="demo-card-topbar"></div>
      
      <!-- Ambient Backdrops -->
      <div class="demo-bg-glow-emerald" aria-hidden="true"></div>
      <div class="demo-bg-glow-cyan" aria-hidden="true"></div>
      <div class="demo-watermark-icon" aria-hidden="true"><i class="fa-solid fa-ticket"></i></div>

      <div class="demo-vip-grid">
        <!-- Left: Demo Class Offer & Value Proposition -->
        <div class="demo-offer-col">
          <div class="demo-eyebrow-row">
            <span class="demo-vip-eyebrow">
              <i class="fa-solid fa-sparkles"></i> Experience KSV Academy
            </span>
            <span class="demo-seats-pill">
              <span class="pulse-seats-dot"></span> Only 8 Seats Available This Week
            </span>
          </div>

          <h2 class="demo-headline">
            Claim Your Free <span class="hero-highlight">3-Days Trial Pass</span>
          </h2>
          <p class="demo-subheadline">
            We invite students and parents in Ulwe and Navi Mumbai to experience our classes, faculty mentorship, and competitive atmosphere with <strong>zero upfront commitment</strong>.
          </p>

          <!-- 3 Interactive Benefits Cards -->
          <div class="demo-benefits-grid">
            <div class="demo-benefit-card">
              <div class="benefit-icon-box em-emerald">
                <i class="fa-solid fa-chalkboard-user"></i>
              </div>
              <div class="benefit-info">
                <strong class="benefit-title">Attend Live Classroom Lectures</strong>
                <p class="benefit-desc">Sit inside actual batches with our senior IIT-JEE, NEET &amp; Board specialists.</p>
              </div>
            </div>

            <div class="demo-benefit-card">
              <div class="benefit-icon-box em-blue">
                <i class="fa-solid fa-book-open-reader"></i>
              </div>
              <div class="benefit-info">
                <strong class="benefit-title">Complimentary Study Material &amp; Notes</strong>
                <p class="benefit-desc">Receive chapter summary sheets, solved formula sheets, and practice problem sets.</p>
              </div>
            </div>

            <div class="demo-benefit-card">
              <div class="benefit-icon-box em-purple">
                <i class="fa-solid fa-chart-line-up"></i>
              </div>
              <div class="benefit-info">
                <strong class="benefit-title">1-on-1 Academic &amp; Scholarship Evaluation</strong>
                <p class="benefit-desc">Get a personalized concept assessment &amp; check eligibility for up to 60% fee scholarships.</p>
              </div>
            </div>
          </div>

          <!-- CTAs -->
          <div class="demo-actions-row">
            <button type="button" class="btn-demo-primary" data-modal="demoModal">
              <i class="fa-solid fa-calendar-plus"></i>
              <span>Reserve Your Free Demo Seat</span>
              <i class="fa-solid fa-arrow-right"></i>
            </button>
            <a href="<?php echo WHATSAPP_LINK; ?>" class="btn-demo-whatsapp" target="_blank" rel="noopener noreferrer">
              <i class="fa-brands fa-whatsapp"></i>
              <span>Instant WhatsApp Booking</span>
            </a>
          </div>

          <!-- Trust Badges -->
          <div class="demo-guarantee-row">
            <span class="guarantee-tag"><i class="fa-solid fa-shield-check"></i> 100% Free Trial</span>
            <span class="guarantee-dot">•</span>
            <span class="guarantee-tag"><i class="fa-solid fa-ban"></i> Zero Credit Card / Fees Required</span>
            <span class="guarantee-dot">•</span>
            <span class="guarantee-tag"><i class="fa-solid fa-bolt"></i> Instant Confirmation</span>
          </div>
        </div>

        <!-- Right: VIP Digital Pass with Animated Laser Scanner -->
        <div class="demo-ticket-col">
          <div class="demo-pass-ticket">
            <div class="ticket-top-tag">
              <i class="fa-solid fa-ticket"></i>
              <span>VIP ADMISSION PASS</span>
            </div>

            <div class="ticket-qr-container">
              <!-- Animated Laser Scanner Line -->
              <div class="qr-laser-line"></div>
              
              <!-- SVG QR Code with Center Logo Badge -->
              <div class="qr-code-frame">
                <svg viewBox="0 0 100 100" fill="currentColor" class="qr-svg-graphic">
                  <path d="M10,10 h30 v30 h-30 z M15,15 v20 h20 v-20 z M22,22 h6 v6 h-6 z M60,10 h30 v30 h-30 z M65,15 v20 h20 v-20 z M72,22 h6 v6 h-6 z M10,60 h30 v30 h-30 z M15,65 v20 h20 v-20 z M22,72 h6 v6 h-6 z M45,15 h10 v10 h-10 z M45,35 h10 v10 h-10 z M45,55 h10 v10 h-10 z M65,45 h10 v10 h-10 z M75,55 h15 v10 h-15 z M60,70 h10 v20 h-10 z M75,70 h15 v10 h-15 z M75,85 h15 v5 h-15 z M15,45 h20 v5 h-20 z M45,75 h10 v15 h-10 z"/>
                </svg>
                <div class="qr-center-emblem">
                  <i class="fa-solid fa-graduation-cap"></i>
                </div>
              </div>
            </div>

            <div class="ticket-details">
              <h4 class="ticket-title">Scan with Phone Camera</h4>
              <p class="ticket-subtitle">Instant Demo Seat Booking via WhatsApp</p>
              
              <div class="ticket-steps-mini">
                <span class="step-badge"><strong>1</strong> Open Camera</span>
                <i class="fa-solid fa-chevron-right step-arrow"></i>
                <span class="step-badge"><strong>2</strong> Scan QR</span>
                <i class="fa-solid fa-chevron-right step-arrow"></i>
                <span class="step-badge"><strong>3</strong> Attend</span>
              </div>
            </div>

            <div class="ticket-verified-footer">
              <i class="fa-solid fa-circle-check"></i>
              <span>Official KSV Academy Verification Pass</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 7. Campus & Classroom Gallery Preview -->
<section class="section-py gallery-section-wrapper" id="gallerySection">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-camera"></i> Life At KSV Academy</span>
      <h2 class="section-title">Classrooms &amp; <span class="hero-highlight">Learning Environment</span></h2>
      <p class="section-subtitle">Take a glimpse inside our vibrant Ulwe campus where focused minds learn, solve doubts, and excel every day.</p>
    </div>

    <!-- Campus Infrastructure Highlights Mini Pills -->
    <div class="gallery-highlights-strip">
      <div class="gallery-highlight-pill">
        <i class="fa-solid fa-snowflake"></i>
        <span>Air-Conditioned Smart Classrooms</span>
      </div>
      <div class="gallery-highlight-pill">
        <i class="fa-solid fa-book-bookmark"></i>
        <span>Dedicated Self-Study Library</span>
      </div>
      <div class="gallery-highlight-pill">
        <i class="fa-solid fa-comments"></i>
        <span>Daily 1-on-1 Doubt Desk</span>
      </div>
      <div class="gallery-highlight-pill">
        <i class="fa-solid fa-video"></i>
        <span>CCTV Monitored Premises</span>
      </div>
    </div>

    <div class="gallery-grid">
      <?php foreach (array_slice($gallery_images, 0, 4) as $idx => $img): 
        $cat = !empty($img['category']) ? $img['category'] : 'Campus Life';
        $cat_icon = 'fa-chalkboard-user';
        if ($cat === 'Doubt Clearing' || $cat === 'Study Sessions') {
          $cat_icon = 'fa-comments';
        } elseif ($cat === 'Smart Classrooms') {
          $cat_icon = 'fa-desktop';
        } elseif ($cat === 'Study Library') {
          $cat_icon = 'fa-book-open-reader';
        }
      ?>
      <div class="gallery-card" data-src="<?php echo url('assets/imgs/gallers/' . $img['file']); ?>" data-caption="<?php echo htmlspecialchars($img['caption']); ?>">
        <div class="gallery-thumb-wrapper">
          <img src="<?php echo url('assets/imgs/gallers/' . $img['file']); ?>" alt="<?php echo htmlspecialchars($img['caption']); ?>" class="gallery-thumb" loading="lazy">
        </div>
        
        <!-- Persistent Top Badges -->
        <div class="gallery-top-badges">
          <span class="gallery-category-pill">
            <i class="fa-solid <?php echo $cat_icon; ?>"></i>
            <span><?php echo htmlspecialchars($cat); ?></span>
          </span>
          <span class="gallery-zoom-icon" title="Click to view full photo">
            <i class="fa-solid fa-magnifying-glass-plus"></i>
          </span>
        </div>

        <!-- Persistent Bottom Frosted Caption Strip -->
        <div class="gallery-overlay">
          <div class="gallery-overlay-inner">
            <p class="gallery-overlay-caption"><?php echo htmlspecialchars($img['caption']); ?></p>
            <span class="gallery-location-tag"><i class="fa-solid fa-location-dot"></i> Ulwe Sector 19 Campus</span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="gallery-cta-wrapper">
      <a href="<?php echo url('gallery'); ?>" class="btn-gallery-explore">
        <i class="fa-solid fa-images"></i>
        <span>Explore Full Campus Photo Gallery</span>
        <span class="gallery-count-pill">30+ Photos</span>
        <i class="fa-solid fa-chevron-right"></i>
      </a>
    </div>
  </div>
</section>

<!-- 7.5 K-SAT Scholarship & Admission Drive -->
<section class="section-py scholarship-drive-section" id="scholarshipDriveSection">
  <div class="container">
    <div class="ksat-banner-card">
      <div class="ksat-bg-glow"></div>
      
      <div class="ksat-grid">
        <!-- Left: Scholarship Offer & 3-Step Process -->
        <div class="ksat-left-col">
          <span class="ksat-badge-hot">
            <i class="fa-solid fa-bolt"></i> Academic Session 2026–27 Scholarship Drive
          </span>
          <h2 class="ksat-title">
            KSV Talent &amp; Scholarship Test <br>
            <span class="highlight-gold">Win Up to 60% Merit Scholarship</span>
          </h2>
          <p class="ksat-desc">
            Empowering deserving students across Ulwe &amp; Navi Mumbai. Take our 45-minute diagnostic assessment to unlock substantial tuition fee waivers, free foundational study kits, and personalized career roadmaps.
          </p>

          <!-- 3-Step Simple Process -->
          <div class="ksat-steps-row">
            <div class="ksat-step-item">
              <div class="ksat-step-num">STEP 01</div>
              <div class="ksat-step-title">Register Online</div>
              <p class="ksat-step-sub">100% Free • No registration fees required</p>
            </div>
            <div class="ksat-step-item">
              <div class="ksat-step-num">STEP 02</div>
              <div class="ksat-step-title">Take 45-Min Test</div>
              <p class="ksat-step-sub">Sunday slots at Ulwe Sector 19 center</p>
            </div>
            <div class="ksat-step-item">
              <div class="ksat-step-num">STEP 03</div>
              <div class="ksat-step-title">Claim Fee Waiver</div>
              <p class="ksat-step-sub">Instant merit-based concession up to 60%</p>
            </div>
          </div>
        </div>

        <!-- Right: Test Details & Instant Action Card -->
        <div class="ksat-right-col">
          <div class="ksat-info-card">
            <div>
              <div class="ksat-info-title">
                <i class="fa-solid fa-award" style="color: #38bdf8;"></i> Test Highlights &amp; Venue
              </div>
              
              <div class="ksat-details-list">
                <div class="ksat-detail-row">
                  <i class="fa-solid fa-clock"></i>
                  <div>
                    <strong>Exam Timing:</strong><br>
                    <span>Every Sunday (10:00 AM &amp; 04:00 PM Slots)</span>
                  </div>
                </div>
                <div class="ksat-detail-row">
                  <i class="fa-solid fa-graduation-cap"></i>
                  <div>
                    <strong>Eligible Classes:</strong><br>
                    <span>Class 5th to 12th (CBSE, ICSE, State) &amp; Degree</span>
                  </div>
                </div>
                <div class="ksat-detail-row">
                  <i class="fa-solid fa-location-dot"></i>
                  <div>
                    <strong>Venue:</strong><br>
                    <span>Sector 19, Ulwe, Navi Mumbai (Opp. Om Sai Hospital)</span>
                  </div>
                </div>
                <div class="ksat-detail-row">
                  <i class="fa-solid fa-gift"></i>
                  <div>
                    <strong>Special Bonus:</strong><br>
                    <span>Free Comprehensive Formula Handbook &amp; Study Planner</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="ksat-btn-actions">
              <button type="button" class="btn btn-gold" data-modal="demoModal" style="width: 100%; justify-content: center; font-weight: 800;">
                <i class="fa-solid fa-ticket"></i> Register for Scholarship (Free)
              </button>
              <a href="tel:<?php echo PHONE_RAW_1; ?>" class="btn btn-outline-white" style="width: 100%; justify-content: center;">
                <i class="fa-solid fa-phone"></i> Call Admission Desk: <?php echo PHONE_PRIMARY; ?>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 8. YouTube Video Classrooms Section -->
<section class="section-py bg-soft" id="digitalLearningSection">
  <div class="container">
    <div class="yt-preview-card">
      <div class="yt-preview-accent-bar"></div>

      <!-- Left: Interactive Video Player Mockup Card -->
      <div class="yt-player-col">
        <a href="<?php echo URL_YOUTUBE; ?>" target="_blank" rel="noopener noreferrer" class="yt-video-card" aria-label="Watch KSV Academy Lectures on YouTube">
          <div class="yt-video-thumb-wrap">
            <img src="<?php echo url('assets/imgs/gallers/youtube-lecture.jpg'); ?>" alt="KSV Academy Physics &amp; Mathematics Concept Lecture" class="yt-video-thumb" loading="lazy">
            <div class="yt-video-overlay">
              <!-- Top Stream Status Bar -->
              <div class="yt-stream-bar">
                <span class="yt-live-chip"><span class="yt-live-dot"></span> CONCEPT MASTERCLASS</span>
                <span class="yt-quality-chip"><i class="fa-solid fa-film"></i> 1080p HD</span>
              </div>

              <!-- Animated Center Radar Play Trigger -->
              <div class="yt-play-radar-wrap">
                <div class="yt-radar-ring ring-1"></div>
                <div class="yt-radar-ring ring-2"></div>
                <div class="yt-play-circle">
                  <i class="fa-solid fa-play"></i>
                </div>
              </div>

              <!-- Bottom Video Info Strip Inside Player -->
              <div class="yt-player-lower-third">
                <div class="yt-video-topic-tag"><i class="fa-solid fa-graduation-cap"></i> Physics &amp; Mathematics Series</div>
                <div class="yt-video-duration"><i class="fa-regular fa-clock"></i> 24:15 • Free Lecture</div>
              </div>
            </div>
          </div>

          <!-- Video Card Channel Footer -->
          <div class="yt-video-footer">
            <div class="yt-channel-meta">
              <span class="yt-channel-name">
                <i class="fa-solid fa-circle-check"></i> KSV Academy Official
                <span class="yt-channel-badge">Verified</span>
              </span>
              <span class="yt-channel-sub">Free Conceptual Lectures &amp; Board PYQ Solutions</span>
            </div>
            <span class="yt-watch-btn">
              Watch on YouTube <i class="fa-solid fa-arrow-up-right-from-square"></i>
            </span>
          </div>
        </a>
      </div>

      <!-- Right: Content Column -->
      <div class="yt-content">
        <div class="yt-eyebrow-wrap">
          <span class="section-eyebrow"><i class="fa-brands fa-youtube"></i> Free Digital Classroom</span>
          <span class="yt-status-pill"><span class="yt-live-pulse-dot"></span> New Weekly Uploads</span>
        </div>

        <h2 class="section-title">Learn Anytime, Anywhere with <span class="hero-highlight">KSV Digital Learning</span></h2>
        
        <p class="yt-desc">
          Accelerate your revision beyond the physical classroom. Stream structured topic walkthroughs, derivations, exam-solving strategies, and competitive shortcuts delivered by Mr. Salam Khan and senior faculty.
        </p>

        <!-- 3 Rich Playlist / Benefit Cards -->
        <div class="yt-features-list">
          <div class="yt-feature-card">
            <div class="yt-feature-icon icon-blue">
              <i class="fa-solid fa-atom"></i>
            </div>
            <div class="yt-feature-info">
              <h4 class="yt-feature-title">Physics &amp; Maths Conceptual Series</h4>
              <p class="yt-feature-desc">Formula derivations, real-world analogies, and crystal-clear visualization.</p>
            </div>
          </div>

          <div class="yt-feature-card">
            <div class="yt-feature-icon icon-green">
              <i class="fa-solid fa-file-signature"></i>
            </div>
            <div class="yt-feature-info">
              <h4 class="yt-feature-title">Board PYQs &amp; Step-by-Step Solutions</h4>
              <p class="yt-feature-desc">Last 10 years question breakdowns with Maharashtra &amp; CBSE board marking rubrics.</p>
            </div>
          </div>

          <div class="yt-feature-card">
            <div class="yt-feature-icon icon-amber">
              <i class="fa-solid fa-bolt-lightning"></i>
            </div>
            <div class="yt-feature-info">
              <h4 class="yt-feature-title">NEET, JEE &amp; MH-CET Speed Shortcuts</h4>
              <p class="yt-feature-desc">Option elimination methods, 30-second problem tricks &amp; exam time savers.</p>
            </div>
          </div>
        </div>

        <!-- Action CTAs -->
        <div class="yt-actions">
          <a href="<?php echo URL_YOUTUBE; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-yt-subscribe btn-md">
            <i class="fa-brands fa-youtube"></i> Subscribe to Channel
          </a>
          <a href="<?php echo URL_INSTAGRAM; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-md">
            <i class="fa-brands fa-instagram"></i> Follow on Instagram
          </a>
        </div>

        <div class="yt-footnote">
          <i class="fa-solid fa-bell"></i>
          <span>100% Free Public Access • Hit the bell icon to get notified on every new lecture.</span>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- 9. Student & Parent Testimonials -->
<section class="section-py" id="testimonialsSection">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-comments"></i> Real Student Stories</span>
      <h2 class="section-title">What Students &amp; <span class="hero-highlight">Parents Say</span></h2>
      <p class="section-subtitle">Real experiences and verified reviews from students and parents who achieved academic milestones at KSV Academy.</p>

      <!-- Google Reviews Trust Badge -->
      <div class="test-rating-banner">
        <div class="test-rating-source">
          <i class="fa-brands fa-google google-icon"></i>
          <span class="rating-num">4.9</span>
          <div class="rating-stars">
            <i class="fa-solid fa-star"></i>
            <i class="fa-solid fa-star"></i>
            <i class="fa-solid fa-star"></i>
            <i class="fa-solid fa-star"></i>
            <i class="fa-solid fa-star"></i>
          </div>
        </div>
        <span class="rating-divider"></span>
        <span class="rating-caption">Based on <strong>150+ Verified Reviews</strong> from Ulwe Sector 19 &amp; Navi Mumbai</span>
      </div>
    </div>

    <div class="testimonials-grid">
      <!-- Testimonial 1: Aditya Sharma (Board Topper) -->
      <div class="testimonial-card theme-blue">
        <div class="test-card-accent"></div>
        <div class="test-card-top">
          <div class="test-stars-wrap">
            <div class="test-stars">
              <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
            </div>
            <span class="test-verified-chip"><i class="fa-solid fa-circle-check"></i> Verified Student</span>
          </div>
          <span class="test-google-pill" title="Verified Google Review"><i class="fa-brands fa-google"></i></span>
        </div>

        <div class="test-score-badge badge-blue">
          <i class="fa-solid fa-trophy"></i> Scored 94% in 12th HSC Boards
        </div>

        <p class="test-quote">
          "<strong>Salam Sir's Physics lectures</strong> made complex numericals effortless for me. The weekly test series, chapter-wise formula sheets, and <strong>personalized doubt solving</strong> gave me the discipline and confidence to score <strong>94% in my 12th boards</strong>."
        </p>

        <div class="test-author-box">
          <div class="test-author-avatar avatar-blue">
            <span>AS</span>
            <span class="avatar-role-icon"><i class="fa-solid fa-graduation-cap"></i></span>
          </div>
          <div class="test-author-info">
            <div class="author-name-row">
              <strong>Aditya Sharma</strong>
              <span class="test-mini-verified" title="Identity Verified"><i class="fa-solid fa-circle-check"></i></span>
            </div>
            <span>12th Science (HSC Board)</span>
            <span class="author-tag">2-Year Classroom Batch</span>
          </div>
        </div>
      </div>

      <!-- Testimonial 2: Pooja Deshmukh (NEET Aspirant) -->
      <div class="testimonial-card theme-emerald">
        <div class="test-card-accent"></div>
        <div class="test-card-top">
          <div class="test-stars-wrap">
            <div class="test-stars">
              <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
            </div>
            <span class="test-verified-chip"><i class="fa-solid fa-circle-check"></i> Verified Student</span>
          </div>
          <span class="test-google-pill" title="Verified Google Review"><i class="fa-brands fa-google"></i></span>
        </div>

        <div class="test-score-badge badge-emerald">
          <i class="fa-solid fa-bullseye"></i> NEET &amp; Medical Focus Batch
        </div>

        <p class="test-quote">
          "Best coaching institute in Ulwe! <strong>Rameshwar Sir</strong> explains Organic Chemistry reaction mechanisms with crystal clarity. The faculty genuinely tracks individual progress, and NCERT line-by-line discussions gave me a huge edge in competitive prep."
        </p>

        <div class="test-author-box">
          <div class="test-author-avatar avatar-emerald">
            <span>PD</span>
            <span class="avatar-role-icon"><i class="fa-solid fa-notes-medical"></i></span>
          </div>
          <div class="test-author-info">
            <div class="author-name-row">
              <strong>Pooja Deshmukh</strong>
              <span class="test-mini-verified" title="Identity Verified"><i class="fa-solid fa-circle-check"></i></span>
            </div>
            <span>NEET &amp; 12th Board Aspirant</span>
            <span class="author-tag">Medical Foundation Cohort</span>
          </div>
        </div>
      </div>

      <!-- Testimonial 3: Rajesh Gupta (Parent) -->
      <div class="testimonial-card theme-purple">
        <div class="test-card-accent"></div>
        <div class="test-card-top">
          <div class="test-stars-wrap">
            <div class="test-stars">
              <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
            </div>
            <span class="test-verified-chip chip-parent"><i class="fa-solid fa-circle-check"></i> Verified Parent</span>
          </div>
          <span class="test-google-pill" title="Verified Google Review"><i class="fa-brands fa-google"></i></span>
        </div>

        <div class="test-score-badge badge-purple">
          <i class="fa-solid fa-user-shield"></i> Parent Reassurance Review
        </div>

        <p class="test-quote">
          "As parents, we wanted an academy in Ulwe with <strong>disciplined attendance tracking</strong> and experienced teachers. KSV Academy exceeded our expectations. Regular bi-weekly feedback reports and parent meetings kept us completely informed of our son's growth."
        </p>

        <div class="test-author-box">
          <div class="test-author-avatar avatar-purple">
            <span>RG</span>
            <span class="avatar-role-icon"><i class="fa-solid fa-heart"></i></span>
          </div>
          <div class="test-author-info">
            <div class="author-name-row">
              <strong>Rajesh Gupta</strong>
              <span class="test-mini-verified" title="Identity Verified"><i class="fa-solid fa-circle-check"></i></span>
            </div>
            <span>Parent of 10th CBSE Student</span>
            <span class="author-tag">Parent Community Member</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Social Proof Metrics Strip -->
    <div class="test-stats-strip">
      <div class="test-stat-item">
        <div class="test-stat-num">98.4%</div>
        <div class="test-stat-label">Board Exam Pass Rate</div>
      </div>
      <div class="test-stat-sep"></div>
      <div class="test-stat-item">
        <div class="test-stat-num">1,200+</div>
        <div class="test-stat-label">Students Guided in Ulwe</div>
      </div>
      <div class="test-stat-sep"></div>
      <div class="test-stat-item">
        <div class="test-stat-num">4.9 / 5</div>
        <div class="test-stat-label">Google Rating (150+ Reviews)</div>
      </div>
      <div class="test-stat-sep"></div>
      <div class="test-stat-item">
        <div class="test-stat-num">100%</div>
        <div class="test-stat-label">Personal Doubt Support</div>
      </div>
    </div>
  </div>
</section>

<!-- 10. Frequently Asked Questions (FAQ) Accordion -->
<section class="section-py bg-soft" id="faqSection">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-circle-question"></i> Instant Answers &amp; Admissions FAQ</span>
      <h2 class="section-title">Frequently Asked <span class="hero-highlight">Questions</span></h2>
      <p class="section-subtitle">Everything you need to know about admissions, free trial lectures, scholarships, and schedules at KSV Academy Ulwe.</p>
    </div>

    <div class="faq-split-layout">
      <!-- Left: Admissions Counselor Helpdesk Card -->
      <div class="faq-counselor-col">
        <div class="faq-helpdesk-card">
          <div class="faq-helpdesk-accent"></div>
          <div class="faq-helpdesk-status">
            <span class="faq-live-dot"></span> Active Admissions Desk (2026-27)
          </div>

          <div class="faq-helpdesk-icon">
            <i class="fa-solid fa-headset"></i>
          </div>

          <h3 class="faq-helpdesk-title">Have a Different Question?</h3>
          <p class="faq-helpdesk-desc">
            Speak directly with Mr. Salam Khan or our senior counselors for personalized guidance on course selection, batch timings, and fee structures.
          </p>

          <div class="faq-helpdesk-perks">
            <div class="faq-perk-item"><i class="fa-solid fa-bolt-lightning"></i> 15-minute quick response</div>
            <div class="faq-perk-item"><i class="fa-solid fa-user-check"></i> Free 1-on-1 career &amp; stream counseling</div>
            <div class="faq-perk-item"><i class="fa-solid fa-handshake-angle"></i> Transparent syllabus &amp; fee breakdown</div>
          </div>

          <div class="faq-helpdesk-actions">
            <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-faq-whatsapp btn-md">
              <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp
            </a>
            <a href="tel:<?php echo PHONE_RAW_1; ?>" class="btn btn-outline-primary btn-md">
              <i class="fa-solid fa-phone"></i> <?php echo PHONE_PRIMARY; ?>
            </a>
          </div>

          <div class="faq-helpdesk-address">
            <i class="fa-solid fa-location-dot"></i>
            <span>Sector 19, Ulwe, Navi Mumbai (Opp. Om Sai Hospital)</span>
          </div>
        </div>
      </div>

      <!-- Right: Interactive Accordion with Rich Content -->
      <div class="faq-accordion-col">
        <div class="faq-accordion">
          <!-- FAQ 1: Free Demo Class -->
          <div class="faq-item active">
            <button type="button" class="faq-header" aria-expanded="true">
              <div class="faq-header-left">
                <span class="faq-num num-blue">01</span>
                <div class="faq-title-wrap">
                  <span class="faq-category-tag cat-blue">Trial Pass</span>
                  <span class="faq-title">How can I avail the 3-Days Free Demo Class?</span>
                </div>
              </div>
              <i class="fa-solid fa-chevron-down faq-icon"></i>
            </button>
            <div class="faq-body">
              <div class="faq-body-content">
                <p>
                  You can register for the <strong>3-Days Free Demo Class</strong> by clicking the "Free Demo" button anywhere on our website, filling the quick enrollment form, or calling/WhatsApping us at <strong><?php echo PHONE_PRIMARY; ?></strong>.
                </p>
                <div class="faq-answer-highlight">
                  <div class="faq-chips-list">
                    <span class="faq-chip chip-blue"><i class="fa-solid fa-circle-check"></i> Attend 3 Regular Classroom Lectures</span>
                    <span class="faq-chip chip-blue"><i class="fa-solid fa-circle-check"></i> Zero Upfront Fees Required</span>
                    <span class="faq-chip chip-blue"><i class="fa-solid fa-circle-check"></i> Meet Faculty &amp; Inspect Notes</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- FAQ 2: Scholarship Criteria -->
          <div class="faq-item">
            <button type="button" class="faq-header" aria-expanded="false">
              <div class="faq-header-left">
                <span class="faq-num num-emerald">02</span>
                <div class="faq-title-wrap">
                  <span class="faq-category-tag cat-emerald">Financial Aid</span>
                  <span class="faq-title">What is the criteria for the scholarship up to 60%?</span>
                </div>
              </div>
              <i class="fa-solid fa-chevron-down faq-icon"></i>
            </button>
            <div class="faq-body">
              <div class="faq-body-content">
                <p>
                  Scholarships <strong>up to 60%</strong> are awarded based on student merit in the <strong>KSV Aptitude &amp; Diagnostic Assessment</strong>, along with previous academic board marks.
                </p>
                <div class="faq-answer-highlight">
                  <div class="faq-chips-list">
                    <span class="faq-chip chip-emerald"><i class="fa-solid fa-award"></i> 85%+ in Previous Board: Direct Concession</span>
                    <span class="faq-chip chip-emerald"><i class="fa-solid fa-file-pen"></i> Free Diagnostic Aptitude Test</span>
                    <span class="faq-chip chip-emerald"><i class="fa-solid fa-percent"></i> Merit-Based Fee Waiver</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- FAQ 3: Boards and Streams Covered -->
          <div class="faq-item">
            <button type="button" class="faq-header" aria-expanded="false">
              <div class="faq-header-left">
                <span class="faq-num num-purple">03</span>
                <div class="faq-title-wrap">
                  <span class="faq-category-tag cat-purple">Curriculum Scope</span>
                  <span class="faq-title">Which boards and streams are covered at KSV Academy?</span>
                </div>
              </div>
              <i class="fa-solid fa-chevron-down faq-icon"></i>
            </button>
            <div class="faq-body">
              <div class="faq-body-content">
                <p>
                  We coach school students across <strong>CBSE, ICSE, and Maharashtra State Board</strong> from Class 5th to 10th. For Junior College and competitive entrances, we offer specialized coaching:
                </p>
                <div class="faq-answer-highlight">
                  <div class="faq-chips-list">
                    <span class="faq-chip chip-purple"><i class="fa-solid fa-book"></i> 5th - 10th (CBSE / ICSE / State Board)</span>
                    <span class="faq-chip chip-purple"><i class="fa-solid fa-atom"></i> 11th - 12th Science (PCM / PCB)</span>
                    <span class="faq-chip chip-purple"><i class="fa-solid fa-chart-line"></i> 11th - 12th Commerce</span>
                    <span class="faq-chip chip-purple"><i class="fa-solid fa-rocket"></i> IIT-JEE • NEET • MH-CET</span>
                    <span class="faq-chip chip-purple"><i class="fa-solid fa-user-graduate"></i> Degree: B.Com, BMS, BAF, BCA</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- FAQ 4: Class Timings and Batch Options -->
          <div class="faq-item">
            <button type="button" class="faq-header" aria-expanded="false">
              <div class="faq-header-left">
                <span class="faq-num num-amber">04</span>
                <div class="faq-title-wrap">
                  <span class="faq-category-tag cat-amber">Schedules</span>
                  <span class="faq-title">What are the class timings and batch options?</span>
                </div>
              </div>
              <i class="fa-solid fa-chevron-down faq-icon"></i>
            </button>
            <div class="faq-body">
              <div class="faq-body-content">
                <p>
                  Batches are structured so that students never have a clash with their school or junior college hours:
                </p>
                <div class="faq-answer-highlight">
                  <div class="faq-chips-list">
                    <span class="faq-chip chip-amber"><i class="fa-regular fa-sun"></i> Morning Batch: 8:00 AM – 11:30 AM</span>
                    <span class="faq-chip chip-amber"><i class="fa-solid fa-moon"></i> Evening Batch: 4:00 PM – 8:30 PM</span>
                    <span class="faq-chip chip-amber"><i class="fa-solid fa-comments"></i> Weekend Dedicated Doubt Sessions</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- FAQ 5: Center Location in Ulwe -->
          <div class="faq-item">
            <button type="button" class="faq-header" aria-expanded="false">
              <div class="faq-header-left">
                <span class="faq-num num-rose">05</span>
                <div class="faq-title-wrap">
                  <span class="faq-category-tag cat-rose">Campus Visit</span>
                  <span class="faq-title">Where is KSV Academy located in Ulwe?</span>
                </div>
              </div>
              <i class="fa-solid fa-chevron-down faq-icon"></i>
            </button>
            <div class="faq-body">
              <div class="faq-body-content">
                <p>
                  Our academy is strategically situated in the heart of Ulwe Sector 19:
                  <strong>MGM Residency, Shop No.02, Plot No.161, opp. Om Sai Hospital, Sector 19, Ulwe, Navi Mumbai, 410206</strong>.
                </p>
                <div class="faq-answer-highlight">
                  <div class="faq-chips-list">
                    <span class="faq-chip chip-rose"><i class="fa-solid fa-hospital"></i> Landmark: Opposite Om Sai Hospital</span>
                    <span class="faq-chip chip-rose"><i class="fa-solid fa-bus"></i> Walking Distance from Transit &amp; Parking</span>
                    <span class="faq-chip chip-rose"><i class="fa-solid fa-shield-halved"></i> CCTV Secured Campus</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
