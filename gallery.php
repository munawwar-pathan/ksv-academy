<?php
require_once __DIR__ . '/config.php';

$active_page = 'gallery';
$page_title = 'Campus Gallery & Infrastructure | KSV Academy Ulwe | Smart Classrooms & Student Life';
$page_description = 'Take a photo tour of KSV Academy in Ulwe Sector 19, Navi Mumbai. Explore our air-conditioned smart classrooms, interactive concept teaching boards, 1:1 doubt-clearing counters, study library, and celebratory milestones.';
$page_keywords = 'KSV Academy gallery, coaching classes in Ulwe photos, Ulwe sector 19 tuition gallery, smart classrooms Ulwe, campus photos KSV Academy, coaching infrastructure Navi Mumbai, Salam Khan physics classroom, reference study library Ulwe, IIT JEE NEET batch photos';
$canonical_url = rtrim(IS_LIVE ? LIVE_URL : SITE_URL, '/') . '/gallery';
$page_image = rtrim(IS_LIVE ? LIVE_URL : SITE_URL, '/') . '/assets/imgs/gallers/gallery-1.png';

$all_active_gallery = get_active_gallery_items();
$gallery_count = count($all_active_gallery);

// Extract distinct categories dynamically
$gallery_categories = ['All'];
foreach ($all_active_gallery as $g_item) {
    if (!empty($g_item['category']) && !in_array($g_item['category'], $gallery_categories)) {
        $gallery_categories[] = $g_item['category'];
    }
}

// Safe URL category pre-filter (?category=... or ?cat=...) with whitelist sanitization
$raw_req_cat = '';
if (isset($_GET['category'])) {
    $raw_req_cat = trim((string)$_GET['category']);
} elseif (isset($_GET['cat'])) {
    $raw_req_cat = trim((string)$_GET['cat']);
}
$active_filter_cat = 'All';
if (!empty($raw_req_cat)) {
    foreach ($gallery_categories as $c) {
        if (strcasecmp($c, $raw_req_cat) === 0) {
            $active_filter_cat = $c;
            break;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Gallery Hero Header Banner (Attractive Design Matching Home & About) -->
<section class="about-hero-banner">
  <div class="container text-center">
    <div class="about-banner-content">
      <!-- Dual Eyebrows -->
      <div class="hero-eyebrows-wrap" style="justify-content: center; margin-bottom: 16px;">
        <div class="hero-eyebrow">
          <span class="hero-live-dot"></span>
          <span>Campus Life • Sector 19, Ulwe</span>
        </div>
        <div class="hero-trust-badge">
          <i class="fa-solid fa-camera-retro"></i>
          <span>Modern Infrastructure &amp; Smart Classrooms</span>
        </div>
      </div>

      <!-- Main Heading -->
      <h1 class="about-banner-title">
        Our Campus <span class="hero-highlight">Gallery &amp; Spaces</span>
      </h1>

      <!-- Slogan / Motto Tagline -->
      <div class="hero-motto-row" style="justify-content: center; margin-bottom: 16px;">
        <span class="motto-lead"><i class="fa-solid fa-sparkles"></i> Where Ambition Meets World-Class Atmosphere</span>
        <span class="motto-divider">•</span>
        <span class="motto-sub">Air-Conditioned Classrooms &amp; Reference Library</span>
      </div>

      <!-- Lead Description -->
      <p class="about-banner-desc">
        Experience the vibrant learning environment of our Ulwe campus — where passionate educators, interactive classrooms, and disciplined study habits converge.
      </p>

      <!-- Quick Stats Counter Strip -->
      <div class="hero-stats-row" style="max-width: 760px; margin: 0 auto;">
        <div class="stat-item">
          <span class="stat-number" data-target="<?php echo $gallery_count; ?>" data-suffix="+"><?php echo $gallery_count; ?>+</span>
          <span class="stat-label">Campus Spaces</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="100" data-suffix="%">100%</span>
          <span class="stat-label">AC Classrooms</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="1" data-prefix="1:" data-suffix="">1:1</span>
          <span class="stat-label">Doubt Counters</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="49" data-prefix="4." data-suffix="/5">4.9/5</span>
          <span class="stat-label">Campus Rating</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. Main Gallery Grid -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-images"></i> Inside KSV Academy</span>
      <h2 class="section-title">Explore Our Modern <span class="hero-highlight">Learning Spaces</span></h2>
      <p class="section-subtitle">A glimpse into our classrooms, faculty interactions, student workshops, and milestone celebrations in Ulwe Sector 19.</p>
    </div>

    <!-- Category Filter Tabs -->
    <?php if (count($gallery_categories) > 1): ?>
    <div class="gallery-client-filter-bar" style="display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin-bottom: 30px;">
      <?php foreach ($gallery_categories as $cat_name): 
        $cat_icon = ($cat_name === 'All') ? 'fa-layer-group' : get_gallery_category_icon($cat_name);
        $is_active_tab = ($cat_name === $active_filter_cat);
      ?>
      <button type="button" 
              class="gallery-filter-pill-btn <?php echo $is_active_tab ? 'active' : ''; ?>" 
              data-cat="<?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?>"
              onclick="filterPublicGallery(this)">
        <i class="fa-solid <?php echo htmlspecialchars($cat_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
        <span><?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?></span>
      </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($all_active_gallery)): ?>
      <div class="gallery-empty-state" style="text-align: center; padding: 60px 20px; background: #ffffff; border-radius: 20px; border: 1px dashed #cbd5e1; margin: 20px auto; max-width: 600px;">
        <div style="font-size: 3rem; color: #94a3b8; margin-bottom: 16px;"><i class="fa-solid fa-images"></i></div>
        <h3 style="font-size: 1.35rem; font-weight: 700; color: #1e293b; margin-bottom: 8px;">No Campus Photos Found</h3>
        <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 20px;">Photos are currently being updated. Please check back soon or visit our Ulwe campus in person!</p>
        <a href="<?php echo htmlspecialchars(url('contact'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-phone"></i> Contact Admissions
        </a>
      </div>
    <?php else: ?>
      <div class="gallery-grid" id="publicGalleryGrid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
        <?php foreach ($all_active_gallery as $img): 
          $cat = !empty($img['category']) ? $img['category'] : 'Campus Life';
          $cat_icon = get_gallery_category_icon($cat);
          $caption_text = !empty($img['caption']) ? $img['caption'] : ($img['title'] ?? 'Campus Life');

          // Strict image URL validation: sanitize against malicious schemes (javascript:, data:, etc.)
          $safe_img_url = trim((string)($img['image'] ?? ''));
          if (preg_match('#^\s*(javascript|vbscript|data):#i', $safe_img_url)) {
              $safe_img_url = url('assets/imgs/gallers/gallery-1.png');
          }
          if (empty($safe_img_url)) {
              $safe_img_url = url('assets/imgs/gallers/gallery-1.png');
          }

          // Initial card visibility according to active category filter
          $is_visible = ($active_filter_cat === 'All' || strcasecmp($cat, $active_filter_cat) === 0);
        ?>
        <div class="gallery-card" 
             style="<?php echo $is_visible ? 'display: flex;' : 'display: none;'; ?>"
             data-src="<?php echo htmlspecialchars($safe_img_url, ENT_QUOTES, 'UTF-8'); ?>" 
             data-caption="<?php echo htmlspecialchars($caption_text, ENT_QUOTES, 'UTF-8'); ?>" 
             data-category="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="gallery-thumb-wrapper">
            <img src="<?php echo htmlspecialchars($safe_img_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($caption_text, ENT_QUOTES, 'UTF-8'); ?>" class="gallery-thumb" loading="lazy">
          </div>
          
          <div class="gallery-top-badges">
            <span class="gallery-category-pill">
              <i class="fa-solid <?php echo htmlspecialchars($cat_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
              <span><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></span>
            </span>
            <span class="gallery-zoom-icon" title="View Fullscreen">
              <i class="fa-solid fa-expand"></i>
            </span>
          </div>

          <div class="gallery-overlay">
            <div class="gallery-overlay-inner">
              <span style="font-size: 0.72rem; color: #38bdf8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">KSV Academy • Ulwe Sector 19</span>
              <p class="gallery-overlay-caption"><?php echo htmlspecialchars($caption_text, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- No items found for selected filter state -->
      <div id="galleryNoResults" style="display: none; text-align: center; padding: 48px 20px; background: #ffffff; border-radius: 16px; border: 1px dashed #cbd5e1; margin-top: 24px;">
        <i class="fa-solid fa-filter-circle-xmark" style="font-size: 2.5rem; color: #94a3b8; margin-bottom: 12px; display: block;"></i>
        <h4 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">No photos found in this category</h4>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 16px;">Try selecting another category or view all campus photos.</p>
        <button type="button" class="btn btn-primary btn-sm" onclick="filterPublicGallery(document.querySelector('.gallery-filter-pill-btn[data-cat=\'All\']'))">
          <i class="fa-solid fa-layer-group"></i> Show All Photos
        </button>
      </div>
    <?php endif; ?>
  </div>
</section>

<style>
.gallery-filter-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 18px;
  border-radius: 999px;
  font-size: 0.84rem;
  font-weight: 700;
  border: 1.5px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}
.gallery-filter-pill-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
  border-color: #94a3b8;
  transform: translateY(-1px);
}
.gallery-filter-pill-btn.active {
  background: linear-gradient(135deg, #02457a 0%, #0092df 100%);
  color: #ffffff;
  border-color: #0092df;
  box-shadow: 0 4px 14px rgba(0, 146, 223, 0.3);
}
[data-theme="dark"] .gallery-filter-pill-btn {
  background: #1e293b;
  border-color: #334155;
  color: #94a3b8;
}
[data-theme="dark"] .gallery-filter-pill-btn:hover {
  background: #334155;
  color: #f8fafc;
}
[data-theme="dark"] .gallery-filter-pill-btn.active {
  background: linear-gradient(135deg, #0092df 0%, #0066aa 100%);
  color: #ffffff;
  border-color: #0092df;
}
</style>

<script>
function filterPublicGallery(btn) {
  if (!btn) return;
  var targetCat = (btn.getAttribute('data-cat') || 'All').trim();
  var btns = document.querySelectorAll('.gallery-filter-pill-btn');
  btns.forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');

  var cards = document.querySelectorAll('#publicGalleryGrid .gallery-card');
  var visibleCount = 0;
  cards.forEach(function(card) {
    var cardCat = (card.getAttribute('data-category') || '').trim();
    if (targetCat.toLowerCase() === 'all' || cardCat.toLowerCase() === targetCat.toLowerCase()) {
      card.style.display = 'flex';
      card.style.opacity = '1';
      visibleCount++;
    } else {
      card.style.display = 'none';
    }
  });

  var noResultEl = document.getElementById('galleryNoResults');
  if (noResultEl) {
    noResultEl.style.display = (visibleCount === 0) ? 'block' : 'none';
  }

  // Update URL history without reloading page
  if (window.history && window.history.replaceState) {
    var newUrl = window.location.pathname;
    if (targetCat.toLowerCase() !== 'all') {
      newUrl += '?category=' + encodeURIComponent(targetCat);
    }
    window.history.replaceState({ cat: targetCat }, '', newUrl);
  }
}

// On page load, if active category was set, ensure view is filtered
document.addEventListener('DOMContentLoaded', function() {
  var activeBtn = document.querySelector('.gallery-filter-pill-btn.active');
  if (activeBtn && (activeBtn.getAttribute('data-cat') || 'All').toLowerCase() !== 'all') {
    filterPublicGallery(activeBtn);
  }
});
</script>

<!-- 3. Classroom Infrastructure Features -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-building-columns"></i> Infrastructure</span>
      <h2 class="section-title">Designed for Maximum <span class="hero-highlight">Focus &amp; Learning</span></h2>
      <p class="section-subtitle">A conducive academic atmosphere equipped with everything an aspiring student needs to excel.</p>
    </div>

    <div class="features-grid">
      <!-- 1. Classrooms -->
      <div class="methodology-card">
        <div class="methodology-topbar bar-blue"></div>
        <div class="methodology-icon icon-blue"><i class="fa-solid fa-chair"></i></div>
        <div class="methodology-tag"><i class="fa-solid fa-snowflake"></i> Fully Air-Conditioned</div>
        <h3>Ergonomic Classrooms</h3>
        <p>Spacious, well-ventilated, and air-conditioned classrooms ensuring maximum concentration during long problem-solving sessions.</p>
        <ul class="methodology-checklist">
          <li><i class="fa-solid fa-check"></i> Ergonomic seating &amp; daylight balanced lighting</li>
          <li><i class="fa-solid fa-check"></i> Disciplined small batches for focused learning</li>
        </ul>
      </div>

      <!-- 2. Teaching Boards -->
      <div class="methodology-card">
        <div class="methodology-topbar bar-emerald"></div>
        <div class="methodology-icon icon-emerald"><i class="fa-solid fa-chalkboard"></i></div>
        <div class="methodology-tag"><i class="fa-solid fa-display"></i> Visual Teaching Aids</div>
        <h3>High-Clarity Teaching Boards</h3>
        <p>State-of-the-art visual instruction aids and demonstration tools to clarify physics circuits, chemistry reaction trees, and mathematical geometry.</p>
        <ul class="methodology-checklist">
          <li><i class="fa-solid fa-check"></i> High-contrast step-by-step concept boards</li>
          <li><i class="fa-solid fa-check"></i> Hands-on scientific models &amp; proofs</li>
        </ul>
      </div>

      <!-- 3. Library -->
      <div class="methodology-card">
        <div class="methodology-topbar bar-amber"></div>
        <div class="methodology-icon icon-amber"><i class="fa-solid fa-book-bookmark"></i></div>
        <div class="methodology-tag"><i class="fa-solid fa-book-open-reader"></i> Self-Study Space</div>
        <h3>Comprehensive Study Library</h3>
        <p>Extensive reference library housing NCERT solutions, previous 10-year question banks, HC Verma physics, and competitive entrance modules.</p>
        <ul class="methodology-checklist">
          <li><i class="fa-solid fa-check"></i> Free access to comprehensive book bank</li>
          <li><i class="fa-solid fa-check"></i> Quiet self-study and revision counters</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- 4. Visit Us CTA (Attractive Banner Card Matching Home & About) -->
<section class="section-py">
  <div class="container">
    <div class="demo-banner-card" style="background: linear-gradient(135deg, #02457a 0%, #0077b6 55%, #0092df 100%); border-radius: 28px; padding: 48px 40px; color: #ffffff; display: grid; grid-template-columns: 1.25fr 0.75fr; gap: 32px; align-items: center; box-shadow: 0 20px 48px -10px rgba(0, 146, 223, 0.35); position: relative; overflow: hidden;">
      <div style="position: absolute; right: -50px; bottom: -50px; width: 260px; height: 260px; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%); pointer-events: none;"></div>
      
      <div>
        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); padding: 5px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.04em;">
          <i class="fa-solid fa-sparkles"></i> Experience It Live
        </div>
        <h2 style="font-size: 2.2rem; font-weight: 800; line-height: 1.2; margin-bottom: 12px; color: #ffffff;">
          Visit Our Ulwe Campus <span style="color: #38bdf8;">in Person</span>
        </h2>
        <p style="font-size: 1.02rem; color: rgba(255,255,255,0.9); line-height: 1.6; margin: 0 0 22px;">
          Walk in with your parents to see the classrooms, meet Director Mr. Salam Khan and our faculty team, and tour our facilities at MGM Residency, Sector 19, Ulwe.
        </p>
        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
          <a href="<?php echo htmlspecialchars(url('contact'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-warning btn-lg" style="background: #f59e0b; color: #08212e; border: none; font-weight: 800; box-shadow: 0 4px 16px rgba(245,158,11,0.4);">
            <i class="fa-solid fa-map-location-dot"></i> Get Directions &amp; Contact
          </a>
          <button type="button" class="btn btn-outline-white btn-lg" data-modal="demoModal" style="border: 2px solid rgba(255,255,255,0.6); color: #ffffff;">
            <i class="fa-regular fa-calendar-check"></i> Book 3-Days Free Demo
          </button>
        </div>
      </div>

      <div style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 26px; backdrop-filter: blur(8px); text-align: center;">
        <div style="font-size: 2.2rem; font-weight: 800; color: #ffffff; line-height: 1;">4.9 / 5.0</div>
        <div style="color: #fbbf24; font-size: 1.1rem; margin: 8px 0;"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
        <div style="font-size: 0.9rem; font-weight: 700; color: #ffffff;">Top-Rated Campus in Ulwe</div>
        <p style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.8); margin: 6px 0 16px;">150+ Verified Google &amp; Parent Reviews</p>
        <a href="tel:<?php echo htmlspecialchars(PHONE_RAW_1, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm" style="background: #ffffff; color: var(--primary-700); font-weight: 700; width: 100%;">
          <i class="fa-solid fa-phone"></i> Call: <?php echo htmlspecialchars(PHONE_PRIMARY, ENT_QUOTES, 'UTF-8'); ?>
        </a>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
