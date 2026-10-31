<?php
/**
 * KSV Academy - Global Configuration & Data Source
 * Where Learning Meets Excellence
 */

// Global Timezone: Indian Standard Time (IST / UTC+05:30)
date_default_timezone_set('Asia/Kolkata');

// Error Handling: Suppress PHP notices and warnings on frontend UI, log errors securely
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);

// Session initialization (before any HTML output)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

/**
 * CSRF Protection Token Helpers
 */
function get_csrf_token() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

// Site Information
if (!defined('SITE_NAME')) define('SITE_NAME', 'KSV Academy');
if (!defined('SITE_TAGLINE')) define('SITE_TAGLINE', 'Where Learning Meets Excellence');
if (!defined('SITE_MOTTO')) define('SITE_MOTTO', 'Ab Sapnon ki Coaching Ulwe Mein! Strong Foundation, Sure Success.');
// Environment Detection (Live vs Localhost)
$http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
$is_ssl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $is_ssl ? 'https://' : 'http://';

$is_local_env = (
    empty($http_host) ||
    in_array($http_host, ['localhost', '127.0.0.1', '::1']) ||
    strpos($http_host, 'localhost:') === 0 ||
    strpos($http_host, '127.0.0.1:') === 0 ||
    preg_match('/\.local$/i', $http_host)
);

if ($is_local_env) {
    if (!defined('IS_LIVE')) define('IS_LIVE', false);
    if (!defined('LIVE_URL')) define('LIVE_URL', 'https://ksvacademy.org');
    if (!defined('SITE_URL')) define('SITE_URL', $http_host ? $protocol . $http_host : 'http://localhost:8000');
} else {
    if (!defined('IS_LIVE')) define('IS_LIVE', true);
    if (!defined('LIVE_URL')) define('LIVE_URL', 'https://ksvacademy.org');
    if (!defined('SITE_URL')) define('SITE_URL', 'https://ksvacademy.org');
}
if (!defined('SITE_LOGO')) define('SITE_LOGO', 'assets/imgs/ksv-logo.jpg');

// Contact Details
if (!defined('PHONE_PRIMARY')) define('PHONE_PRIMARY', '+91 79059 00350');
if (!defined('PHONE_SECONDARY')) define('PHONE_SECONDARY', '+91 99840 58406');
if (!defined('PHONE_RAW_1')) define('PHONE_RAW_1', '+917905900350');
if (!defined('PHONE_RAW_2')) define('PHONE_RAW_2', '+919984058406');
if (!defined('WHATSAPP_NUMBER')) define('WHATSAPP_NUMBER', '917905900350');
if (!defined('WHATSAPP_LINK')) define('WHATSAPP_LINK', 'https://wa.me/917905900350?text=' . urlencode('Hello KSV Academy, I would like to inquire about admissions and the 3-day free demo classes.'));
if (!defined('EMAIL_PRIMARY')) define('EMAIL_PRIMARY', 'KSVAcademy2024@gmail.com');

// Outbound SMTP Mailer Configuration (Override here or via Web-App School Settings)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', 'tls');
if (!defined('SMTP_USER')) define('SMTP_USER', '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'admissions@ksvacademy.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'KSV Academy');

// Address
if (!defined('ADDRESS_STREET')) define('ADDRESS_STREET', 'MGM Residency, Shop No.02, Plot No.161, opp. Om Sai Hospital, Sector 19');
if (!defined('ADDRESS_LOCALITY')) define('ADDRESS_LOCALITY', 'Ulwe, Navi Mumbai');
if (!defined('ADDRESS_PINCODE')) define('ADDRESS_PINCODE', '410206');
if (!defined('ADDRESS_STATE')) define('ADDRESS_STATE', 'Maharashtra, India');
if (!defined('FULL_ADDRESS')) define('FULL_ADDRESS', 'MGM Residency, Shop No.02, Plot No.161, opp. Om Sai Hospital, Sector 19, Ulwe, Navi Mumbai, 410206');

// Social Media Links
if (!defined('URL_YOUTUBE')) define('URL_YOUTUBE', 'https://www.youtube.com/@KSVAcademy-i9t/videos');
if (!defined('URL_INSTAGRAM')) define('URL_INSTAGRAM', 'https://www.instagram.com/ksv.academy2024?igsh=ZDlscXZ1dWVodTk0');
if (!defined('URL_FACEBOOK')) define('URL_FACEBOOK', 'https://www.facebook.com');

// Academic Session
if (!defined('CURRENT_SESSION')) define('CURRENT_SESSION', '2026-27');
if (!defined('SCHOLARSHIP_OFFER')) define('SCHOLARSHIP_OFFER', 'Up to 60% Scholarship');

// Base URL detection for clean routing
function get_base_url() {
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    return rtrim($script_dir, '/');
}

function url($path = '') {
    $base = get_base_url();
    $path = ltrim($path, '/');
    if (empty($path)) {
        return $base ? $base . '/' : '/';
    }
    return ($base ? $base : '') . '/' . $path;
}

function get_current_page() {
    global $active_page;
    if (!empty($active_page)) {
        return $active_page;
    }

    // 1. Check REQUEST_URI (Clean URL / routing)
    if (!empty($_SERVER['REQUEST_URI'])) {
        $uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base = get_base_url();
        if (!empty($base) && strpos($uri_path, $base) === 0) {
            $uri_path = substr($uri_path, strlen($base));
        }
        $path = trim($uri_path, '/');
        if (!empty($path)) {
            $parts = explode('/', $path);
            $slug = strtolower(basename($parts[0], '.php'));
            if (!empty($slug) && $slug !== 'index' && $slug !== 'router') {
                return $slug;
            }
        }
    }

    // 2. Check backtrace for calling PHP template
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    foreach ($trace as $step) {
        if (!empty($step['file'])) {
            $base_name = strtolower(basename($step['file'], '.php'));
            if (!in_array($base_name, ['config', 'header', 'footer', 'router', 'modals', 'functions'])) {
                return ($base_name === 'index') ? 'home' : $base_name;
            }
        }
    }

    // 3. Fallback to SCRIPT_NAME / PHP_SELF
    $script = strtolower(basename($_SERVER['SCRIPT_NAME'] ?? '', '.php'));
    if (!empty($script) && !in_array($script, ['index', 'router'])) {
        return $script;
    }

    return 'home';
}

function is_active($page) {
    return (get_current_page() === $page) ? 'active' : '';
}

// Faculty Data
$faculties = [
    [
        'id' => 'salam-khan',
        'name' => 'Mr. Salam Khan',
        'title' => 'Founder & Director',
        'degrees' => 'M.Sc, B.Ed',
        'specialization' => 'Physics',
        'experience' => '10 Years',
        'image' => 'assets/imgs/faculties/founder-director.png',
        'bio' => 'Founder & Director of KSV Academy. Mr. Salam Khan has 10+ years of dedicated teaching experience in Physics and Mathematics. His passion for conceptual clarity and strategic problem solving has guided hundreds of aspirants to top percentiles in NEET, IIT-JEE, and MH-CET.',
        'highlight' => '10+ Years Experience • NEET, IIT-JEE & MH-CET Specialist'
    ],
    [
        'id' => 'rameshwar-mishra',
        'name' => 'Mr. Rameshwar Mishra',
        'title' => 'Senior Faculty - Chemistry',
        'degrees' => 'M.Sc, M.Ed',
        'specialization' => 'Chemistry',
        'experience' => '15 Years',
        'image' => 'assets/imgs/faculties/mr-rameshwar-mishra.png',
        'bio' => 'A veteran educator with 15 years of prestigious teaching experience in Organic, Inorganic, and Physical Chemistry. Renowned for simplifying complex reactions and driving stellar chemistry scores in Boards & Competitive Entrances.',
        'highlight' => '15 Years Experience • Master of Organic & Physical Chemistry'
    ],
    [
        'id' => 'aman-kumar-soni',
        'name' => 'Mr. Aman Kumar Soni',
        'title' => 'Faculty - Chemistry & Mathematics',
        'degrees' => 'B.Tech',
        'specialization' => 'Chemistry & Mathematics',
        'experience' => '5 Years',
        'image' => 'assets/imgs/faculties/mr-aman-kumar-soni.png',
        'bio' => 'Engineering graduate with deep analytical rigor. Brings modern pedagogical approaches to Mathematics and Chemistry, fostering sharp analytical problem-solving skills for competitive exams.',
        'highlight' => '5 Years Experience • Analytical Problem Solving & High-Yield Techniques'
    ],
    [
        'id' => 'hafiza-palikar',
        'name' => 'Hafiza Palikar',
        'title' => 'Faculty - Mathematics',
        'degrees' => 'M.Sc (Mathematics), Mumbai University',
        'specialization' => 'Pure & Applied Mathematics',
        'experience' => 'Specialist Educator',
        'image' => 'assets/imgs/faculties/hafiza-palikar.png',
        'bio' => 'Holds a Masters in Mathematics from prestigious Mumbai University. Specializes in building unwavering mathematical fundamentals, calculus, and algebra intuition from school boards to higher secondary.',
        'highlight' => 'Mumbai University Alumna • Pure & Applied Math Specialist'
    ],
    [
        'id' => 'rafeek-khan',
        'name' => 'Rafeek Khan',
        'title' => 'Faculty - Computer Science & Math',
        'degrees' => 'M.Sc (Computer Science)',
        'specialization' => 'Computer Science & Mathematics',
        'experience' => 'Senior Tech & Math Faculty',
        'image' => 'assets/imgs/faculties/rafeek-khan.png',
        'bio' => 'Postgraduate in Computer Science. Mentors students in programming, discrete mathematics, computer applications (BCA), and advanced secondary science and mathematics curriculum.',
        'highlight' => 'M.Sc Computer Science • Programming & Applied Computing'
    ],
    [
        'id' => 'lubna-khan',
        'name' => 'Mrs. Lubna Khan',
        'title' => 'Faculty - English & SST',
        'degrees' => 'Bachelor of Management Studies (BMS)',
        'specialization' => 'English & Social Studies (SST)',
        'experience' => 'Management & Humanities Educator',
        'image' => 'assets/imgs/faculties/mrs-lubna-khan.png',
        'bio' => 'Management graduate bringing effective communication, conceptual comprehension, and answer-structuring techniques for English language, literature, and Social Sciences.',
        'highlight' => 'Management Graduate • Language & Humanities Expert'
    ]
];

/**
 * Fetch dynamic active faculty profiles from database (managed via /web-app/admin/staff)
 * with graceful fallback to static $faculties array.
 */
function get_active_faculties($limit = null) {
    global $faculties;
    try {
        require_once __DIR__ . '/web-app/config.php';
        $db = get_db_connection();
        if ($db) {
            $has_sort = false;
            try {
                $so_chk = $db->query("SHOW COLUMNS FROM staff_profiles LIKE 'sort_order'");
                $has_sort = ($so_chk && $so_chk->rowCount() > 0);
            } catch (Throwable $e) {}

            $order_by = $has_sort 
                ? "ORDER BY CASE WHEN sp.sort_order = 0 THEN 9999 ELSE sp.sort_order END ASC, sp.id ASC" 
                : "ORDER BY sp.id ASC";

            $sql = "
                SELECT sp.*, u.username, u.full_name, u.avatar, u.status as user_status
                FROM staff_profiles sp
                JOIN users u ON sp.user_id = u.id
                WHERE u.status = 'active'
                {$order_by}
            ";
            if ($limit !== null && (int)$limit > 0) {
                $sql .= " LIMIT " . (int)$limit;
            }
            $stmt = $db->query($sql);
            if ($stmt) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $formatted = [];
                    foreach ($rows as $r) {
                        // Determine photo
                        $photo = '';
                        if (!empty($r['photo']) && preg_match('#^https?://#i', $r['photo'])) {
                            $photo = $r['photo'];
                        } elseif (!empty($r['photo']) && $r['photo'] !== '/assets/imgs/ksv-logo.jpg') {
                            $photo = ltrim($r['photo'], '/');
                        } elseif (!empty($r['avatar'])) {
                            if (file_exists(__DIR__ . '/assets/imgs/faculties/' . $r['avatar'])) {
                                $photo = 'assets/imgs/faculties/' . $r['avatar'];
                            } elseif (file_exists(__DIR__ . '/assets/imgs/' . $r['avatar'])) {
                                $photo = 'assets/imgs/' . $r['avatar'];
                            }
                        }
                        if (empty($photo)) {
                            $lower = strtolower($r['full_name']);
                            if (strpos($lower, 'salam') !== false || strpos($lower, 'director') !== false) {
                                $photo = 'assets/imgs/faculties/founder-director.png';
                            } elseif (strpos($lower, 'mishra') !== false || strpos($lower, 'rameshwar') !== false) {
                                $photo = 'assets/imgs/faculties/mr-rameshwar-mishra.png';
                            } elseif (strpos($lower, 'aman') !== false) {
                                $photo = 'assets/imgs/faculties/mr-aman-kumar-soni.png';
                            } elseif (strpos($lower, 'hafiza') !== false) {
                                $photo = 'assets/imgs/faculties/hafiza-palikar.png';
                            } elseif (strpos($lower, 'rafeek') !== false) {
                                $photo = 'assets/imgs/faculties/rafeek-khan.png';
                            } elseif (strpos($lower, 'lubna') !== false) {
                                $photo = 'assets/imgs/faculties/mrs-lubna-khan.png';
                            } elseif (strpos($lower, 'priya') !== false) {
                                $photo = 'assets/imgs/faculties/staff-priya.png';
                            } else {
                                $photo = 'assets/imgs/ksv-logo.jpg';
                            }
                        }

                        // Experience fallback
                        $exp = !empty($r['experience']) ? $r['experience'] : '';
                        if (empty($exp) && !empty($r['joining_date'])) {
                            $joined_year = (int)date('Y', strtotime($r['joining_date']));
                            $curr_year = (int)date('Y');
                            $diff = max(1, $curr_year - $joined_year);
                            $exp = $diff . '+ Years';
                        }
                        if (empty($exp)) {
                            $exp = 'Senior Faculty';
                        }

                        // Specialization fallback
                        $spec = !empty($r['specialization']) ? $r['specialization'] : (!empty($r['department']) ? $r['department'] : 'Academic Mentor');

                        // Highlight fallback
                        $highlight = !empty($r['highlight']) ? $r['highlight'] : "{$exp} Experience • Concept & Board Specialist";

                        // Bio fallback
                        $bio = !empty($r['bio']) ? $r['bio'] : "Experienced educator at KSV Academy dedicated to conceptual clarity, rigorous problem-solving, and mentoring students to top competitive exam ranks.";

                        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $r['full_name']), '-'));

                        $formatted[] = [
                            'id' => $slug ?: ('staff-' . $r['id']),
                            'staff_id' => $r['id'],
                            'user_id' => $r['user_id'],
                            'employee_code' => $r['employee_code'],
                            'name' => $r['full_name'],
                            'title' => $r['designation'] ?: 'Faculty Member',
                            'department' => $r['department'],
                            'degrees' => $r['qualification'] ?: 'M.Sc, B.Ed',
                            'specialization' => $spec,
                            'experience' => $exp,
                            'image' => $photo,
                            'bio' => $bio,
                            'highlight' => $highlight,
                            'sort_order' => (int)($r['sort_order'] ?? 0)
                        ];
                    }
                    return $formatted;
                }
            }
        }
    } catch (Throwable $e) {
        // Fallback
    }

    if ($limit !== null && (int)$limit > 0) {
        return array_slice($faculties, 0, (int)$limit);
    }
    return $faculties;
}

/**
 * Return theme class and subject badge icon for a faculty card
 */
function get_faculty_theme_info($fac, $idx = 0) {
    $themes = ['fac-theme-blue', 'fac-theme-emerald', 'fac-theme-amber', 'fac-theme-purple', 'fac-theme-cyan', 'fac-theme-rose'];
    $spec_lower = strtolower(($fac['specialization'] ?? '') . ' ' . ($fac['department'] ?? '') . ' ' . ($fac['name'] ?? ''));

    if (strpos($spec_lower, 'chem') !== false && strpos($spec_lower, 'math') !== false) {
        return [
            'theme' => 'fac-theme-amber',
            'icon' => 'fa-square-root-variable',
            'label' => 'Chem & Math'
        ];
    } elseif (strpos($spec_lower, 'chem') !== false) {
        return [
            'theme' => 'fac-theme-emerald',
            'icon' => 'fa-flask-vial',
            'label' => 'Chemistry'
        ];
    } elseif (strpos($spec_lower, 'math') !== false || strpos($spec_lower, 'calculus') !== false || strpos($spec_lower, 'algebra') !== false) {
        return [
            'theme' => 'fac-theme-purple',
            'icon' => 'fa-infinity',
            'label' => 'Mathematics'
        ];
    } elseif (strpos($spec_lower, 'computer') !== false || strpos($spec_lower, 'cs') !== false || strpos($spec_lower, 'code') !== false) {
        return [
            'theme' => 'fac-theme-cyan',
            'icon' => 'fa-laptop-code',
            'label' => 'CS & Math'
        ];
    } elseif (strpos($spec_lower, 'english') !== false || strpos($spec_lower, 'sst') !== false || strpos($spec_lower, 'humanities') !== false) {
        return [
            'theme' => 'fac-theme-rose',
            'icon' => 'fa-book-open-reader',
            'label' => 'English & SST'
        ];
    } elseif (strpos($spec_lower, 'bio') !== false || strpos($spec_lower, 'life') !== false || strpos($spec_lower, 'neet') !== false) {
        return [
            'theme' => 'fac-theme-emerald',
            'icon' => 'fa-dna',
            'label' => 'Biology'
        ];
    } elseif (strpos($spec_lower, 'phys') !== false || strpos($spec_lower, 'salam') !== false || strpos($spec_lower, 'director') !== false) {
        return [
            'theme' => 'fac-theme-blue',
            'icon' => 'fa-atom',
            'label' => 'Physics'
        ];
    }

    $theme = $themes[$idx % count($themes)];
    return [
        'theme' => $theme,
        'icon' => 'fa-chalkboard-user',
        'label' => !empty($fac['specialization']) ? $fac['specialization'] : (!empty($fac['department']) ? $fac['department'] : 'Academic Mentor')
    ];
}

// Courses Data categorized KGS style
$courses = [
    [
        'id' => 'school-section',
        'category' => 'school',
        'category_name' => 'School Section (Classes 5th - 10th)',
        'title' => 'Secondary Foundation & Board Mastery',
        'standards' => 'Class 5th, 6th, 7th, 8th, 9th & 10th',
        'boards' => 'CBSE, ICSE & Maharashtra State Board',
        'subjects' => 'Physics, Chemistry, Mathematics, Biology, English, SST',
        'badge' => 'Strong Foundation',
        'description' => 'Comprehensive concept-building curriculum designed to give students thorough subject mastery, regular test series, doubt clearing, and board examination preparedness.',
        'features' => [
            'Interactive daily lectures with visual conceptual proofs',
            'Weekly chapter-wise tests and performance analytics',
            'Dedicated doubt-clearing sessions every Saturday',
            'Exam strategy & answer writing workshops'
        ]
    ],
    [
        'id' => 'competitive-jee-neet',
        'category' => 'competitive',
        'category_name' => 'IIT-JEE / NEET / MH-CET',
        'title' => 'Target Medical & Engineering Pinnacle',
        'standards' => 'Class 11th & 12th + Repeaters',
        'boards' => 'National & State Level Competitive Entrances',
        'subjects' => 'Physics, Chemistry, Mathematics & Biology',
        'badge' => 'High Yield & Result Driven',
        'description' => 'Rigorous entrance exam preparation led by seasoned faculties with 10-15+ years experience. Tailored for top ranks in IIT-JEE (Main + Advanced), NEET-UG, and MH-CET.',
        'features' => [
            'Complete coverage of NCERT & advanced competitive problem sets',
            'Regular full-length mock tests simulating real CBT environments',
            'Personalized mentorship by Mr. Salam Khan & Mr. Rameshwar Mishra',
            'Up to 60% Scholarship based on aptitude assessment'
        ]
    ],
    [
        'id' => 'junior-college',
        'category' => 'college',
        'category_name' => 'Junior College (11th & 12th)',
        'title' => 'Higher Secondary Science & Commerce',
        'standards' => 'Class 11th & 12th (HSC, CBSE, ISC)',
        'boards' => 'State Board HSC, CBSE, ISC',
        'subjects' => 'Science (PCM/PCB) & Commerce (Accounts, Economics, OCM, SP, Maths)',
        'badge' => 'Board Excellence',
        'description' => 'In-depth classroom coaching for Junior College students targeting 90%+ in 12th board exams with seamless integration of entrance concepts.',
        'features' => [
            'Board-specific question bank solving & past 10 years papers',
            'Detailed chapter notes & quick revision mind maps',
            'Small batch sizes ensuring individual attention to every student',
            'Regular parent-teacher progress evaluations'
        ]
    ],
    [
        'id' => 'degree-programs',
        'category' => 'degree',
        'category_name' => 'Undergraduate & Degree Programs',
        'title' => 'Commerce & Management Degree Coaching',
        'standards' => 'First Year (FY), Second Year (SY), Third Year (TY)',
        'boards' => 'Mumbai University & Autonomous Colleges',
        'subjects' => 'B.Com, BMS, BAF, BBA, BCA',
        'badge' => 'University Rankers',
        'description' => 'Expert university syllabus coaching for B.Com (Accounting & Finance), BMS (Management Studies), BAF, BBA, and BCA (Computer Applications) by specialized professors.',
        'features' => [
            'In-depth conceptual lectures for Financial Accounting, Taxation & Costing',
            'Practical programming guidance for BCA students (C, Java, Python, Web)',
            'Case study analysis and presentation mentoring for BMS/BBA',
            'University exam oriented question solving and test series'
        ]
    ],
    [
        'id' => 'postgraduate-programs',
        'category' => 'postgraduate',
        'category_name' => 'Postgraduate Programs',
        'title' => 'Master of Commerce & Management',
        'standards' => 'Part 1 & Part 2',
        'boards' => 'Mumbai University & Leading Institutions',
        'subjects' => 'M.Com (Advanced Accounting & Business Mgmt), MBA Support',
        'badge' => 'Advanced Studies',
        'description' => 'Advanced postgraduate lectures covering higher financial management, research methodology, business analytics, and advanced commerce subjects.',
        'features' => [
            'Expert faculty with industry and university teaching background',
            'Weekend and flexible batch timings for working professionals',
            'Comprehensive project guidance and viva preparation',
            'Past papers solving & strategic examination tips'
        ]
    ],
    [
        'id' => 'olympiad-foundation',
        'category' => 'school',
        'category_name' => 'Olympiad & NTSE Foundation',
        'title' => 'Olympiad, Homi Bhabha & NTSE',
        'standards' => 'Class 6th to 10th',
        'boards' => 'Science & Math Olympiad Foundations',
        'subjects' => 'Logical Reasoning, Advanced Math & Science',
        'badge' => 'Early Edge',
        'description' => 'Nurturing young minds with higher-order thinking skills, mathematical aptitude, and scientific temper to crack competitive talent exams early.',
        'features' => [
            'Mental ability & analytical problem solving drills',
            'Exposure to national and international Olympiad patterns',
            'Hands-on experimental demonstrations & concept clarity',
            'Certificate of excellence & scholarship rewards'
        ]
    ]
];

// Campus Gallery Images
$gallery_images = [
    [
        'file' => 'gallery-1.png',
        'caption' => 'Interactive Classroom Session with Mr. Salam Khan',
        'category' => 'Smart Classrooms'
    ],
    [
        'file' => 'gallery-7.png',
        'caption' => 'One-on-One Concept Problem Solving & Doubt Mentorship',
        'category' => 'Doubt Clearing'
    ],
    [
        'file' => 'gallery-3.png',
        'caption' => 'High School Science Lecture & Physics Fundamentals',
        'category' => 'Smart Classrooms'
    ],
    [
        'file' => 'gallery-4.png',
        'caption' => 'Dedicated Self-Study Library & Reference Reading Hall',
        'category' => 'Study Library'
    ],
    [
        'file' => 'gallery-2.png',
        'caption' => 'Science Exhibition & Innovation Lab Demonstration',
        'category' => 'Academics'
    ],
    [
        'file' => 'gallery-8.png',
        'caption' => 'Student Celebration and Academic Milestone',
        'category' => 'Events'
    ],
    [
        'file' => 'gallery-9.png',
        'caption' => 'Collaborative Group Study & Problem Solving',
        'category' => 'Campus Life'
    ],
    [
        'file' => 'gallery-10.png',
        'caption' => 'Interactive Teaching & Board Exam Guidance',
        'category' => 'Classrooms'
    ]
];

/**
 * Fetch active media items for website gallery sections & gallery page.
 * Powered dynamically by web-app/admin/gallery.
 *
 * @param int|null $limit
 * @param string|null $category
 * @return array
 */
function get_active_gallery_items($limit = null, $category = null) {
    global $gallery_images;

    try {
        $db = null;
        if (function_exists('get_db_connection')) {
            $db = get_db_connection();
        } elseif (file_exists(__DIR__ . '/web-app/config.php')) {
            require_once __DIR__ . '/web-app/config.php';
            if (function_exists('get_db_connection')) {
                $db = get_db_connection();
            }
        }

        if (!empty($db)) {
            // Check available columns
            $has_status = false;
            $has_sort = false;
            try {
                $st_chk = $db->query("SHOW COLUMNS FROM gallery LIKE 'status'");
                $has_status = ($st_chk && $st_chk->rowCount() > 0);
            } catch (Throwable $e) {}

            try {
                $so_chk = $db->query("SHOW COLUMNS FROM gallery LIKE 'sort_order'");
                $has_sort = ($so_chk && $so_chk->rowCount() > 0);
            } catch (Throwable $e) {}

            $sql = "SELECT * FROM gallery";
            $where = [];
            $params = [];

            if ($has_status) {
                $where[] = "status = 'active'";
            }

            if (!empty($category) && $category !== 'all') {
                $where[] = "category = ?";
                $params[] = $category;
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }

            if ($has_sort) {
                $sql .= " ORDER BY sort_order ASC, id DESC";
            } else {
                $sql .= " ORDER BY id DESC";
            }

            if ($limit !== null && (int)$limit > 0) {
                $sql .= " LIMIT " . (int)$limit;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $items = [];
                foreach ($rows as $r) {
                    $raw_img = trim($r['image'] ?? '');
                    if (strpos($raw_img, 'http://') === 0 || strpos($raw_img, 'https://') === 0) {
                        $img_url = $raw_img;
                    } elseif (strpos($raw_img, '/') === 0) {
                        $img_url = url(ltrim($raw_img, '/'));
                    } else {
                        $img_url = url($raw_img);
                    }

                    $caption = !empty($r['caption']) ? $r['caption'] : (!empty($r['title']) ? $r['title'] : 'Campus Life');

                    $items[] = [
                        'id' => (int)$r['id'],
                        'title' => $r['title'] ?? 'Campus Life',
                        'caption' => $caption,
                        'category' => !empty($r['category']) ? $r['category'] : 'Campus Life',
                        'image' => $img_url,
                        'raw_image' => $raw_img,
                        'sort_order' => (int)($r['sort_order'] ?? 0),
                        'status' => $r['status'] ?? 'active',
                        'created_at' => $r['created_at'] ?? ''
                    ];
                }
                return $items;
            }
        }
    } catch (Throwable $e) {
        // Fallback below
    }

    // Static fallback
    $fallback_items = [];
    $raw_list = is_array($gallery_images) ? $gallery_images : [];
    foreach ($raw_list as $idx => $g) {
        $img_url = url('assets/imgs/gallers/' . $g['file']);
        $fallback_items[] = [
            'id' => $idx + 1,
            'title' => $g['caption'] ?? ('Campus Life Photo ' . ($idx + 1)),
            'caption' => $g['caption'] ?? '',
            'category' => $g['category'] ?? 'Campus Life',
            'image' => $img_url,
            'raw_image' => '/assets/imgs/gallers/' . $g['file'],
            'sort_order' => $idx + 1,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    if (!empty($category) && $category !== 'all') {
        $fallback_items = array_values(array_filter($fallback_items, function($it) use ($category) {
            return strcasecmp($it['category'], $category) === 0;
        }));
    }

    if ($limit !== null && (int)$limit > 0) {
        return array_slice($fallback_items, 0, (int)$limit);
    }
    return $fallback_items;
}

/**
 * Helper to return FontAwesome icon for gallery categories
 */
function get_gallery_category_icon($cat) {
    $c = strtolower(trim((string)$cat));
    if (strpos($c, 'doubt') !== false || strpos($c, 'mentor') !== false) return 'fa-comments';
    if (strpos($c, 'smart') !== false) return 'fa-desktop';
    if (strpos($c, 'class') !== false) return 'fa-chalkboard-user';
    if (strpos($c, 'lib') !== false || strpos($c, 'book') !== false || strpos($c, 'study') !== false) return 'fa-book-open-reader';
    if (strpos($c, 'sport') !== false) return 'fa-trophy';
    if (strpos($c, 'event') !== false) return 'fa-champagne-glasses';
    if (strpos($c, 'acad') !== false || strpos($c, 'sci') !== false) return 'fa-graduation-cap';
    if (strpos($c, 'celeb') !== false) return 'fa-wand-magic-sparkles';
    return 'fa-camera';
}

