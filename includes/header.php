<?php
require_once __DIR__ . '/../config.php';

// Default SEO values if not set by page
$page_title = isset($page_title) ? $page_title : SITE_NAME . ' | Best Coaching Classes in Ulwe | School, College, IIT-JEE, NEET & Degree';
$page_description = isset($page_description) ? $page_description : 'KSV Academy in Ulwe, Navi Mumbai provides premier coaching for Classes 5th-12th (CBSE/ICSE/State), IIT-JEE, NEET, MH-CET, and Degree programs (B.Com, BMS, BCA). 10+ years experience, expert faculty & up to 60% scholarship.';
$page_keywords = isset($page_keywords) ? $page_keywords : 'KSV Academy, coaching classes in Ulwe, tuition in Ulwe sector 19, best coaching classes Navi Mumbai, IIT JEE coaching Ulwe, NEET classes Ulwe, MH-CET tuition, CBSE ICSE coaching 8th 9th 10th, BCom BMS BCA tuition, Salam Khan Physics, Rameshwar Mishra Chemistry';
$current_canonical = isset($canonical_url) ? $canonical_url : SITE_URL . $_SERVER['REQUEST_URI'];
$og_image = isset($page_image) ? $page_image : SITE_URL . '/' . SITE_LOGO;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  
  <!-- Primary SEO Meta Tags -->
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
  <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">
  <meta name="author" content="KSV Academy">
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
  <link rel="canonical" href="<?php echo htmlspecialchars($current_canonical); ?>">

  <!-- Open Graph / Facebook Meta Tags -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
  <meta property="og:url" content="<?php echo htmlspecialchars($current_canonical); ?>">
  <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
  <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
  <meta property="og:locale" content="en_IN">

  <!-- Twitter Card Meta Tags -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:url" content="<?php echo htmlspecialchars($current_canonical); ?>">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
  <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">
  <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">

  <!-- Favicon and Brand Icons -->
  <link rel="icon" type="image/jpeg" href="<?php echo url(SITE_LOGO); ?>">
  <link rel="apple-touch-icon" href="<?php echo url(SITE_LOGO); ?>">
  <meta name="theme-color" content="#0092df">

  <!-- Preconnect and Google Fonts (Academic & EdTech Hierarchy) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800;900&family=Lora:ital,wght@0,500;0,600;0,700;1,400;1,500;1,600&family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
  
  <!-- FontAwesome for EdTech Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Core Stylesheet -->
  <link rel="stylesheet" href="<?php echo url('assets/css/style.css?v=' . filemtime(__DIR__ . '/../assets/css/style.css')); ?>">

  <!-- JSON-LD Structured Data Schema for Search Engines -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "EducationalOrganization",
    "name": "<?php echo SITE_NAME; ?>",
    "url": "<?php echo SITE_URL; ?>",
    "logo": "<?php echo SITE_URL . '/' . SITE_LOGO; ?>",
    "description": "<?php echo addslashes($page_description); ?>",
    "telephone": "<?php echo PHONE_RAW_1; ?>",
    "email": "<?php echo EMAIL_PRIMARY; ?>",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "MGM Residency, Shop No.02, Plot No.161, opp. Om Sai Hospital, Sector 19",
      "addressLocality": "Ulwe, Navi Mumbai",
      "addressRegion": "Maharashtra",
      "postalCode": "410206",
      "addressCountry": "IN"
    },
    "geo": {
      "@type": "GeoCoordinates",
      "latitude": "18.9734",
      "longitude": "73.0232"
    },
    "sameAs": [
      "<?php echo URL_YOUTUBE; ?>",
      "<?php echo URL_INSTAGRAM; ?>",
      "<?php echo URL_FACEBOOK; ?>"
    ],
    "priceRange": "₹₹",
    "openingHoursSpecification": [
      {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
        "opens": "07:30",
        "closes": "21:30"
      },
      {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": "Sunday",
        "opens": "08:00",
        "closes": "14:00"
      }
    ]
  }
  </script>
</head>
<body>

  <!-- Top Announcement Ribbon -->
  <div class="top-announcement-bar">
    <div class="container top-bar-container">
      <div class="announcement-ticker">
        <span class="ticker-badge"><i class="fa-solid fa-graduation-cap"></i> Session <?php echo CURRENT_SESSION; ?></span>
        <div class="ticker-text-wrapper">
          <span class="ticker-text">
            <strong>Admissions Open</strong> &bull; Scholarship up to 60% &bull; 3-Days Free Demo Classes
          </span>
        </div>
      </div>
      <div class="top-bar-right">
        <div class="top-contact-item d-none-mobile">
          <i class="fa-solid fa-phone"></i>
          <span>Call:</span>
          <a href="tel:<?php echo PHONE_RAW_1; ?>"><?php echo PHONE_PRIMARY; ?></a>
        </div>
        <div class="top-social-links">
          <a href="<?php echo URL_YOUTUBE; ?>" target="_blank" rel="noopener noreferrer" title="YouTube Channel" aria-label="YouTube">
            <i class="fa-brands fa-youtube"></i>
          </a>
          <a href="<?php echo URL_INSTAGRAM; ?>" target="_blank" rel="noopener noreferrer" title="Instagram" aria-label="Instagram">
            <i class="fa-brands fa-instagram"></i>
          </a>
          <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener noreferrer" title="WhatsApp Chat" aria-label="WhatsApp">
            <i class="fa-brands fa-whatsapp"></i>
          </a>
        </div>
        <a href="<?php echo url('web-app'); ?>" class="top-contact-item" style="color:#ffffff; font-weight:700; text-decoration:none; background:rgba(255,255,255,0.18); padding:3px 10px; border-radius:4px; border:1px solid rgba(255,255,255,0.25);" title="Staff &amp; Student Web App Portal">
          <i class="fa-solid fa-lock" style="font-size:0.75rem; color:#fef08a;"></i>
          <span>Portal Login</span>
        </a>
        <!-- Dark/Light Mode Switcher -->
        <button id="themeToggleBtn" class="theme-toggle-btn" aria-label="Toggle dark and light theme" title="Toggle Theme">
          <i class="fa-solid fa-moon dark-icon"></i>
          <i class="fa-solid fa-sun light-icon"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Main Navigation Header -->
  <header class="site-header" id="siteHeader">
    <div class="container nav-container">
      <!-- Brand Logo -->
      <a href="<?php echo url(); ?>" class="brand-logo" aria-label="KSV Academy Home">
        <div class="logo-symbol-wrapper">
          <img src="<?php echo url(SITE_LOGO); ?>" alt="KSV Academy Logo" width="54" height="54" class="brand-img">
        </div>
        <div class="logo-typography">
          <span class="brand-title">KSV ACADEMY</span>
          <span class="brand-subtitle">Where Learning Meets Excellence</span>
        </div>
      </a>

      <!-- Desktop Navigation Menu -->
      <nav class="desktop-navigation" aria-label="Primary Navigation">
        <ul class="nav-menu-list">
          <li class="nav-item">
            <a href="<?php echo url(); ?>" class="nav-link <?php echo is_active('home'); ?>">
              <span>Home</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo url('about'); ?>" class="nav-link <?php echo is_active('about'); ?>">
              <span>About Us</span>
            </a>
          </li>
          <li class="nav-item has-dropdown">
            <a href="<?php echo url('courses'); ?>" class="nav-link <?php echo is_active('courses'); ?>" id="coursesDropdownToggle">
              <span>Courses</span>
              <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
            </a>
            <div class="dropdown-menu-box" id="coursesDropdownMenu">
              <div class="dropdown-list">
                <a href="<?php echo url('courses?filter=school'); ?>" class="dropdown-link-item" data-course-filter="school">
                  <div class="dropdown-icon-box"><i class="fa-solid fa-school"></i></div>
                  <div class="dropdown-text">
                    <span class="dropdown-title">School Section</span>
                    <span class="dropdown-subtitle">Classes 5th – 10th (CBSE / ICSE / State)</span>
                  </div>
                </a>
                <a href="<?php echo url('courses?filter=competitive'); ?>" class="dropdown-link-item" data-course-filter="competitive">
                  <div class="dropdown-icon-box"><i class="fa-solid fa-atom"></i></div>
                  <div class="dropdown-text">
                    <span class="dropdown-title">IIT-JEE / NEET / MH-CET</span>
                    <span class="dropdown-subtitle">Medical &amp; Engineering Entrances</span>
                  </div>
                </a>
                <a href="<?php echo url('courses?filter=college'); ?>" class="dropdown-link-item" data-course-filter="college">
                  <div class="dropdown-icon-box"><i class="fa-solid fa-graduation-cap"></i></div>
                  <div class="dropdown-text">
                    <span class="dropdown-title">Junior College</span>
                    <span class="dropdown-subtitle">11th &amp; 12th Science &amp; Commerce</span>
                  </div>
                </a>
                <a href="<?php echo url('courses?filter=degree'); ?>" class="dropdown-link-item" data-course-filter="degree">
                  <div class="dropdown-icon-box"><i class="fa-solid fa-building-columns"></i></div>
                  <div class="dropdown-text">
                    <span class="dropdown-title">Undergraduate &amp; Degree</span>
                    <span class="dropdown-subtitle">B.Com, BMS, BAF, BBA &amp; BCA</span>
                  </div>
                </a>
                <a href="<?php echo url('courses?filter=postgraduate'); ?>" class="dropdown-link-item" data-course-filter="postgraduate">
                  <div class="dropdown-icon-box"><i class="fa-solid fa-award"></i></div>
                  <div class="dropdown-text">
                    <span class="dropdown-title">Postgraduate Programs</span>
                    <span class="dropdown-subtitle">Master of Commerce &amp; Management</span>
                  </div>
                </a>
              </div>
              <div class="dropdown-footer">
                <a href="<?php echo url('courses?filter=all'); ?>" class="dropdown-footer-link" data-course-filter="all">
                  <span>View All Courses &amp; Batches</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </a>
              </div>
            </div>
          </li>
          <li class="nav-item">
            <a href="<?php echo url('faculty'); ?>" class="nav-link <?php echo is_active('faculty'); ?>">
              <span>Faculty</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo url('admissions'); ?>" class="nav-link <?php echo is_active('admissions'); ?>">
              <span>Admissions</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo url('gallery'); ?>" class="nav-link <?php echo is_active('gallery'); ?>">
              <span>Gallery</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo url('contact'); ?>" class="nav-link <?php echo is_active('contact'); ?>">
              <span>Contact</span>
            </a>
          </li>
        </ul>
      </nav>

      <!-- Action Buttons -->
      <div class="nav-action-buttons">
        <button type="button" class="btn btn-demo-glow btn-demo-trigger" data-modal="demoModal">
          <i class="fa-regular fa-calendar-check"></i>
          <span>Free Demo</span>
        </button>
        <a href="<?php echo url('admissions'); ?>" class="btn btn-primary btn-apply">
          <i class="fa-solid fa-user-graduate"></i>
          <span>Apply Now</span>
        </a>
        <button id="mobileMenuToggle" class="mobile-toggle-btn" aria-label="Open Mobile Menu">
          <span></span>
          <span></span>
          <span></span>
        </button>
      </div>
    </div>
  </header>

  <!-- Mobile Off-Canvas Drawer -->
  <div class="mobile-drawer-overlay" id="drawerOverlay"></div>
  <aside class="mobile-navigation-drawer" id="mobileDrawer" aria-label="Mobile Navigation">
    <div class="drawer-header">
      <div class="drawer-brand">
        <img src="<?php echo url(SITE_LOGO); ?>" alt="KSV Academy Logo" width="48" height="48" class="drawer-logo-img">
        <div>
          <span class="drawer-brand-name">KSV ACADEMY</span>
          <span class="drawer-brand-tagline">Ulwe, Navi Mumbai</span>
        </div>
      </div>
      <button id="drawerCloseBtn" class="drawer-close-btn" aria-label="Close Mobile Menu">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="drawer-body">
      <ul class="drawer-menu-list">
        <li><a href="<?php echo url(); ?>" class="<?php echo is_active('home'); ?>"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="<?php echo url('about'); ?>" class="<?php echo is_active('about'); ?>"><i class="fa-solid fa-circle-info"></i> About Us</a></li>
        <li class="drawer-item-dropdown">
          <a href="<?php echo url('courses'); ?>" class="<?php echo is_active('courses'); ?>"><i class="fa-solid fa-book-open"></i> Courses &amp; Batches</a>
          <div class="drawer-sub-links">
            <a href="<?php echo url('courses?filter=school'); ?>" data-course-filter="school">&bull; School Section (5th–10th)</a>
            <a href="<?php echo url('courses?filter=competitive'); ?>" data-course-filter="competitive">&bull; IIT-JEE / NEET / MH-CET</a>
            <a href="<?php echo url('courses?filter=college'); ?>" data-course-filter="college">&bull; Junior College (11th &amp; 12th)</a>
            <a href="<?php echo url('courses?filter=degree'); ?>" data-course-filter="degree">&bull; Undergraduate &amp; Degree</a>
            <a href="<?php echo url('courses?filter=postgraduate'); ?>" data-course-filter="postgraduate">&bull; Postgraduate Programs</a>
          </div>
        </li>
        <li><a href="<?php echo url('faculty'); ?>" class="<?php echo is_active('faculty'); ?>"><i class="fa-solid fa-chalkboard-user"></i> Star Faculty</a></li>
        <li><a href="<?php echo url('admissions'); ?>" class="<?php echo is_active('admissions'); ?>"><i class="fa-solid fa-award"></i> Admissions & Scholarship</a></li>
        <li><a href="<?php echo url('gallery'); ?>" class="<?php echo is_active('gallery'); ?>"><i class="fa-solid fa-images"></i> Campus & Class Gallery</a></li>
        <li><a href="<?php echo url('contact'); ?>" class="<?php echo is_active('contact'); ?>"><i class="fa-solid fa-headset"></i> Student Support & Contact</a></li>
      </ul>

      <div class="drawer-cta-section">
        <button type="button" class="btn btn-outline-primary btn-block mb-2" data-modal="demoModal">
          <i class="fa-solid fa-calendar-check"></i> Book 3-Days Free Demo
        </button>
        <a href="<?php echo url('admissions'); ?>" class="btn btn-primary btn-block mb-3">
          <i class="fa-solid fa-user-plus"></i> Online Admission Form
        </a>
        <div class="drawer-contact-card">
          <div class="drawer-phone">
            <i class="fa-solid fa-phone-volume"></i>
            <div>
              <small>Call Direct</small>
              <a href="tel:<?php echo PHONE_RAW_1; ?>"><?php echo PHONE_PRIMARY; ?></a>
            </div>
          </div>
          <div class="drawer-whatsapp">
            <a href="<?php echo WHATSAPP_LINK; ?>" class="btn-whatsapp-drawer" target="_blank" rel="noopener noreferrer">
              <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp
            </a>
          </div>
        </div>
      </div>
    </div>
  </aside>
