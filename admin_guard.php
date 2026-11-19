<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

if (!isset($_SESSION['uzytkownik_id']) || ($_SESSION['rola'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$stmtGuard = $pdo->prepare("SELECT 1 FROM uzytkownicy WHERE id = ? AND rola = 'admin' AND aktywny = 1");
$stmtGuard->execute([$_SESSION['uzytkownik_id']]);
if (!$stmtGuard->fetchColumn()) {
    http_response_code(403);
    exit('Brak dostępu.');
}

function admin_csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function admin_csrf_ok() {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function admin_flash($typ, $tekst) {
    $_SESSION['flash'] = ['typ' => $typ, 'tekst' => $tekst];
}

function admin_flash_show() {
    if (empty($_SESSION['flash'])) {
        return;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $typ = $f['typ'] === 'danger' ? 'danger' : 'success';
    echo '<div class="alert alert-' . $typ . ' alert-dismissible fade show" role="alert">'
        . htmlspecialchars($f['tekst'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}