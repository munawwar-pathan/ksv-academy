<?php
// Defense-in-depth HTTP security headers
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

require_once __DIR__ . '/config.php';

$active_page = 'toppers';
$page_title = 'Academic Toppers & Merit Rank Holders | KSV Academy Ulwe Sector 19';
$page_description = 'Meet the star academic toppers and board exam rank holders of KSV Academy in Ulwe Sector 19. Celebrating 98.8% highest score, 100% board pass rate, and distinction achievers across Science, Commerce & NEET.';
$page_keywords = 'KSV Academy toppers, coaching toppers Ulwe, board exam toppers Ulwe Sector 19, HSC science rankers Navi Mumbai, CBSE 10th 12th toppers Ulwe, NEET medical achievers Ulwe, IIT JEE rankers, best coaching results Ulwe';
$page_image = rtrim(IS_LIVE ? LIVE_URL : SITE_URL, '/') . '/assets/imgs/students/topper-boy.jpg';
$canonical_url = rtrim(IS_LIVE ? LIVE_URL : SITE_URL, '/') . '/toppers';

// Fetch active toppers from database
$toppers = get_active_toppers();
$total_toppers = count($toppers);

// Extract available sessions from database & active toppers
$toppers_active_session = function_exists('get_toppers_active_session') ? get_toppers_active_session() : null;
$topper_active_name = $toppers_active_session['session_name'] ?? CURRENT_SESSION;

$sessions_available = [];
if (function_exists('get_all_sessions')) {
    $all_sess_db = get_all_sessions();
    foreach ($all_sess_db as $as) {
        $sn = trim($as['session_name'] ?? '');
        if (!empty($sn) && !in_array($sn, $sessions_available, true)) {
            $sessions_available[] = $sn;
        }
    }
}
foreach ($toppers as $t) {
    $s_name = trim($t['session_name'] ?? '');
    if (!empty($s_name) && !in_array($s_name, $sessions_available, true)) {
        $sessions_available[] = $s_name;
    }
}
if (!in_array($topper_active_name, $sessions_available, true)) {
    $sessions_available[] = $topper_active_name;
}
if (!in_array('2025-2026', $sessions_available, true)) {
    $sessions_available[] = '2025-2026';
}
if (!in_array('2026-2027', $sessions_available, true)) {
    $sessions_available[] = '2026-2027';
}
rsort($sessions_available);

// URL pre-filter for session (strictly whitelist validated & sanitized against injection)
$raw_req_session = isset($_GET['session']) && $_GET['session'] !== '' ? trim((string)$_GET['session']) : $topper_active_name;
$clean_req_session = preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '', $raw_req_session);
if ($clean_req_session === 'all' || in_array($clean_req_session, $sessions_available, true)) {
    $active_req_session = $clean_req_session;
} else {
    $active_req_session = $topper_active_name;
}

// Calculate initial matching scholars
$initial_matching_toppers = 0;
foreach ($toppers as $t) {
    $t_sess = trim($t['session_name'] ?? '');
    if (empty($t_sess)) $t_sess = '2025-2026';
    if ($active_req_session === 'all' || strcasecmp($t_sess, $active_req_session) === 0) {
        $initial_matching_toppers++;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Page Hero Banner -->
<section class="about-hero-banner">
  <div class="container text-center">
    <div class="about-banner-content">
      
      <!-- Dual Eyebrows -->
      <div class="hero-eyebrows-wrap" style="justify-content: center; margin-bottom: 16px;">
        <div class="hero-eyebrow">
          <span class="hero-live-dot" style="background: #f59e0b;"></span>
          <span>Academic Hall of Fame • Session <?php echo htmlspecialchars($topper_active_name, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="hero-trust-badge" style="background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.3); color: #d97706;">
          <i class="fa-solid fa-trophy" style="color: #f59e0b;"></i>
          <span>100% Board Pass Rate &amp; Distinction Scorers</span>
        </div>
      </div>

      <!-- Main Heading -->
      <h1 class="about-banner-title">
        Our Academic <span class="hero-highlight">Toppers &amp; Achievers</span>
      </h1>

      <!-- Slogan / Motto Tagline -->
      <div class="hero-motto-row" style="justify-content: center; margin-bottom: 16px;">
        <span class="motto-lead"><i class="fa-solid fa-crown" style="color: #f59e0b;"></i> Extraordinary Academic Milestones</span>
        <span class="motto-divider">•</span>
        <span class="motto-sub">Dedicated Mentorship &amp; Relentless Diligence</span>
      </div>

      <!-- Lead Description -->
      <p class="about-banner-desc" style="max-width: 820px; margin: 0 auto 24px;">
        Every year, students of KSV Academy scale unprecedented heights in Maharashtra State Board (HSC/SSC), CBSE, ICSE, and competitive entrance examinations. Meet our pride scholars who turned dedication into distinction.
      </p>

      <!-- Quick Stats Counter Strip -->
      <div class="hero-stats-row" style="max-width: 760px; margin: 0 auto;">
        <div class="stat-item">
          <span class="stat-number">98.8%</span>
          <span class="stat-label">Highest Score</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number">100%</span>
          <span class="stat-label">Board Pass Rate</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number">150+</span>
          <span class="stat-label">Distinction Marks</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number">15+</span>
          <span class="stat-label">Years of Toppers</span>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- 2. Main Toppers Showcase Grid Section -->
<section class="section-py" style="position: relative;">
  <div class="container">
    
    <div class="section-header" style="text-align: center; margin-bottom: 36px;">
      <span class="section-eyebrow eyebrow-gold">
        <i class="fa-solid fa-award"></i> Merit Gallery
      </span>
      <h2 class="section-title">
        Meet Our Star <span class="hero-highlight">Rank Holders</span>
      </h2>
      <p class="section-subtitle" style="max-width: 650px; margin: 8px auto 0;">
        Witness the outstanding performances of our scholars across Science, Commerce, and Competitive exams.
      </p>
    </div>

    <!-- Interactive Filter Toolbar (Session Dropdown & Stream Filters) -->
    <div class="toppers-filter-container">
      <div class="toppers-filter-card">
        
        <!-- Top Filter Row: Academic Sessions Dropdown & Count -->
        <div class="toppers-filter-row-top">
          <div class="toppers-session-dropdown-wrap">
            <label for="topperSessionSelect" class="topper-filter-label">
              <i class="fa-solid fa-calendar-days" style="color: #0092df;"></i> Academic Session:
            </label>
            <div class="topper-select-container">
              <select id="topperSessionSelect" class="topper-session-select" aria-label="Select Academic Session">
                <option value="<?php echo htmlspecialchars($topper_active_name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($active_req_session === $topper_active_name) ? 'selected' : ''; ?>>
                  Session <?php echo htmlspecialchars($topper_active_name, ENT_QUOTES, 'UTF-8'); ?> (Active)
                </option>
                <?php foreach ($sessions_available as $s_name): ?>
                  <?php if ($s_name === $topper_active_name) continue; ?>
                  <option value="<?php echo htmlspecialchars($s_name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($active_req_session === $s_name) ? 'selected' : ''; ?>>
                    Session <?php echo htmlspecialchars($s_name, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
                <option value="all" <?php echo ($active_req_session === 'all') ? 'selected' : ''; ?>>
                  All Sessions
                </option>
              </select>
              <i class="fa-solid fa-chevron-down topper-select-caret"></i>
            </div>
          </div>

          <!-- Total Counter Badge -->
          <div class="topper-counter-pill">
            <span class="topper-live-dot"></span>
            <span id="toppersMatchCounter">Showing <strong><?php echo $initial_matching_toppers; ?></strong> <?php echo $initial_matching_toppers === 1 ? 'Scholar' : 'Scholars'; ?></span>
          </div>
        </div>

        <!-- Bottom Filter Row: Academic Streams -->
        <div class="toppers-filter-row-bottom">
          <div class="topper-streams-wrap">
            <span class="topper-stream-label">Stream:</span>
            <div class="topper-stream-btns-group" id="topperStreamFilter">
              <button type="button" class="topper-stream-btn active" data-stream="all">All</button>
              <button type="button" class="topper-stream-btn" data-stream="science"><i class="fa-solid fa-atom"></i> Science</button>
              <button type="button" class="topper-stream-btn" data-stream="commerce"><i class="fa-solid fa-chart-line"></i> Commerce</button>
              <button type="button" class="topper-stream-btn" data-stream="neet"><i class="fa-solid fa-dna"></i> Medical (NEET)</button>
            </div>
          </div>
        </div>

      </div>
    </div>

    <?php if (empty($toppers)): ?>
      <div class="toppers-empty-match" style="max-width: 600px; margin: 0 auto; padding: 60px 20px;">
        <div class="toppers-empty-icon" style="width: 64px; height: 64px; font-size: 1.8rem; margin: 0 auto 16px;">
          <i class="fa-solid fa-trophy"></i>
        </div>
        <h4>Toppers List Updating Soon</h4>
        <p>Our newest batch results and merit ranks are currently being consolidated. Check back shortly!</p>
      </div>
    <?php else: ?>
      
      <div class="toppers-card-grid">
        <?php foreach ($toppers as $idx => $t): ?>
          <?php
            $raw_photo = !empty($t['image']) ? trim($t['image']) : '';
            if (empty($raw_photo) || !preg_match('#^(https?://|/assets/|assets/)#i', $raw_photo)) {
                $photo_src = url('/assets/imgs/ksv-logo.jpg');
            } else {
                $photo_src = preg_match('#^https?://#i', $raw_photo) ? $raw_photo : url($raw_photo);
            }
            
            // Clean percentage representation
            $pct_raw = trim($t['percentage']);
            $pct_display = (strpos($pct_raw, '%') !== false) ? $pct_raw : ($pct_raw . '%');

            // Rank crown or star badge label
            $rank_badge = !empty($t['rank_title']) ? $t['rank_title'] : ('Merit Scholar #' . ($idx + 1));
            $batch_tag = !empty($t['year_or_batch']) ? $t['year_or_batch'] : 'Board Examination';

            // Distinct gradient accents for cards
            $card_accents = [
              ['badge_bg' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)', 'border' => '#f59e0b', 'pill' => '#fef3c7', 'pill_text' => '#b45309'],
              ['badge_bg' => 'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)', 'border' => '#0284c7', 'pill' => '#e0f2fe', 'pill_text' => '#0369a1'],
              ['badge_bg' => 'linear-gradient(135deg, #10b981 0%, #047857 100%)', 'border' => '#10b981', 'pill' => '#d1fae5', 'pill_text' => '#065f46'],
              ['badge_bg' => 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)', 'border' => '#8b5cf6', 'pill' => '#ede9fe', 'pill_text' => '#5b21b6'],
            ];
            $acc = $card_accents[$idx % count($card_accents)];
          ?>
          
          <?php
            $t_sess = trim($t['session_name'] ?? '');
            if (empty($t_sess)) $t_sess = '2025-2026';
            $is_initial_visible = ($active_req_session === 'all' || strcasecmp($t_sess, $active_req_session) === 0);
          ?>
          
          <div class="topper-profile-card"
               itemscope itemtype="https://schema.org/Person"
               <?php echo !$is_initial_visible ? 'style="display: none;"' : ''; ?>
               data-session="<?php echo htmlspecialchars($t_sess, ENT_QUOTES, 'UTF-8'); ?>"
               data-session-id="<?php echo intval($t['session_id'] ?: 2); ?>"
               data-stream="<?php echo htmlspecialchars(strtolower($t['subject']), ENT_QUOTES, 'UTF-8'); ?>"
               data-name="<?php echo htmlspecialchars(strtolower($t['name']), ENT_QUOTES, 'UTF-8'); ?>">
            
            <!-- Image & Badge Container -->
            <div class="topper-img-wrapper">
              <img src="<?php echo htmlspecialchars($photo_src, ENT_QUOTES, 'UTF-8'); ?>" 
                   alt="<?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?> - KSV Academy Topper" 
                   class="topper-student-img" 
                   itemprop="image"
                   loading="lazy">
              
              <!-- Floating Top Rank Badge -->
              <div class="topper-rank-ribbon" style="background: <?php echo $acc['badge_bg']; ?>;" itemprop="award">
                <i class="fa-solid fa-crown" style="font-size: 0.72rem;"></i>
                <span><?php echo htmlspecialchars($rank_badge, ENT_QUOTES, 'UTF-8'); ?></span>
              </div>

              <!-- Floating Percentage Badge -->
              <div class="topper-score-tag">
                <span class="score-number"><?php echo htmlspecialchars($pct_display, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="score-label">Score</span>
              </div>
            </div>

            <!-- Profile Info Content -->
            <div class="topper-content-box">
              
              <!-- Batch & Exam Tag + Academic Session Tag -->
              <div class="topper-meta-row" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;">
                <span class="topper-batch-tag">
                  <i class="fa-regular fa-calendar-check"></i>
                  <?php echo htmlspecialchars($batch_tag, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span class="topper-session-tag">
                  <i class="fa-solid fa-calendar-days"></i> Session <?php echo htmlspecialchars($t['session_name'] ?: '2025-2026', ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </div>

              <!-- Student Name -->
              <h3 class="topper-name-title" itemprop="name">
                <?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?>
              </h3>

              <!-- Subject / Stream Badge -->
              <div class="topper-subject-badge" style="background: <?php echo $acc['pill']; ?>; color: <?php echo $acc['pill_text']; ?>;" itemprop="knowsAbout">
                <i class="fa-solid fa-book-bookmark"></i>
                <span><?php echo htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8'); ?></span>
              </div>

              <!-- Student Description / Achievement Story -->
              <?php if (!empty($t['description'])): ?>
                <div class="topper-desc-box">
                  <i class="fa-solid fa-quote-left topper-quote-icon"></i>
                  <p class="topper-desc-text" itemprop="description">
                    <?php echo nl2br(htmlspecialchars($t['description'], ENT_QUOTES, 'UTF-8')); ?>
                  </p>
                </div>
              <?php endif; ?>

              <!-- Academy Seal Verification -->
              <div class="topper-footer-seal">
                <span class="seal-text">
                  <i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Verified KSV Academy Record
                </span>
              </div>

            </div>

          </div>

        <?php endforeach; ?>
      </div>

      <!-- Dynamic Empty State when filters yield 0 results -->
      <div id="noMatchingToppersBox" class="toppers-empty-match" style="<?php echo ($initial_matching_toppers === 0 && !empty($toppers)) ? 'display: block;' : 'display: none;'; ?>">
        <div class="toppers-empty-icon">
          <i class="fa-solid fa-filter"></i>
        </div>
        <h4>No Toppers Found</h4>
        <p>No student records match the selected academic session or stream.</p>
        <button type="button" class="btn btn-sm btn-primary" onclick="resetTopperFilters()" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 8px; font-weight: 700;">
          <i class="fa-solid fa-rotate-left"></i> Reset All Filters
        </button>
      </div>

    <?php endif; ?>

  </div>
</section>

<!-- 3. Why KSV Students Top Exams Section -->
<section class="section-py bg-soft toppers-methodology-section">
  <div class="container">
    <div class="section-header text-center">
      <span class="section-eyebrow"><i class="fa-solid fa-stairs"></i> The Formula for Success</span>
      <h2 class="section-title">Why KSV Academy Produces <span class="hero-highlight">Top Rankers</span></h2>
      <p class="section-subtitle">A proven combination of disciplined teaching, individual mentorship, and targeted test series.</p>
    </div>

    <div class="features-grid faculty-methodology-grid">
      <!-- Feature 1 -->
      <div class="methodology-card">
        <div class="methodology-topbar bar-blue"></div>
        <div class="methodology-icon icon-blue"><i class="fa-solid fa-clipboard-check"></i></div>
        <div class="methodology-tag"><i class="fa-solid fa-file-pen"></i> Rigorous Practice</div>
        <h3>Structured Chapter &amp; Prelim Test Series</h3>
        <p>Continuous mock examinations strictly modeled after latest CBSE &amp; Maharashtra State Board papers ensure students master time management and zero-error presentation.</p>
        <ul class="methodology-checklist">
          <li><i class="fa-solid fa-check"></i> Weekly Chapter Assessment Tests</li>
          <li><i class="fa-solid fa-check"></i> Full Syllabus Model Prelims</li>
        </ul>
      </div>

      <!-- Feature 2 -->
      <div class="methodology-card">
        <div class="methodology-topbar bar-emerald"></div>
        <div class="methodology-icon icon-emerald"><i class="fa-solid fa-bullseye"></i></div>
        <div class="methodology-tag"><i class="fa-solid fa-lightbulb"></i> Focus on Weak Areas</div>
        <h3>1-on-1 Personalized Doubt Resolution</h3>
        <p>Our senior subject faculties provide dedicated doubt clearance hours where concepts are re-explained until every scholar attains 100% clarity and confidence.</p>
        <ul class="methodology-checklist">
          <li><i class="fa-solid fa-check"></i> Individual Scholar Mentorship</li>
          <li><i class="fa-solid fa-check"></i> Step-by-Step Answer Evaluation</li>
        </ul>
      </div>

      <!-- Feature 3 -->
      <div class="methodology-card">
        <div class="methodology-topbar bar-amber"></div>
        <div class="methodology-icon icon-amber"><i class="fa-solid fa-award"></i></div>
        <div class="methodology-tag"><i class="fa-solid fa-trophy"></i> Championship Mindset</div>
        <h3>Motivation &amp; Exam Strategy Mentoring</h3>
        <p>Personalized guidance by Director Mr. Salam Khan and core faculty eliminates exam anxiety, cultivates positive study habits, and builds unwavering self-belief.</p>
        <ul class="methodology-checklist">
          <li><i class="fa-solid fa-check"></i> Regular Parent Performance Alignment</li>
          <li><i class="fa-solid fa-check"></i> Scientific Revision Blueprints</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- 4. Inspiring Call to Action Banner -->
<section class="section-py">
  <div class="container">
    <div class="demo-banner-card" style="background: linear-gradient(135deg, #02457a 0%, #0077b6 55%, #0092df 100%); border-radius: 28px; padding: 48px 40px; color: #ffffff; display: grid; grid-template-columns: 1.25fr 0.75fr; gap: 32px; align-items: center; box-shadow: 0 20px 48px -10px rgba(0, 146, 223, 0.35); position: relative; overflow: hidden;">
      <div style="position: absolute; right: -50px; bottom: -50px; width: 260px; height: 260px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); pointer-events: none;"></div>
      
      <div>
        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); padding: 5px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.04em;">
          <i class="fa-solid fa-trophy" style="color: #f59e0b;"></i> Aim for 95%+ Marks
        </div>
        <h2 style="font-size: 2.2rem; font-weight: 800; line-height: 1.2; margin-bottom: 12px; color: #ffffff;">
          Aspire to be Our <span style="color: #38bdf8;">Next Academic Topper?</span>
        </h2>
        <p style="font-size: 1.02rem; color: rgba(255,255,255,0.9); line-height: 1.6; margin: 0 0 22px;">
          Join KSV Academy Ulwe and study with top faculty mentors who will guide you at every single step toward board and competitive exam excellence.
        </p>
        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
          <button type="button" class="btn btn-warning btn-lg" data-modal="demoModal" style="background: #f59e0b; color: #08212e; border: none; font-weight: 800; box-shadow: 0 4px 16px rgba(245,158,11,0.4);">
            <i class="fa-regular fa-calendar-check"></i> Book 3-Days Free Demo Class
          </button>
          <a href="<?php echo htmlspecialchars(WHATSAPP_LINK, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-white btn-lg" target="_blank" rel="noopener noreferrer" style="border: 2px solid rgba(255,255,255,0.6); color: #ffffff;">
            <i class="fa-brands fa-whatsapp"></i> Chat with Counselor
          </a>
        </div>
      </div>

      <div style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 26px; backdrop-filter: blur(8px); text-align: center;">
        <div style="font-size: 2.2rem; font-weight: 800; color: #ffffff; line-height: 1;">100%</div>
        <div style="color: #fbbf24; font-size: 1.1rem; margin: 8px 0;">
          <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
        </div>
        <div style="font-size: 0.95rem; font-weight: 700; color: #ffffff;">Board Exam Success Guarantee</div>
        <p style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.8); margin: 6px 0 16px;">Small batch size &amp; daily tracking</p>
        <a href="<?php echo url('admissions'); ?>" class="btn btn-sm" style="background: #ffffff; color: var(--primary-700); font-weight: 700; width: 100%;">
          <i class="fa-solid fa-graduation-cap"></i> Check Admissions &amp; Scholarship
        </a>
      </div>
    </div>
  </div>
</section>

<!-- 5. Frequently Asked Questions (Structured FAQ) -->
<section class="section-py bg-soft toppers-faq-section">
  <div class="container">
    <div class="section-header text-center">
      <span class="section-eyebrow"><i class="fa-solid fa-circle-question"></i> Academic Excellence &amp; Results</span>
      <h2 class="section-title">Frequently Asked <span class="hero-highlight">Questions</span></h2>
      <p class="section-subtitle">Answers to common questions about KSV Academy toppers, exam preparation, and mentoring programs.</p>
    </div>

    <div class="faq-accordion" style="max-width: 860px; margin: 0 auto;">
      <div class="faq-item active">
        <button type="button" class="faq-header">
          <span class="faq-title">What is the highest score achieved by KSV Academy students?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            The highest score achieved by KSV Academy students is <strong>98.8%</strong> in the Maharashtra State Board HSC Science exams, alongside a consistent <strong>100% board examination pass rate</strong> across all academic batches.
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">Which boards and competitive entrance exams do KSV Academy toppers belong to?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            Our toppers represent Maharashtra State Board (HSC Science &amp; Commerce, SSC 10th), CBSE Board (Classes 8th to 12th), ICSE, and competitive national entrance examinations including NEET-UG Medical and IIT-JEE Main.
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">How does KSV Academy mentor students to produce top rankers in Ulwe?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            KSV Academy produces consistent top rankers through small batches capped at 25 students, concept-first teaching led by senior mentors Mr. Salam Khan and Mr. Rameshwar Mishra, weekly subjective Prelim Test Series, and dedicated daily 1-on-1 doubt clearance.
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">Are merit scholarships available for students aiming to become toppers?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            Yes, KSV Academy offers up to <strong>60% merit scholarships</strong> based on the KSV Diagnostic cum Scholarship Aptitude Test or previous academic board scores (85%+ earns direct fee concessions).
          </p>
        </div>
      </div>

      <div class="faq-item">
        <button type="button" class="faq-header">
          <span class="faq-title">How can I attend demo classes at KSV Academy?</span>
          <i class="fa-solid fa-chevron-down faq-icon"></i>
        </button>
        <div class="faq-body">
          <p>
            Students can attend a <strong>3-Days Free Demo Class</strong> with zero upfront fees or financial commitments by registering on our website (<a href="<?php echo url('admissions'); ?>">ksvacademy.org/admissions</a>) or calling/WhatsApping our Ulwe Sector 19 center directly at <strong><?php echo htmlspecialchars(PHONE_PRIMARY, ENT_QUOTES, 'UTF-8'); ?></strong>.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Custom Styles for Toppers Showcase Grid -->
<style>
/* Section borders using theme tokens */
.toppers-methodology-section {
  border-top: 1px solid var(--border-subtle, #d6e8f5);
  border-bottom: 1px solid var(--border-subtle, #d6e8f5);
}
.toppers-faq-section {
  border-top: 1px solid var(--border-subtle, #d6e8f5);
}

.eyebrow-gold {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
  border-color: rgba(245, 158, 11, 0.25);
}

.faculty-methodology-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  align-items: stretch;
}

@media (max-width: 991px) {
  .faculty-methodology-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 767px) {
  .faculty-methodology-grid {
    grid-template-columns: 1fr;
  }
}

.toppers-card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 30px;
  align-items: stretch;
}

.topper-profile-card {
  background: var(--bg-card, #ffffff);
  border: 1px solid var(--border-subtle, #d6e8f5);
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  flex-direction: column;
}

.topper-profile-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 36px -8px rgba(15, 23, 42, 0.12), 0 0 0 1px #f59e0b;
}

.topper-img-wrapper {
  position: relative;
  width: 100%;
  height: 290px;
  background: var(--bg-surface-soft, #eaf3f8);
  overflow: hidden;
}

.topper-student-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
  transition: transform 0.5s ease;
}

.topper-profile-card:hover .topper-student-img {
  transform: scale(1.04);
}

.topper-rank-ribbon {
  position: absolute;
  top: 14px;
  left: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 9999px;
  color: #ffffff;
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  z-index: 2;
}

.topper-score-tag {
  position: absolute;
  bottom: 14px;
  right: 14px;
  background: rgba(15, 23, 42, 0.85);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border: 1.5px solid rgba(255, 255, 255, 0.25);
  padding: 6px 14px;
  border-radius: 12px;
  color: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
  line-height: 1.1;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
  z-index: 2;
}

.topper-score-tag .score-number {
  font-size: 1.25rem;
  font-weight: 900;
  color: #fbbf24;
  font-family: 'Outfit', sans-serif;
  letter-spacing: -0.01em;
}

.topper-score-tag .score-label {
  font-size: 0.60rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #e2e8f0;
}

.topper-content-box {
  padding: 22px 22px 20px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.topper-meta-row {
  margin-bottom: 8px;
}

.topper-batch-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--text-muted, #4e6b7d);
  background: var(--bg-surface-soft, #eaf3f8);
  padding: 3px 9px;
  border-radius: 6px;
  border: 1px solid var(--border-subtle, #d6e8f5);
}

.topper-name-title {
  font-size: 1.28rem;
  font-weight: 800;
  color: var(--text-main, #002538);
  margin: 0 0 8px;
  font-family: 'Outfit', sans-serif;
}

.topper-subject-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.80rem;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 8px;
  margin-bottom: 14px;
  width: fit-content;
}

.topper-desc-box {
  position: relative;
  background: var(--bg-surface-soft, #eaf3f8);
  border: 1px solid var(--border-subtle, #d6e8f5);
  border-left: 3px solid #f59e0b;
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 16px;
  flex: 1;
}

.topper-quote-icon {
  font-size: 0.75rem;
  color: #f59e0b;
  margin-bottom: 4px;
  display: block;
  opacity: 0.8;
}

.topper-desc-text {
  font-size: 0.84rem;
  color: var(--text-muted, #4e6b7d);
  line-height: 1.55;
  margin: 0;
  font-style: italic;
}

.topper-footer-seal {
  padding-top: 12px;
  border-top: 1px solid var(--border-subtle, #d6e8f5);
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--text-muted, #4e6b7d);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/* ── Public Toppers Filter Toolbar Styles ── */
.toppers-filter-container {
  max-width: 1040px;
  margin: 0 auto 34px;
}
.toppers-filter-card {
  background: var(--bg-card, #ffffff);
  border: 1.5px solid var(--border-subtle, #d6e8f5);
  border-radius: 16px;
  padding: 16px 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}
.toppers-filter-row-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 14px;
  border-bottom: 1px solid var(--border-subtle, #d6e8f5);
  padding-bottom: 14px;
}
.toppers-session-dropdown-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.topper-filter-label {
  font-size: 0.80rem;
  font-weight: 800;
  color: var(--text-main, #002538);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  display: flex;
  align-items: center;
  gap: 6px;
}
.topper-select-container {
  position: relative;
  display: inline-flex;
  align-items: center;
  min-width: 250px;
}
.topper-session-select {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  width: 100%;
  padding: 8px 38px 8px 14px;
  border-radius: 10px;
  border: 1.5px solid var(--border-strong, #aed3ed);
  background: var(--bg-surface, #ffffff);
  color: var(--text-main, #002538);
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
  outline: none;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.topper-session-select:hover {
  border-color: #0092df;
}
.topper-session-select:focus {
  border-color: #0092df;
  box-shadow: 0 0 0 3px rgba(0, 146, 223, 0.18);
}
.topper-select-caret {
  position: absolute;
  right: 14px;
  pointer-events: none;
  color: #0092df;
  font-size: 0.72rem;
  transition: transform 0.2s ease;
}
.topper-counter-pill {
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.25);
  color: #047857;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.topper-live-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
  display: inline-block;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}
.toppers-filter-row-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.topper-streams-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.topper-stream-label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-muted, #4e6b7d);
}
.topper-stream-btns-group {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.topper-stream-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 8px;
  font-size: 0.76rem;
  font-weight: 700;
  border: 1px solid var(--border-subtle, #d6e8f5);
  background: var(--bg-surface-soft, #eaf3f8);
  color: var(--text-muted, #4e6b7d);
  cursor: pointer;
  transition: all 0.15s ease;
}
.topper-stream-btn:hover {
  background: var(--primary-100, #d0eaf7);
  color: var(--text-main, #002538);
}
.topper-stream-btn.active {
  background: #0284c7;
  color: #ffffff;
  border-color: #0284c7;
  box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);
}

/* Card Academic Session Pill */
.topper-session-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.70rem;
  font-weight: 700;
  color: #4338ca;
  background: #e0e7ff;
  border: 1px solid #c7d2fe;
  padding: 2px 8px;
  border-radius: 6px;
}

/* Empty State Box */
.toppers-empty-match {
  text-align: center;
  padding: 50px 20px;
  background: var(--bg-card, #ffffff);
  border-radius: 16px;
  border: 1.5px dashed var(--border-subtle, #d6e8f5);
  max-width: 520px;
  margin: 30px auto;
}
.toppers-empty-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #fef3c7;
  color: #d97706;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
  margin: 0 auto 14px;
}
.toppers-empty-match h4 {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text-main, #002538);
  margin-bottom: 6px;
  font-family: 'Outfit', sans-serif;
}
.toppers-empty-match p {
  color: var(--text-muted, #4e6b7d);
  font-size: 0.88rem;
  margin: 0 0 16px;
}

/* ==========================================================================
   Dark Theme Overrides - Royal Azure Palette
   ========================================================================== */
[data-theme="dark"] .eyebrow-gold {
  background: rgba(245, 158, 11, 0.18);
  color: #fbbf24;
  border-color: rgba(245, 158, 11, 0.35);
}

[data-theme="dark"] .toppers-methodology-section,
[data-theme="dark"] .toppers-faq-section {
  background-color: var(--bg-surface-soft, #0f3049) !important;
  border-color: var(--border-subtle, #0f3049) !important;
}

[data-theme="dark"] .toppers-methodology-section .section-title,
[data-theme="dark"] .toppers-faq-section .section-title {
  color: var(--text-main, #f0f8fd) !important;
}

[data-theme="dark"] .toppers-methodology-section .section-subtitle,
[data-theme="dark"] .toppers-faq-section .section-subtitle {
  color: var(--text-muted, #8eb7d3) !important;
}

[data-theme="dark"] .methodology-card {
  background: var(--bg-card, #082133) !important;
  border-color: var(--border-subtle, #0f3049) !important;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.4) !important;
}

[data-theme="dark"] .methodology-card h3 {
  color: var(--text-main, #f0f8fd) !important;
}

[data-theme="dark"] .methodology-card p {
  color: var(--text-muted, #8eb7d3) !important;
}

[data-theme="dark"] .methodology-checklist li {
  color: var(--text-main, #f0f8fd) !important;
}

[data-theme="dark"] .faq-accordion .faq-item {
  background-color: var(--bg-card, #082133) !important;
  border-color: var(--border-subtle, #0f3049) !important;
}

[data-theme="dark"] .faq-accordion .faq-item.active {
  border-color: rgba(56, 189, 248, 0.4) !important;
  box-shadow: 0 10px 28px -6px rgba(0, 0, 0, 0.4) !important;
}

[data-theme="dark"] .faq-header .faq-title {
  color: var(--text-main, #f0f8fd) !important;
}

[data-theme="dark"] .faq-item.active .faq-header .faq-title {
  color: #7dd3fc !important;
}

[data-theme="dark"] .faq-body p {
  color: var(--text-muted, #8eb7d3) !important;
}

[data-theme="dark"] .faq-body p strong {
  color: var(--text-main, #f0f8fd) !important;
}

[data-theme="dark"] .toppers-filter-card {
  background: var(--bg-card, #082133);
  border-color: var(--border-subtle, #0f3049);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

[data-theme="dark"] .toppers-filter-row-top {
  border-bottom-color: var(--border-subtle, #0f3049);
}

[data-theme="dark"] .topper-filter-label {
  color: var(--text-main, #f0f8fd);
}

[data-theme="dark"] .topper-session-select {
  background: #041826;
  border-color: var(--border-strong, #174263);
  color: var(--text-main, #f0f8fd);
}

[data-theme="dark"] .topper-session-select option {
  background: #082133;
  color: #f0f8fd;
}

[data-theme="dark"] .topper-session-select:hover,
[data-theme="dark"] .topper-session-select:focus {
  border-color: #0092df;
}

[data-theme="dark"] .topper-counter-pill {
  background: rgba(16, 185, 129, 0.15);
  border-color: rgba(16, 185, 129, 0.3);
  color: #34d399;
}

[data-theme="dark"] .topper-stream-label {
  color: var(--text-muted, #8eb7d3);
}

[data-theme="dark"] .topper-stream-btn {
  background: #041826;
  border-color: var(--border-subtle, #0f3049);
  color: var(--text-muted, #8eb7d3);
}

[data-theme="dark"] .topper-stream-btn:hover {
  background: var(--bg-surface-soft, #0f3049);
  color: var(--text-main, #f0f8fd);
}

[data-theme="dark"] .topper-stream-btn.active {
  background: #0092df;
  border-color: #0092df;
  color: #ffffff;
}

[data-theme="dark"] .topper-session-tag {
  background: rgba(99, 102, 241, 0.18);
  border-color: rgba(99, 102, 241, 0.35);
  color: #c7d2fe;
}

[data-theme="dark"] .toppers-empty-match {
  background: var(--bg-card, #082133);
  border-color: var(--border-strong, #174263);
}

[data-theme="dark"] .toppers-empty-match h4 {
  color: var(--text-main, #f0f8fd);
}

[data-theme="dark"] .toppers-empty-match p {
  color: var(--text-muted, #8eb7d3);
}

[data-theme="dark"] .toppers-empty-icon {
  background: rgba(245, 158, 11, 0.18);
  color: #fbbf24;
}

[data-theme="dark"] .topper-profile-card {
  background: var(--bg-card, #082133);
  border-color: var(--border-subtle, #0f3049);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

[data-theme="dark"] .topper-profile-card:hover {
  box-shadow: 0 16px 36px -8px rgba(0, 0, 0, 0.6), 0 0 0 1px #f59e0b;
}

[data-theme="dark"] .topper-img-wrapper {
  background: #041826;
}

[data-theme="dark"] .topper-name-title {
  color: var(--text-main, #f0f8fd);
}

[data-theme="dark"] .topper-batch-tag {
  background: #041826;
  border-color: var(--border-subtle, #0f3049);
  color: var(--text-muted, #8eb7d3);
}

[data-theme="dark"] .topper-subject-badge {
  background: rgba(0, 146, 223, 0.18) !important;
  color: #38bdf8 !important;
  border: 1px solid rgba(56, 189, 248, 0.28);
}

[data-theme="dark"] .topper-desc-box {
  background: #041826;
  border-color: var(--border-subtle, #0f3049);
  border-left-color: #f59e0b;
}

[data-theme="dark"] .topper-desc-text {
  color: #cbd5e1;
}

[data-theme="dark"] .topper-footer-seal {
  border-top-color: var(--border-subtle, #0f3049);
  color: var(--text-muted, #8eb7d3);
}

@media (max-width: 768px) {
  .toppers-card-grid {
    grid-template-columns: 1fr;
    gap: 22px;
  }
  .topper-img-wrapper {
    height: 260px;
  }
  .toppers-filter-row-top {
    flex-direction: column;
    align-items: flex-start;
  }
  .toppers-session-dropdown-wrap {
    width: 100%;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  .topper-select-container {
    width: 100%;
    min-width: 100%;
  }
  .toppers-filter-row-bottom {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>

<script>
let currentTopperSession = <?php echo json_encode((string)$active_req_session, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let currentTopperStream = 'all';

function applyTopperFilters() {
  const cards = document.querySelectorAll('.toppers-card-grid .topper-profile-card');
  const noMatchBox = document.getElementById('noMatchingToppersBox');
  const counterSpan = document.getElementById('toppersMatchCounter');

  let visibleCount = 0;

  cards.forEach(card => {
    const cardSession = (card.getAttribute('data-session') || '').toLowerCase().trim();
    const cardStream = (card.getAttribute('data-stream') || '').toLowerCase();

    // Check Session match
    let matchSession = (currentTopperSession === 'all' || cardSession === currentTopperSession.toLowerCase().trim());

    // Check Stream match
    let matchStream = true;
    if (currentTopperStream !== 'all') {
      if (currentTopperStream === 'science') {
        matchStream = cardStream.includes('sci') || cardStream.includes('phy') || cardStream.includes('math') || cardStream.includes('chem');
      } else if (currentTopperStream === 'commerce') {
        matchStream = cardStream.includes('com') || cardStream.includes('acc') || cardStream.includes('eco');
      } else if (currentTopperStream === 'neet') {
        matchStream = cardStream.includes('neet') || cardStream.includes('bio') || cardStream.includes('med');
      }
    }

    if (matchSession && matchStream) {
      card.style.display = 'flex';
      visibleCount++;
    } else {
      card.style.display = 'none';
    }
  });

  if (counterSpan) {
    counterSpan.innerHTML = `Showing <strong>${visibleCount}</strong> ${visibleCount === 1 ? 'Scholar' : 'Scholars'}`;
  }

  if (noMatchBox) {
    noMatchBox.style.display = (visibleCount === 0) ? 'block' : 'none';
  }

  // Sync URL parameter without page reload
  if (window.history && window.history.replaceState) {
    const url = new URL(window.location.href);
    if (currentTopperSession !== 'all' && currentTopperSession !== '') {
      url.searchParams.set('session', currentTopperSession);
    } else {
      url.searchParams.delete('session');
    }
    window.history.replaceState({}, '', url.toString());
  }
}

function resetTopperFilters() {
  const sessionSelect = document.getElementById('topperSessionSelect');
  const defaultActiveSession = <?php echo json_encode((string)$topper_active_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  if (sessionSelect) {
    sessionSelect.value = defaultActiveSession;
  }
  currentTopperSession = defaultActiveSession;
  currentTopperStream = 'all';

  document.querySelectorAll('#topperStreamFilter .topper-stream-btn').forEach(btn => {
    btn.classList.toggle('active', btn.getAttribute('data-stream') === 'all');
  });

  applyTopperFilters();
}

document.addEventListener('DOMContentLoaded', function() {
  // Session Filter Dropdown
  const sessionSelect = document.getElementById('topperSessionSelect');
  if (sessionSelect) {
    sessionSelect.addEventListener('change', function() {
      currentTopperSession = this.value;
      applyTopperFilters();
    });
  }

  // Stream Filter Buttons
  document.querySelectorAll('#topperStreamFilter .topper-stream-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('#topperStreamFilter .topper-stream-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      currentTopperStream = this.getAttribute('data-stream') || 'all';
      applyTopperFilters();
    });
  });

  // Apply initial filters if pre-selected
  applyTopperFilters();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
