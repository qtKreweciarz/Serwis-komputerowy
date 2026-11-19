<?php
require_once 'auth.php';
wymagaj_roli('klient');
require_once 'db.php';

$klient_id = $_SESSION['uzytkownik_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcja']) && $_POST['akcja'] === 'anuluj') {
    $rezerwacja_id = (int)($_POST['rezerwacja_id'] ?? 0);
    
    $stmtCheck = $pdo->prepare("SELECT * FROM rezerwacje WHERE id = ? AND klient_id = ?");
    $stmtCheck->execute([$rezerwacja_id, $klient_id]);
    $rez = $stmtCheck->fetch();

    if ($rez) {
        $stmtUp = $pdo->prepare("UPDATE rezerwacje SET status = 'anulowana' WHERE id = ?");
        $stmtUp->execute([$rezerwacja_id]);
        $_SESSION['flash'] = ['typ' => 'success', 'tekst' => 'Rezerwacja została pomyślnie anulowana.'];
    } else {
        $_SESSION['flash'] = ['typ' => 'danger', 'tekst' => 'Nie masz uprawnień do anulowania tej rezerwacji.'];
    }
    
    header('Location: panel_klient.php');
    exit;
}

$stmtRez = $pdo->prepare("
    SELECT r.*, u.nazwa AS usluga_nazwa, u.cena, 
           pu.imie AS pracownik_imie, pu.nazwisko AS pracownik_nazwisko
    FROM rezerwacje r
    JOIN uslugi u ON r.usluga_id = u.id
    JOIN pracownicy p ON r.pracownik_id = p.id
    JOIN uzytkownicy pu ON p.uzytkownik_id = pu.id
    WHERE r.klient_id = ?
    ORDER BY r.data_rezerwacji DESC, r.godzina_od DESC
");
$stmtRez->execute([$klient_id]);
$rezerwacje = $stmtRez->fetchAll();

require_once 'header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Panel klienta</h2>
            <p class="mb-0">Zalogowano jako: <?= htmlspecialchars($_SESSION['imie'] ?? '') ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="rezerwuj.php" class="btn btn-primary">+ Zarezerwuj wizytę</a>
            <a href="wyloguj.php" class="btn btn-outline-secondary">Wyloguj</a>
        </div>
    </div>

    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= $_SESSION['flash']['typ'] ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash']['tekst']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Usługa</th>
                    <th>Pracownik</th>
                    <th>Termin</th>
                    <th>Cena</th>
                    <th>Status</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rezerwacje)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nie masz jeszcze żadnych rezerwacji.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rezerwacje as $r): ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><strong><?= htmlspecialchars($r['usluga_nazwa']) ?></strong></td>
                            <td><?= htmlspecialchars($r['pracownik_imie'] . ' ' . $r['pracownik_nazwisko']) ?></td>
                            <td><?= htmlspecialchars($r['data_rezerwacji']) ?> <br><small class="text-muted"><?= htmlspecialchars(substr($r['godzina_od'], 0, 5)) ?> - <?= htmlspecialchars(substr($r['godzina_do'], 0, 5)) ?></small></td>
                            <td><?= number_format((float)$r['cena'], 2, ',', ' ') ?> zł</td>
                            <td>
                                <?php 
                                    $badge = 'secondary';
                                    if ($r['status'] === 'potwierdzona') $badge = 'success';
                                    elseif ($r['status'] === 'oczekujaca') $badge = 'warning text-dark';
                                    elseif ($r['status'] === 'anulowana') $badge = 'danger';
                                    elseif ($r['status'] === 'zrealizowana') $badge = 'info';
                                ?>
                                <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'oczekujaca' || $r['status'] === 'potwierdzona'): ?>
                                    <form action="panel_klient.php" method="POST" onsubmit="return confirm('Czy na pewno chcesz anulować tę rezerwację?');" class="d-inline">
                                        <input type="hidden" name="akcja" value="anuluj">
                                        <input type="hidden" name="rezerwacja_id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Anuluj</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">Brak akcji</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>