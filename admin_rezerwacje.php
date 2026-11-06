<?php
/*
 Panel admina: rezerwacje.
 Lista z filtrami (data, status, pracownik, klient) i paginacją, podgląd szczegółów z historią statusów,
 zmiana statusu i anulowanie. Każda zmiana trafia do historii statusów i logu administratora.
*/
require_once 'admin_helpers.php';
adm_wymagaj_admina();
date_default_timezone_set('Europe/Warsaw');

const REZERWACJE_NA_STRONE = 15;

const STATUSY = [
    'oczekujaca'   => ['Oczekująca', 'warning text-dark'],
    'potwierdzona' => ['Potwierdzona', 'primary'],
    'zrealizowana' => ['Zrealizowana', 'success'],
    'anulowana'    => ['Anulowana', 'secondary'],
];

const PRZEJSCIA = [
    'oczekujaca'   => ['potwierdzona', 'anulowana'],
    'potwierdzona' => ['zrealizowana', 'anulowana'],
    'zrealizowana' => [],
    'anulowana'    => [],
];

$f_od = adm_data_ok($_GET['data_od'] ?? null) ? $_GET['data_od'] : '';
$f_do = adm_data_ok($_GET['data_do'] ?? null) ? $_GET['data_do'] : '';
$f_status = (is_string($_GET['status'] ?? null) && isset(STATUSY[$_GET['status']])) ? $_GET['status'] : '';
$f_pracownik = (int)($_GET['pracownik'] ?? 0);
$f_q = adm_tekst($_GET['q'] ?? '');
$f_strona = max(1, (int)($_GET['strona'] ?? 1));

$f_bez_strony = array_filter([
    'data_od' => $f_od, 'data_do' => $f_do, 'status' => $f_status,
    'pracownik' => $f_pracownik ?: '', 'q' => $f_q,
], fn($v) => $v !== '');

function url_rezerwacji(array $filtry, int $strona): string
{
    if ($strona > 1) {
        $filtry['strona'] = $strona;
    }
    return 'admin_rezerwacje.php' . ($filtry ? '?' . http_build_query($filtry) : '');
}

$adres_listy = url_rezerwacji($f_bez_strony, $f_strona);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!adm_csrf_ok()) {
        adm_flash('danger', 'Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.');
        adm_przekieruj($adres_listy);
    }

    $id = (int)($_POST['id'] ?? 0);
    $nowy = is_string($_POST['nowy'] ?? null) ? $_POST['nowy'] : '';

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT status, data_rezerwacji, godzina_od FROM rezerwacje WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $r = $st->fetch();

        if (!$r) {
            $pdo->rollBack();
            adm_flash('danger', 'Taka rezerwacja nie istnieje.');
        } elseif (!in_array($nowy, PRZEJSCIA[$r['status']], true)) {
            $pdo->rollBack();
            adm_flash('warning', 'Nie można zmienić statusu rezerwacji #' . $id . ' z „' . STATUSY[$r['status']][0] . '” na wybrany.');
        } elseif ($nowy === 'zrealizowana' && strtotime($r['data_rezerwacji'] . ' ' . $r['godzina_od']) > time()) {
            $pdo->rollBack();
            adm_flash('warning', 'Wizyta #' . $id . ' jeszcze się nie rozpoczęła, więc nie można jej oznaczyć jako zrealizowanej.');
        } else {
            $pdo->prepare('UPDATE rezerwacje SET status = ? WHERE id = ?')->execute([$nowy, $id]);
            $pdo->prepare('INSERT INTO historia_statusow_rezerwacji (rezerwacja_id, status_poprzedni, status_nowy, zmienione_przez) VALUES (?, ?, ?, ?)')
                ->execute([$id, $r['status'], $nowy, (int)$_SESSION['uzytkownik_id']]);
            $pdo->commit();

            adm_log($pdo, 'zmiana_statusu', 'Rezerwacja #' . $id . ': ' . $r['status'] . ' -> ' . $nowy);
            adm_flash('success', 'Status rezerwacji #' . $id . ' zmieniony na: ' . STATUSY[$nowy][0] . '.');
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('admin_rezerwacje.php: ' . $e->getMessage());
        adm_flash('danger', 'Operacja nie powiodła się. Spróbuj ponownie.');
    }
    adm_przekieruj($adres_listy);
}

$where = [];
$par = [];
if ($f_od !== '') {
    $where[] = 'r.data_rezerwacji >= :od';
    $par[':od'] = $f_od;
}
if ($f_do !== '') {
    $where[] = 'r.data_rezerwacji <= :do';
    $par[':do'] = $f_do;
}
if ($f_status !== '') {
    $where[] = 'r.status = :status';
    $par[':status'] = $f_status;
}
if ($f_pracownik > 0) {
    $where[] = 'r.pracownik_id = :prac';
    $par[':prac'] = $f_pracownik;
}
if ($f_q !== '') {
    $where[] = "(CONCAT(k.imie, ' ', k.nazwisko) LIKE :q1 OR k.email LIKE :q2)";
    $like = '%' . addcslashes($f_q, '%_\\') . '%';
    $par[':q1'] = $like;
    $par[':q2'] = $like;
}
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql_from = "
    FROM rezerwacje r
    JOIN uzytkownicy k ON k.id = r.klient_id
    JOIN pracownicy pr ON pr.id = r.pracownik_id
    JOIN uzytkownicy pu ON pu.id = pr.uzytkownik_id
    JOIN uslugi u ON u.id = r.usluga_id
    JOIN kategorie_uslug kat ON kat.id = u.kategoria_id
    $sql_where
";

$st = $pdo->prepare("SELECT COUNT(*) $sql_from");
$st->execute($par);
$razem = (int)$st->fetchColumn();
$stron = max(1, (int)ceil($razem / REZERWACJE_NA_STRONE));
$f_strona = min($f_strona, $stron);

$st = $pdo->prepare("
    SELECT r.*, k.imie AS k_imie, k.nazwisko AS k_nazwisko, k.email AS k_email, k.telefon AS k_telefon,
           pu.imie AS p_imie, pu.nazwisko AS p_nazwisko,
           u.nazwa AS usluga, u.cena, u.czas_trwania, kat.nazwa AS kategoria
    $sql_from
    ORDER BY r.data_rezerwacji DESC, r.godzina_od DESC
    LIMIT :lim OFFSET :off
");
foreach ($par as $k => $v) {
    $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$st->bindValue(':lim', REZERWACJE_NA_STRONE, PDO::PARAM_INT);
$st->bindValue(':off', ($f_strona - 1) * REZERWACJE_NA_STRONE, PDO::PARAM_INT);
$st->execute();
$rezerwacje = $st->fetchAll();

$historia = [];
if ($rezerwacje) {
    $ids = array_map('intval', array_column($rezerwacje, 'id'));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("
        SELECT h.rezerwacja_id, h.status_poprzedni, h.status_nowy, h.data_zmiany, a.imie, a.nazwisko
        FROM historia_statusow_rezerwacji h
        JOIN uzytkownicy a ON a.id = h.zmienione_przez
        WHERE h.rezerwacja_id IN ($in)
        ORDER BY h.data_zmiany, h.id
    ");
    $st->execute($ids);
    foreach ($st->fetchAll() as $h) {
        $historia[$h['rezerwacja_id']][] = $h;
    }
}

$pracownicy = $pdo->query("
    SELECT p.id, u.imie, u.nazwisko
    FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id
    ORDER BY u.nazwisko, u.imie
")->fetchAll();

$dzis = date('Y-m-d');
$adm_aktywna = 'rezerwacje';
require_once 'header.php';
require_once 'admin_top.php';

function znacznik_statusu(string $status): string
{
    return '<span class="badge bg-' . STATUSY[$status][1] . '">' . adm_h(STATUSY[$status][0]) . '</span>';
}
?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Zarządzanie wizytami <span class="badge bg-secondary"><?= $razem ?></span></h4>
        <a href="admin_rezerwacje.php?data_od=<?= $dzis ?>&data_do=<?= $dzis ?>" class="btn btn-outline-primary btn-sm">Tylko dzisiejsze</a>
    </div>

    <form method="get" action="admin_rezerwacje.php" class="row g-2 mb-3">
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0" for="fo">Data od</label>
            <input type="date" id="fo" name="data_od" class="form-control form-control-sm" value="<?= adm_h($f_od) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0" for="fd">Data do</label>
            <input type="date" id="fd" name="data_do" class="form-control form-control-sm" value="<?= adm_h($f_do) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0" for="fs">Status</label>
            <select id="fs" name="status" class="form-select form-select-sm">
                <option value="">Wszystkie</option>
                <?php foreach (STATUSY as $klucz => $s): ?>
                    <option value="<?= $klucz ?>" <?= $f_status === $klucz ? 'selected' : '' ?>><?= adm_h($s[0]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0" for="fp">Pracownik</label>
            <select id="fp" name="pracownik" class="form-select form-select-sm">
                <option value="0">Wszyscy</option>
                <?php foreach ($pracownicy as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $f_pracownik === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= adm_h($p['imie'] . ' ' . $p['nazwisko']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small mb-0" for="fq">Klient</label>
            <input type="search" id="fq" name="q" class="form-control form-control-sm" placeholder="Imię, nazwisko, e-mail" value="<?= adm_h($f_q) ?>">
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end gap-1">
            <button class="btn btn-dark btn-sm flex-fill" type="submit">Filtruj</button>
            <a href="admin_rezerwacje.php" class="btn btn-outline-secondary btn-sm">Wyczyść</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Klient</th><th>Usługa</th><th>Pracownik</th><th>Termin</th><th>Status</th><th class="text-end">Akcje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rezerwacje as $r): ?>
                <?php
                $start = strtotime($r['data_rezerwacji'] . ' ' . $r['godzina_od']);
                $dozwolone = PRZEJSCIA[$r['status']];
                ?>
                <tr class="<?= $r['status'] === 'anulowana' ? 'table-secondary' : '' ?>">
                    <td>#<?= (int)$r['id'] ?></td>
                    <td>
                        <strong><?= adm_h($r['k_imie'] . ' ' . $r['k_nazwisko']) ?></strong>
                        <br><small class="text-muted"><?= adm_h($r['k_email']) ?></small>
                    </td>
                    <td>
                        <?= adm_h($r['usluga']) ?>
                        <br><small class="text-muted"><?= adm_zl($r['cena']) ?></small>
                    </td>
                    <td><?= adm_h($r['p_imie'] . ' ' . $r['p_nazwisko']) ?></td>
                    <td class="text-nowrap">
                        <?= date('d.m.Y', strtotime($r['data_rezerwacji'])) ?>
                        <?php if ($r['data_rezerwacji'] === $dzis): ?><span class="badge bg-info text-dark">dziś</span><?php endif; ?>
                        <br><small class="text-muted"><?= substr($r['godzina_od'], 0, 5) ?>–<?= substr($r['godzina_do'], 0, 5) ?></small>
                    </td>
                    <td><?= znacznik_statusu($r['status']) ?></td>
                    <td class="text-end">
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#szczegoly<?= (int)$r['id'] ?>">Szczegóły</button>

                            <?php if (in_array('potwierdzona', $dozwolone, true)): ?>
                                <form method="post" action="<?= adm_h($adres_listy) ?>" class="d-inline">
                                    <?= adm_csrf_pole() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="nowy" value="potwierdzona">
                                    <button type="submit" class="btn btn-sm btn-outline-success">Potwierdź</button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array('zrealizowana', $dozwolone, true)): ?>
                                <form method="post" action="<?= adm_h($adres_listy) ?>" class="d-inline">
                                    <?= adm_csrf_pole() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="nowy" value="zrealizowana">
                                    <button type="submit" class="btn btn-sm btn-outline-info" <?= $start > time() ? 'disabled title="Wizyta jeszcze się nie rozpoczęła"' : '' ?>>Zrealizuj</button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array('anulowana', $dozwolone, true)): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#anulujModal" data-id="<?= (int)$r['id'] ?>">Anuluj</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rezerwacje): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">
                    <?= $where ? 'Brak rezerwacji spełniających kryteria.' : 'Brak rezerwacji w systemie.' ?>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($stron > 1): ?>
        <nav aria-label="Strony listy rezerwacji" class="mt-3">
            <ul class="pagination pagination-sm justify-content-center flex-wrap">
                <li class="page-item <?= $f_strona <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= adm_h(url_rezerwacji($f_bez_strony, $f_strona - 1)) ?>">«</a>
                </li>
                <?php for ($i = 1; $i <= $stron; $i++): ?>
                    <li class="page-item <?= $i === $f_strona ? 'active' : '' ?>">
                        <a class="page-link" href="<?= adm_h(url_rezerwacji($f_bez_strony, $i)) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $f_strona >= $stron ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= adm_h(url_rezerwacji($f_bez_strony, $f_strona + 1)) ?>">»</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <?php foreach ($rezerwacje as $r): ?>
        <div class="modal fade" id="szczegoly<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Rezerwacja #<?= (int)$r['id'] ?> <?= znacznik_statusu($r['status']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Klient</h6>
                                <strong><?= adm_h($r['k_imie'] . ' ' . $r['k_nazwisko']) ?></strong><br>
                                <a href="mailto:<?= adm_h($r['k_email']) ?>"><?= adm_h($r['k_email']) ?></a><br>
                                <?= adm_h($r['k_telefon'] ?? 'brak telefonu') ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Pracownik</h6>
                                <strong><?= adm_h($r['p_imie'] . ' ' . $r['p_nazwisko']) ?></strong>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Usługa</h6>
                                <strong><?= adm_h($r['usluga']) ?></strong> (<?= adm_h($r['kategoria']) ?>)<br>
                                <?= adm_zl($r['cena']) ?>, <?= (int)$r['czas_trwania'] ?> min
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted">Termin</h6>
                                <strong><?= date('d.m.Y', strtotime($r['data_rezerwacji'])) ?></strong>,
                                <?= substr($r['godzina_od'], 0, 5) ?>–<?= substr($r['godzina_do'], 0, 5) ?><br>
                                <small class="text-muted">Utworzona: <?= adm_h($r['data_utworzenia']) ?></small>
                            </div>
                        </div>

                        <h6 class="text-muted">Komentarz klienta</h6>
                        <p><?= $r['komentarz'] ? nl2br(adm_h($r['komentarz'])) : '<span class="text-muted">brak</span>' ?></p>

                        <h6 class="text-muted">Historia statusów</h6>
                        <?php if (empty($historia[$r['id']])): ?>
                            <p class="text-muted mb-0">Brak zmian statusu.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($historia[$r['id']] as $h): ?>
                                    <li class="list-group-item small">
                                        <?= adm_h($h['data_zmiany']) ?>:
                                        <?= $h['status_poprzedni'] ? adm_h(STATUSY[$h['status_poprzedni']][0]) : '—' ?>
                                        → <strong><?= adm_h(STATUSY[$h['status_nowy']][0]) ?></strong>
                                        (<?= adm_h($h['imie'] . ' ' . $h['nazwisko']) ?>)
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Zamknij</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="modal fade" id="anulujModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?= adm_h($adres_listy) ?>">
                    <?= adm_csrf_pole() ?>
                    <input type="hidden" name="id" id="anuluj-id" value="0">
                    <input type="hidden" name="nowy" value="anulowana">
                    <div class="modal-header">
                        <h5 class="modal-title">Anulować rezerwację?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Czy na pewno chcesz anulować rezerwację <strong id="anuluj-numer"></strong>?
                        Termin zostanie zwolniony, a anulowanej rezerwacji nie da się już przywrócić.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Wróć</button>
                        <button type="submit" class="btn btn-danger btn-sm">Anuluj rezerwację</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
document.getElementById('anulujModal').addEventListener('show.bs.modal', function (e) {
    var id = e.relatedTarget.dataset.id;
    document.getElementById('anuluj-id').value = id;
    document.getElementById('anuluj-numer').textContent = '#' + id;
});
</script>

<?php require_once 'footer.php'; ?>
