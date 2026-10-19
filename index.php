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
      <!-- Clean & Sober Eyebrow -->
      <div class="hero-eyebrow">
        <span class="hero-eyebrow-dot"></span>
        <span>Admissions Open 2026–27 • Sector 19, Ulwe</span>
      </div>

      <!-- Main Heading -->
      <h1 class="hero-title">
        Where Learning Meets <span class="hero-highlight">Excellence.</span>
      </h1>

      <!-- Slogan / Motto (Clean Typographic Line, Not an overwhelming box) -->
      <div class="hero-motto-row">
        <span class="motto-lead">Ab Sapnon Ki Coaching Ulwe Mein!</span>
        <span class="motto-divider">•</span>
        <span class="motto-sub">Strong Foundation, Sure Success</span>
      </div>

      <!-- Academic Lead Description -->
      <p class="hero-description">
        Ulwe’s trusted coaching institute for Classes 5th to 12th (CBSE, ICSE, State), IIT-JEE, NEET, and Degree courses with concept-first mentoring and small, disciplined batches.
      </p>

      <!-- Key Course Streams Marquee (Clean, Sober & Uniform) -->
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

    <!-- Right Side: Dignified & Sober Academy Showcase Card -->
    <div class="hero-media-wrapper">
      <div class="hero-sober-card">
        <!-- Faculty Spotlight Header -->
        <div class="sober-card-header">
          <div class="sober-avatar-wrap">
            <img src="<?php echo url('assets/imgs/faculties/founder-director.png'); ?>" alt="Mr. Salam Khan - Founder & Director" class="sober-avatar-img">
          </div>
          <div class="sober-profile-info">
            <span class="sober-lead-badge">Academic Leadership</span>
            <h3 class="sober-name">Mr. Salam Khan</h3>
            <p class="sober-qualification">Founder &amp; Director • M.Sc, B.Ed</p>
            <span class="sober-experience">10+ Years Mentoring Physics &amp; Mathematics</span>
          </div>
        </div>

        <!-- Academic Features Checklist -->
        <ul class="sober-features-list">
          <li>
            <i class="fa-solid fa-circle-check"></i>
            <div>
              <strong>Concept-First Learning:</strong> Complete syllabus clarity for CBSE, ICSE, SSC &amp; HSC boards.
            </div>
          </li>
          <li>
            <i class="fa-solid fa-circle-check"></i>
            <div>
              <strong>Competitive Coaching:</strong> Integrated preparation for IIT-JEE, NEET-UG &amp; MHT-CET.
            </div>
          </li>
          <li>
            <i class="fa-solid fa-circle-check"></i>
            <div>
              <strong>Weekly Assessments:</strong> Regular chapter-wise tests with timely parent progress updates.
            </div>
          </li>
          <li>
            <i class="fa-solid fa-circle-check"></i>
            <div>
              <strong>Individual Attention:</strong> Small batch sizes with dedicated daily 1-on-1 doubt solving.
            </div>
          </li>
        </ul>

        <!-- Reassurance Footer -->
        <div class="sober-card-footer">
          <div class="sober-rating">
            <div class="sober-stars">
              <i class="fa-solid fa-star"></i>
              <i class="fa-solid fa-star"></i>
              <i class="fa-solid fa-star"></i>
              <i class="fa-solid fa-star"></i>
              <i class="fa-solid fa-star-half-stroke"></i>
            </div>
            <strong>4.9 / 5</strong>
            <span>Rating</span>
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
      <!-- Pillar 1 -->
      <div class="pillar-sober-card">
        <div class="pillar-sober-icon">
          <i class="fa-solid fa-graduation-cap"></i>
        </div>
        <h3 class="pillar-sober-title">Result-Oriented Coaching</h3>
        <p class="pillar-sober-desc">
          Structured preparation engineered to help students maximize scores in school boards (CBSE, ICSE, SSC), HSC, and premier competitive examinations.
        </p>
        <ul class="pillar-sober-list">
          <li><i class="fa-solid fa-circle-check"></i> Concept-first pedagogy for complete syllabus clarity</li>
          <li><i class="fa-solid fa-circle-check"></i> Integrated Board + IIT-JEE &amp; NEET syllabus mapping</li>
          <li><i class="fa-solid fa-circle-check"></i> Systematic solving of past 10-year question patterns</li>
        </ul>
      </div>

      <!-- Pillar 2 -->
      <div class="pillar-sober-card">
        <div class="pillar-sober-icon">
          <i class="fa-solid fa-clipboard-check"></i>
        </div>
        <h3 class="pillar-sober-title">Weekly Tests &amp; Monitoring</h3>
        <p class="pillar-sober-desc">
          Continuous chapter-wise and cumulative assessments track progress systematically, identifying weak spots early and building ironclad exam confidence.
        </p>
        <ul class="pillar-sober-list">
          <li><i class="fa-solid fa-circle-check"></i> Sunday chapter tests and computerized mock test series</li>
          <li><i class="fa-solid fa-circle-check"></i> Regular parent-teacher progress review meetings</li>
          <li><i class="fa-solid fa-circle-check"></i> Speed enhancement and negative-marking control</li>
        </ul>
      </div>

      <!-- Pillar 3 -->
      <div class="pillar-sober-card">
        <div class="pillar-sober-icon">
          <i class="fa-solid fa-user-shield"></i>
        </div>
        <h3 class="pillar-sober-title">Individual Attention &amp; Care</h3>
        <p class="pillar-sober-desc">
          We maintain disciplined, small batch sizes so senior educators can mentor each student personally, fostering absolute confidence without hesitation.
        </p>
        <ul class="pillar-sober-list">
          <li><i class="fa-solid fa-circle-check"></i> Small batch sizes ensuring every learner is attended to</li>
          <li><i class="fa-solid fa-circle-check"></i> Daily 1-on-1 dedicated doubt clearing counters</li>
          <li><i class="fa-solid fa-circle-check"></i> Personal mentorship by Director Salam Khan</li>
        </ul>
      </div>
    </div>

    <!-- Simple & Sober Trust Bar -->
    <div class="pillars-sober-trust">
      <div class="sober-trust-item">
        <div class="sober-trust-icon"><i class="fa-solid fa-award"></i></div>
        <div class="sober-trust-info">
          <strong>10+ Years Legacy in Ulwe</strong>
          <span>1500+ successful alumni across top colleges</span>
        </div>
      </div>
      <div class="sober-trust-divider"></div>
      <div class="sober-trust-item">
        <div class="sober-trust-icon"><i class="fa-solid fa-book-open-reader"></i></div>
        <div class="sober-trust-info">
          <strong>Comprehensive Study Kits</strong>
          <span>Handcrafted notes, mind maps &amp; question banks</span>
        </div>
      </div>
      <div class="sober-trust-divider"></div>
      <div class="sober-trust-item">
        <div class="sober-trust-icon"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="sober-trust-info">
          <strong>3-Days Free Trial Lectures</strong>
          <span>Attend real sessions before taking admission</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 3. Course Catalog Explorer (Interactive Tabbed View like Khan Global Studies) -->
<section class="section-py" id="coursesSection">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-book-bookmark"></i> Programs We Offer</span>
      <h2 class="section-title">Explore Our <span class="hero-highlight">Academic Batches</span></h2>
      <p class="section-subtitle">From foundational schooling to university degrees and competitive exams, discover customized learning pathways.</p>
    </div>

    <!-- Filter Buttons -->
    <div class="courses-tab-nav">
      <button type="button" class="course-tab-btn active" data-filter="all">All Programs</button>
      <button type="button" class="course-tab-btn" data-filter="school">School Section (5th - 10th)</button>
      <button type="button" class="course-tab-btn" data-filter="competitive">IIT-JEE / NEET / MH-CET</button>
      <button type="button" class="course-tab-btn" data-filter="college">Junior College (11th & 12th)</button>
      <button type="button" class="course-tab-btn" data-filter="degree">Undergraduate & Degrees</button>
    </div>

    <!-- Course Cards Grid -->
    <div class="courses-grid">
      <?php foreach ($courses as $c): ?>
      <div class="course-card" data-category="<?php echo htmlspecialchars($c['category']); ?>">
        <div class="course-card-top">
          <span class="course-cat-tag"><?php echo htmlspecialchars($c['category_name']); ?></span>
          <span class="course-status-pill"><?php echo htmlspecialchars($c['badge']); ?></span>
        </div>

        <div class="course-card-main">
          <h3 class="course-title"><?php echo htmlspecialchars($c['title']); ?></h3>
          <div class="course-standards"><?php echo htmlspecialchars($c['standards']); ?></div>
          <p class="course-desc"><?php echo htmlspecialchars($c['description']); ?></p>
        </div>

        <div class="course-card-specs">
          <div class="course-spec-item">
            <i class="fa-solid fa-layer-group"></i>
            <span><strong>Boards:</strong> <?php echo htmlspecialchars($c['boards']); ?></span>
          </div>
          <div class="course-spec-item">
            <i class="fa-solid fa-book-open"></i>
            <span><strong>Curriculum:</strong> <?php echo htmlspecialchars($c['subjects']); ?></span>
          </div>
        </div>

        <ul class="course-checklist">
          <?php foreach (array_slice($c['features'], 0, 3) as $f): ?>
          <li>
            <i class="fa-solid fa-circle-check"></i>
            <span><?php echo htmlspecialchars($f); ?></span>
          </li>
          <?php endforeach; ?>
        </ul>

        <div class="course-actions">
          <button type="button" class="btn btn-primary btn-sm" data-modal="demoModal">
            <span>Free Demo Pass</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>
          <a href="<?php echo url('courses'); ?>" class="btn btn-outline-primary btn-sm">
            View Details
          </a>
        </div>
      </div>
      <?php endforeach; ?>
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
      <?php foreach ($faculties as $fac): ?>
      <div class="faculty-card reveal-on-scroll">
        <div class="faculty-photo-wrapper">
          <img src="<?php echo url($fac['image']); ?>" alt="<?php echo htmlspecialchars($fac['name']); ?>" class="faculty-photo" loading="lazy">
          <span class="faculty-exp-badge"><i class="fa-solid fa-award"></i> <?php echo htmlspecialchars($fac['experience']); ?> Exp</span>
        </div>
        <div class="faculty-info-block">
          <h3 class="faculty-name"><?php echo htmlspecialchars($fac['name']); ?></h3>
          <div class="faculty-role"><?php echo htmlspecialchars($fac['title']); ?></div>
          <div class="faculty-qualification"><i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($fac['degrees']); ?></div>
          <div class="faculty-spec-tag"><i class="fa-solid fa-atom"></i> Specialization: <?php echo htmlspecialchars($fac['specialization']); ?></div>
          <p class="faculty-bio"><?php echo htmlspecialchars($fac['bio']); ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align: center; margin-top: 36px;">
      <a href="<?php echo url('faculty'); ?>" class="btn btn-outline-primary btn-md">
        <i class="fa-solid fa-users"></i>
        <span>View All Faculty Profiles &amp; Credentials</span>
      </a>
    </div>
  </div>
</section>

<!-- 5. Founder & Director Message Section -->
<section class="section-py" id="directorSection">
  <div class="container">
    <div class="director-sober-card">
      <div class="director-sober-grid">
        <!-- Left: Clean Director Portrait Frame -->
        <div class="director-sober-portrait-col">
          <div class="director-sober-portrait-frame">
            <img src="<?php echo url('assets/imgs/faculties/founder-director.png'); ?>" alt="Mr. Salam Khan - Founder &amp; Director" class="director-sober-img" loading="lazy">
          </div>
          <div class="director-sober-nameplate">
            <h4 class="director-sober-name">Mr. Salam Khan</h4>
            <span class="director-sober-role">Founder &amp; Director, KSV Academy</span>
            <div class="director-sober-qual">
              <i class="fa-solid fa-graduation-cap"></i> M.Sc, B.Ed (Physics) • 10+ Years Exp
            </div>
          </div>
        </div>

        <!-- Right: Message Content -->
        <div class="director-sober-content">
          <span class="section-eyebrow"><i class="fa-solid fa-quote-left"></i> Founder &amp; Director's Desk</span>
          <h2 class="section-title">Empowering Minds, <span class="hero-highlight">Shaping Futures</span></h2>
          
          <div class="director-sober-quote">
            "At KSV Academy, we are dedicated to providing a dynamic learning environment that nurtures intellectual curiosity, creativity, and personal growth. Our mission is to empower students with the knowledge, skills, and confidence to excel academically and thrive in life."
          </div>

          <p class="director-sober-bio">
            With over 10 years of passionate teaching in Physics &amp; Mathematics, Mr. Salam Khan has helped hundreds of students build clear concept fundamentals. His mentorship has guided aspirants into premier engineering and medical institutions through NEET, IIT-JEE, and MH-CET.
          </p>

          <div class="director-sober-creds">
            <span class="director-cred-item"><i class="fa-solid fa-circle-check"></i> 10+ Years Dedicated Teaching</span>
            <span class="director-cred-item"><i class="fa-solid fa-circle-check"></i> Concept-First Physics Pedagogy</span>
            <span class="director-cred-item"><i class="fa-solid fa-circle-check"></i> IIT-JEE, NEET &amp; MH-CET Mentor</span>
          </div>

          <div class="director-sober-actions">
            <button type="button" class="btn btn-primary btn-md" data-modal="demoModal">
              <i class="fa-solid fa-calendar-check"></i> Book 1-on-1 Guidance with Director
            </button>
            <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-outline-primary btn-md" target="_blank" rel="noopener noreferrer">
              <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 6. Free 3-Day Demo Class & QR Code Section -->
<section class="section-py bg-soft" id="demoSection">
  <div class="container">
    <div class="demo-banner-card">
      <div class="demo-banner-content">
        <span class="section-eyebrow"><i class="fa-solid fa-calendar-check"></i> Experience KSV Academy</span>
        <h2 class="section-title">Claim Your Free <span class="hero-highlight">3-Days Demo Class</span></h2>
        <p class="demo-banner-desc">
          We invite students and parents in Ulwe and Navi Mumbai to experience our classes and teaching methodology before making any admission commitment.
        </p>

        <div class="demo-points">
          <div class="demo-pt"><i class="fa-solid fa-circle-check"></i> Attend actual lectures with our senior subject mentors</div>
          <div class="demo-pt"><i class="fa-solid fa-circle-check"></i> Review class notes, study material &amp; practice sheets</div>
          <div class="demo-pt"><i class="fa-solid fa-circle-check"></i> Get 1-on-1 counseling &amp; scholarship assessment eligibility</div>
        </div>

        <div class="demo-banner-actions">
          <button type="button" class="btn btn-primary btn-md" data-modal="demoModal">
            <i class="fa-solid fa-calendar-plus"></i> Reserve Your Demo Seat Now
          </button>
          <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-outline-primary btn-md" target="_blank" rel="noopener noreferrer">
            <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp
          </a>
        </div>
      </div>

      <div class="demo-qr-box">
        <div class="demo-qr-header">
          <span class="demo-qr-badge"><i class="fa-solid fa-qrcode"></i> Quick Scan</span>
        </div>
        <div class="qr-code-placeholder">
          <!-- Clean SVG QR Code Representation -->
          <svg viewBox="0 0 100 100" fill="currentColor">
            <path d="M10,10 h30 v30 h-30 z M15,15 v20 h20 v-20 z M22,22 h6 v6 h-6 z M60,10 h30 v30 h-30 z M65,15 v20 h20 v-20 z M72,22 h6 v6 h-6 z M10,60 h30 v30 h-30 z M15,65 v20 h20 v-20 z M22,72 h6 v6 h-6 z M45,15 h10 v10 h-10 z M45,35 h10 v10 h-10 z M45,55 h10 v10 h-10 z M65,45 h10 v10 h-10 z M75,55 h15 v10 h-15 z M60,70 h10 v20 h-10 z M75,70 h15 v10 h-15 z M75,85 h15 v5 h-15 z M15,45 h20 v5 h-20 z M45,75 h10 v15 h-10 z"/>
          </svg>
        </div>
        <div class="demo-qr-info">
          <h4 class="demo-qr-title">Scan for Free 3-Days Demo</h4>
          <p class="demo-qr-subtitle">Instant registration via WhatsApp or Web</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 7. Campus & Classroom Gallery Preview -->
<section class="section-py" id="gallerySection">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-camera"></i> Life At KSV Academy</span>
      <h2 class="section-title">Classrooms &amp; <span class="hero-highlight">Learning Environment</span></h2>
      <p class="section-subtitle">Take a glimpse inside our vibrant Ulwe campus where focused minds learn and excel every day.</p>
    </div>

    <div class="gallery-grid">
      <?php foreach (array_slice($gallery_images, 0, 4) as $img): ?>
      <div class="gallery-card" data-src="<?php echo url('assets/imgs/gallers/' . $img['file']); ?>" data-caption="<?php echo htmlspecialchars($img['caption']); ?>">
        <img src="<?php echo url('assets/imgs/gallers/' . $img['file']); ?>" alt="<?php echo htmlspecialchars($img['caption']); ?>" class="gallery-thumb" loading="lazy">
        <div class="gallery-overlay">
          <span class="gallery-zoom-icon"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
          <p class="gallery-overlay-caption"><?php echo htmlspecialchars($img['caption']); ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align: center; margin-top: 32px;">
      <a href="<?php echo url('gallery'); ?>" class="btn btn-outline-primary btn-md">
        <i class="fa-solid fa-images"></i>
        <span>View Full Campus Photo Gallery</span>
      </a>
    </div>
  </div>
</section>

<!-- 8. YouTube Video Classrooms Section -->
<section class="section-py bg-soft" id="digitalLearningSection">
  <div class="container">
    <div class="yt-preview-card">
      <!-- Left: Sober Video Mockup Card -->
      <a href="<?php echo URL_YOUTUBE; ?>" target="_blank" rel="noopener noreferrer" class="yt-video-card" aria-label="Watch KSV Academy Lectures on YouTube">
        <div class="yt-video-thumb-wrap">
          <img src="<?php echo url('assets/imgs/gallers/gallery-1.png'); ?>" alt="KSV Academy Classroom Lectures" class="yt-video-thumb" loading="lazy">
          <div class="yt-video-overlay">
            <div class="yt-play-circle">
              <i class="fa-solid fa-play"></i>
            </div>
            <div class="yt-video-badge">
              <i class="fa-brands fa-youtube"></i> Free Video Lectures
            </div>
          </div>
        </div>
        <div class="yt-video-footer">
          <div class="yt-channel-meta">
            <span class="yt-channel-name"><i class="fa-solid fa-circle-check"></i> KSV Academy Official</span>
            <span class="yt-channel-sub">Physics • Chemistry • Mathematics</span>
          </div>
          <span class="yt-watch-btn">
            Watch Lectures <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </span>
        </div>
      </a>

      <!-- Right: Content Column -->
      <div class="yt-content">
        <span class="section-eyebrow"><i class="fa-brands fa-youtube"></i> Digital Learning</span>
        <h2 class="section-title">Learn Anytime, Anywhere with <span class="hero-highlight">KSV Academy</span></h2>
        <p class="yt-desc">
          Subscribe to our official YouTube channel for free topic walkthroughs, exam solving strategies, board question discussions, and competitive problem tricks delivered by Mr. Salam Khan and our educator team.
        </p>

        <div class="yt-features">
          <div class="yt-feature-item"><i class="fa-solid fa-circle-check"></i> Free Physics &amp; Mathematics concept series</div>
          <div class="yt-feature-item"><i class="fa-solid fa-circle-check"></i> Board exam preparation &amp; previous year solutions</div>
          <div class="yt-feature-item"><i class="fa-solid fa-circle-check"></i> NEET, IIT-JEE &amp; MH-CET problem tricks</div>
        </div>

        <div class="yt-actions">
          <a href="<?php echo URL_YOUTUBE; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-md">
            <i class="fa-brands fa-youtube"></i> Subscribe to Channel
          </a>
          <a href="<?php echo URL_INSTAGRAM; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-md">
            <i class="fa-brands fa-instagram"></i> Follow on Instagram
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 9. Student & Parent Testimonials -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-comments"></i> Student Success</span>
      <h2 class="section-title">What Students &amp; <span class="hero-highlight">Parents Say</span></h2>
      <p class="section-subtitle">Real experiences and feedback from students who transformed their grades and confidence at KSV Academy.</p>
    </div>

    <div class="testimonials-grid">
      <div class="testimonial-card">
        <div class="test-card-top">
          <div class="test-stars">
            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
          </div>
          <span class="test-quote-icon"><i class="fa-solid fa-quote-right"></i></span>
        </div>
        <p class="test-quote">
          "Salam Sir's Physics lectures made concepts effortless for me. I used to struggle with numerical problems, but the weekly tests and personalized doubt sessions gave me the confidence to score 94% in my 12th boards."
        </p>
        <div class="test-author-box">
          <div class="test-author-avatar">A</div>
          <div class="test-author-info">
            <strong>Aditya Sharma</strong>
            <span>12th Science (HSC Board)</span>
          </div>
        </div>
      </div>

      <div class="testimonial-card">
        <div class="test-card-top">
          <div class="test-stars">
            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
          </div>
          <span class="test-quote-icon"><i class="fa-solid fa-quote-right"></i></span>
        </div>
        <p class="test-quote">
          "Best coaching institute in Ulwe. Rameshwar Sir explains Organic Chemistry so clearly that reactions make complete logical sense. The faculty genuinely cares about each student's growth and progress."
        </p>
        <div class="test-author-box">
          <div class="test-author-avatar">P</div>
          <div class="test-author-info">
            <strong>Pooja Deshmukh</strong>
            <span>NEET &amp; 12th Board Aspirant</span>
          </div>
        </div>
      </div>

      <div class="testimonial-card">
        <div class="test-card-top">
          <div class="test-stars">
            <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
          </div>
          <span class="test-quote-icon"><i class="fa-solid fa-quote-right"></i></span>
        </div>
        <p class="test-quote">
          "As parents, we were looking for an academy in Ulwe with disciplined monitoring and experienced teachers. KSV Academy exceeded our expectations. Regular feedback and parent meetings keep us informed."
        </p>
        <div class="test-author-box">
          <div class="test-author-avatar">R</div>
          <div class="test-author-info">
            <strong>Rajesh Gupta (Parent)</strong>
            <span>Parent of 10th CBSE Student</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 10. Frequently Asked Questions (FAQ) Accordion -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-circle-question"></i> Have Questions?</span>
      <h2 class="section-title">Frequently Asked <span class="hero-highlight">Questions</span></h2>
      <p class="section-subtitle">Everything you need to know about joining KSV Academy in Ulwe Sector 19.</p>
    </div>

    <div class="faq-accordion">
      <div class="faq-item active">
        <button type="button" class="faq-header">
          <span class="faq-title">How can I avail the 3-Days Free Demo Class?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            You can register for the 3-Days Free Demo Class by clicking the "Free Demo" button anywhere on our website, filling the quick form, or calling/WhatsApping us at <strong>+91 79059 00350</strong>. You can attend 3 regular lectures free of cost before confirming admission.
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
            Scholarships up to 60% are awarded based on student eligibility and performance in our KSV Academy Aptitude Assessment Test, as well as previous academic board results. Contact our counselor for evaluation dates.
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">Which boards and streams are covered at KSV Academy?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            We coach students for CBSE, ICSE, and Maharashtra State Board from Class 5th to 10th. For Junior College (11th & 12th), we cover Science (PCM/PCB) and Commerce. We also run dedicated entrance batches for IIT-JEE, NEET, and MH-CET, along with Degree courses (B.Com, BMS, BAF, BCA, M.Com).
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">What are the class timings and batch options?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            We offer both Morning (8:00 AM – 11:30 AM) and Evening (4:00 PM – 8:30 PM) batches, tailored to align comfortably with school and college schedules. Special weekend doubt-clearing sessions are also held every Saturday and Sunday.
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">Where is KSV Academy located in Ulwe?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            Our center is conveniently located at: <strong>MGM Residency, Shop No.02, Plot No.161, opp. Om Sai Hospital, Sector 19, Ulwe, Navi Mumbai, 410206</strong>. It is easily accessible with ample transit and parking.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
