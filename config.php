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
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
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
        'category' => 'Classroom Batches'
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
        'file' => 'gallery-7.png',
        'caption' => 'Dedicated Faculty Guidance & Board Preparation',
        'category' => 'Mentorship'
    ],
    [
        'file' => 'gallery-8.png',
        'caption' => 'Student Celebration and Academic Milestone',
        'category' => 'Events'
    ],
    [
        'file' => 'gallery-9.png',
        'caption' => 'Collaborative Group Study & Problem Solving',
        'category' => 'Students'
    ],
    [
        'file' => 'gallery-10.png',
        'caption' => 'Interactive Teaching & Board Exam Guidance',
        'category' => 'Classrooms'
    ]
];
