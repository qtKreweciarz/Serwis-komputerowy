<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function wymagaj_logowania() {
    if (!isset($_SESSION['uzytkownik_id'])) {
        header('Location: login.php');
        exit;
    }
}

function wymagaj_roli($rola) {
    wymagaj_logowania();
    $dozwolone = (array) $rola;
    if (!in_array($_SESSION['rola'] ?? '', $dozwolone, true)) {
        odmowa_dostepu();
    }
}

function odmowa_dostepu() {
    http_response_code(403);
    require_once __DIR__ . '/header.php';
    echo '<h1>Brak dostępu</h1>';
    echo '<p>Nie masz uprawnień do tej strony.</p>';
    echo '<p><a href="index.php">Wróć na stronę główną</a></p>';
    require_once __DIR__ . '/footer.php';
    exit;
}
