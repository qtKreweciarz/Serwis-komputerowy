<?php
/**
 * Panel administratora – zarządzanie usługami (CRUD + aktywacja + wyszukiwanie + paginacja).
 */
require_once 'admin_helpers.php';
adm_wymagaj_admina();

const USLUGI_NA_STRONE = 10;

/* ---------- Filtry listy (GET) – zostają w adresie także po zapisie formularza ---------- */
$f_q         = adm_tekst($_GET['q'] ?? '');
$f_kategoria = (int)($_GET['kategoria'] ?? 0);
$f_status    = in_array($_GET['status'] ?? '', ['aktywne', 'nieaktywne'], true) ? $_GET['status'] : '';
$f_strona    = max(1, (int)($_GET['strona'] ?? 1));

$filtry = array_filter(
    ['q' => $f_q, 'kategoria' => $f_kategoria ?: '', 'status' => $f_status, 'strona' => $f_strona > 1 ? $f_strona : ''],
    fn($v) => $v !== ''
);
$qs_lista = http_build_query($filtry);                       // do linków i akcji formularzy
$adres_listy = 'admin_uslugi.php' . ($qs_lista ? '?' . $qs_lista : '');

/* ---------- Kategorie do list wyboru ---------- */
$kategorie = $pdo->query('SELECT id, nazwa FROM kategorie_uslug ORDER BY nazwa')->fetchAll();
$id_kategorii = array_map('intval', array_column($kategorie, 'id'));

/**
 * Walidacja danych usługi. Zwraca [tablica błędów, wyczyszczone dane].
 * $id = 0 przy dodawaniu, id usługi przy edycji (do sprawdzenia duplikatów).
 */
function waliduj_usluge(PDO $pdo, array $in, array $id_kategorii, int $id): array
{
    $bledy = [];
    $nazwa = adm_tekst($in['nazwa'] ?? '');
    $kat   = (int)($in['kategoria_id'] ?? 0);
    $cena  = str_replace(',', '.', trim((string)($in['cena'] ?? '')));
    $czas  = trim((string)($in['czas_trwania'] ?? ''));
    $opis  = trim((string)($in['opis'] ?? ''));
    $akt   = isset($in['aktywna']) ? 1 : 0;

    if ($nazwa === '') {
        $bledy[] = 'Podaj nazwę usługi.';
    } elseif (mb_strlen($nazwa) < 2 || mb_strlen($nazwa) > 100) {
        $bledy[] = 'Nazwa usługi musi mieć od 2 do 100 znaków.';
    }

    if (!in_array($kat, $id_kategorii, true)) {
        $bledy[] = 'Wybierz istniejącą kategorię.';
    }

    if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $cena)) {
        $bledy[] = 'Cena musi być liczbą od 0 do 99999,99 (maks. 2 miejsca po przecinku).';
    }

    if (!ctype_digit($czas) || (int)$czas < 5 || (int)$czas > 480) {
        $bledy[] = 'Czas trwania musi być liczbą całkowitą minut od 5 do 480.';
    }

    if (mb_strlen($opis) > 1000) {
        $bledy[] = 'Opis może mieć maksymalnie 1000 znaków.';
    }

    if (!$bledy) {
        $st = $pdo->prepare('SELECT id FROM uslugi WHERE nazwa = ? AND kategoria_id = ? AND id <> ?');
        $st->execute([$nazwa, $kat, $id]);
        if ($st->fetch()) {
            $bledy[] = 'W tej kategorii istnieje już usługa o takiej nazwie.';
        }
    }

    return [$bledy, [
        'nazwa' => $nazwa, 'kategoria_id' => $kat, 'cena' => $cena,
        'czas_trwania' => $czas, 'opis' => $opis, 'aktywna' => $akt,
    ]];
}

$bledy = [];
$stare = [];
$otworz_modal = false;

/* ===================== OBSŁUGA FORMULARZY (POST) ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!adm_csrf_ok()) {
        adm_flash('danger', 'Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.');
        adm_przekieruj($adres_listy);
    }

    $akcja = $_POST['akcja'] ?? '';
    $id    = (int)($_POST['id'] ?? 0);

    try {
        /* ---------- DODAWANIE / EDYCJA ---------- */
        if ($akcja === 'dodaj' || $akcja === 'edytuj') {

            if ($akcja === 'edytuj') {
                $st = $pdo->prepare('SELECT id FROM uslugi WHERE id = ?');
                $st->execute([$id]);
                if (!$st->fetch()) {
                    adm_flash('danger', 'Taka usługa nie istnieje.');
                    adm_przekieruj($adres_listy);
                }
            }

            [$bledy, $d] = waliduj_usluge($pdo, $_POST, $id_kategorii, $akcja === 'edytuj' ? $id : 0);

            if ($bledy) {
                $stare = $d + ['tryb' => $akcja, 'id' => $id];
                $otworz_modal = true;
            } elseif ($akcja === 'dodaj') {
                $pdo->prepare('INSERT INTO uslugi (kategoria_id, nazwa, opis, czas_trwania, cena, aktywna) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$d['kategoria_id'], $d['nazwa'], $d['opis'] !== '' ? $d['opis'] : null,
                               (int)$d['czas_trwania'], $d['cena'], $d['aktywna']]);
                adm_log($pdo, 'dodanie_uslugi', 'Dodano usługę #' . $pdo->lastInsertId() . ' „' . $d['nazwa'] . '” ('
                    . adm_zl($d['cena']) . ', ' . $d['czas_trwania'] . ' min)');
                adm_flash('success', 'Usługa „' . $d['nazwa'] . '” została dodana.');
                adm_przekieruj($adres_listy);
            } else {
                $pdo->prepare('UPDATE uslugi SET kategoria_id = ?, nazwa = ?, opis = ?, czas_trwania = ?, cena = ?, aktywna = ? WHERE id = ?')
                    ->execute([$d['kategoria_id'], $d['nazwa'], $d['opis'] !== '' ? $d['opis'] : null,
                               (int)$d['czas_trwania'], $d['cena'], $d['aktywna'], $id]);
                adm_log($pdo, 'edycja_uslugi', 'Zmieniono usługę #' . $id . ' „' . $d['nazwa'] . '” ('
                    . adm_zl($d['cena']) . ', ' . $d['czas_trwania'] . ' min)');
                adm_flash('success', 'Usługa została zaktualizowana.');
                adm_przekieruj($adres_listy);
            }
        }

        /* ---------- AKTYWACJA / DEZAKTYWACJA ---------- */
        elseif ($akcja === 'przelacz') {
            $st = $pdo->prepare('SELECT nazwa, aktywna FROM uslugi WHERE id = ?');
            $st->execute([$id]);
            $u = $st->fetch();
            if (!$u) {
                adm_flash('danger', 'Taka usługa nie istnieje.');
            } else {
                $nowa = (int)$u['aktywna'] === 1 ? 0 : 1;
                $pdo->prepare('UPDATE uslugi SET aktywna = ? WHERE id = ?')->execute([$nowa, $id]);
                adm_log($pdo, $nowa ? 'aktywacja_uslugi' : 'dezaktywacja_uslugi',
                    ($nowa ? 'Aktywowano' : 'Dezaktywowano') . ' usługę #' . $id . ' „' . $u['nazwa'] . '”');
                adm_flash('success', 'Usługa „' . $u['nazwa'] . '” została ' . ($nowa ? 'aktywowana (widoczna dla klientów).' : 'dezaktywowana (ukryta przed klientami).'));
            }
            adm_przekieruj($adres_listy);
        }

        /* ---------- USUWANIE ---------- */
        elseif ($akcja === 'usun') {
            $st = $pdo->prepare('SELECT nazwa FROM uslugi WHERE id = ?');
            $st->execute([$id]);
            $u = $st->fetch();

            if (!$u) {
                adm_flash('danger', 'Taka usługa nie istnieje.');
            } else {
                $st = $pdo->prepare('SELECT COUNT(*) FROM rezerwacje WHERE usluga_id = ?');
                $st->execute([$id]);
                $ile = (int)$st->fetchColumn();

                if ($ile > 0) {
                    adm_flash('warning', 'Nie można usunąć usługi „' . $u['nazwa'] . '” – ma rezerwacje (' . $ile . '). '
                        . 'Zamiast usuwać, dezaktywuj ją – zniknie z oferty, a historia rezerwacji zostanie zachowana.');
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare('DELETE FROM pracownicy_uslugi WHERE usluga_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM uslugi WHERE id = ?')->execute([$id]);
                    $pdo->commit();
                    adm_log($pdo, 'usuniecie_uslugi', 'Usunięto usługę #' . $id . ' „' . $u['nazwa'] . '”');
                    adm_flash('success', 'Usługa „' . $u['nazwa'] . '” została usunięta.');
                }
            }
            adm_przekieruj($adres_listy);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('admin_uslugi.php: ' . $e->getMessage());
        $kod = $e->errorInfo[1] ?? 0;
        adm_flash('danger', $kod == 1451
            ? 'Nie można usunąć usługi – jest używana w rezerwacjach. Dezaktywuj ją.'
            : 'Operacja nie powiodła się. Spróbuj ponownie.');
        adm_przekieruj($adres_listy);
    }
}

/* ===================== LISTA USŁUG (filtry + paginacja) ===================== */
$where = [];
$par   = [];
if ($f_q !== '') {
    $where[]    = '(u.nazwa LIKE :q OR u.opis LIKE :q)';
    $par[':q']  = '%' . addcslashes($f_q, '%_\\') . '%';
}
if ($f_kategoria > 0) {
    $where[]    = 'u.kategoria_id = :kat';
    $par[':kat'] = $f_kategoria;
}
if ($f_status === 'aktywne')    { $where[] = 'u.aktywna = 1'; }
if ($f_status === 'nieaktywne') { $where[] = 'u.aktywna = 0'; }
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = $pdo->prepare("SELECT COUNT(*) FROM uslugi u $sql_where");
$st->execute($par);
$razem  = (int)$st->fetchColumn();
$stron  = max(1, (int)ceil($razem / USLUGI_NA_STRONE));
$f_strona = min($f_strona, $stron);

$st = $pdo->prepare("
    SELECT u.*, k.nazwa AS kategoria,
           (SELECT COUNT(*) FROM pracownicy_uslugi pu WHERE pu.usluga_id = u.id) AS liczba_pracownikow,
           (SELECT COUNT(*) FROM rezerwacje r WHERE r.usluga_id = u.id)          AS liczba_rezerwacji
    FROM uslugi u
    JOIN kategorie_uslug k ON k.id = u.kategoria_id
    $sql_where
    ORDER BY k.nazwa, u.nazwa
    LIMIT :lim OFFSET :off
");
foreach ($par as $k => $v) {
    $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$st->bindValue(':lim', USLUGI_NA_STRONE, PDO::PARAM_INT);
$st->bindValue(':off', ($f_strona - 1) * USLUGI_NA_STRONE, PDO::PARAM_INT);
$st->execute();
$uslugi = $st->fetchAll();

$qs_lista = http_build_query(array_filter(
    ['q' => $f_q, 'kategoria' => $f_kategoria ?: '', 'status' => $f_status, 'strona' => $f_strona > 1 ? $f_strona : ''],
    fn($v) => $v !== ''
));
$akcja_url = 'admin_uslugi.php' . ($qs_lista ? '?' . $qs_lista : '');

function url_strony(int $n, array $f): string
{
    $f['strona'] = $n > 1 ? $n : '';
    return 'admin_uslugi.php?' . http_build_query(array_filter($f, fn($v) => $v !== ''));
}
$f_bez_strony = ['q' => $f_q, 'kategoria' => $f_kategoria ?: '', 'status' => $f_status];

$adm_aktywna = 'uslugi';
require_once 'header.php';
require_once 'admin_nav.php';

$opcje_kat = '';
foreach ($kategorie as $k) {
    $opcje_kat .= '<option value="' . (int)$k['id'] . '">' . adm_h($k['nazwa']) . '</option>';
}
?>

<?php adm_flash_wypisz(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Katalog usług <span class="badge bg-secondary"><?= $razem ?></span></h4>
    <?php if ($kategorie): ?>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#formModal" data-tryb="dodaj">
            + Dodaj usługę
        </button>
    <?php else: ?>
        <a href="admin_kategorie.php" class="btn btn-outline-primary btn-sm">Najpierw dodaj kategorię →</a>
    <?php endif; ?>
</div>

<!-- FILTRY / WYSZUKIWANIE -->
<form method="get" action="admin_uslugi.php" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Szukaj w nazwie i opisie…" value="<?= adm_h($f_q) ?>">
    </div>
    <div class="col-md-3">
        <select name="kategoria" class="form-select form-select-sm">
            <option value="0">Wszystkie kategorie</option>
            <?php foreach ($kategorie as $k): ?>
                <option value="<?= (int)$k['id'] ?>" <?= $f_kategoria === (int)$k['id'] ? 'selected' : '' ?>><?= adm_h($k['nazwa']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
            <option value="">Wszystkie statusy</option>
            <option value="aktywne" <?= $f_status === 'aktywne' ? 'selected' : '' ?>>Aktywne</option>
            <option value="nieaktywne" <?= $f_status === 'nieaktywne' ? 'selected' : '' ?>>Nieaktywne</option>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-1">
        <button class="btn btn-dark btn-sm flex-fill" type="submit">Filtruj</button>
        <a href="admin_uslugi.php" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>ID</th><th>Usługa</th><th>Kategoria</th><th>Czas</th><th>Cena</th>
                <th class="text-center">Pracownicy</th><th class="text-center">Rezerwacje</th><th>Status</th><th class="text-end">Akcje</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($uslugi as $u): ?>
            <tr class="<?= $u['aktywna'] ? '' : 'table-secondary' ?>">
                <td>#<?= (int)$u['id'] ?></td>
                <td>
                    <strong><?= adm_h($u['nazwa']) ?></strong>
                    <?php if ($u['opis']): ?>
                        <br><small class="text-muted"><?= adm_h(mb_strimwidth($u['opis'], 0, 90, '…', 'UTF-8')) ?></small>
                    <?php endif; ?>
                </td>
                <td><span class="badge bg-info text-dark"><?= adm_h($u['kategoria']) ?></span></td>
                <td><?= (int)$u['czas_trwania'] ?> min</td>
                <td class="text-nowrap"><?= adm_zl($u['cena']) ?></td>
                <td class="text-center">
                    <?php if ((int)$u['liczba_pracownikow'] === 0): ?>
                        <span class="badge bg-warning text-dark" title="Żaden pracownik nie wykonuje tej usługi – klient nie zarezerwuje terminu">0</span>
                    <?php else: ?>
                        <?= (int)$u['liczba_pracownikow'] ?>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= (int)$u['liczba_rezerwacji'] ?></td>
                <td>
                    <?= $u['aktywna']
                        ? '<span class="badge bg-success">Aktywna</span>'
                        : '<span class="badge bg-secondary">Nieaktywna</span>' ?>
                </td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#formModal" data-tryb="edytuj"
                            data-id="<?= (int)$u['id'] ?>"
                            data-nazwa="<?= adm_h($u['nazwa']) ?>"
                            data-kategoria="<?= (int)$u['kategoria_id'] ?>"
                            data-cena="<?= adm_h($u['cena']) ?>"
                            data-czas="<?= (int)$u['czas_trwania'] ?>"
                            data-opis="<?= adm_h($u['opis'] ?? '') ?>"
                            data-aktywna="<?= (int)$u['aktywna'] ?>">Edytuj</button>

                    <form method="post" action="<?= adm_h($akcja_url) ?>" class="d-inline">
                        <?= adm_csrf_pole() ?>
                        <input type="hidden" name="akcja" value="przelacz">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <?php if ($u['aktywna']): ?>
                            <button type="submit" class="btn btn-sm btn-outline-warning">Dezaktywuj</button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-sm btn-outline-success">Aktywuj</button>
                        <?php endif; ?>
                    </form>

                    <?php if ((int)$u['liczba_rezerwacji'] > 0): ?>
                        <span class="d-inline-block" tabindex="0" title="Usługa ma rezerwacje – można ją tylko dezaktywować">
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Usuń</button>
                        </span>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                data-id="<?= (int)$u['id'] ?>" data-nazwa="<?= adm_h($u['nazwa']) ?>">Usuń</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$uslugi): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">
                <?= $razem === 0 && !$where ? 'Brak usług. Dodaj pierwszą.' : 'Brak usług spełniających kryteria wyszukiwania.' ?>
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- PAGINACJA -->
<?php if ($stron > 1): ?>
    <nav aria-label="Strony listy usług" class="mt-3">
        <ul class="pagination pagination-sm justify-content-center flex-wrap">
            <li class="page-item <?= $f_strona <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= adm_h(url_strony($f_strona - 1, $f_bez_strony)) ?>">«</a>
            </li>
            <?php for ($i = 1; $i <= $stron; $i++): ?>
                <li class="page-item <?= $i === $f_strona ? 'active' : '' ?>">
                    <a class="page-link" href="<?= adm_h(url_strony($i, $f_bez_strony)) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $f_strona >= $stron ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= adm_h(url_strony($f_strona + 1, $f_bez_strony)) ?>">»</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<!-- OKNO: DODAWANIE / EDYCJA -->
<div class="modal fade" id="formModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= adm_h($akcja_url) ?>" novalidate>
                <?= adm_csrf_pole() ?>
                <input type="hidden" name="akcja" id="f-akcja" value="dodaj">
                <input type="hidden" name="id" id="f-id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="f-tytul">Nowa usługa</h5>
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
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="f-nazwa" class="form-label">Nazwa usługi *</label>
                            <input type="text" class="form-control" id="f-nazwa" name="nazwa" maxlength="100" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="f-kategoria" class="form-label">Kategoria *</label>
                            <select class="form-select" id="f-kategoria" name="kategoria_id" required><?= $opcje_kat ?></select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="f-cena" class="form-label">Cena (zł) *</label>
                            <input type="number" class="form-control" id="f-cena" name="cena" min="0" max="99999.99" step="0.01" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="f-czas" class="form-label">Czas trwania (min) *</label>
                            <input type="number" class="form-control" id="f-czas" name="czas_trwania" min="5" max="480" step="1" required>
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="f-aktywna" name="aktywna" value="1">
                                <label class="form-check-label" for="f-aktywna">Widoczna dla klientów</label>
                            </div>
                        </div>
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
            <form method="post" action="<?= adm_h($akcja_url) ?>">
                <?= adm_csrf_pole() ?>
                <input type="hidden" name="akcja" value="usun">
                <input type="hidden" name="id" id="d-id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title">Usunąć usługę?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Czy na pewno chcesz usunąć usługę <strong id="d-nazwa"></strong>?
                    Zostanie też odpięta od przypisanych pracowników. Tej operacji nie można cofnąć.
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
    var el = function (id) { return document.getElementById(id); };

    function wypelnij(d) {
        el('f-akcja').value     = d.tryb;
        el('f-id').value        = d.id;
        el('f-nazwa').value     = d.nazwa;
        el('f-kategoria').value = d.kategoria;
        el('f-cena').value      = d.cena;
        el('f-czas').value      = d.czas;
        el('f-opis').value      = d.opis;
        el('f-aktywna').checked = String(d.aktywna) === '1';
        el('f-tytul').textContent = d.tryb === 'edytuj' ? 'Edycja usługi #' + d.id : 'Nowa usługa';
    }

    formModal.addEventListener('show.bs.modal', function (e) {
        var b = e.relatedTarget;
        if (!b) return;                       // otwarte automatycznie po błędzie – zostaw wpisane dane
        if (b.dataset.tryb === 'edytuj') {
            wypelnij({tryb: 'edytuj', id: b.dataset.id, nazwa: b.dataset.nazwa, kategoria: b.dataset.kategoria,
                      cena: b.dataset.cena, czas: b.dataset.czas, opis: b.dataset.opis, aktywna: b.dataset.aktywna});
        } else {
            wypelnij({tryb: 'dodaj', id: 0, nazwa: '', kategoria: el('f-kategoria').options[0] ? el('f-kategoria').options[0].value : '',
                      cena: '', czas: 30, opis: '', aktywna: 1});
        }
    });

    deleteModal.addEventListener('show.bs.modal', function (e) {
        el('d-id').value = e.relatedTarget.dataset.id;
        el('d-nazwa').textContent = e.relatedTarget.dataset.nazwa;
    });

    <?php if ($otworz_modal): ?>
    var stare = <?= json_encode([
        'tryb' => $stare['tryb'], 'id' => $stare['id'], 'nazwa' => $stare['nazwa'], 'kategoria' => $stare['kategoria_id'],
        'cena' => $stare['cena'], 'czas' => $stare['czas_trwania'], 'opis' => $stare['opis'], 'aktywna' => $stare['aktywna'],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    wypelnij(stare);
    bootstrap.Modal.getOrCreateInstance(formModal).show();
    <?php endif; ?>
})();
</script>

<?php require_once 'footer.php'; ?>
