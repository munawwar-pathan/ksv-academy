<?php
require_once __DIR__ . '/config.php';

$active_page = 'gallery';
$page_title = 'Campus Gallery & Student Life | KSV Academy Ulwe';
$page_description = 'Explore life at KSV Academy Ulwe. Take a photo tour of our modern classrooms, interactive physics & chemistry sessions, test series, and celebratory milestones.';
$page_keywords = 'KSV Academy gallery, coaching photos Ulwe, classroom photos Ulwe, student life KSV Academy, Salam Khan physics classroom';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Gallery Hero Header -->
<section class="section-py-sm page-header-banner">
  <div class="container text-center">
    <span class="section-eyebrow" style="background: rgba(255, 255, 255, 0.1); color: #bae6fd; border: 1px solid rgba(255, 255, 255, 0.18); margin-bottom: 14px;">
      <i class="fa-solid fa-camera-retro"></i> Campus Life &amp; Facilities
    </span>
    <h1 style="color: #ffffff; font-size: 2.8rem; font-weight: 800; margin-bottom: 14px; letter-spacing: -0.02em;">
      Our Campus <span class="hero-highlight">Gallery</span>
    </h1>
    <p style="color: #cbd5e1; max-width: 680px; margin: 0 auto; font-size: 1.08rem; line-height: 1.6;">
      Experience the vibrant learning environment of our Ulwe campus — where passionate educators, interactive classrooms, and disciplined study habits converge.
    </p>
  </div>
</section>

<!-- Main Gallery Grid -->
<section class="section-py">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-images"></i> Inside KSV Academy</span>
      <h2 class="section-title">Explore Our Modern <span class="hero-highlight">Learning Spaces</span></h2>
      <p class="section-subtitle">A glimpse into our classrooms, faculty interactions, student workshops, and milestone celebrations in Ulwe Sector 19.</p>
    </div>

    <div class="gallery-grid" style="grid-template-columns: repeat(3, 1fr); gap: 24px;">
      <?php foreach ($gallery_images as $img): ?>
      <div class="gallery-card" style="height: 280px;" data-src="<?php echo url('assets/imgs/gallers/' . $img['file']); ?>" data-caption="<?php echo htmlspecialchars($img['caption']); ?>">
        <img src="<?php echo url('assets/imgs/gallers/' . $img['file']); ?>" alt="<?php echo htmlspecialchars($img['caption']); ?>" class="gallery-thumb" loading="lazy">
        <div class="gallery-overlay">
          <span class="gallery-zoom-icon"><i class="fa-solid fa-expand"></i></span>
          <span class="gallery-category-pill"><?php echo htmlspecialchars($img['category']); ?></span>
          <p class="gallery-overlay-caption"><?php echo htmlspecialchars($img['caption']); ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Classroom Infrastructure Features -->
<section class="section-py bg-soft">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><i class="fa-solid fa-building-columns"></i> Infrastructure</span>
      <h2 class="section-title">Designed for Maximum <span class="hero-highlight">Focus &amp; Learning</span></h2>
      <p class="section-subtitle">A conducive academic atmosphere equipped with everything an aspiring student needs to excel.</p>
    </div>

    <div class="features-grid">
      <div class="methodology-card">
        <div class="methodology-icon"><i class="fa-solid fa-chair"></i></div>
        <h3>Ergonomic Classrooms</h3>
        <p>Spacious, well-ventilated, and air-conditioned classrooms ensuring maximum concentration during long problem-solving sessions.</p>
      </div>

      <div class="methodology-card">
        <div class="methodology-icon"><i class="fa-solid fa-chalkboard"></i></div>
        <h3>High-Clarity Teaching Boards</h3>
        <p>State-of-the-art visual instruction aids and demonstration tools to clarify physics circuits, chemistry reaction trees, and mathematical geometry.</p>
      </div>

      <div class="methodology-card">
        <div class="methodology-icon"><i class="fa-solid fa-book-bookmark"></i></div>
        <h3>Comprehensive Study Library</h3>
        <p>Extensive reference library housing NCERT solutions, previous 10-year question banks, HC Verma physics, and competitive entrance modules.</p>
      </div>
    </div>
  </div>
</section>

<!-- Visit Us CTA -->
<section class="section-py" style="text-align: center;">
  <div class="container">
    <div class="section-header" style="max-width: 720px; margin: 0 auto 30px auto;">
      <span class="section-eyebrow"><i class="fa-solid fa-location-dot"></i> Experience It Live</span>
      <h2 class="section-title">Visit Our Ulwe Campus <span class="hero-highlight">in Person</span></h2>
      <p class="section-subtitle">Walk in with your parents to see the classrooms, meet the faculties, and tour our facilities at MGM Residency, Sector 19, Ulwe.</p>
    </div>
    <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
      <a href="<?php echo url('contact'); ?>" class="btn btn-primary btn-lg">
        <i class="fa-solid fa-map-location-dot"></i> Get Directions &amp; Contact
      </a>
      <button type="button" class="btn btn-outline-primary btn-lg" data-modal="demoModal">
        <i class="fa-regular fa-calendar-check"></i> Book 3-Days Free Demo
      </button>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
