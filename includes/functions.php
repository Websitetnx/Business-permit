<?php
declare(strict_types=1);

function app_config(?string $key = null): mixed
{
    static $config = null;
    $config ??= require dirname(__DIR__) . '/config.php';
    return $key === null ? $config : ($config[$key] ?? null);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (str_ends_with($directory, '/admin')) {
        $directory = dirname($directory);
    }
    return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function posted_geolocation(array $source): array
{
    $latitudeRaw = trim((string) ($source['latitude'] ?? ''));
    $longitudeRaw = trim((string) ($source['longitude'] ?? ''));
    $accuracyRaw = trim((string) ($source['location_accuracy_m'] ?? ''));

    if ($latitudeRaw === '' && $longitudeRaw === '') {
        return ['latitude' => null, 'longitude' => null, 'accuracy' => null, 'error' => null];
    }
    if (!is_numeric($latitudeRaw) || !is_numeric($longitudeRaw)) {
        return ['latitude' => null, 'longitude' => null, 'accuracy' => null, 'error' => 'Capture the business location again or leave it blank.'];
    }

    $latitude = (float) $latitudeRaw;
    $longitude = (float) $longitudeRaw;
    $accuracy = $accuracyRaw === '' ? null : (is_numeric($accuracyRaw) ? (float) $accuracyRaw : -1.0);
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 || ($accuracy !== null && ($accuracy < 0 || $accuracy > 100000))) {
        return ['latitude' => null, 'longitude' => null, 'accuracy' => null, 'error' => 'The captured business location is invalid. Please capture it again.'];
    }

    return ['latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => $accuracy, 'error' => null];
}

function openstreetmap_url(mixed $latitude, mixed $longitude): string
{
    $lat = number_format((float) $latitude, 7, '.', '');
    $lng = number_format((float) $longitude, 7, '.', '');
    return 'https://www.openstreetmap.org/?mlat=' . rawurlencode($lat) . '&mlon=' . rawurlencode($lng) . '#map=18/' . rawurlencode($lat) . '/' . rawurlencode($lng);
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('The form expired. Please go back, refresh the page, and try again.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

function require_guest(): void
{
    if (current_user()) {
        redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
    }
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
    return $user;
}

function status_class(string $status): string
{
    return match ($status) {
        'Approved' => 'approved',
        'Needs Revision' => 'revision',
        'Released' => 'released',
        default => 'review',
    };
}

function application_stage(string $status): int
{
    return match ($status) {
        'Approved' => 3,
        'Released' => 4,
        default => 2,
    };
}

function create_reference(PDO $pdo): string
{
    do {
        $reference = 'BPL-' . date('Y') . '-' . random_int(10000, 99999);
        $statement = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE reference = ?');
        $statement->execute([$reference]);
    } while ((int) $statement->fetchColumn() > 0);
    return $reference;
}

function create_permit_number(PDO $pdo): string
{
    do {
        $number = 'BP-' . date('Y') . '-' . random_int(10000, 99999);
        $statement = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE permit_number = ?');
        $statement->execute([$number]);
    } while ((int) $statement->fetchColumn() > 0);
    return $number;
}

function document_definitions(): array
{
    return [
        'registration_doc' => ['DTI / SEC / CDA Registration', true, 'Required'],
        'bfp_application_doc' => ['BFP Application Form', true, 'Required'],
        'bfp_questionnaire_doc' => ['BFP Questionnaire', true, 'Required'],
        'consent_form_doc' => ['Consent Form', true, 'Required'],
        'lease_contract_doc' => ['Lease Contract for Private Building', false, 'If renting'],
        'fsic_occupancy_doc' => ['FSIC of Occupancy Valid for 9 Months', false, 'If applicable'],
        'occupancy_doc' => ['Occupancy Permit', false, 'Provide one option'],
        'tax_declaration_doc' => ['Tax Declaration — Current Year', false, 'If owner'],
        'health_results_doc' => ['X-Ray Result and Stool Examination', false, 'CHO sanitary'],
        'nga_clearance_doc' => ['NGA Clearance', false, 'Regulated businesses'],
        'occupancy_affidavit_doc' => ['Affidavit of Undertaking in Absence of Occupancy', false, 'Occupancy alternative'],
        'building_owner_permit_doc' => ["Building Owner's Business Permit", false, 'If renting'],
        'current_fsic_doc' => ['Fire Safety Inspection Certificate (FSIC)', false, 'Current year'],
        'sanitary_permit_doc' => ['Sanitary Permit', false, 'Current year'],
    ];
}

function store_application_documents(PDO $pdo, int $applicationId): array
{
    $config = app_config();
    $definitions = document_definitions();
    $allowedTypes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    $errors = [];
    $prepared = [];

    foreach ($definitions as $field => [$label, $required]) {
        $file = $_FILES[$field] ?? null;
        $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                $errors[] = $label . ' is required.';
            }
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = $label . ' could not be uploaded.';
            continue;
        }
        if ((int) $file['size'] > $config['max_upload_bytes']) {
            $errors[] = $label . ' must be 5 MB or smaller.';
            continue;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowedTypes[$mime])) {
            $errors[] = $label . ' must be a PDF, JPG, or PNG file.';
            continue;
        }
        $prepared[] = [$field, $label, $file, $mime, $allowedTypes[$mime]];
    }

    $hasOccupancy = isset($_FILES['occupancy_doc']) && ($_FILES['occupancy_doc']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    $hasAffidavit = isset($_FILES['occupancy_affidavit_doc']) && ($_FILES['occupancy_affidavit_doc']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    if (!$hasOccupancy && !$hasAffidavit) {
        $errors[] = 'Upload an Occupancy Permit or its Affidavit of Undertaking alternative.';
    }
    if ($errors) {
        return $errors;
    }

    if (!is_dir($config['upload_dir']) && !mkdir($config['upload_dir'], 0750, true) && !is_dir($config['upload_dir'])) {
        return ['The secure upload directory could not be created.'];
    }

    $insert = $pdo->prepare('INSERT INTO application_documents (application_id, document_type, original_name, stored_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
    $movedPaths = [];
    foreach ($prepared as [$field, $label, $file, $mime, $extension]) {
        $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
        $destination = $config['upload_dir'] . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = $label . ' could not be saved.';
            break;
        }
        $movedPaths[] = $destination;
        $insert->execute([$applicationId, $field, basename($file['name']), $storedName, $mime, (int) $file['size']]);
    }

    if ($errors) {
        foreach ($movedPaths as $path) {
            if (is_file($path)) unlink($path);
        }
    }

    return $errors;
}

function record_status(PDO $pdo, int $applicationId, string $status, ?int $changedBy, ?string $notes = null): void
{
    $statement = $pdo->prepare('INSERT INTO application_status_history (application_id, status, notes, changed_by) VALUES (?, ?, ?, ?)');
    $statement->execute([$applicationId, $status, $notes ?: null, $changedBy]);
}

function audit(PDO $pdo, int $userId, string $action, string $entityType, ?int $entityId = null): void
{
    $statement = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)');
    $statement->execute([$userId, $action, $entityType, $entityId, $_SERVER['REMOTE_ADDR'] ?? null]);
}
