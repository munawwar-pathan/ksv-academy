<?php
require_once __DIR__ . '/../config.php';
?>
<!-- Floating Action Widgets -->
<div class="floating-actions-container" aria-label="Quick Actions">
  <!-- Scroll to Top Button -->
  <button id="scrollToTopBtn" class="floating-btn scroll-top-btn" aria-label="Scroll to top of page" title="Back to Top">
    <i class="fa-solid fa-arrow-up"></i>
  </button>

  <!-- Floating Call Button -->
  <a href="tel:<?php echo PHONE_RAW_1; ?>" class="floating-btn call-float-btn" aria-label="Call KSV Academy" title="Call KSV Academy (+91 79059 00350)">
    <i class="fa-solid fa-phone"></i>
  </a>

  <!-- Floating WhatsApp Button with pulse badge -->
  <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener noreferrer" class="floating-btn wa-float-btn" aria-label="Chat on WhatsApp" title="WhatsApp Us (+91 79059 00350)">
    <i class="fa-brands fa-whatsapp"></i>
    <span class="wa-tooltip">Chat with Counselor</span>
  </a>
</div>

<!-- Mobile Sticky Quick-Action Bar -->
<div class="mobile-sticky-bar">
  <a href="tel:<?php echo PHONE_RAW_1; ?>" class="mobile-bar-action bar-call">
    <i class="fa-solid fa-phone"></i>
    <span>Call Now</span>
  </a>
  <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener noreferrer" class="mobile-bar-action bar-whatsapp">
    <i class="fa-brands fa-whatsapp"></i>
    <span>WhatsApp</span>
  </a>
  <button type="button" class="mobile-bar-action bar-demo btn-demo-trigger" data-modal="demoModal">
    <i class="fa-solid fa-graduation-cap"></i>
    <span>Free Demo</span>
  </button>
</div>
