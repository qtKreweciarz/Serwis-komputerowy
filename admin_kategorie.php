<?php
/*
 * Panel administratora – zarządzanie kategoriami usług (CRUD).
 */
require_once 'admin_helpers.php';
adm_wymagaj_admina();

$bledy = [];          
$stare = [];          
$otworz_modal = false;

/* ===================== OBSŁUGA FORMULARZY (POST) ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!adm_csrf_ok()) {
        adm_flash('danger', 'Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.');
        adm_przekieruj('admin_kategorie.php');
    }

    $akcja = $_POST['akcja'] ?? '';
    $id    = (int)($_POST['id'] ?? 0);

    try {
        /* ---------- DODAWANIE / EDYCJA ---------- */
        if ($akcja === 'dodaj' || $akcja === 'edytuj') {
            $nazwa = adm_tekst($_POST['nazwa'] ?? '');
            $opis  = trim((string)($_POST['opis'] ?? ''));

            // Walidacja po stronie serwera
            if ($nazwa === '') {
                $bledy[] = 'Podaj nazwę kategorii.';
            } elseif (mb_strlen($nazwa) < 2 || mb_strlen($nazwa) > 100) {
                $bledy[] = 'Nazwa kategorii musi mieć od 2 do 100 znaków.';
            }
            if (mb_strlen($opis) > 1000) {
                $bledy[] = 'Opis może mieć maksymalnie 1000 znaków.';
            }

            if ($akcja === 'edytuj') {
                $st = $pdo->prepare('SELECT id FROM kategorie_uslug WHERE id = ?');
                $st->execute([$id]);
                if (!$st->fetch()) {
                    adm_flash('danger', 'Taka kategoria nie istnieje.');
                    adm_przekieruj('admin_kategorie.php');
                }
            }

            if (!$bledy) {
                $st = $pdo->prepare('SELECT id FROM kategorie_uslug WHERE nazwa = ? AND id <> ?');
                $st->execute([$nazwa, $akcja === 'edytuj' ? $id : 0]);
                if ($st->fetch()) {
                    $bledy[] = 'Kategoria o takiej nazwie już istnieje.';
                }
            }

            if ($bledy) {
                $stare = ['tryb' => $akcja, 'id' => $id, 'nazwa' => $nazwa, 'opis' => $opis];
                $otworz_modal = true;
            } elseif ($akcja === 'dodaj') {
                $pdo->prepare('INSERT INTO kategorie_uslug (nazwa, opis) VALUES (?, ?)')
                    ->execute([$nazwa, $opis !== '' ? $opis : null]);
                adm_log($pdo, 'dodanie_kategorii', 'Dodano kategorię #' . $pdo->lastInsertId() . ' „' . $nazwa . '”');
                adm_flash('success', 'Kategoria „' . $nazwa . '” została dodana.');
                adm_przekieruj('admin_kategorie.php');
            } else {
                $pdo->prepare('UPDATE kategorie_uslug SET nazwa = ?, opis = ? WHERE id = ?')
                    ->execute([$nazwa, $opis !== '' ? $opis : null, $id]);
                adm_log($pdo, 'edycja_kategorii', 'Zmieniono kategorię #' . $id . ' na „' . $nazwa . '”');
                adm_flash('success', 'Kategoria została zaktualizowana.');
                adm_przekieruj('admin_kategorie.php');
            }
        }

        /* ---------- USUWANIE ---------- */
        elseif ($akcja === 'usun') {
            $st = $pdo->prepare('SELECT nazwa FROM kategorie_uslug WHERE id = ?');
            $st->execute([$id]);
            $kat = $st->fetch();

            if (!$kat) {
                adm_flash('danger', 'Taka kategoria nie istnieje.');
            } else {
                $st = $pdo->prepare('SELECT COUNT(*) FROM uslugi WHERE kategoria_id = ?');
                $st->execute([$id]);
                $ile = (int)$st->fetchColumn();

                if ($ile > 0) {
                    adm_flash('warning', 'Nie można usunąć kategorii „' . $kat['nazwa'] . '” – zawiera usługi (' . $ile . '). '
                        . 'Najpierw przenieś je do innej kategorii lub usuń.');
                } else {
                    $pdo->prepare('DELETE FROM kategorie_uslug WHERE id = ?')->execute([$id]);
                    adm_log($pdo, 'usuniecie_kategorii', 'Usunięto kategorię #' . $id . ' „' . $kat['nazwa'] . '”');
                    adm_flash('success', 'Kategoria „' . $kat['nazwa'] . '” została usunięta.');
                }
            }
            adm_przekieruj('admin_kategorie.php');
        }
    } catch (PDOException $e) {
        error_log('admin_kategorie.php: ' . $e->getMessage());
        adm_flash('danger', 'Operacja nie powiodła się. Spróbuj ponownie.');
        adm_przekieruj('admin_kategorie.php');
    }
}

/* ===================== DANE DO WYŚWIETLENIA ===================== */
$kategorie = $pdo->query("
    SELECT k.id, k.nazwa, k.opis, COUNT(u.id) AS liczba_uslug
    FROM kategorie_uslug k
    LEFT JOIN uslugi u ON u.kategoria_id = k.id
    GROUP BY k.id, k.nazwa, k.opis
    ORDER BY k.nazwa
")->fetchAll();

$adm_aktywna = 'kategorie';
require_once 'header.php';
require_once 'admin_nav.php';
?>

<?php adm_flash_wypisz(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Kategorie usług <span class="badge bg-secondary"><?= count($kategorie) ?></span></h4>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#formModal" data-tryb="dodaj">
        + Dodaj kategorię
    </button>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr><th>ID</th><th>Nazwa</th><th>Opis</th><th class="text-center">Usługi</th><th class="text-end">Akcje</th></tr>
        </thead>
        <tbody>
        <?php foreach ($kategorie as $k): ?>
            <tr>
                <td>#<?= (int)$k['id'] ?></td>
                <td><strong><?= adm_h($k['nazwa']) ?></strong></td>
                <td class="text-muted small"><?= adm_h($k['opis'] ?? '') ?></td>
                <td class="text-center">
                    <a href="admin_uslugi.php?kategoria=<?= (int)$k['id'] ?>" class="badge bg-info text-dark text-decoration-none">
                        <?= (int)$k['liczba_uslug'] ?>
                    </a>
                </td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#formModal" data-tryb="edytuj"
                            data-id="<?= (int)$k['id'] ?>"
                            data-nazwa="<?= adm_h($k['nazwa']) ?>"
                            data-opis="<?= adm_h($k['opis'] ?? '') ?>">Edytuj</button>

                    <?php if ((int)$k['liczba_uslug'] > 0): ?>
                        <span class="d-inline-block" tabindex="0" title="Kategoria zawiera usługi – nie można jej usunąć">
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Usuń</button>
                        </span>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                data-id="<?= (int)$k['id'] ?>" data-nazwa="<?= adm_h($k['nazwa']) ?>">Usuń</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$kategorie): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Brak kategorii. Dodaj pierwszą.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- OKNO: DODAWANIE / EDYCJA -->
<div class="modal fade" id="formModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="admin_kategorie.php" novalidate>
                <?= adm_csrf_pole() ?>
                <input type="hidden" name="akcja" id="f-akcja" value="dodaj">
                <input type="hidden" name="id" id="f-id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="f-tytul">Nowa kategoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($bledy): ?>
                        <div class="alert alert-danger py-2">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($bledy as $b): ?><li><?= adm_h($b) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="f-nazwa" class="form-label">Nazwa *</label>
                        <input type="text" class="form-control" id="f-nazwa" name="nazwa" maxlength="100" required>
                    </div>
                    <div class="mb-2">
                        <label for="f-opis" class="form-label">Opis</label>
                        <textarea class="form-control" id="f-opis" name="opis" rows="3" maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary btn-sm">Zapisz</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- OKNO: POTWIERDZENIE USUNIĘCIA -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="admin_kategorie.php">
                <?= adm_csrf_pole() ?>
                <input type="hidden" name="akcja" value="usun">
                <input type="hidden" name="id" id="d-id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title">Usunąć kategorię?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Czy na pewno chcesz usunąć kategorię <strong id="d-nazwa"></strong>? Tej operacji nie można cofnąć.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-danger btn-sm">Usuń</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var formModal = document.getElementById('formModal');
    var deleteModal = document.getElementById('deleteModal');

    formModal.addEventListener('show.bs.modal', function (e) {
        var b = e.relatedTarget;
        if (!b) return;                       
        var edycja = b.dataset.tryb === 'edytuj';
        document.getElementById('f-akcja').value = b.dataset.tryb;
        document.getElementById('f-id').value    = edycja ? b.dataset.id : 0;
        document.getElementById('f-nazwa').value = edycja ? b.dataset.nazwa : '';
        document.getElementById('f-opis').value  = edycja ? b.dataset.opis : '';
        document.getElementById('f-tytul').textContent = edycja ? 'Edycja kategorii #' + b.dataset.id : 'Nowa kategoria';
    });

    deleteModal.addEventListener('show.bs.modal', function (e) {
        document.getElementById('d-id').value = e.relatedTarget.dataset.id;
        document.getElementById('d-nazwa').textContent = e.relatedTarget.dataset.nazwa;
    });

    <?php if ($otworz_modal): ?>
    var stare = <?= json_encode($stare, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    document.getElementById('f-akcja').value = stare.tryb;
    document.getElementById('f-id').value    = stare.id;
    document.getElementById('f-nazwa').value = stare.nazwa;
    document.getElementById('f-opis').value  = stare.opis;
    document.getElementById('f-tytul').textContent = stare.tryb === 'edytuj' ? 'Edycja kategorii #' + stare.id : 'Nowa kategoria';
    bootstrap.Modal.getOrCreateInstance(formModal).show();
    <?php endif; ?>
})();
</script>

<?php require_once 'footer.php'; ?>
