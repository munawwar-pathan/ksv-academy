<?php
require_once __DIR__ . '/config.php';

$active_page = 'about';
$page_title = 'About Us | KSV Academy Ulwe | Where Learning Meets Excellence';
$page_description = 'Learn about KSV Academy in Ulwe, Navi Mumbai. Discover our mission, teaching philosophy, 10+ years academic legacy, and commitment to shaping future doctors, engineers, and toppers.';
$page_keywords = 'About KSV Academy, coaching philosophy, Salam Khan physics, Ulwe coaching classes, educational institute Navi Mumbai, tuition classes Ulwe';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header Hero Banner (Attractive EdTech Design Matching Home) -->
<section class="about-hero-banner">
  <div class="container text-center">
    <div class="about-banner-content">
      <!-- Dual Eyebrows -->
      <div class="hero-eyebrows-wrap" style="justify-content: center; margin-bottom: 16px;">
        <div class="hero-eyebrow">
          <span class="hero-live-dot"></span>
          <span>Know Our Journey • Session <?php echo CURRENT_SESSION; ?></span>
        </div>
        <div class="hero-trust-badge">
          <i class="fa-solid fa-award"></i>
          <span>#1 Coaching in Ulwe Sector 19</span>
        </div>
      </div>

      <!-- Main Heading -->
      <h1 class="about-banner-title">
        About <span class="hero-highlight">KSV Academy</span>
      </h1>

      <!-- Slogan / Motto Tagline -->
      <div class="hero-motto-row" style="justify-content: center; margin-bottom: 16px;">
        <span class="motto-lead"><i class="fa-solid fa-sparkles"></i> Ab Sapnon Ki Coaching Ulwe Mein!</span>
        <span class="motto-divider">•</span>
        <span class="motto-sub">Strong Foundation, Sure Success</span>
      </div>

      <!-- Lead Description -->
      <p class="about-banner-desc">
        Where Learning Meets Excellence — Empowering minds, building strong foundations, and guiding students to peak academic achievement in Ulwe, Navi Mumbai.
      </p>

      <!-- Quick Stats Counter Strip (From Home Page) -->
      <div class="hero-stats-row" style="max-width: 760px; margin: 0 auto;">
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
  </div>
</section>

<!-- Section 1: Who We Are -->
<section class="section-py">
  <div class="container">
    <div class="about-top-grid">
      <div>
        <span class="section-eyebrow"><i class="fa-solid fa-star"></i> Who We Are</span>
        <h2 class="section-title">Welcome to <span class="hero-highlight">KSV Academy</span></h2>
        <p style="font-size: 1.02rem; color: var(--text-muted); line-height: 1.75; margin-bottom: 16px;">
          Welcome to KSV Academy, where education meets excellence! Our academy is committed to fostering an environment where students can grow intellectually, socially, and personally. With a strong emphasis on quality education, we offer a comprehensive curriculum that focuses on academic achievement, critical thinking, and holistic development.
        </p>
        <p style="font-size: 1.02rem; color: var(--text-muted); line-height: 1.75; margin-bottom: 16px;">
          At KSV Academy, we believe every student has the potential to succeed. Our team of highly qualified educators is dedicated to nurturing that potential, providing personalized attention, and creating learning experiences that inspire and empower. We strive to build a foundation of knowledge that prepares our students for a bright future, equipping them with the skills needed to thrive in a rapidly changing world.
        </p>
        <p style="font-size: 1.02rem; color: var(--text-muted); line-height: 1.75; margin-bottom: 20px;">
          Through modern teaching methods, state-of-the-art facilities, and a supportive learning atmosphere, we ensure our students receive the best possible education. Join KSV Academy and embark on a journey of academic excellence and personal growth.
        </p>

        <!-- 3 Modern Feature Point Pills -->
        <div class="about-feature-points">
          <div class="about-feature-point">
            <i class="fa-solid fa-circle-check"></i>
            <span>Concept-First Pedagogy for CBSE, ICSE, SSC &amp; HSC Boards</span>
          </div>
          <div class="about-feature-point">
            <i class="fa-solid fa-circle-check"></i>
            <span>Disciplined Small Batches with Daily 1-on-1 Doubt Solving</span>
          </div>
          <div class="about-feature-point">
            <i class="fa-solid fa-circle-check"></i>
            <span>Integrated Entrance Coaching for IIT-JEE, NEET-UG &amp; MH-CET</span>
          </div>
        </div>

        <div style="display: flex; gap: 14px; flex-wrap: wrap; margin-top: 24px;">
          <button type="button" class="btn btn-primary btn-lg" data-modal="demoModal">
            <span>Book 3-Days Free Demo</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>
          <a href="<?php echo url('contact'); ?>" class="btn btn-outline-primary btn-lg">
            <i class="fa-solid fa-location-dot"></i>
            <span>Visit Campus</span>
          </a>
        </div>
      </div>

      <!-- Right Side: Director Executive Showcase Card (Matching Home Quality) -->
      <div>
        <div class="hero-sober-card">
          <div class="hero-card-accent-bar"></div>

          <!-- Director Spotlight Header -->
          <div class="sober-card-header">
            <div class="sober-avatar-wrap">
              <img src="<?php echo url('assets/imgs/faculties/founder-director.png'); ?>" alt="Mr. Salam Khan - Founder &amp; Director" class="sober-avatar-img">
              <span class="sober-verified-check" title="Verified Director"><i class="fa-solid fa-circle-check"></i></span>
            </div>
            <div class="sober-profile-info">
              <span class="sober-lead-badge"><i class="fa-solid fa-shield-halved"></i> Academic Leadership</span>
              <h3 class="sober-name">Mr. Salam Khan</h3>
              <p class="sober-qualification">Founder &amp; Director • M.Sc, B.Ed (Physics)</p>
              <span class="sober-experience">10+ Years Mentoring Physics &amp; Mathematics</span>
            </div>
          </div>

          <!-- Director Quote -->
          <div style="background: rgba(0, 146, 223, 0.05); border-left: 3px solid var(--primary-500); padding: 14px 16px; border-radius: 8px; font-size: 0.88rem; color: var(--text-main); line-height: 1.65; font-style: italic;">
            "Mr. Salam Sir has 10 years teaching experience specially in Physics &amp; Mathematics and his mentorship has made a significant impact on the lives of his students. His students have achieved remarkable success in highly competitive exams like NEET, IIT-JEE &amp; MH-CET."
          </div>

          <!-- Verified Trust Badges -->
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <div class="badge-tag" style="background: var(--primary-soft); color: var(--primary-700); border: 1px solid var(--border-primary); font-weight: 700; font-size: 0.82rem; padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 8px;">
              <i class="fa-solid fa-award" style="color: var(--primary-600);"></i> 10+ Years Mentoring Success in Ulwe
            </div>
            <div class="badge-tag" style="background: rgba(16, 185, 129, 0.08); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); font-weight: 700; font-size: 0.82rem; padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 8px;">
              <i class="fa-solid fa-graduation-cap" style="color: #10b981;"></i> Specialized NEET, JEE &amp; Board Guidance
            </div>
          </div>

          <!-- Verified Trust Footer -->
          <div class="hero-sober-trust-footer" style="border-top: 1px solid var(--border-subtle); padding-top: 14px; margin-top: 6px;">
            <div class="sober-rating-pill">
              <span class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></span>
              <strong>4.9 / 5</strong>
              <span>Rating (150+ Reviews)</span>
            </div>
            <div class="sober-location-tag">
              <i class="fa-solid fa-location-dot"></i>
              <span>Sector 19, Ulwe</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Section 2: Core Philosophy / Official Message -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="about-message-box">
      <div class="about-message-content">
        <span class="section-eyebrow" style="background: rgba(56, 189, 248, 0.15); color: #bae6fd; border: 1px solid rgba(56, 189, 248, 0.3); margin-bottom: 16px;">
          <i class="fa-solid fa-quote-left"></i> Core Philosophy &amp; Vision
        </span>
        <h2 class="section-title" style="color: #ffffff; font-size: 2.3rem; font-weight: 800; margin-bottom: 18px;">
          The KSV Academy <span style="color: #38bdf8;">Message</span>
        </h2>
        <blockquote class="about-message-quote">
          "At KSV Academy, we are dedicated to providing a dynamic learning environment that nurtures intellectual curiosity, creativity, and personal growth. Our mission is to empower students with the knowledge, skills, and confidence to excel academically and thrive in life."
        </blockquote>
        <p style="color: #cbd5e1; font-size: 1.02rem; line-height: 1.7; margin: 0 auto; max-width: 740px;">
          Through innovative teaching methods, experienced faculty, and a student-centric approach, we ensure every learner reaches their full potential. Join us at KSV Academy, where education meets opportunity, and success knows no limits!
        </p>

        <!-- Core Value Chips -->
        <div class="about-message-chips">
          <span class="about-message-chip"><i class="fa-solid fa-brain"></i> Intellectual Curiosity</span>
          <span class="about-message-chip"><i class="fa-solid fa-lightbulb"></i> Critical Thinking</span>
          <span class="about-message-chip"><i class="fa-solid fa-seedling"></i> Personal Growth</span>
          <span class="about-message-chip"><i class="fa-solid fa-shield-halved"></i> Ethical Discipline</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Section 3: Academic Spectrum Breakdown -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-diagram-project"></i> Comprehensive Academic Reach</span>
      <h2 class="section-title">What We Cover at <span class="hero-highlight">KSV Academy</span></h2>
      <p class="section-subtitle">Structured, concept-first coaching across all core academic tiers in Ulwe.</p>
    </div>

    <div class="cover-cards-grid">
      <!-- Tier 1: School Section -->
      <div class="cover-card">
        <div class="cover-card-header">
          <div class="cover-card-icon" style="background: rgba(0, 146, 223, 0.12); color: var(--primary-600);"><i class="fa-solid fa-school"></i></div>
          <div class="cover-card-title-group">
            <h3>School Section</h3>
            <span class="cover-card-level">Classes 5th to 10th</span>
          </div>
        </div>
        <p class="cover-card-desc">
          Building a strong conceptual base in mathematics, science, and languages for board rankers and academic distinction.
        </p>
        <ul class="cover-card-list">
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> CBSE, ICSE &amp; State Board Syllabus</li>
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> Core Subjects: Maths, Science, English &amp; SST</li>
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> Concept-First Pedagogy &amp; Problem Solving</li>
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> Regular Chapter Tests &amp; Board Mock Exams</li>
        </ul>
        <div class="cover-card-footer">
          <span style="font-size: 0.8rem; color: var(--text-muted);"><i class="fa-solid fa-users"></i> Small Batches</span>
          <a href="<?php echo url('courses?filter=school'); ?>" class="cover-card-link">Explore Courses <i class="fa-solid fa-arrow-right"></i></a>
        </div>
      </div>

      <!-- Tier 2: Junior College & Competitive Entrance -->
      <div class="cover-card">
        <div class="cover-card-header">
          <div class="cover-card-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;"><i class="fa-solid fa-atom"></i></div>
          <div class="cover-card-title-group">
            <h3>Junior College &amp; Entrance</h3>
            <span class="cover-card-level">11th, 12th &bull; JEE / NEET / CET</span>
          </div>
        </div>
        <p class="cover-card-desc">
          High-yield preparation for medical and engineering entrances seamlessly integrated with 11th &amp; 12th board curricula.
        </p>
        <ul class="cover-card-list">
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> IIT-JEE (Main &amp; Advanced) Specialization</li>
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> NEET-UG Medical Entrance Mentorship</li>
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> MH-CET Engineering &amp; Pharmacy Batches</li>
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> 11th &amp; 12th Science &amp; Commerce Boards</li>
        </ul>
        <div class="cover-card-footer">
          <span style="font-size: 0.8rem; color: var(--text-muted);"><i class="fa-solid fa-user-graduate"></i> Top Mentors</span>
          <a href="<?php echo url('courses?filter=competitive'); ?>" class="cover-card-link">Explore Courses <i class="fa-solid fa-arrow-right"></i></a>
        </div>
      </div>

      <!-- Tier 3: Undergraduate Degree Programs -->
      <div class="cover-card">
        <div class="cover-card-header">
          <div class="cover-card-icon" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6;"><i class="fa-solid fa-graduation-cap"></i></div>
          <div class="cover-card-title-group">
            <h3>Undergraduate Degree</h3>
            <span class="cover-card-level">Commerce &amp; IT Programs</span>
          </div>
        </div>
        <p class="cover-card-desc">
          Expert degree college coaching covering accounts, finance, management, and computing with university exam focus.
        </p>
        <ul class="cover-card-list">
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> B.Com: Accounts, Taxation, Costing &amp; Economics</li>
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> BMS &amp; BAF: Financial &amp; Business Management</li>
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> BCA: Programming, Databases &amp; IT Skills</li>
          <li><i class="fa-solid fa-check" style="color: #10b981;"></i> M.Com &amp; Postgraduate Academic Guidance</li>
        </ul>
        <div class="cover-card-footer">
          <span style="font-size: 0.8rem; color: var(--text-muted);"><i class="fa-solid fa-briefcase"></i> Career Focus</span>
          <a href="<?php echo url('courses?filter=degree'); ?>" class="cover-card-link">Explore Courses <i class="fa-solid fa-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Section 4: Values & Pillars Grid -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-gem"></i> Our Guiding Principles</span>
      <h2 class="section-title">The Four Pillars of <span class="hero-highlight">KSV Excellence</span></h2>
      <p class="section-subtitle">Guiding every classroom session, test, and mentorship meeting.</p>
    </div>

    <div class="four-pillars-grid">
      <!-- Pillar 1 -->
      <div class="pillar-clean-card">
        <div class="pillar-card-top">
          <div class="pillar-card-icon icon-blue"><i class="fa-solid fa-lightbulb"></i></div>
          <span class="pillar-step-num">01</span>
        </div>
        <h3 class="pillar-clean-title">Concept Clarity</h3>
        <span class="pillar-clean-tag">Deep Fundamentals</span>
        <p class="pillar-clean-desc">
          No rote learning. We focus on 'Why' before 'How' so students build authentic understanding to tackle complex and unexpected exam problems.
        </p>
      </div>

      <!-- Pillar 2 -->
      <div class="pillar-clean-card">
        <div class="pillar-card-top">
          <div class="pillar-card-icon icon-emerald"><i class="fa-solid fa-chart-line"></i></div>
          <span class="pillar-step-num">02</span>
        </div>
        <h3 class="pillar-clean-title">Continuous Testing</h3>
        <span class="pillar-clean-tag">Weekly Assessments</span>
        <p class="pillar-clean-desc">
          Weekly chapter-wise tests and board-pattern exams build exam temperament, time management, and lasting academic retention.
        </p>
      </div>

      <!-- Pillar 3 -->
      <div class="pillar-clean-card">
        <div class="pillar-card-top">
          <div class="pillar-card-icon icon-amber"><i class="fa-solid fa-user-check"></i></div>
          <span class="pillar-step-num">03</span>
        </div>
        <h3 class="pillar-clean-title">Individual Mentorship</h3>
        <span class="pillar-clean-tag">Personal Attention</span>
        <p class="pillar-clean-desc">
          Maintained small batch sizes ensure dedicated mentors track each student's progress and provide personalized 1-on-1 doubt solving.
        </p>
      </div>

      <!-- Pillar 4 -->
      <div class="pillar-clean-card">
        <div class="pillar-card-top">
          <div class="pillar-card-icon icon-purple"><i class="fa-solid fa-award"></i></div>
          <span class="pillar-step-num">04</span>
        </div>
        <h3 class="pillar-clean-title">Merit Scholarships</h3>
        <span class="pillar-clean-tag">Accessible Excellence</span>
        <p class="pillar-clean-desc">
          Empowering ambitious students with up to 60% merit scholarships, ensuring premier quality education remains accessible to all.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Section 5: CTA Ribbon (Matching Home Page Demo Card Quality) -->
<section class="section-py">
  <div class="container">
    <div class="demo-banner-card" style="background: linear-gradient(135deg, #02457a 0%, #0077b6 55%, #0092df 100%); border-radius: 28px; padding: 48px 40px; color: #ffffff; display: grid; grid-template-columns: 1.25fr 0.75fr; gap: 32px; align-items: center; box-shadow: 0 20px 48px -10px rgba(0, 146, 223, 0.35); position: relative; overflow: hidden;">
      <div style="position: absolute; right: -50px; bottom: -50px; width: 260px; height: 260px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); pointer-events: none;"></div>
      
      <div>
        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); padding: 5px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.04em;">
          <i class="fa-solid fa-sparkles"></i> Take the First Step
        </div>
        <h2 style="font-size: 2.2rem; font-weight: 800; line-height: 1.2; margin-bottom: 12px; color: #ffffff;">
          Ready to Experience <span style="color: #38bdf8;">KSV Academy?</span>
        </h2>
        <p style="font-size: 1.02rem; color: rgba(255,255,255,0.9); line-height: 1.6; margin: 0 0 22px;">
          Take advantage of our 3-Days Free Demo Class to witness our classroom atmosphere, concept-first mentoring, and dedicated doubt solving first-hand.
        </p>
        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
          <button type="button" class="btn btn-warning btn-lg" data-modal="demoModal" style="background: #f59e0b; color: #08212e; border: none; font-weight: 800; box-shadow: 0 4px 16px rgba(245,158,11,0.4);">
            <i class="fa-regular fa-calendar-check"></i> Book 3-Days Free Demo
          </button>
          <a href="<?php echo url('contact'); ?>" class="btn btn-outline-white btn-lg" style="border: 2px solid rgba(255,255,255,0.6); color: #ffffff;">
            <i class="fa-solid fa-location-dot"></i> Visit Our Ulwe Campus
          </a>
        </div>
      </div>

      <div style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 26px; backdrop-filter: blur(8px); text-align: center;">
        <div style="font-size: 2.2rem; font-weight: 800; color: #ffffff; line-height: 1;">4.9 / 5.0</div>
        <div style="color: #fbbf24; font-size: 1.1rem; margin: 8px 0;"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
        <div style="font-size: 0.9rem; font-weight: 700; color: #ffffff;">Top-Rated Coaching in Ulwe</div>
        <p style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.8); margin: 6px 0 16px;">150+ Verified Google &amp; Parent Reviews</p>
        <a href="tel:<?php echo PHONE_RAW_1; ?>" class="btn btn-sm" style="background: #ffffff; color: var(--primary-700); font-weight: 700; width: 100%;">
          <i class="fa-solid fa-phone"></i> Call: <?php echo PHONE_PRIMARY; ?>
        </a>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
