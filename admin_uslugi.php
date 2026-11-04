<?php
require_once 'admin_guard.php';

function blad_walidacji_uslugi($pdo, $nazwa, $kategoria_id, $cena, $czas, $opis) {
    if ($nazwa === '' || mb_strlen($nazwa) > 100) {
        return 'Nazwa usługi jest wymagana i może mieć maksymalnie 100 znaków.';
    }
    $kat = $pdo->prepare('SELECT id FROM kategorie_uslug WHERE id = ?');
    $kat->execute([$kategoria_id]);
    if (!$kat->fetch()) {
        return 'Wybrana kategoria nie istnieje.';
    }
    if ($cena === false || $cena <= 0 || $cena > 99999.99) {
        return 'Cena musi być liczbą większą od zera i nie większą niż 99999,99.';
    }
    if ($czas === false || $czas < 5 || $czas > 480) {
        return 'Czas trwania musi być liczbą całkowitą od 5 do 480 minut.';
    }
    if (mb_strlen($opis) > 1000) {
        return 'Opis może mieć maksymalnie 1000 znaków.';
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cel = 'admin_uslugi.php' . ($_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');

    if (!admin_csrf_ok()) {
        admin_flash('danger', 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.');
        header('Location: ' . $cel);
        exit;
    }

    $akcja = $_POST['akcja'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    try {
        if ($akcja === 'dodaj' || $akcja === 'edytuj') {
            $nazwa = trim($_POST['nazwa'] ?? '');
            $kategoria_id = (int)($_POST['kategoria_id'] ?? 0);
            $cena = filter_var(str_replace(',', '.', trim($_POST['cena'] ?? '')), FILTER_VALIDATE_FLOAT);
            $czas = filter_var(trim($_POST['czas_trwania'] ?? ''), FILTER_VALIDATE_INT);
            $opis = trim($_POST['opis'] ?? '');
            $blad = blad_walidacji_uslugi($pdo, $nazwa, $kategoria_id, $cena, $czas, $opis);

            if ($blad !== '') {
                admin_flash('danger', $blad);
            } elseif ($akcja === 'dodaj') {
                $pdo->prepare('INSERT INTO uslugi (kategoria_id, nazwa, opis, czas_trwania, cena, aktywna) VALUES (?, ?, ?, ?, ?, 1)')
                    ->execute([$kategoria_id, $nazwa, $opis, $czas, round($cena, 2)]);
                admin_flash('success', 'Usługa została dodana.');
            } else {
                $pdo->prepare('UPDATE uslugi SET kategoria_id = ?, nazwa = ?, opis = ?, czas_trwania = ?, cena = ? WHERE id = ?')
                    ->execute([$kategoria_id, $nazwa, $opis, $czas, round($cena, 2), $id]);
                admin_flash('success', 'Usługa została zaktualizowana.');
            }
        } elseif ($akcja === 'stan') {
            $stan = ($_POST['stan'] ?? '') === '1' ? 1 : 0;
            $pdo->prepare('UPDATE uslugi SET aktywna = ? WHERE id = ?')->execute([$stan, $id]);
            admin_flash('success', $stan ? 'Usługa została aktywowana.' : 'Usługa została dezaktywowana i nie jest widoczna dla klientów.');
        } elseif ($akcja === 'usun') {
            $rez = $pdo->prepare('SELECT COUNT(*) FROM rezerwacje WHERE usluga_id = ?');
            $rez->execute([$id]);
            if ($rez->fetchColumn() > 0) {
                admin_flash('danger', 'Nie można usunąć usługi, która ma rezerwacje. Zdezaktywuj ją, a zniknie z oferty dla klientów.');
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM pracownicy_uslugi WHERE usluga_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM uslugi WHERE id = ?')->execute([$id]);
                $pdo->commit();
                admin_flash('success', 'Usługa została usunięta.');
            }
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        admin_flash('danger', 'Operacja nie powiodła się. Spróbuj ponownie.');
    }

    header('Location: ' . $cel);
    exit;
}

$kategorie = $pdo->query('SELECT id, nazwa FROM kategorie_uslug ORDER BY nazwa')->fetchAll();

$q = trim($_GET['q'] ?? '');
$kat = (int)($_GET['kat'] ?? 0);
$strona = max(1, (int)($_GET['strona'] ?? 1));
$naStrone = 10;

$where = [];
$parametry = [];
if ($q !== '') {
    $where[] = '(u.nazwa LIKE ? OR u.opis LIKE ?)';
    $parametry[] = '%' . $q . '%';
    $parametry[] = '%' . $q . '%';
}
if ($kat > 0) {
    $where[] = 'u.kategoria_id = ?';
    $parametry[] = $kat;
}
$warunek = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$licznik = $pdo->prepare("SELECT COUNT(*) FROM uslugi u $warunek");
$licznik->execute($parametry);
$razem = (int)$licznik->fetchColumn();
$stron = max(1, (int)ceil($razem / $naStrone));
$strona = min($strona, $stron);
$offset = ($strona - 1) * $naStrone;

$lista = $pdo->prepare("SELECT u.*, k.nazwa AS kategoria FROM uslugi u LEFT JOIN kategorie_uslug k ON u.kategoria_id = k.id $warunek ORDER BY u.id DESC LIMIT $naStrone OFFSET $offset");
$lista->execute($parametry);
$uslugi = $lista->fetchAll();

$qs = $_SERVER['QUERY_STRING'] !== '' ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '';

require_once 'header.php';
?>

<div class="container my-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">Katalog usług</h2>
        <div class="d-flex gap-2">
            <a href="panel_admin.php" class="btn btn-outline-secondary btn-sm">← Panel</a>
            <a href="admin_kategorie.php" class="btn btn-outline-primary btn-sm">Kategorie</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addServiceForm">+ Dodaj usługę</button>
        </div>
    </div>

    <?php admin_flash_show(); ?>

    <?php if (!$kategorie): ?>
        <div class="alert alert-warning">Najpierw dodaj przynajmniej jedną <a href="admin_kategorie.php">kategorię</a>.</div>
    <?php endif; ?>

    <div class="collapse mb-4" id="addServiceForm">
        <div class="card card-body bg-light">
            <h5>Nowa usługa</h5>
            <form method="post" action="admin_uslugi.php<?= $qs ?>">
                <input type="hidden" name="csrf" value="<?= admin_csrf_token() ?>">
                <input type="hidden" name="akcja" value="dodaj">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Nazwa usługi</label>
                        <input type="text" name="nazwa" class="form-control" maxlength="100" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Kategoria</label>
                        <select name="kategoria_id" class="form-select" required>
                            <?php foreach ($kategorie as $k): ?>
                                <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nazwa']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Cena (zł)</label>
                        <input type="number" step="0.01" min="0.01" name="cena" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Czas trwania (min)</label>
                        <input type="number" min="5" max="480" name="czas_trwania" class="form-control" value="30" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Opis usługi</label>
                    <textarea name="opis" class="form-control" rows="2" maxlength="1000"></textarea>
                </div>
                <button type="submit" class="btn btn-success btn-sm">Zapisz usługę</button>
            </form>
        </div>
    </div>

    <form method="get" action="admin_uslugi.php" class="row g-2 mb-3">
        <div class="col-md-5">
            <input type="text" name="q" class="form-control" placeholder="Szukaj w nazwie lub opisie..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-md-4">
            <select name="kat" class="form-select">
                <option value="0">Wszystkie kategorie</option>
                <?php foreach ($kategorie as $k): ?>
                    <option value="<?= (int)$k['id'] ?>" <?= $kat === (int)$k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nazwa']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-dark btn-sm flex-fill">Szukaj</button>
            <a href="admin_uslugi.php" class="btn btn-outline-secondary btn-sm flex-fill">Wyczyść</a>
        </div>
    </form>

    <p class="text-muted small">Znaleziono usług: <?= $razem ?></p>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Nazwa usługi</th><th>Kategoria</th><th>Czas</th><th>Cena</th><th>Status</th><th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$uslugi): ?>
                    <tr><td colspan="7" class="text-center text-muted">Brak usług do wyświetlenia.</td></tr>
                <?php endif; ?>
                <?php foreach ($uslugi as $s): ?>
                    <tr>
                        <td><?= (int)$s['id'] ?></td>
                        <td><strong><?= htmlspecialchars($s['nazwa']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($s['opis'] ?? '') ?></small></td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($s['kategoria'] ?? 'Brak') ?></span></td>
                        <td><?= (int)$s['czas_trwania'] ?> min</td>
                        <td><?= number_format((float)$s['cena'], 2, ',', ' ') ?> zł</td>
                        <td>
                            <?= $s['aktywna'] ? '<span class="badge bg-success">Aktywna</span>' : '<span class="badge bg-secondary">Nieaktywna</span>' ?>
                        </td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editServiceModal<?= (int)$s['id'] ?>">Edytuj</button>
                            <form method="post" action="admin_uslugi.php<?= $qs ?>" class="d-inline">
                                <input type="hidden" name="csrf" value="<?= admin_csrf_token() ?>">
                                <input type="hidden" name="akcja" value="stan">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <input type="hidden" name="stan" value="<?= $s['aktywna'] ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-sm <?= $s['aktywna'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"><?= $s['aktywna'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
                            </form>
                            <form method="post" action="admin_uslugi.php<?= $qs ?>" class="d-inline" onsubmit="return confirm('Usunąć tę usługę?')">
                                <input type="hidden" name="csrf" value="<?= admin_csrf_token() ?>">
                                <input type="hidden" name="akcja" value="usun">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Usuń</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($stron > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $stron; $i++): ?>
                    <li class="page-item <?= $i === $strona ? 'active' : '' ?>">
                        <a class="page-link" href="admin_uslugi.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'kat' => $kat, 'strona' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <?php foreach ($uslugi as $s): ?>
        <div class="modal fade" id="editServiceModal<?= (int)$s['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="admin_uslugi.php<?= $qs ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Edycja usługi #<?= (int)$s['id'] ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf" value="<?= admin_csrf_token() ?>">
                            <input type="hidden" name="akcja" value="edytuj">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label">Nazwa</label>
                                <input type="text" name="nazwa" class="form-control" maxlength="100" value="<?= htmlspecialchars($s['nazwa']) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Kategoria</label>
                                <select name="kategoria_id" class="form-select" required>
                                    <?php foreach ($kategorie as $k): ?>
                                        <option value="<?= (int)$k['id'] ?>" <?= (int)$s['kategoria_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nazwa']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Cena (zł)</label>
                                    <input type="number" step="0.01" min="0.01" name="cena" class="form-control" value="<?= htmlspecialchars($s['cena']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Czas (min)</label>
                                    <input type="number" min="5" max="480" name="czas_trwania" class="form-control" value="<?= (int)$s['czas_trwania'] ?>" required>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Opis</label>
                                <textarea name="opis" class="form-control" rows="2" maxlength="1000"><?= htmlspecialchars($s['opis'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                            <button type="submit" class="btn btn-primary btn-sm">Zapisz zmiany</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once 'footer.php'; ?>