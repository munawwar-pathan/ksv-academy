<?php
require_once __DIR__ . '/../config.php';

// Active page context detection
$active_page_name = isset($active_page) ? $active_page : 'home';
$is_home_page = ($active_page_name === 'home');

// Navigation Section Anchor Mappings (for home in-page smooth scroll & cross-page navigation)
$nav_home_url       = $is_home_page ? '#heroSection' : url();
$nav_about_url      = $is_home_page ? '#aboutSection' : url('#aboutSection');
$nav_courses_url    = $is_home_page ? '#coursesSection' : url('#coursesSection');
$nav_faculty_url    = $is_home_page ? '#facultySection' : url('#facultySection');
$nav_admissions_url = $is_home_page ? '#admissionsSection' : url('#admissionsSection');
$nav_gallery_url    = $is_home_page ? '#gallerySection' : url('#gallerySection');
$nav_contact_url    = $is_home_page ? '#contactSection' : url('#contactSection');

// Clean canonical URL determination (strips tracking query strings)
$parsed_req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$clean_req_path = ($parsed_req_path === '/index.php' || $parsed_req_path === '' || $parsed_req_path === false) ? '/' : $parsed_req_path;
$base_domain_url = IS_LIVE ? LIVE_URL : SITE_URL;

// Default SEO values optimized for Google & AI search
$page_title = isset($page_title) ? $page_title : SITE_NAME . ' | Best Coaching Classes in Ulwe | 5th-12th, IIT-JEE, NEET, MH-CET, Degree';
$page_description = isset($page_description) ? $page_description : 'KSV Academy in Ulwe Sector 19, Navi Mumbai offers top-notch coaching for Classes 5th to 12th (CBSE, ICSE, State Board), IIT-JEE Main & Advanced, NEET, MH-CET and Degree Courses (B.Com, BMS, BCA). Book your 3-Days Free Demo Class today!';
$page_keywords = isset($page_keywords) ? $page_keywords : 'KSV Academy, coaching classes in Ulwe, tuition in Ulwe sector 19, best coaching classes Navi Mumbai, IIT JEE coaching Ulwe, NEET classes Ulwe, MH-CET tuition, CBSE ICSE coaching 8th 9th 10th, 11th 12th science commerce, BCom BMS BCA tuition, Salam Khan Physics, Rameshwar Mishra Chemistry, tuition near Om Sai hospital';
$current_canonical = isset($canonical_url) ? $canonical_url : rtrim($base_domain_url, '/') . $clean_req_path;
$og_image = isset($page_image) ? $page_image : rtrim($base_domain_url, '/') . '/' . SITE_LOGO;

// Breadcrumb navigation labels for rich snippets
$breadcrumb_titles = [
    'home'       => 'Home',
    'about'      => 'About Us',
    'courses'    => 'Courses & Batches',
    'admissions' => 'Admissions & Scholarships',
    'faculty'    => 'Expert Faculty',
    'gallery'    => 'Campus Gallery',
    'contact'    => 'Contact & Helpdesk',
    '404'        => '404 Page Not Found'
];
$current_crumb_name = $breadcrumb_titles[$active_page_name] ?? 'Page';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  
  <!-- ══════════════════════════════════════════════════════
       1. PRIMARY GOOGLE SEO & AI CRAWLER META TAGS
       ══════════════════════════════════════════════════════ -->
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
  <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">
  <meta name="author" content="KSV Academy - Director Salam Khan">
  <link rel="canonical" href="<?php echo htmlspecialchars($current_canonical); ?>">

  <!-- AI & Search Bot Directives (Google, Bing, SearchGPT, Perplexity, Copilot, Claude) -->
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
  <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
  <meta name="bingbot" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">

  <!-- Semantic Content Classification for LLMs & Search Engines -->
  <meta name="language" content="English">
  <meta name="target" content="all">
  <meta name="audience" content="Students, Parents, Teachers, IIT-JEE Aspirants, NEET Aspirants, Board Exam Students">
  <meta name="topic" content="Education, Coaching Classes, IIT-JEE, NEET, Board Tuition, Degree Courses">
  <meta name="classification" content="Education, Coaching Institute, Tuition Center, Entrance Exam Training">
  <meta name="coverage" content="Ulwe, Navi Mumbai, Panvel, Nerul, Belapur, Kharghar, Raigad, Maharashtra, India">
  <meta name="distribution" content="Global">
  <meta name="rating" content="General">
  <meta name="revisit-after" content="2 days">

  <!-- Geographic Location Tags for Hyper-Local & Voice AI Queries -->
  <meta name="geo.region" content="IN-MH">
  <meta name="geo.placename" content="Ulwe, Navi Mumbai, Maharashtra, India">
  <meta name="geo.position" content="18.9734;73.0232">
  <meta name="ICBM" content="18.9734, 73.0232">

  <!-- ══════════════════════════════════════════════════════
       2. OPEN GRAPH META TAGS (Facebook, WhatsApp, LinkedIn)
       ══════════════════════════════════════════════════════ -->
  <meta property="og:type" content="<?php echo ($active_page_name === 'home') ? 'website' : 'article'; ?>">
  <meta property="og:site_name" content="<?php echo SITE_NAME; ?> - Where Learning Meets Excellence">
  <meta property="og:url" content="<?php echo htmlspecialchars($current_canonical); ?>">
  <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
  <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
  <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($og_image); ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:type" content="image/jpeg">
  <meta property="og:image:alt" content="<?php echo htmlspecialchars($page_title); ?>">
  <meta property="og:locale" content="en_IN">

  <!-- ══════════════════════════════════════════════════════
       3. TWITTER / X CARDS
       ══════════════════════════════════════════════════════ -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:url" content="<?php echo htmlspecialchars($current_canonical); ?>">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
  <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">
  <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">
  <meta name="twitter:image:alt" content="<?php echo htmlspecialchars($page_title); ?>">

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
  <!-- Responsive Stylesheet -->
  <link rel="stylesheet" href="<?php echo url('assets/css/responsive.css?v=' . (file_exists(__DIR__ . '/../assets/css/responsive.css') ? filemtime(__DIR__ . '/../assets/css/responsive.css') : time())); ?>">

  <!-- ══════════════════════════════════════════════════════
       4. JSON-LD STRUCTURED DATA SCHEMA GRAPH (Google & AI Knowledge Graph)
       ══════════════════════════════════════════════════════ -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": ["EducationalOrganization", "LocalBusiness", "School"],
        "@id": "https://ksvacademy.org/#organization",
        "name": "<?php echo SITE_NAME; ?>",
        "alternateName": ["KSV Academy Ulwe", "KSV Coaching Classes", "KSV Classes Sector 19", "KSV Educational Academy"],
        "url": "https://ksvacademy.org",
        "logo": {
          "@type": "ImageObject",
          "url": "https://ksvacademy.org/assets/imgs/ksv-logo.jpg",
          "caption": "KSV Academy Ulwe Logo"
        },
        "image": "https://ksvacademy.org/assets/imgs/ksv-logo.jpg",
        "description": "KSV Academy in Ulwe, Navi Mumbai is a premier coaching institute providing specialized coaching for Classes 5th to 12th (CBSE, ICSE, Maharashtra State Board), IIT-JEE (Main & Advanced), NEET Medical, MH-CET, and Degree Courses (B.Com, BMS, BCA). Features 10+ years academic legacy, expert faculty, up to 60% scholarship, and 3-Days Free Demo classes.",
        "telephone": "<?php echo PHONE_RAW_1; ?>",
        "email": "<?php echo EMAIL_PRIMARY; ?>",
        "priceRange": "₹₹",
        "currenciesAccepted": "INR",
        "paymentAccepted": "Cash, UPI, Credit Card, Debit Card, Net Banking, Cheque",
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
          "latitude": 18.9734,
          "longitude": 73.0232
        },
        "hasMap": "https://maps.google.com/?q=18.9734,73.0232",
        "sameAs": [
          "<?php echo URL_YOUTUBE; ?>",
          "<?php echo URL_INSTAGRAM; ?>",
          "<?php echo URL_FACEBOOK; ?>"
        ],
        "areaServed": [
          { "@type": "AdministrativeArea", "name": "Ulwe" },
          { "@type": "AdministrativeArea", "name": "Navi Mumbai" },
          { "@type": "AdministrativeArea", "name": "Panvel" },
          { "@type": "AdministrativeArea", "name": "Nerul" },
          { "@type": "AdministrativeArea", "name": "Belapur" },
          { "@type": "AdministrativeArea", "name": "Kharghar" },
          { "@type": "AdministrativeArea", "name": "Maharashtra" }
        ],
        "knowsAbout": [
          "IIT-JEE Main & Advanced Coaching",
          "NEET Medical Entrance Preparation",
          "MH-CET Engineering & Pharmacy Coaching",
          "CBSE 8th, 9th, 10th Science & Mathematics",
          "ICSE Board Coaching Classes",
          "Maharashtra State Board SSC & HSC",
          "11th & 12th Science Stream (Physics, Chemistry, Maths, Biology)",
          "11th & 12th Commerce Stream (Accounts, Economics, OCM, SP)",
          "B.Com, BMS, BCA Degree Coaching",
          "Academic Mentorship & Scholarship Tests"
        ],
        "founder": {
          "@type": "Person",
          "name": "Mr. Salam Khan",
          "jobTitle": "Founder & Physics Head",
          "description": "Founder and Director of KSV Academy with 10+ years experience mentoring IIT-JEE and NEET physics toppers."
        },
        "employee": [
          {
            "@type": "Person",
            "name": "Mr. Rameshwar Mishra",
            "jobTitle": "Senior Chemistry Faculty",
            "description": "Chemistry specialist with 15+ years experience in Organic and Inorganic chemistry."
          },
          {
            "@type": "Person",
            "name": "Mr. Aman Kumar Soni",
            "jobTitle": "Senior Mathematics Mentor",
            "description": "Mathematics expert specializing in calculus, algebra and competitive entrance problem solving."
          },
          {
            "@type": "Person",
            "name": "Mrs. Hafiza Palikar",
            "jobTitle": "Senior Mathematics Faculty",
            "description": "Mumbai University academician with 12+ years experience in higher secondary mathematics."
          }
        ],
        "aggregateRating": {
          "@type": "AggregateRating",
          "ratingValue": "4.9",
          "bestRating": "5",
          "ratingCount": "184",
          "reviewCount": "184"
        },
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
      },
      {
        "@type": "WebSite",
        "@id": "https://ksvacademy.org/#website",
        "url": "https://ksvacademy.org",
        "name": "KSV Academy",
        "description": "Best Coaching Classes in Ulwe Sector 19 for IIT-JEE, NEET, MH-CET, 5th-12th & Degree Courses",
        "publisher": {
          "@id": "https://ksvacademy.org/#organization"
        },
        "inLanguage": "en-IN"
      },
      {
        "@type": "BreadcrumbList",
        "@id": "<?php echo htmlspecialchars($current_canonical); ?>#breadcrumb",
        "itemListElement": [
          {
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "https://ksvacademy.org/"
          }
          <?php if ($active_page_name !== 'home'): ?>
          ,{
            "@type": "ListItem",
            "position": 2,
            "name": "<?php echo addslashes($current_crumb_name); ?>",
            "item": "<?php echo htmlspecialchars($current_canonical); ?>"
          }
          <?php endif; ?>
        ]
      }
      <?php if ($active_page_name === 'courses'): ?>
      ,{
        "@type": "ItemList",
        "@id": "https://ksvacademy.org/courses#courselist",
        "name": "Academic & Competitive Programs at KSV Academy",
        "itemListElement": [
          {
            "@type": "Course",
            "position": 1,
            "name": "IIT-JEE Main & Advanced Integrated Program",
            "description": "Rigorous 2-year and 1-year coaching for engineering entrance aspirants covering Physics, Chemistry, and Mathematics with mock test series.",
            "provider": { "@id": "https://ksvacademy.org/#organization" }
          },
          {
            "@type": "Course",
            "position": 2,
            "name": "NEET Medical Entrance Preparation Batch",
            "description": "Comprehensive coaching for NEET aspirants focusing on NCERT syllabus mastery, biological diagrams, and chemical problem-solving.",
            "provider": { "@id": "https://ksvacademy.org/#organization" }
          },
          {
            "@type": "Course",
            "position": 3,
            "name": "11th & 12th Science (Board + MH-CET)",
            "description": "State and CBSE board excellence coaching integrated with MH-CET entrance preparation in Physics, Chemistry, Maths & Biology.",
            "provider": { "@id": "https://ksvacademy.org/#organization" }
          },
          {
            "@type": "Course",
            "position": 4,
            "name": "11th & 12th Commerce Stream",
            "description": "Expert mentorship in Bookkeeping, Accountancy, Economics, Secretarial Practice (SP), and Organization of Commerce (OCM).",
            "provider": { "@id": "https://ksvacademy.org/#organization" }
          },
          {
            "@type": "Course",
            "position": 5,
            "name": "Secondary School Foundation (Classes 8th to 10th)",
            "description": "Strong foundation in Science, Mathematics and English for CBSE, ICSE, and Maharashtra State Board students.",
            "provider": { "@id": "https://ksvacademy.org/#organization" }
          },
          {
            "@type": "Course",
            "position": 6,
            "name": "Undergraduate Degree Programs (B.Com, BMS, BCA)",
            "description": "Specialized college tuition for Mumbai University commerce, management and computer application degrees.",
            "provider": { "@id": "https://ksvacademy.org/#organization" }
          }
        ]
      }
      <?php endif; ?>
      <?php if ($active_page_name === 'admissions'): ?>
      ,{
        "@type": "FAQPage",
        "@id": "https://ksvacademy.org/admissions#faq",
        "mainEntity": [
          {
            "@type": "Question",
            "name": "How do I claim the 3-Days Free Demo Class at KSV Academy Ulwe?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Parents and students can register online through our website admission form or visit our Ulwe Sector 19 center directly. No advance payment or registration fee is required for attending the 3-day trial demo sessions."
            }
          },
          {
            "@type": "Question",
            "name": "What is the criteria for the scholarship up to 60%?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Scholarships up to 60% are awarded based on performance in the KSV Scholarship cum Diagnostic Test, previous academic board results, or entrance mock scores."
            }
          },
          {
            "@type": "Question",
            "name": "Are tuition fees payable in flexible installments?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Yes, KSV Academy offers interest-free monthly and quarterly installment options to make quality education accessible to every family."
            }
          },
          {
            "@type": "Question",
            "name": "Can students switch between Morning and Evening shifts?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Yes, we provide flexible batch scheduling with morning and evening sessions to accommodate school and junior college timings comfortably."
            }
          },
          {
            "@type": "Question",
            "name": "What study material is included upon admission?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Students receive comprehensive chapter-wise study modules, formula cheat sheets, past 10 years question banks, and weekly test series booklets."
            }
          }
        ]
      }
      <?php endif; ?>
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
        <div class="ticker-text-wrapper" id="topbarTicker">
          <div class="ticker-slide slide-active">
            <span class="topbar-live-dot"></span>
            <strong>Admissions Open 2026–27</strong>
          </div>
          <div class="ticker-slide">
            <span class="topbar-chip-gold"><i class="fa-solid fa-sparkles"></i> Up to 60% Scholarship</span>
          </div>
          <div class="ticker-slide">
            <span class="topbar-chip-white"><i class="fa-solid fa-calendar-check"></i> 3-Days Free Demo</span>
          </div>
        </div>
      </div>
      <div class="top-bar-right">
        <div class="top-contact-item d-none-mobile top-phone-capsule">
          <i class="fa-solid fa-phone-volume"></i>
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
        <div class="top-portal-btn-wrap d-none-mobile">
          <a href="<?php echo url('web-app'); ?>" class="topbar-portal-btn" title="Staff &amp; Student Web App Portal">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Portal Login</span>
          </a>
        </div>
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
          <span class="brand-subtitle"> Where Learning Meets Excellence</span>
        </div>
      </a>

      <!-- Desktop Navigation Menu -->
      <nav class="desktop-navigation" aria-label="Primary Navigation">
        <ul class="nav-menu-list">
          <li class="nav-item">
            <a href="<?php echo $nav_home_url; ?>" class="nav-link <?php echo is_active('home'); ?>" data-scroll="#heroSection">
              <span>Home</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo $nav_about_url; ?>" class="nav-link <?php echo is_active('about'); ?>" data-scroll="#aboutSection">
              <span>About Us</span>
            </a>
          </li>
          <li class="nav-item has-dropdown">
            <a href="<?php echo $nav_courses_url; ?>" class="nav-link <?php echo is_active('courses'); ?>" id="coursesDropdownToggle" data-scroll="#coursesSection">
              <span>Courses</span>
              <!-- <span class="nav-courses-badge">New Batches</span> -->
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
            <a href="<?php echo $nav_faculty_url; ?>" class="nav-link <?php echo is_active('faculty'); ?>" data-scroll="#facultySection">
              <span>Faculty</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo $nav_admissions_url; ?>" class="nav-link <?php echo is_active('admissions'); ?>" data-scroll="#admissionsSection">
              <span>Admissions</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo $nav_gallery_url; ?>" class="nav-link <?php echo is_active('gallery'); ?>" data-scroll="#gallerySection">
              <span>Gallery</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?php echo $nav_contact_url; ?>" class="nav-link <?php echo is_active('contact'); ?>" data-scroll="#contactSection">
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
          <span class="drawer-brand-tagline">Where Learning Meets Excellence</span>
        </div>
      </div>
      <button id="drawerCloseBtn" class="drawer-close-btn" aria-label="Close Mobile Menu">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="drawer-body">

      <ul class="drawer-menu-list">
        <li><a href="<?php echo $nav_home_url; ?>" class="<?php echo is_active('home'); ?>" data-scroll="#heroSection"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="<?php echo $nav_about_url; ?>" class="<?php echo is_active('about'); ?>" data-scroll="#aboutSection"><i class="fa-solid fa-circle-info"></i> About Us</a></li>
        <li class="drawer-item-dropdown">
          <a href="<?php echo $nav_courses_url; ?>" class="<?php echo is_active('courses'); ?>" data-scroll="#coursesSection"><i class="fa-solid fa-book-open"></i> Courses &amp; Batches</a>
          <div class="drawer-sub-links">
            <a href="<?php echo url('courses?filter=school'); ?>" data-course-filter="school">&bull; School Section (5th–10th)</a>
            <a href="<?php echo url('courses?filter=competitive'); ?>" data-course-filter="competitive">&bull; IIT-JEE / NEET / MH-CET</a>
            <a href="<?php echo url('courses?filter=college'); ?>" data-course-filter="college">&bull; Junior College (11th &amp; 12th)</a>
            <a href="<?php echo url('courses?filter=degree'); ?>" data-course-filter="degree">&bull; Undergraduate &amp; Degree</a>
            <a href="<?php echo url('courses?filter=postgraduate'); ?>" data-course-filter="postgraduate">&bull; Postgraduate Programs</a>
          </div>
        </li>
        <li><a href="<?php echo $nav_faculty_url; ?>" class="<?php echo is_active('faculty'); ?>" data-scroll="#facultySection"><i class="fa-solid fa-chalkboard-user"></i> Star Faculty</a></li>
        <li><a href="<?php echo $nav_admissions_url; ?>" class="<?php echo is_active('admissions'); ?>" data-scroll="#admissionsSection"><i class="fa-solid fa-award"></i> Admissions &amp; Scholarship</a></li>
        <li><a href="<?php echo $nav_gallery_url; ?>" class="<?php echo is_active('gallery'); ?>" data-scroll="#gallerySection"><i class="fa-solid fa-images"></i> Campus &amp; Class Gallery</a></li>
        <li><a href="<?php echo $nav_contact_url; ?>" class="<?php echo is_active('contact'); ?>" data-scroll="#contactSection"><i class="fa-solid fa-headset"></i> Student Support &amp; Contact</a></li>
        <li class="drawer-login-menu-item">
          <a href="<?php echo url('web-app'); ?>" class="drawer-login-menu-link">
            <div class="drawer-login-left">
              <i class="fa-solid fa-lock"></i>
              <div class="drawer-login-text">
                <span class="drawer-login-title">Portal Login</span>
                <span class="drawer-login-subtitle">Admin, Staff &amp; Student</span>
              </div>
            </div>
            <span class="drawer-login-pill">Sign In</span>
          </a>
        </li>
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
