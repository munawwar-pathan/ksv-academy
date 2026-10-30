<?php
require_once __DIR__ . '/config.php';

$active_page = 'faculty';
$page_title = 'Our Expert Faculty | KSV Academy Ulwe | Dedicated Mentors & Subject Specialists';
$page_description = 'Meet the distinguished faculty team at KSV Academy Ulwe. Learn with Mr. Salam Khan (Physics, 10 Yrs), Mr. Rameshwar Mishra (Chemistry, 15 Yrs), Mr. Aman Kumar Soni, Hafiza Palikar, Rafeek Khan & Mrs. Lubna Khan.';
$page_keywords = 'KSV Academy faculty, Salam Khan Physics Ulwe, Rameshwar Mishra Chemistry, Aman Kumar Soni, Hafiza Palikar Mumbai University math, Rafeek Khan CS, Lubna Khan English SST, Ulwe tuition teachers';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Faculty Page Hero Header (Attractive Design Matching Home & About) -->
<section class="about-hero-banner">
  <div class="container text-center">
    <div class="about-banner-content">
      <!-- Dual Eyebrows -->
      <div class="hero-eyebrows-wrap" style="justify-content: center; margin-bottom: 16px;">
        <div class="hero-eyebrow">
          <span class="hero-live-dot"></span>
          <span>Distinguished Academic Mentors • Session <?php echo CURRENT_SESSION; ?></span>
        </div>
        <div class="hero-trust-badge">
          <i class="fa-solid fa-award"></i>
          <span>10–15+ Years Avg. Experience in Ulwe</span>
        </div>
      </div>

      <!-- Main Heading -->
      <h1 class="about-banner-title">
        Our Expert <span class="hero-highlight">Faculty Team</span>
      </h1>

      <!-- Slogan / Motto Tagline -->
      <div class="hero-motto-row" style="justify-content: center; margin-bottom: 16px;">
        <span class="motto-lead"><i class="fa-solid fa-sparkles"></i> Dedicated Professionals &amp; Subject Leaders</span>
        <span class="motto-divider">•</span>
        <span class="motto-sub">Individual Attention &amp; Unmatched Mentorship</span>
      </div>

      <!-- Lead Description -->
      <p class="about-banner-desc">
        "Many dedicated professionals and academics comprise our leadership." — Learn from experienced educators with proven expertise across school boards, engineering, medical, and degree curricula.
      </p>

      <!-- Quick Stats Counter Strip -->
      <div class="hero-stats-row" style="max-width: 760px; margin: 0 auto;">
        <div class="stat-item">
          <span class="stat-number" data-target="15" data-suffix="+">15+</span>
          <span class="stat-label">Years Max Exp.</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="6" data-suffix="">6</span>
          <span class="stat-label">Senior Specialists</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="100" data-suffix="%">100%</span>
          <span class="stat-label">1-on-1 Doubt Support</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="49" data-prefix="4." data-suffix="/5">4.9/5</span>
          <span class="stat-label">Student Rating</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. Faculty Grid Section -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-chalkboard-user"></i> Master Mentors</span>
      <h2 class="section-title">Meet Our Distinguished <span class="hero-highlight">Educators</span></h2>
      <p class="section-subtitle">Dedicated subject specialists with 10–15+ years of classroom experience guiding students in Ulwe.</p>
    </div>

    <div class="faculty-grid">
      <?php
      $all_faculties = get_active_faculties();
      foreach ($all_faculties as $idx => $fac): 
        $theme_meta = get_faculty_theme_info($fac, $idx);
        $themeClass = $theme_meta['theme'];
        $subj_icon = $theme_meta['icon'];
        $subj_label = $theme_meta['label'];
      ?>
      <div class="faculty-card <?php echo $themeClass; ?>" id="<?php echo htmlspecialchars($fac['id']); ?>">
        <div class="faculty-card-topbar"></div>
        
        <div class="faculty-photo-wrapper">
          <div class="faculty-ambient-glow"></div>
          <img src="<?php echo url($fac['image']); ?>" alt="<?php echo htmlspecialchars($fac['name']); ?>" class="faculty-photo" loading="lazy">
          
          <div class="faculty-photo-badges">
            <span class="faculty-subj-badge">
              <i class="fa-solid <?php echo $subj_icon; ?>"></i>
              <span><?php echo htmlspecialchars($subj_label); ?></span>
            </span>
            <span class="faculty-exp-badge">
              <i class="fa-solid fa-medal"></i>
              <span><?php echo htmlspecialchars($fac['experience']); ?></span>
            </span>
          </div>
        </div>

        <div class="faculty-info-block">
          <div class="faculty-header-meta">
            <h3 class="faculty-name"><?php echo htmlspecialchars($fac['name']); ?></h3>
            <span class="faculty-role-pill"><?php echo htmlspecialchars($fac['title']); ?></span>
          </div>

          <div class="faculty-qualification-row">
            <span class="faculty-qual-icon"><i class="fa-solid fa-graduation-cap"></i></span>
            <span><?php echo htmlspecialchars($fac['degrees']); ?></span>
          </div>
          
          <div class="faculty-spec-tag">
            <i class="fa-solid fa-award"></i> Specialization: <?php echo htmlspecialchars($fac['specialization']); ?>
          </div>

          <div class="faculty-highlight-strip">
            <i class="fa-solid fa-circle-check"></i>
            <span><?php echo htmlspecialchars($fac['highlight']); ?></span>
          </div>

          <p class="faculty-bio"><?php echo htmlspecialchars($fac['bio']); ?></p>

          <div class="faculty-actions-row">
            <button type="button" class="btn-faculty-action" data-modal="demoModal">
              <span>Book Demo with <?php echo htmlspecialchars(explode(' ', $fac['name'])[0] === 'Mr.' || explode(' ', $fac['name'])[0] === 'Mrs.' ? $fac['name'] : explode(' ', $fac['name'])[0]); ?></span>
              <i class="fa-solid fa-arrow-right"></i>
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 3. Teaching Methodology -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-lightbulb"></i> Mentorship Style</span>
      <h2 class="section-title">Why Students Love <span class="hero-highlight">Our Faculty</span></h2>
      <p class="section-subtitle">Our teachers don't just deliver lectures; they foster an environment of inquisitive thinking and exam mastery.</p>
    </div>

    <div class="features-grid">
      <!-- Methodology 1 -->
      <div class="methodology-card">
        <div class="methodology-topbar bar-blue"></div>
        <div class="methodology-icon icon-blue"><i class="fa-solid fa-hands-holding-circle"></i></div>
        <div class="methodology-tag"><i class="fa-solid fa-user-check"></i> 1-on-1 Mentorship</div>
        <h3>Accessible Doubt Clearing</h3>
        <p>Teachers stay back after every lecture to resolve individual student doubts. No student leaves the academy with unresolved queries.</p>
        <ul class="methodology-checklist">
          <li><i class="fa-solid fa-check"></i> Dedicated Saturday Doubt Counters</li>
          <li><i class="fa-solid fa-check"></i> Zero Fear, Patient Explanations</li>
        </ul>
      </div>

      <!-- Methodology 2 -->
      <div class="methodology-card">
        <div class="methodology-topbar bar-emerald"></div>
        <div class="methodology-icon icon-emerald"><i class="fa-solid fa-chart-pie"></i></div>
        <div class="methodology-tag"><i class="fa-solid fa-bullseye"></i> High-Yield Focus</div>
        <h3>Exam-Oriented Insights</h3>
        <p>Our educators analyze past 10 years exam patterns (CBSE, HSC, NEET, JEE) to highlight high-weightage topics and recurring questions.</p>
        <ul class="methodology-checklist">
          <li><i class="fa-solid fa-check"></i> 10-Year Board Paper Trends</li>
          <li><i class="fa-solid fa-check"></i> Proven Answer Writing Frameworks</li>
        </ul>
      </div>

      <!-- Methodology 3 -->
      <div class="methodology-card">
        <div class="methodology-topbar bar-amber"></div>
        <div class="methodology-icon icon-amber"><i class="fa-solid fa-heart-pulse"></i></div>
        <div class="methodology-tag"><i class="fa-solid fa-fire"></i> Inspiring Mindset</div>
        <h3>Continuous Motivation</h3>
        <p>Frequent motivational sessions and stress-management guidance by Director Mr. Salam Khan keep students inspired and disciplined.</p>
        <ul class="methodology-checklist">
          <li><i class="fa-solid fa-check"></i> Regular Parent-Teacher Alignment</li>
          <li><i class="fa-solid fa-check"></i> Stress-Free Exam Preparation</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- 4. Demo Class CTA (Attractive Banner Card Matching Home & About) -->
<section class="section-py">
  <div class="container">
    <div class="demo-banner-card" style="background: linear-gradient(135deg, #02457a 0%, #0077b6 55%, #0092df 100%); border-radius: 28px; padding: 48px 40px; color: #ffffff; display: grid; grid-template-columns: 1.25fr 0.75fr; gap: 32px; align-items: center; box-shadow: 0 20px 48px -10px rgba(0, 146, 223, 0.35); position: relative; overflow: hidden;">
      <div style="position: absolute; right: -50px; bottom: -50px; width: 260px; height: 260px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); pointer-events: none;"></div>
      
      <div>
        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); padding: 5px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.04em;">
          <i class="fa-solid fa-sparkles"></i> Experience Excellence
        </div>
        <h2 style="font-size: 2.2rem; font-weight: 800; line-height: 1.2; margin-bottom: 12px; color: #ffffff;">
          Experience Learning from <span style="color: #38bdf8;">Our Star Educators</span>
        </h2>
        <p style="font-size: 1.02rem; color: rgba(255,255,255,0.9); line-height: 1.6; margin: 0 0 22px;">
          Register for a 3-Days Free Demo Class and interact directly with our senior faculties at our Ulwe Sector 19 center. Observe our interactive teaching methodology firsthand.
        </p>
        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
          <button type="button" class="btn btn-warning btn-lg" data-modal="demoModal" style="background: #f59e0b; color: #08212e; border: none; font-weight: 800; box-shadow: 0 4px 16px rgba(245,158,11,0.4);">
            <i class="fa-regular fa-calendar-check"></i> Claim 3-Days Free Demo Pass
          </button>
          <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-outline-white btn-lg" target="_blank" rel="noopener noreferrer" style="border: 2px solid rgba(255,255,255,0.6); color: #ffffff;">
            <i class="fa-brands fa-whatsapp"></i> Inquire via WhatsApp
          </a>
        </div>
      </div>

      <div style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 26px; backdrop-filter: blur(8px); text-align: center;">
        <div style="font-size: 2.2rem; font-weight: 800; color: #ffffff; line-height: 1;">4.9 / 5.0</div>
        <div style="color: #fbbf24; font-size: 1.1rem; margin: 8px 0;"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
        <div style="font-size: 0.9rem; font-weight: 700; color: #ffffff;">Top-Rated Mentors in Ulwe</div>
        <p style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.8); margin: 6px 0 16px;">150+ Verified Google &amp; Parent Reviews</p>
        <a href="tel:<?php echo PHONE_RAW_1; ?>" class="btn btn-sm" style="background: #ffffff; color: var(--primary-700); font-weight: 700; width: 100%;">
          <i class="fa-solid fa-phone"></i> Call: <?php echo PHONE_PRIMARY; ?>
        </a>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
