<?php
/**
 * Unified Form Submission Handler for KSV Academy
 * Handles:
 * 1. Book Your 3-Days Free Demo Class (form_type = 'demo_class')
 * 2. Candidate Registration Form (form_type = 'admission')
 * 3. Send an Inquiry (form_type = 'inquiry')
 *
 * Stores all submissions into table: form_data
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/web-app/config.php';

// Check if request is JSON
$is_json = false;
$raw_input = file_get_contents('php://input');
$json_data = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($content_type, 'application/json') !== false || (!empty($raw_input) && (substr(trim($raw_input), 0, 1) === '{'))) {
    $decoded = json_decode($raw_input, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $json_data = $decoded;
        $is_json = true;
    }
}

$input = !empty($json_data) ? $json_data : $_POST;

$is_ajax = $is_json || 
           (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
           (!empty($_POST['ajax']) && $_POST['ajax'] == '1') ||
           (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

function send_response($success, $message, $data = [], $redirect_url = '') {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message
        ], $data));
        exit;
    }

    if (!empty($redirect_url)) {
        $safe_redirect = preg_replace('/[\r\n]/', '', $redirect_url);
        header('Location: ' . $safe_redirect);
        exit;
    }

    $ref = $_SERVER['HTTP_REFERER'] ?? '/';
    // Strip control characters to prevent HTTP response splitting
    $ref = preg_replace('/[\r\n]/', '', $ref);
    // Ensure redirect stays within site
    $parsed_ref = parse_url($ref);
    if (!empty($parsed_ref['host']) && $parsed_ref['host'] !== ($_SERVER['HTTP_HOST'] ?? '')) {
        $ref = '/';
    }
    $joiner = (strpos($ref, '?') !== false) ? '&' : '?';
    header('Location: ' . $ref . $joiner . 'form_status=' . ($success ? 'success' : 'error'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    send_response(false, 'Invalid request method. Only POST allowed.');
}

// 1. Anti-Bot Honeypot Validation: silent exit if bot filled out honeypot field
if (!empty($input['website_hp'])) {
    send_response(true, 'Thank you! Your submission has been received.');
}

// 2. CSRF Token Validation
$client_csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($client_csrf)) {
    send_response(false, 'Security validation expired. Please refresh the page and submit again.');
}

// 3. Rate Limiting Protection (Max 5 submissions per 5 minutes per session)
$now = time();
if (!isset($_SESSION['form_submissions']) || !is_array($_SESSION['form_submissions'])) {
    $_SESSION['form_submissions'] = [];
}
$_SESSION['form_submissions'] = array_filter($_SESSION['form_submissions'], function($timestamp) use ($now) {
    return ($now - (int)$timestamp) < 300;
});
if (count($_SESSION['form_submissions']) >= 5) {
    send_response(false, 'Too many requests. Please wait a few minutes before submitting another form.');
}
$_SESSION['form_submissions'][] = $now;

// Ensure DB connection and table exist
try {
    $db = get_db_connection();
    ensure_form_data_table($db);
} catch (Exception $e) {
    error_log('Database connection error in submit-form.php: ' . $e->getMessage());
    send_response(false, 'Service is temporarily unavailable. Please try again or message us on WhatsApp.');
}

// 4. Determine form type
$raw_type = strtolower(trim($input['form_type'] ?? ''));
$form_type = 'demo_class'; // default
if (in_array($raw_type, ['demo', 'demo_class', 'free_demo'])) {
    $form_type = 'demo_class';
} elseif (in_array($raw_type, ['admission', 'admissions', 'candidate_registration', 'registration'])) {
    $form_type = 'admission';
} elseif (in_array($raw_type, ['inquiry', 'enquiry', 'contact', 'send_inquiry'])) {
    $form_type = 'inquiry';
}

// 5. Extract, sanitize, and length-limit all fields
$full_name = mb_substr(strip_tags(trim($input['student_name'] ?? ($input['full_name'] ?? ($input['name'] ?? '')))), 0, 150);
$phone = mb_substr(strip_tags(trim($input['phone'] ?? ($input['mobile'] ?? ($input['contact'] ?? '')))), 0, 30);
$email_raw = trim($input['email'] ?? '');
$email = filter_var($email_raw, FILTER_VALIDATE_EMAIL) ? mb_substr($email_raw, 0, 150) : null;
$parent_name = mb_substr(strip_tags(trim($input['parent_name'] ?? ($input['guardian_name'] ?? ''))), 0, 150);
$course = mb_substr(strip_tags(trim($input['course_standard'] ?? ($input['course'] ?? ($input['course_applying'] ?? '')))), 0, 150);
$batch_timing = mb_substr(strip_tags(trim($input['batch_timing'] ?? ($input['batch'] ?? ($input['preferred_shift'] ?? '')))), 0, 150);
$school_name = mb_substr(strip_tags(trim($input['school_name'] ?? ($input['school'] ?? ($input['college_name'] ?? '')))), 0, 255);
$query_type = mb_substr(strip_tags(trim($input['query_type'] ?? ($input['subject'] ?? ''))), 0, 150);
$message = mb_substr(strip_tags(trim($input['message'] ?? ($input['remarks'] ?? ($input['query'] ?? '')))), 0, 1000);

// Source tracking
$source_page = mb_substr(strip_tags(preg_replace('/[\r\n]/', '', trim($input['source_page'] ?? ($_SERVER['HTTP_REFERER'] ?? '')))), 0, 255);
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
if (strpos($ip_address, ',') !== false) {
    $parts = explode(',', $ip_address);
    $ip_address = trim($parts[0]);
}
$ip_address = filter_var($ip_address, FILTER_VALIDATE_IP) ? $ip_address : '127.0.0.1';
$user_agent = mb_substr(strip_tags($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);

// Validation
if (empty($full_name)) {
    send_response(false, 'Please provide your full name.');
}

if (empty($phone)) {
    send_response(false, 'Please provide a valid phone/WhatsApp number.');
}

// Clean phone digits for sanity check
$clean_phone = preg_replace('/[^0-9+]/', '', $phone);
$digit_count = strlen(preg_replace('/[^0-9]/', '', $clean_phone));
if ($digit_count < 7 || $digit_count > 15) {
    send_response(false, 'Please enter a valid mobile number (7 to 15 digits).');
}

// Insert into form_data table using prepared statement
try {
    $stmt = $db->prepare("INSERT INTO form_data 
        (form_type, full_name, phone, email, parent_name, course, batch_timing, school_name, query_type, message, status, source_page, ip_address, user_agent)
        VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unread', ?, ?, ?)");

    $stmt->execute([
        $form_type,
        $full_name,
        $clean_phone,
        !empty($email) ? $email : null,
        !empty($parent_name) ? $parent_name : null,
        !empty($course) ? $course : null,
        !empty($batch_timing) ? $batch_timing : null,
        !empty($school_name) ? $school_name : null,
        !empty($query_type) ? $query_type : null,
        !empty($message) ? $message : null,
        $source_page,
        $ip_address,
        $user_agent
    ]);

    $insert_id = $db->lastInsertId();

    $success_messages = [
        'demo_class' => "Thank you, {$full_name}! Your 3-day demo class seat has been reserved. Our academic counselor will contact you shortly.",
        'admission'  => "Thank you, {$full_name}! Your admission application has been registered successfully. Our admissions desk will call you within 24 hours.",
        'inquiry'    => "Thank you, {$full_name}! Your inquiry has been received. Our team will get back to you shortly."
    ];

    send_response(true, $success_messages[$form_type] ?? 'Form submitted successfully!', [
        'id' => $insert_id,
        'form_type' => $form_type,
        'full_name' => $full_name,
        'phone' => $clean_phone,
        'course' => $course
    ]);

} catch (Exception $e) {
    error_log('Error saving form submission: ' . $e->getMessage());
    send_response(false, 'An unexpected error occurred while saving your request. Please try again or reach out on WhatsApp.');
}
