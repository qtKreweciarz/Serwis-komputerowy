<?php
/*
 Obsługa formularza rezerwacji (tylko POST, tylko zalogowany klient).
 Pola formularza: csrf_token, usluga_id, pracownik_id, data (RRRR-MM-DD), godzina (GG:MM), komentarz.
 Po zapisie wraca na stronę z komunikatem: sukces w $_SESSION['rez_komunikat'],
 błędy w $_SESSION['rez_bledy'], a wpisane dane w $_SESSION['rez_stare'] (do wypełnienia formularza).
*/
require_once 'auth.php';
wymagaj_roli('klient');
require_once 'db.php';
require_once 'rezerwacje_logika.php';

$dozwolone_powroty = ['rezerwuj.php', 'rezerwuj_test.php', 'panel_klient.php'];
$powrot = in_array($_POST['powrot'] ?? '', $dozwolone_powroty, true) ? $_POST['powrot'] : null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ($powrot ?? 'rezerwuj.php'));
    exit;
}

if (!rez_csrf_ok()) {
    $_SESSION['rez_bledy'] = ['Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.'];
    header('Location: ' . ($powrot ?? 'rezerwuj.php'));
    exit;
}

$wynik = rez_utworz($pdo, (int)$_SESSION['uzytkownik_id'], [
    'usluga_id'    => $_POST['usluga_id'] ?? 0,
    'pracownik_id' => $_POST['pracownik_id'] ?? 0,
    'data'         => $_POST['data'] ?? '',
    'godzina'      => $_POST['godzina'] ?? '',
    'komentarz'    => $_POST['komentarz'] ?? '',
]);

if ($wynik['ok']) {
    unset($_SESSION['rez_bledy'], $_SESSION['rez_stare']);
    $_SESSION['rez_komunikat'] = 'Rezerwacja #' . $wynik['id'] . ' została zapisana: ' . $wynik['usluga'] . ', '
        . date('d.m.Y', strtotime($wynik['data'])) . ' ' . $wynik['od'] . '–' . $wynik['do']
        . ', pracownik: ' . $wynik['pracownik'] . '. Status: oczekująca.';
    header('Location: ' . ($powrot ?? 'panel_klient.php'));
    exit;
}

$_SESSION['rez_bledy'] = $wynik['bledy'];
$_SESSION['rez_stare'] = [
    'usluga_id'    => (int)($_POST['usluga_id'] ?? 0),
    'pracownik_id' => (int)($_POST['pracownik_id'] ?? 0),
    'data'         => is_string($_POST['data'] ?? null) ? $_POST['data'] : '',
    'godzina'      => is_string($_POST['godzina'] ?? null) ? $_POST['godzina'] : '',
    'komentarz'    => is_string($_POST['komentarz'] ?? null) ? $_POST['komentarz'] : '',
];
header('Location: ' . ($powrot ?? 'rezerwuj.php'));
exit;
