<?php
require_once __DIR__ . '/../config.php';
?>
<!-- 3-Days Free Demo Class Registration Modal -->
<div class="modal-backdrop" id="demoModal" role="dialog" aria-modal="true" aria-labelledby="demoModalTitle">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="modal-badge"><i class="fa-solid fa-sparkles"></i> 100% Free & No Obligation</span>
          <h3 class="modal-title" id="demoModalTitle">Book Your 3-Days Free Demo Class</h3>
        </div>
        <button type="button" class="modal-close-btn" data-close="modal" aria-label="Close dialog">&times;</button>
      </div>
      <div class="modal-body">
        <p class="modal-subtext">
          Experience our interactive conceptual teaching methodology first-hand. Meet Mr. Salam Khan and our specialized faculty in Ulwe Sector 19.
        </p>

        <form id="demoClassForm" class="interactive-form" method="POST" action="/submit-form" onsubmit="handleDemoSubmit(event)">
          <input type="hidden" name="form_type" value="demo_class">
          <div class="form-group">
            <label for="demoStudentName"><i class="fa-solid fa-user"></i> Student Name <span class="required">*</span></label>
            <input type="text" id="demoStudentName" name="student_name" class="form-control" placeholder="Enter full name" required>
          </div>

          <div class="form-row">
            <div class="form-group col-half">
              <label for="demoPhone"><i class="fa-solid fa-phone"></i> WhatsApp / Phone <span class="required">*</span></label>
              <input type="tel" id="demoPhone" name="phone" class="form-control" placeholder="10-digit mobile number" pattern="[0-9]{10}" required>
            </div>
            <div class="form-group col-half">
              <label for="demoClass"><i class="fa-solid fa-book"></i> Course / Standard <span class="required">*</span></label>
              <select id="demoClass" name="course_standard" class="form-control" required>
                <option value="">-- Select Course --</option>
                <optgroup label="School Section">
                  <option value="Class 5th - 8th Foundation">Class 5th - 8th Foundation</option>
                  <option value="Class 9th (CBSE/ICSE/State)">Class 9th (CBSE/ICSE/State)</option>
                  <option value="Class 10th Board Target">Class 10th Board Target</option>
                </optgroup>
                <optgroup label="Competitive Entrances">
                  <option value="IIT-JEE (Main + Advanced)">IIT-JEE (Main + Advanced)</option>
                  <option value="NEET-UG Medical">NEET-UG Medical</option>
                  <option value="MH-CET (Engg / Pharmacy)">MH-CET (Engg / Pharmacy)</option>
                </optgroup>
                <optgroup label="Junior College (11th & 12th)">
                  <option value="11th & 12th Science (PCM / PCB)">11th & 12th Science (PCM / PCB)</option>
                  <option value="11th & 12th Commerce Section">11th & 12th Commerce Section</option>
                </optgroup>
                <optgroup label="Degree Section">
                  <option value="B.Com / BAF Degree">B.Com / BAF Degree</option>
                  <option value="BMS / BBA Management">BMS / BBA Management</option>
                  <option value="BCA Computer Applications">BCA Computer Applications</option>
                  <option value="M.Com Postgraduate">M.Com Postgraduate</option>
                </optgroup>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="demoSchool"><i class="fa-solid fa-school"></i> Current School / College Name</label>
            <input type="text" id="demoSchool" name="school_name" class="form-control" placeholder="e.g. DAV Public School, Ulwe / College">
          </div>

          <div class="form-group">
            <label for="demoBatchPref"><i class="fa-solid fa-clock"></i> Preferred Timing</label>
            <select id="demoBatchPref" name="batch_timing" class="form-control">
              <option value="Morning Batch (8:00 AM - 11:30 AM)">Morning Batch (8:00 AM - 11:30 AM)</option>
              <option value="Evening Batch (4:00 PM - 8:30 PM)">Evening Batch (4:00 PM - 8:30 PM)</option>
              <option value="Flexible / Weekend">Flexible / Weekend</option>
            </select>
          </div>

          <div class="form-submit-block">
            <button type="submit" id="demoSubmitBtn" class="btn btn-primary btn-block btn-lg">
              <i class="fa-solid fa-check-circle"></i> Confirm Free 3-Days Demo Pass
            </button>
            <p class="form-note">
              <i class="fa-solid fa-shield-halved"></i> We respect privacy. No spam. You will receive session timing directly on WhatsApp/SMS.
            </p>
          </div>
        </form>

        <div id="demoSuccessState" class="form-success-message" style="display: none;">
          <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
          <h4>Demo Seat Reserved Successfully!</h4>
          <p id="demoSuccessText">Thank you! Our academic counselor will contact you shortly with the schedule for your 3-day demo class at our Ulwe campus.</p>
          <div class="success-actions">
            <a href="<?php echo WHATSAPP_LINK; ?>" class="btn btn-whatsapp btn-block" target="_blank" rel="noopener noreferrer">
              <i class="fa-brands fa-whatsapp"></i> Connect Directly on WhatsApp
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Image Lightbox Modal for Gallery with Zoom-In & Left-Right Navigation -->
<div class="modal-backdrop lightbox-modal" id="imageLightboxModal" role="dialog" aria-modal="true" aria-label="Gallery Image Viewer">
  <div class="lightbox-container">
    <div class="lightbox-toolbar">
      <span class="lightbox-counter" id="lightboxCounter">1 / 4</span>
      <button type="button" class="lightbox-close-btn" data-close="modal" aria-label="Close Lightbox" title="Close (Esc)">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="lightbox-stage">
      <button type="button" class="lightbox-nav-btn lightbox-prev" id="lightboxPrevBtn" aria-label="Previous Image" title="Previous (Left Arrow)">
        <i class="fa-solid fa-chevron-left"></i>
      </button>
      <div class="lightbox-image-wrap">
        <img src="" alt="Gallery Image" id="lightboxTargetImage" class="lightbox-img">
      </div>
      <button type="button" class="lightbox-nav-btn lightbox-next" id="lightboxNextBtn" aria-label="Next Image" title="Next (Right Arrow)">
        <i class="fa-solid fa-chevron-right"></i>
      </button>
    </div>
    <div class="lightbox-caption" id="lightboxCaption"></div>
  </div>
</div>

<?php
// Load dynamic promo popup slides cleanly from database without session conflict
$active_popup_slides = [];
try {
    $ps_pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=ksvacademy;charset=utf8mb4", "root", "myPassword$10", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $ps_stmt = $ps_pdo->query("SELECT * FROM popup_sliders WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
    if ($ps_stmt) {
        $active_popup_slides = $ps_stmt->fetchAll();
    }
} catch (Throwable $e) {
    $active_popup_slides = [];
}

if (empty($active_popup_slides)) {
    $active_popup_slides = [
        [
            'title' => 'Admissions & Scholarship 2026-27',
            'image' => '/assets/imgs/popup/popup-1.png',
            'caption' => 'Up to 60% Scholarship • Admissions 2026–27',
            'link_url' => '/admissions',
        ],
        [
            'title' => 'Strong Foundation, Sure Success!',
            'image' => '/assets/imgs/popup/popup-2.jpeg',
            'caption' => 'Strong Foundation, Sure Success!',
            'link_url' => '/admissions',
        ],
        [
            'title' => 'Excellence in Education - Join KSV Academy',
            'image' => '/assets/imgs/popup/popup-3.png',
            'caption' => 'Admissions 2026–27 • Join KSV Academy',
            'link_url' => '/admissions',
        ],
    ];
}
?>

<!-- 3. Automatic Reload Scholarship & Admission Popup Modal -->
<div class="modal-backdrop promo-modal-backdrop" id="reloadPromoModal" role="dialog" aria-modal="true" aria-labelledby="promoModalTitle">
  <div class="promo-modal-dialog">
    <!-- Close Button -->
    <button type="button" class="promo-close-btn" data-close="modal" aria-label="Close popup" title="Close">
      <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="promo-modal-body">
      <!-- Left Column: Elegant Admissions & Scholarship Showcase -->
      <div class="promo-content-col">
        <!-- Dual Eyebrow Badges -->
        <div class="promo-eyebrows-wrap">
          <div class="hero-eyebrow promo-eyebrow">
            <span class="hero-live-dot"></span>
            <span>Admissions Open <?php echo CURRENT_SESSION; ?></span>
          </div>
          <div class="hero-trust-badge promo-trust-badge">
            <i class="fa-solid fa-sparkles"></i>
            <span>Ab Sapnon Ki Coaching Ulwe Mein!</span>
          </div>
        </div>

        <!-- Heading -->
        <h2 class="promo-title" id="promoModalTitle">
          Scholarship Up to <span class="promo-highlight-gold">60%</span>
        </h2>
        
        <p class="promo-desc">
          Empowering ambitious students in Ulwe with concept-focused learning, expert mentorship, and merit scholarship benefits.
        </p>

        <!-- Clean Academic Points -->
        <div class="promo-points-list">
          <div class="promo-point-row">
            <i class="fa-solid fa-circle-check"></i>
            <span><strong>Classes 5th to 12th</strong> (CBSE / ICSE / Maharashtra State)</span>
          </div>
          <div class="promo-point-row">
            <i class="fa-solid fa-circle-check"></i>
            <span><strong>IIT-JEE, NEET-UG &amp; MH-CET</strong> Special Batches</span>
          </div>
          <div class="promo-point-row">
            <i class="fa-solid fa-circle-check"></i>
            <span><strong>Mentored by Mr. Salam Khan</strong> &amp; Senior Faculty</span>
          </div>
          <div class="promo-point-row">
            <i class="fa-solid fa-circle-check"></i>
            <span><strong>Small Disciplined Batches</strong> with Individual Attention</span>
          </div>
        </div>

        <!-- Trust Stats Strip -->
        <div class="promo-trust-strip">
          <div class="promo-trust-stat">
            <span class="pts-val">10+ Yrs</span>
            <span class="pts-lbl">Ulwe Legacy</span>
          </div>
          <div class="pts-sep"></div>
          <div class="promo-trust-stat">
            <span class="pts-val">1500+</span>
            <span class="pts-lbl">Students Guided</span>
          </div>
          <div class="pts-sep"></div>
          <div class="promo-trust-stat">
            <span class="pts-val">98%</span>
            <span class="pts-lbl">Success Rate</span>
          </div>
        </div>

        <!-- Call to Action Buttons -->
        <div class="promo-btn-container">
          <a href="<?php echo url('admissions'); ?>" class="btn btn-primary btn-md promo-btn-apply">
            <i class="fa-solid fa-user-graduate"></i>
            <span>Apply for Admission</span>
          </a>
          <button type="button" class="btn btn-outline-primary btn-md btn-demo-trigger promo-btn-demo" data-modal="demoModal">
            <i class="fa-solid fa-calendar-check"></i>
            <span>Book Free Demo</span>
          </button>
        </div>

        <!-- Helpline & Location Footer -->
        <div class="promo-micro-footer">
          <a href="tel:<?php echo PHONE_RAW_1; ?>" class="promo-phone-link">
            <i class="fa-solid fa-phone"></i> <?php echo PHONE_PRIMARY; ?>
          </a>
          <span class="dot-separator">•</span>
          <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener" class="promo-wa-link">
            <i class="fa-brands fa-whatsapp"></i> WhatsApp
          </a>
          <span class="dot-separator">•</span>
          <span class="promo-loc"><i class="fa-solid fa-location-dot"></i> Ulwe Sector 19</span>
        </div>
      </div>

      <!-- Right Column: Interactive Image Slider with assets/imgs/popup/ images -->
      <div class="promo-image-col">
        <div class="promo-slider-container" id="promoSlider">
          <div class="promo-slides-wrapper">
            <div class="promo-slides-track" id="promoSlidesTrack">
              <?php foreach ($active_popup_slides as $ps_idx => $ps_slide):
                  $ps_clean_img = ltrim($ps_slide['image'], '/');
                  $ps_link = !empty($ps_slide['link_url']) ? ((strpos($ps_slide['link_url'], 'http') === 0) ? $ps_slide['link_url'] : url(ltrim($ps_slide['link_url'], '/'))) : url('admissions');
                  $ps_caption = !empty($ps_slide['caption']) ? $ps_slide['caption'] : $ps_slide['title'];
              ?>
              <div class="promo-slide <?php echo $ps_idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $ps_idx; ?>">
                <a href="<?php echo htmlspecialchars($ps_link); ?>" class="promo-slide-link" title="<?php echo htmlspecialchars($ps_slide['title']); ?>">
                  <img src="<?php echo url($ps_clean_img); ?>" alt="<?php echo htmlspecialchars($ps_slide['title']); ?>" class="promo-slider-img" loading="<?php echo $ps_idx === 0 ? 'eager' : 'lazy'; ?>">
                  <span class="promo-slide-caption"><i class="fa-solid fa-award"></i> <?php echo htmlspecialchars($ps_caption); ?></span>
                </a>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Slider Controls -->
          <button type="button" class="promo-nav-btn prev-btn" id="promoPrevBtn" aria-label="Previous Poster">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <button type="button" class="promo-nav-btn next-btn" id="promoNextBtn" aria-label="Next Poster">
            <i class="fa-solid fa-chevron-right"></i>
          </button>

          <!-- Slider Pagination Dots -->
          <div class="promo-dots-bar" id="promoDotsBar">
            <?php foreach ($active_popup_slides as $ps_idx => $ps_slide): ?>
              <button type="button" class="promo-dot <?php echo $ps_idx === 0 ? 'active' : ''; ?>" data-slide="<?php echo $ps_idx; ?>" aria-label="Poster <?php echo $ps_idx + 1; ?>"></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


