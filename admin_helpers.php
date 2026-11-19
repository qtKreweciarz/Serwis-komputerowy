<?php
/**
 * Wspólne funkcje panelu administratora (Etap 3).
 * Dołączaj na początku każdej strony admina:  require_once 'admin_helpers.php';
 */
require_once __DIR__ . '/auth.php';   
require_once __DIR__ . '/db.php';    

function adm_wymagaj_admina(): void
{
    global $pdo;

    wymagaj_roli('admin');

    $stmt = $pdo->prepare('SELECT rola, aktywny FROM uzytkownicy WHERE id = ?');
    $stmt->execute([(int)$_SESSION['uzytkownik_id']]);
    $u = $stmt->fetch();

    if (!$u || $u['rola'] !== 'admin' || (int)$u['aktywny'] !== 1) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

/** Bezpieczne wypisywanie tekstu w HTML (ochrona przed XSS). */
function adm_h($tekst): string
{
    return htmlspecialchars((string)$tekst, ENT_QUOTES, 'UTF-8');
}

/* ---------- CSRF ---------- */

function adm_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Ukryte pole do wstawienia w każdym formularzu POST. */
function adm_csrf_pole(): string
{
    return '<input type="hidden" name="csrf_token" value="' . adm_h(adm_csrf_token()) . '">';
}

/** Sprawdza token z formularza. */
function adm_csrf_ok(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/* ---------- Komunikaty (flash) ---------- */

/** $typ: success | danger | warning | info */
function adm_flash(string $typ, string $tekst): void
{
    $_SESSION['adm_flash'][] = ['typ' => $typ, 'tekst' => $tekst];
}

/** Wyświetla i czyści zapisane komunikaty. */
function adm_flash_wypisz(): void
{
    if (empty($_SESSION['adm_flash'])) {
        return;
    }
    foreach ($_SESSION['adm_flash'] as $f) {
        echo '<div class="alert alert-' . adm_h($f['typ']) . ' alert-dismissible fade show" role="alert">'
           . adm_h($f['tekst'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['adm_flash']);
}

function adm_przekieruj(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Log administratora ---------- */

/** Zapisuje operację w tabeli log_administracyjny. Błąd logu nie przerywa operacji. */
function adm_log(PDO $pdo, string $akcja, string $opis): void
{
    try {
        $pdo->prepare('INSERT INTO log_administracyjny (admin_id, akcja, opis) VALUES (?, ?, ?)')
            ->execute([(int)$_SESSION['uzytkownik_id'], $akcja, $opis]);
    } catch (PDOException $e) {
        error_log('adm_log: ' . $e->getMessage());
    }
}

/* ---------- Pomocnicze ---------- */

/** Obcina nadmiarowe białe znaki ("  Wymiana   dysku " -> "Wymiana dysku"). */
function adm_tekst($wartosc): string
{
    if (!is_string($wartosc)) {
        return '';
    }
    return trim(preg_replace('/\s+/u', ' ', $wartosc));
}

/** Sprawdza, czy tekst jest poprawną datą w formacie RRRR-MM-DD. */
function adm_data_ok($tekst): bool
{
    if (!is_string($tekst)) {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $tekst);
    return $d !== false && $d->format('Y-m-d') === $tekst;
}

function adm_zl($kwota): string
{
    return number_format((float)$kwota, 2, ',', ' ') . ' zł';
}
