<?php
require_once 'admin_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_ok()) {
        admin_flash('danger', 'Nieprawidłowe żądanie. Odśwież stronę i spróbuj ponownie.');
        header('Location: admin_kategorie.php');
        exit;
    }

    $akcja = $_POST['akcja'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $nazwa = trim($_POST['nazwa'] ?? '');
    $opis = trim($_POST['opis'] ?? '');

    try {
        if ($akcja === 'dodaj' || $akcja === 'edytuj') {
            if ($nazwa === '' || mb_strlen($nazwa) > 100) {
                admin_flash('danger', 'Nazwa kategorii jest wymagana i może mieć maksymalnie 100 znaków.');
            } elseif (mb_strlen($opis) > 1000) {
                admin_flash('danger', 'Opis może mieć maksymalnie 1000 znaków.');
            } else {
                $dup = $pdo->prepare('SELECT id FROM kategorie_uslug WHERE nazwa = ? AND id <> ?');
                $dup->execute([$nazwa, $id]);
                if ($dup->fetch()) {
                    admin_flash('danger', 'Kategoria o takiej nazwie już istnieje.');
                } elseif ($akcja === 'dodaj') {
                    $pdo->prepare('INSERT INTO kategorie_uslug (nazwa, opis) VALUES (?, ?)')->execute([$nazwa, $opis]);
                    admin_flash('success', 'Kategoria została dodana.');
                } else {
                    $pdo->prepare('UPDATE kategorie_uslug SET nazwa = ?, opis = ? WHERE id = ?')->execute([$nazwa, $opis, $id]);
                    admin_flash('success', 'Kategoria została zaktualizowana.');
                }
            }
        } elseif ($akcja === 'usun') {
            $ile = $pdo->prepare('SELECT COUNT(*) FROM uslugi WHERE kategoria_id = ?');
            $ile->execute([$id]);
            if ($ile->fetchColumn() > 0) {
                admin_flash('danger', 'Nie można usunąć kategorii, która ma przypisane usługi. Przenieś je do innej kategorii lub usuń.');
            } else {
                $pdo->prepare('DELETE FROM kategorie_uslug WHERE id = ?')->execute([$id]);
                admin_flash('success', 'Kategoria została usunięta.');
            }
        }
    } catch (PDOException $ex) {
        admin_flash('danger', 'Operacja nie powiodła się. Spróbuj ponownie.');
    }

    header('Location: admin_kategorie.php');
    exit;
}

$kategorie = $pdo->query('SELECT k.*, (SELECT COUNT(*) FROM uslugi u WHERE u.kategoria_id = k.id) AS liczba_uslug FROM kategorie_uslug k ORDER BY k.nazwa')->fetchAll();

require_once 'header.php';
?>

<div class="container my-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">Kategorie usług</h2>
        <div class="d-flex gap-2">
            <a href="panel_admin.php" class="btn btn-outline-secondary btn-sm">← Panel</a>
            <a href="admin_uslugi.php" class="btn btn-outline-primary btn-sm">Usługi</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addCategoryForm">+ Dodaj kategorię</button>
        </div>
    </div>

    <?php admin_flash_show(); ?>

    <div class="collapse mb-4" id="addCategoryForm">
        <div class="card card-body bg-light">
            <h5>Nowa kategoria</h5>
            <form method="post" action="admin_kategorie.php">
                <input type="hidden" name="csrf" value="<?= admin_csrf_token() ?>">
                <input type="hidden" name="akcja" value="dodaj">
                <div class="mb-2">
                    <label class="form-label">Nazwa kategorii</label>
                    <input type="text" name="nazwa" class="form-control" maxlength="100" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Opis</label>
                    <textarea name="opis" class="form-control" rows="2" maxlength="1000"></textarea>
                </div>
                <button type="submit" class="btn btn-success btn-sm">Zapisz kategorię</button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Nazwa</th><th>Opis</th><th>Liczba usług</th><th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$kategorie): ?>
                    <tr><td colspan="5" class="text-center text-muted">Brak kategorii.</td></tr>
                <?php endif; ?>
                <?php foreach ($kategorie as $k): ?>
                    <tr>
                        <td><?= (int)$k['id'] ?></td>
                        <td><strong><?= htmlspecialchars($k['nazwa']) ?></strong></td>
                        <td><?= htmlspecialchars($k['opis'] ?? '') ?></td>
                        <td><span class="badge bg-info text-dark"><?= (int)$k['liczba_uslug'] ?></span></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?= (int)$k['id'] ?>">Edytuj</button>
                            <form method="post" action="admin_kategorie.php" class="d-inline" onsubmit="return confirm('Usunąć tę kategorię?')">
                                <input type="hidden" name="csrf" value="<?= admin_csrf_token() ?>">
                                <input type="hidden" name="akcja" value="usun">
                                <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Usuń</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php foreach ($kategorie as $k): ?>
        <div class="modal fade" id="editCategoryModal<?= (int)$k['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="admin_kategorie.php">
                        <div class="modal-header">
                            <h5 class="modal-title">Edycja kategorii #<?= (int)$k['id'] ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf" value="<?= admin_csrf_token() ?>">
                            <input type="hidden" name="akcja" value="edytuj">
                            <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label">Nazwa</label>
                                <input type="text" name="nazwa" class="form-control" maxlength="100" value="<?= htmlspecialchars($k['nazwa']) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Opis</label>
                                <textarea name="opis" class="form-control" rows="3" maxlength="1000"><?= htmlspecialchars($k['opis'] ?? '') ?></textarea>
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