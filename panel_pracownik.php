<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// Kontrola dostępu: tylko zalogowany użytkownik z rolą 'pracownik'
if (!isset($_SESSION['uzytkownik_id'])) {
    header('Location: login.php');
    exit();
}

$uzytkownikId = (int)$_SESSION['uzytkownik_id'];

$stmtRola = $pdo->prepare('SELECT rola, aktywny FROM uzytkownicy WHERE id = ? LIMIT 1');
$stmtRola->execute([$uzytkownikId]);
$konto = $stmtRola->fetch(PDO::FETCH_ASSOC);

if (!$konto || (int)$konto['aktywny'] !== 1 || $konto['rola'] !== 'pracownik') {
    header('Location: index.php');
    exit();
}

// Pobranie powiązanego profilu pracownika
$stmtPrac = $pdo->prepare('SELECT id, opis, aktywny FROM pracownicy WHERE uzytkownik_id = ? LIMIT 1');
$stmtPrac->execute([$uzytkownikId]);
$pracownik = $stmtPrac->fetch(PDO::FETCH_ASSOC);

if (!$pracownik || (int)$pracownik['aktywny'] !== 1) {
    require_once 'header.php';
    echo '<div class="container my-5"><div class="alert alert-warning">Twoje konto pracownika nie jest powiązane z aktywnym profilem serwisowym. Skontaktuj się z administratorem.</div></div>';
    require_once 'footer.php';
    exit();
}

$pracownikId = (int)$pracownik['id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$dozwoloneStatusy = ['oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana'];
$etykietyStatusow = [
    'oczekujaca'   => ['tekst' => 'Oczekująca',   'klasa' => 'bg-warning text-dark'],
    'potwierdzona' => ['tekst' => 'Potwierdzona', 'klasa' => 'bg-primary'],
    'zrealizowana' => ['tekst' => 'Zrealizowana', 'klasa' => 'bg-success'],
    'anulowana'    => ['tekst' => 'Anulowana',    'klasa' => 'bg-secondary'],
];

$errors = [];
$success = '';

// Obsługa zmiany statusu wizyty należącej wyłącznie do zalogowanego pracownika
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zmien_status'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Błąd bezpieczeństwa: Nieprawidłowy token CSRF.';
    } else {
        $rezerwacjaId = filter_input(INPUT_POST, 'rezerwacja_id', FILTER_VALIDATE_INT);
        $nowyStatus = $_POST['nowy_status'] ?? '';

        if (!$rezerwacjaId || !in_array($nowyStatus, $dozwoloneStatusy, true)) {
            $errors[] = 'Nieprawidłowe dane żądania zmiany statusu.';
        } else {
            try {
                $pdo->beginTransaction();

                // Blokada wiersza i weryfikacja, czy wizyta należy do tego pracownika
                $stmtRez = $pdo->prepare('
                    SELECT id, status
                    FROM rezerwacje
                    WHERE id = ? AND pracownik_id = ?
                    FOR UPDATE
                ');
                $stmtRez->execute([$rezerwacjaId, $pracownikId]);
                $rezerwacja = $stmtRez->fetch(PDO::FETCH_ASSOC);

                if (!$rezerwacja) {
                    $pdo->rollBack();
                    $errors[] = 'Nie znaleziono rezerwacji lub nie masz uprawnień do zarządzania tą wizytą.';
                } elseif ($rezerwacja['status'] === $nowyStatus) {
                    $pdo->rollBack();
                    $success = 'Rezerwacja posiada już wybrany status.';
                } else {
                    $statusPoprzedni = $rezerwacja['status'];

                    $upd = $pdo->prepare('UPDATE rezerwacje SET status = ? WHERE id = ? AND pracownik_id = ?');
                    $upd->execute([$nowyStatus, $rezerwacjaId, $pracownikId]);

                    $hist = $pdo->prepare('
                        INSERT INTO historia_statusow_rezerwacji
                            (rezerwacja_id, status_poprzedni, status_nowy, zmienione_przez)
                        VALUES (?, ?, ?, ?)
                    ');
                    $hist->execute([$rezerwacjaId, $statusPoprzedni, $nowyStatus, $uzytkownikId]);

                    $pdo->commit();
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $success = 'Zaktualizowano status rezerwacji #' . $rezerwacjaId . ' na: ' . $etykietyStatusow[$nowyStatus]['tekst'] . '.';
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Wystąpił błąd podczas zapisu zmiany statusu.';
            }
        }
    }
}

// Filtrowanie wizyt po statusie (opcjonalne)
$filtrStatus = $_GET['status'] ?? 'wszystkie';
$sqlWizyty = '
    SELECT
        r.id,
        r.data_rezerwacji,
        r.godzina_od,
        r.godzina_do,
        r.status,
        r.komentarz,
        u.imie AS klient_imie,
        u.nazwisko AS klient_nazwisko,
        u.email AS klient_email,
        u.telefon AS klient_telefon,
        s.nazwa AS usluga_nazwa,
        s.czas_trwania,
        s.cena
    FROM rezerwacje r
    INNER JOIN uzytkownicy u ON r.klient_id = u.id
    INNER JOIN uslugi s ON r.usluga_id = s.id
    WHERE r.pracownik_id = ?
';
$params = [$pracownikId];

if (in_array($filtrStatus, $dozwoloneStatusy, true)) {
    $sqlWizyty .= ' AND r.status = ?';
    $params[] = $filtrStatus;
} else {
    $filtrStatus = 'wszystkie';
}

$sqlWizyty .= ' ORDER BY r.data_rezerwacji ASC, r.godzina_od ASC';

$stmtWizyty = $pdo->prepare($sqlWizyty);
$stmtWizyty->execute($params);
$wizyty = $stmtWizyty->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<div class="container my-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1">Panel pracownika</h2>
            <p class="text-muted mb-0">
                <?= htmlspecialchars(($_SESSION['imie'] ?? '') . ' ' . ($_SESSION['nazwisko'] ?? '')) ?>
                <?php if (!empty($pracownik['opis'])): ?>
                    — <?= htmlspecialchars($pracownik['opis']) ?>
                <?php endif; ?>
            </p>
        </div>

        <form method="get" action="panel_pracownik.php" class="d-flex align-items-center gap-2">
            <label for="status" class="form-label mb-0 fw-semibold">Status:</label>
            <select name="status" id="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value="wszystkie" <?= $filtrStatus === 'wszystkie' ? 'selected' : '' ?>>Wszystkie</option>
                <?php foreach ($dozwoloneStatusy as $st): ?>
                    <option value="<?= $st ?>" <?= $filtrStatus === $st ? 'selected' : '' ?>>
                        <?= htmlspecialchars($etykietyStatusow[$st]['tekst']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Termin</th>
                    <th>Klient i kontakt</th>
                    <th>Usługa</th>
                    <th>Cena</th>
                    <th>Status</th>
                    <th>Zmiana statusu</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($wizyty)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            Brak przypisanych rezerwacji dla wybranego filtra.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($wizyty as $w): ?>
                        <?php
                            $stKey = $w['status'];
                            $badge = $etykietyStatusow[$stKey] ?? ['tekst' => $stKey, 'klasa' => 'bg-secondary'];
                        ?>
                        <tr>
                            <td class="fw-bold">#<?= (int)$w['id'] ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($w['data_rezerwacji']) ?></div>
                                <small class="text-muted">
                                    <?= htmlspecialchars(substr($w['godzina_od'], 0, 5)) ?> – <?= htmlspecialchars(substr($w['godzina_do'], 0, 5)) ?>
                                </small>
                            </td>
                            <td>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($w['klient_imie'] . ' ' . $w['klient_nazwisko']) ?>
                                </div>
                                <small class="text-muted d-block"><?= htmlspecialchars($w['klient_email']) ?></small>
                                <?php if (!empty($w['klient_telefon'])): ?>
                                    <small class="text-muted d-block">Tel.: <?= htmlspecialchars($w['klient_telefon']) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($w['komentarz'])): ?>
                                    <small class="text-info d-block mt-1">Uwagi: <?= htmlspecialchars($w['komentarz']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($w['usluga_nazwa']) ?></div>
                                <small class="text-muted"><?= (int)$w['czas_trwania'] ?> min</small>
                            </td>
                            <td class="fw-semibold">
                                <?= number_format((float)$w['cena'], 2, ',', ' ') ?> zł
                            </td>
                            <td>
                                <span class="badge <?= $badge['klasa'] ?>">
                                    <?= htmlspecialchars($badge['tekst']) ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" action="panel_pracownik.php<?= $filtrStatus !== 'wszystkie' ? '?status=' . urlencode($filtrStatus) : '' ?>" class="d-flex flex-wrap gap-1">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="rezerwacja_id" value="<?= (int)$w['id'] ?>">

                                    <?php if ($stKey !== 'potwierdzona' && $stKey !== 'zrealizowana'): ?>
                                        <button type="submit" name="nowy_status" value="potwierdzona" class="btn btn-sm btn-outline-primary" title="Potwierdź wizytę">
                                            Potwierdź
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($stKey !== 'zrealizowana' && $stKey !== 'anulowana'): ?>
                                        <button type="submit" name="nowy_status" value="zrealizowana" class="btn btn-sm btn-outline-success" title="Oznacz jako zrealizowaną">
                                            Zrealizowana
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($stKey !== 'anulowana' && $stKey !== 'zrealizowana'): ?>
                                        <button type="submit" name="nowy_status" value="anulowana" class="btn btn-sm btn-outline-danger" title="Anuluj wizytę">
                                            Anuluj
                                        </button>
                                    <?php endif; ?>

                                    <button type="submit" name="zmien_status" value="1" class="d-none" aria-hidden="true"></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>