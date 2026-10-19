<?php
require_once __DIR__ . '/../config.php';
?>
  <!-- Global Footer -->
  <footer class="site-footer">
    <div class="footer-top-strip">
      <div class="container footer-strip-container">
        <div class="strip-item">
          <div class="strip-icon"><i class="fa-solid fa-graduation-cap"></i></div>
          <div class="strip-info">
            <strong>10+ Years Excellence</strong>
            <span>Proven coaching track record in Ulwe</span>
          </div>
        </div>
        <div class="strip-item">
          <div class="strip-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
          <div class="strip-info">
            <strong>Expert & Master Educators</strong>
            <span>M.Sc, M.Ed, B.Tech Specialized Mentors</span>
          </div>
        </div>
        <div class="strip-item">
          <div class="strip-icon"><i class="fa-solid fa-chart-line"></i></div>
          <div class="strip-info">
            <strong>Weekly Progress Analytics</strong>
            <span>Continuous testing & parent updates</span>
          </div>
        </div>
        <div class="strip-item">
          <div class="strip-icon"><i class="fa-solid fa-award"></i></div>
          <div class="strip-info">
            <strong>Up to 60% Scholarship</strong>
            <span>Merit-based financial assistance</span>
          </div>
        </div>
      </div>
    </div>

    <div class="container footer-main-content">
      <div class="footer-grid">
        <!-- Col 1: Brand Info -->
        <div class="footer-col col-brand">
          <div class="footer-logo-block">
            <img src="<?php echo url(SITE_LOGO); ?>" alt="KSV Academy Logo" width="56" height="56" class="footer-brand-logo">
            <div>
              <h3 class="footer-brand-name">KSV ACADEMY</h3>
              <p class="footer-brand-slogan">Where Learning Meets Excellence</p>
            </div>
          </div>
          <p class="footer-description">
            KSV Academy is the premier coaching institute in Ulwe, Navi Mumbai, dedicated to empowering students from Class 5th to 12th, IIT-JEE, NEET, MH-CET, and Degree College streams with conceptual clarity and high-yield problem solving.
          </p>
          <div class="footer-social-bar">
            <a href="<?php echo URL_YOUTUBE; ?>" target="_blank" rel="noopener noreferrer" class="social-circle yt" aria-label="YouTube">
              <i class="fa-brands fa-youtube"></i>
            </a>
            <a href="<?php echo URL_INSTAGRAM; ?>" target="_blank" rel="noopener noreferrer" class="social-circle ig" aria-label="Instagram">
              <i class="fa-brands fa-instagram"></i>
            </a>
            <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener noreferrer" class="social-circle wa" aria-label="WhatsApp">
              <i class="fa-brands fa-whatsapp"></i>
            </a>
          </div>
        </div>

        <!-- Col 2: Academic Programs -->
        <div class="footer-col col-links">
          <h4 class="footer-title">Academic Courses</h4>
          <ul class="footer-nav-list">
            <li><a href="<?php echo url('courses#school'); ?>"><i class="fa-solid fa-angle-right"></i> Class 5th to 10th (CBSE/ICSE/State)</a></li>
            <li><a href="<?php echo url('courses#competitive'); ?>"><i class="fa-solid fa-angle-right"></i> IIT-JEE (Main & Advanced)</a></li>
            <li><a href="<?php echo url('courses#competitive'); ?>"><i class="fa-solid fa-angle-right"></i> NEET-UG Medical Preparation</a></li>
            <li><a href="<?php echo url('courses#competitive'); ?>"><i class="fa-solid fa-angle-right"></i> MH-CET (Engineering & Pharmacy)</a></li>
            <li><a href="<?php echo url('courses#college'); ?>"><i class="fa-solid fa-angle-right"></i> 11th & 12th Science (PCM/PCB)</a></li>
            <li><a href="<?php echo url('courses#college'); ?>"><i class="fa-solid fa-angle-right"></i> 11th & 12th Commerce Section</a></li>
            <li><a href="<?php echo url('courses#degree'); ?>"><i class="fa-solid fa-angle-right"></i> Degree: B.Com, BMS, BAF, BCA</a></li>
            <li><a href="<?php echo url('courses#degree'); ?>"><i class="fa-solid fa-angle-right"></i> Postgraduate: M.Com & MBA Guidance</a></li>
          </ul>
        </div>

        <!-- Col 3: Quick Navigation -->
        <div class="footer-col col-links">
          <h4 class="footer-title">Helpful Links</h4>
          <ul class="footer-nav-list">
            <li><a href="<?php echo url(); ?>"><i class="fa-solid fa-angle-right"></i> Home Overview</a></li>
            <li><a href="<?php echo url('about'); ?>"><i class="fa-solid fa-angle-right"></i> About KSV Academy</a></li>
            <li><a href="<?php echo url('faculty'); ?>"><i class="fa-solid fa-angle-right"></i> Our Expert Faculty</a></li>
            <li><a href="<?php echo url('admissions'); ?>"><i class="fa-solid fa-angle-right"></i> Admissions & Scholarship</a></li>
            <li><a href="<?php echo url('gallery'); ?>"><i class="fa-solid fa-angle-right"></i> Campus Life & Gallery</a></li>
            <li><a href="<?php echo url('contact'); ?>"><i class="fa-solid fa-angle-right"></i> Student Support & Helpdesk</a></li>
            <li><a href="#" data-modal="demoModal"><i class="fa-solid fa-angle-right"></i> 3-Days Free Demo Class</a></li>
            <li><a href="<?php echo URL_YOUTUBE; ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-angle-right"></i> Video Lectures (YouTube)</a></li>
          </ul>
        </div>

        <!-- Col 4: Contact & Academy Office -->
        <div class="footer-col col-contact">
          <h4 class="footer-title">Ulwe Campus & Office</h4>
          <div class="footer-contact-info">
            <div class="contact-row">
              <i class="fa-solid fa-location-dot"></i>
              <div>
                <strong>Academy Address:</strong>
                <p><?php echo FULL_ADDRESS; ?></p>
              </div>
            </div>
            <div class="contact-row">
              <i class="fa-solid fa-phone"></i>
              <div>
                <strong>Helpline Numbers:</strong>
                <p>
                  <a href="tel:<?php echo PHONE_RAW_1; ?>"><?php echo PHONE_PRIMARY; ?></a><br>
                  <a href="tel:<?php echo PHONE_RAW_2; ?>"><?php echo PHONE_SECONDARY; ?></a>
                </p>
              </div>
            </div>
            <div class="contact-row">
              <i class="fa-solid fa-envelope"></i>
              <div>
                <strong>Official Email:</strong>
                <p><a href="mailto:<?php echo EMAIL_PRIMARY; ?>"><?php echo EMAIL_PRIMARY; ?></a></p>
              </div>
            </div>
            <div class="contact-row">
              <i class="fa-solid fa-clock"></i>
              <div>
                <strong>Academy Hours:</strong>
                <p>Mon - Sat: 7:30 AM – 9:30 PM<br>Sunday: 8:00 AM – 2:00 PM</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Copyright Ribbon -->
    <div class="footer-bottom-bar">
      <div class="container footer-bottom-container">
        <div class="copyright-text">
          &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All Rights Reserved. Developed by <a href="https://thecompanies.in/" target="_blank" rel="noopener noreferrer" style="text-decoration: none;">The Companies</a>.
        </div>
        <div class="footer-bottom-links">
          <span>Ulwe Sector 19, Navi Mumbai, 410206</span>
          <span class="sep">•</span>
          <a href="<?php echo url('admissions'); ?>">Apply Online</a>
          <span class="sep">•</span>
          <a href="<?php echo url('contact'); ?>">Support Desk</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Include Floating Actions and Modals -->
  <?php require_once __DIR__ . '/floating-actions.php'; ?>
  <?php require_once __DIR__ . '/modals.php'; ?>

  <!-- Core JavaScript -->
  <script src="<?php echo url('assets/js/main.js?v=' . filemtime(__DIR__ . '/../assets/js/main.js')); ?>"></script>
</body>
</html>
