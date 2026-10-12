<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

/** Redirect helper */
function redirect($path) {
    header("Location: $path");
    exit;
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        redirect('/ccms/login.php');
    }
    // Session (idle) timeout
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        session_unset();
        session_destroy();
        redirect('/ccms/login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();
}

function require_role(array $roles) {
    require_login();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center">
                <h2>403 - Access Denied</h2>
                <p>Your role (' . htmlspecialchars($_SESSION['role']) . ') does not have permission to view this page.</p>
                <a href="/ccms/dashboard.php">&larr; Back to Dashboard</a>
             </div>');
    }
}

function current_user_id() { return $_SESSION['user_id'] ?? null; }
function current_role()    { return $_SESSION['role'] ?? null; }
function current_name()    { return $_SESSION['full_name'] ?? ''; }

function audit(PDO $pdo, string $action, string $table, ?int $record_id = null) {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_log (user_id, action, table_name, record_id) VALUES (?,?,?,?)"
    );
    $stmt->execute([current_user_id(), $action, $table, $record_id]);
}

function set_flash(string $type, string $message, ?string $title = null) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
        'title' => $title ?? ($type === 'success' ? 'Task Completed Successfully' : ($type === 'danger' ? 'Action Failed' : 'System Notification'))
    ];
}

/**
 * Creates a persistent notification in DB and sets flash toast for immediate popup display.
 */
function create_notification(PDO $pdo, string $title, string $message, string $type = 'success', ?string $link = null, ?int $target_user_id = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, created_by) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$target_user_id, $title, $message, $type, $link, current_user_id()]);
    } catch (Exception $e) {
        // Graceful fallback
    }
    set_flash($type, $message, $title);
}

/**
 * Sri Lankan NIC Validation Helper
 * Old format: 9 digits + V/X
 * New format: 12 digits
 */
function validate_sri_lankan_nic(?string $nic): bool {
    if ($nic === null) return false;
    $nic = strtoupper(trim($nic));
    if ($nic === '') return false;
    if (!preg_match('/^([0-9]{9}[VX]|[0-9]{12})$/', $nic)) {
        return false;
    }
    // Validate day of year range (1-366 for male, 501-866 for female)
    $days = (strlen($nic) === 10) ? (int)substr($nic, 2, 3) : (int)substr($nic, 4, 3);
    if ($days > 500) {
        $days -= 500;
    }
    return ($days >= 1 && $days <= 366);
}

/**
 * Sri Lankan Phone Validation Helper
 * Requirements:
 *  - Starting with 0: exactly 10 digits (e.g. 0712345678, 0112345678)
 *  - Starting with +94: exactly 11 digits (e.g. +94712345678, where 94 + 9 digits = 11 digits)
 *  - Starting with 94: exactly 11 digits (e.g. 94712345678)
 */
function get_phone_validation_error(?string $phone): ?string {
    if ($phone === null || trim($phone) === '') {
        return 'Phone number is required.';
    }
    $clean = preg_replace('/[\s\-\(\)\.]/', '', trim($phone));
    if ($clean === '') {
        return 'Phone number is required.';
    }

    if (str_starts_with($clean, '0')) {
        if (!preg_match('/^0[0-9]{9}$/', $clean)) {
            return 'Phone number starting with 0 must have exactly 10 digits (e.g. 0712345678).';
        }
        return null;
    }

    if (str_starts_with($clean, '+94')) {
        if (!preg_match('/^\+94[0-9]{9}$/', $clean)) {
            return 'Phone number starting with +94 must have 11 digits (e.g. +94712345678).';
        }
        return null;
    }

    if (str_starts_with($clean, '94')) {
        if (!preg_match('/^94[0-9]{9}$/', $clean)) {
            return 'Phone number starting with 94 must have 11 digits (e.g. 94712345678).';
        }
        return null;
    }

    return 'Phone number must start with 0 (10 digits) or +94 (11 digits).';
}

function validate_phone_number(?string $phone): bool {
    return get_phone_validation_error($phone) === null;
}

/**
 * Normalize phone number (removes spaces, hyphens, brackets)
 */
function normalize_phone_number(?string $phone): string {
    if ($phone === null) return '';
    return preg_replace('/[\s\-\(\)\.]/', '', trim($phone));
}

/**
 * Validates Email Address with strict format check
 */
function validate_email(?string $email): bool {
    if ($email === null) return false;
    $email = trim($email);
    if ($email === '') return false;
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL) && (bool)preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
}

/**
 * Parses Sri Lankan NIC details (DOB, Gender, Format)
 */
function parse_sri_lankan_nic(?string $nic): ?array {
    if (!validate_sri_lankan_nic($nic)) return null;
    $nic = strtoupper(trim($nic));

    $year = '';
    $days = 0;
    if (strlen($nic) === 10) {
        $year = '19' . substr($nic, 0, 2);
        $days = (int)substr($nic, 2, 3);
    } else {
        $year = substr($nic, 0, 4);
        $days = (int)substr($nic, 4, 3);
    }

    $gender = 'Male';
    if ($days > 500) {
        $gender = 'Female';
        $days -= 500;
    }

    if ($days < 1 || $days > 366) return null;

    $dateObj = DateTime::createFromFormat('Y z', "$year " . ($days - 1));
    $dob = $dateObj ? $dateObj->format('Y-m-d') : null;

    return [
        'nic' => $nic,
        'format' => strlen($nic) === 10 ? 'Old (9 Digits + V/X)' : 'New (12 Digits)',
        'gender' => $gender,
        'dob' => $dob
    ];
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}
