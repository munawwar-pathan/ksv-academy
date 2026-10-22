<?php
/**
 * KSV Academy - Global Configuration & Data Source
 * Where Learning Meets Excellence
 */

// Site Information
define('SITE_NAME', 'KSV Academy');
define('SITE_TAGLINE', 'Where Learning Meets Excellence');
define('SITE_MOTTO', 'Ab Sapnon ki Coaching Ulwe Mein! Strong Foundation, Sure Success.');
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
    define('IS_LIVE', false);
    define('LIVE_URL', 'https://ksvacademy.org');
    define('SITE_URL', $http_host ? $protocol . $http_host : 'http://localhost:8000');
} else {
    define('IS_LIVE', true);
    define('LIVE_URL', 'https://ksvacademy.org');
    define('SITE_URL', 'https://ksvacademy.org');
}
define('SITE_LOGO', 'assets/imgs/ksv-logo.jpg');

// Contact Details
define('PHONE_PRIMARY', '+91 79059 00350');
define('PHONE_SECONDARY', '+91 99840 58406');
define('PHONE_RAW_1', '+917905900350');
define('PHONE_RAW_2', '+919984058406');
define('WHATSAPP_NUMBER', '917905900350');
define('WHATSAPP_LINK', 'https://wa.me/917905900350?text=' . urlencode('Hello KSV Academy, I would like to inquire about admissions and the 3-day free demo classes.'));
define('EMAIL_PRIMARY', 'KSVAcademy2024@gmail.com');

// Address
define('ADDRESS_STREET', 'MGM Residency, Shop No.02, Plot No.161, opp. Om Sai Hospital, Sector 19');
define('ADDRESS_LOCALITY', 'Ulwe, Navi Mumbai');
define('ADDRESS_PINCODE', '410206');
define('ADDRESS_STATE', 'Maharashtra, India');
define('FULL_ADDRESS', 'MGM Residency, Shop No.02, Plot No.161, opp. Om Sai Hospital, Sector 19, Ulwe, Navi Mumbai, 410206');

// Social Media Links
define('URL_YOUTUBE', 'https://www.youtube.com/@KSVAcademy-i9t/videos');
define('URL_INSTAGRAM', 'https://www.instagram.com/ksv.academy2024?igsh=ZDlscXZ1dWVodTk0');
define('URL_FACEBOOK', 'https://www.facebook.com');

// Academic Session
define('CURRENT_SESSION', '2026-27');
define('SCHOLARSHIP_OFFER', 'Up to 60% Scholarship');

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
        'caption' => 'Interactive Classroom Session at KSV Academy Ulwe',
        'category' => 'Classrooms'
    ],
    [
        'file' => 'gallery-7.png',
        'caption' => 'Focused Concept Problem Solving & Mentorship',
        'category' => 'Study Sessions'
    ],
    [
        'file' => 'gallery-2.png',
        'caption' => 'Doubt Clearing & Personalized Attention Session',
        'category' => 'Classrooms'
    ],
    [
        'file' => 'gallery-7.png',
        'caption' => 'Dedicated Faculty Guidance & Board Preparation',
        'category' => 'Mentorship'
    ],
    [
        'file' => 'gallery-7.png',
        'caption' => 'Director Mr. Salam Khan Mentoring Science Aspirants',
        'category' => 'Faculty'
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
