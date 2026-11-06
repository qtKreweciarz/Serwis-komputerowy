<?php
/*
 Panel admina: pracownicy.
 Dodawanie i edycja danych, przypisywanie usług (relacja N:M), godziny pracy w tygodniu, urlopy,
 aktywacja/dezaktywacja oraz usuwanie. Zmiany, które kolidowałyby z przyszłymi rezerwacjami, są blokowane.
*/
require_once 'admin_helpers.php';
adm_wymagaj_admina();
date_default_timezone_set('Europe/Warsaw');

const DNI = [1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela'];
const DNI_KROTKO = [1 => 'Pn', 2 => 'Wt', 3 => 'Śr', 4 => 'Cz', 5 => 'Pt', 6 => 'So', 7 => 'Nd'];
const AKTYWNE_STATUSY = "('oczekujaca','potwierdzona')";

function pobierz_pracownika(PDO $pdo, int $id)
{
    $st = $pdo->prepare('
        SELECT p.id, p.uzytkownik_id, p.opis, p.aktywny AS p_aktywny,
               u.imie, u.nazwisko, u.email, u.telefon, u.aktywny AS u_aktywny
        FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id
        WHERE p.id = ?
    ');
    $st->execute([$id]);
    return $st->fetch();
}

function przyszle_rezerwacje(PDO $pdo, int $pracownik_id): array
{
    $st = $pdo->prepare('
        SELECT id, data_rezerwacji, godzina_od, godzina_do, usluga_id
        FROM rezerwacje
        WHERE pracownik_id = ? AND status IN ' . AKTYWNE_STATUSY . ' AND data_rezerwacji >= ?
        ORDER BY data_rezerwacji, godzina_od
    ');
    $st->execute([$pracownik_id, date('Y-m-d')]);
    return $st->fetchAll();
}

function opisz_rezerwacje(array $lista): string
{
    $opisy = [];
    foreach (array_slice($lista, 0, 4) as $r) {
        $opisy[] = '#' . $r['id'] . ' (' . $r['data_rezerwacji'] . ' ' . substr($r['godzina_od'], 0, 5) . ')';
    }
    return implode(', ', $opisy) . (count($lista) > 4 ? ' i inne' : '');
}

function waliduj_pracownika(PDO $pdo, array $in, int $uzytkownik_id, bool $haslo_wymagane): array
{
    $bledy = [];
    $imie = adm_tekst($in['imie'] ?? '');
    $nazwisko = adm_tekst($in['nazwisko'] ?? '');
    $email = strtolower(adm_tekst($in['email'] ?? ''));
    $telefon_wpisany = adm_tekst($in['telefon'] ?? '');
    $opis = is_string($in['opis'] ?? null) ? trim($in['opis']) : '';
    $haslo = is_string($in['haslo'] ?? null) ? $in['haslo'] : '';
    $wzor_nazwy = "/^\p{L}[\p{L} '\-]{1,49}$/u";

    if (!preg_match($wzor_nazwy, $imie)) {
        $bledy[] = 'Imię musi mieć 2–50 liter (dozwolone spacje, apostrof i myślnik).';
    }
    if (!preg_match($wzor_nazwy, $nazwisko)) {
        $bledy[] = 'Nazwisko musi mieć 2–50 liter (dozwolone spacje, apostrof i myślnik).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $bledy[] = 'Podaj poprawny adres e-mail (maks. 100 znaków).';
    } else {
        $st = $pdo->prepare('SELECT id FROM uzytkownicy WHERE email = ? AND id <> ?');
        $st->execute([$email, $uzytkownik_id]);
        if ($st->fetch()) {
            $bledy[] = 'Ten adres e-mail jest już zajęty.';
        }
    }

    $telefon = null;
    if ($telefon_wpisany !== '') {
        $telefon = preg_replace('/[\s\-]/', '', $telefon_wpisany);
        if (!preg_match('/^(\+48)?[0-9]{9}$/', $telefon)) {
            $bledy[] = 'Telefon musi mieć 9 cyfr (opcjonalnie z prefiksem +48).';
        }
    }

    if (mb_strlen($opis) > 1000) {
        $bledy[] = 'Opis może mieć maksymalnie 1000 znaków.';
    }

    if ($haslo === '' && $haslo_wymagane) {
        $bledy[] = 'Podaj hasło.';
    } elseif ($haslo !== '' && strlen($haslo) < 8) {
        $bledy[] = 'Hasło musi mieć co najmniej 8 znaków.';
    }

    return [$bledy, [
        'imie' => $imie, 'nazwisko' => $nazwisko, 'email' => $email, 'telefon' => $telefon,
        'telefon_wpisany' => $telefon_wpisany, 'opis' => $opis, 'haslo' => $haslo,
    ]];
}

function podsumuj_dostepnosc(array $dni): array
{
    ksort($dni);
    $grupy = [];
    foreach ($dni as $d => [$od, $do]) {
        $ostatnia = array_key_last($grupy);
        if ($ostatnia !== null && $grupy[$ostatnia]['godziny'] === $od . '–' . $do && $grupy[$ostatnia]['do'] === $d - 1) {
            $grupy[$ostatnia]['do'] = $d;
        } else {
            $grupy[] = ['od' => $d, 'do' => $d, 'godziny' => $od . '–' . $do];
        }
    }
    $linie = [];
    foreach ($grupy as $g) {
        $dni_txt = $g['od'] === $g['do'] ? DNI_KROTKO[$g['od']] : DNI_KROTKO[$g['od']] . '–' . DNI_KROTKO[$g['do']];
        $linie[] = $dni_txt . ' ' . $g['godziny'];
    }
    return $linie;
}

$bledy = [];
$stare = [];
$otworz_modal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!adm_csrf_ok()) {
        adm_flash('danger', 'Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.');
        adm_przekieruj('admin_pracownicy.php');
    }

    $akcja = $_POST['akcja'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    try {
        if ($akcja === 'dodaj') {
            [$bledy, $d] = waliduj_pracownika($pdo, $_POST, 0, true);

            if ($bledy) {
                $stare = $d + ['modal' => 'dodaj'];
                $otworz_modal = 'dodaj';
            } else {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, telefon, rola, aktywny) VALUES (?, ?, ?, ?, ?, 'pracownik', 1)")
                    ->execute([$d['imie'], $d['nazwisko'], $d['email'], password_hash($d['haslo'], PASSWORD_DEFAULT), $d['telefon']]);
                $uid = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO pracownicy (uzytkownik_id, opis, aktywny) VALUES (?, ?, 1)')
                    ->execute([$uid, $d['opis'] !== '' ? $d['opis'] : null]);
                $pid = (int)$pdo->lastInsertId();
                $pdo->commit();

                adm_log($pdo, 'dodanie_pracownika', 'Dodano pracownika #' . $pid . ' ' . $d['imie'] . ' ' . $d['nazwisko']);
                adm_flash('success', 'Pracownik ' . $d['imie'] . ' ' . $d['nazwisko'] . ' został dodany. Przypisz mu usługi i ustaw godziny pracy.');
                adm_przekieruj('admin_pracownicy.php');
            }
        } else {
            $p = pobierz_pracownika($pdo, $id);
            if (!$p) {
                adm_flash('danger', 'Taki pracownik nie istnieje.');
                adm_przekieruj('admin_pracownicy.php');
            }
            $nazwa = $p['imie'] . ' ' . $p['nazwisko'];

            if ($akcja === 'edytuj') {
                [$bledy, $d] = waliduj_pracownika($pdo, $_POST, (int)$p['uzytkownik_id'], false);

                if ($bledy) {
                    $stare = $d + ['modal' => 'edytuj' . $id];
                    $otworz_modal = 'edytuj' . $id;
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE uzytkownicy SET imie = ?, nazwisko = ?, email = ?, telefon = ? WHERE id = ?')
                        ->execute([$d['imie'], $d['nazwisko'], $d['email'], $d['telefon'], $p['uzytkownik_id']]);
                    if ($d['haslo'] !== '') {
                        $pdo->prepare('UPDATE uzytkownicy SET haslo = ? WHERE id = ?')
                            ->execute([password_hash($d['haslo'], PASSWORD_DEFAULT), $p['uzytkownik_id']]);
                    }
                    $pdo->prepare('UPDATE pracownicy SET opis = ? WHERE id = ?')
                        ->execute([$d['opis'] !== '' ? $d['opis'] : null, $id]);
                    $pdo->commit();

                    adm_log($pdo, 'edycja_pracownika', 'Zmieniono dane pracownika #' . $id . ' ' . $d['imie'] . ' ' . $d['nazwisko']
                        . ($d['haslo'] !== '' ? ' (zmiana hasła)' : ''));
                    adm_flash('success', 'Dane pracownika zostały zaktualizowane.');
                    adm_przekieruj('admin_pracownicy.php');
                }
            }

            elseif ($akcja === 'uslugi') {
                $wybrane = array_values(array_unique(array_map('intval', array_filter((array)($_POST['uslugi'] ?? []), 'is_scalar'))));
                $istniejace = array_map('intval', $pdo->query('SELECT id FROM uslugi')->fetchAll(PDO::FETCH_COLUMN));
                $wybrane = array_values(array_intersect($wybrane, $istniejace));

                $st = $pdo->prepare('SELECT usluga_id FROM pracownicy_uslugi WHERE pracownik_id = ?');
                $st->execute([$id]);
                $obecne = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
                $odpinane = array_values(array_diff($obecne, $wybrane));

                $blokady = [];
                if ($odpinane) {
                    $in = implode(',', array_fill(0, count($odpinane), '?'));
                    $st = $pdo->prepare('
                        SELECT u.nazwa, COUNT(*) AS ile
                        FROM rezerwacje r JOIN uslugi u ON u.id = r.usluga_id
                        WHERE r.pracownik_id = ? AND r.usluga_id IN (' . $in . ') AND r.status IN ' . AKTYWNE_STATUSY . ' AND r.data_rezerwacji >= ?
                        GROUP BY u.id, u.nazwa
                    ');
                    $st->execute(array_merge([$id], $odpinane, [date('Y-m-d')]));
                    foreach ($st->fetchAll() as $b) {
                        $blokady[] = $b['nazwa'] . ' (' . $b['ile'] . ')';
                    }
                }

                if ($blokady) {
                    adm_flash('warning', 'Nie można odpiąć usług, które mają przyszłe rezerwacje u tego pracownika: ' . implode(', ', $blokady)
                        . '. Najpierw zrealizuj lub anuluj te rezerwacje.');
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare('DELETE FROM pracownicy_uslugi WHERE pracownik_id = ?')->execute([$id]);
                    $ins = $pdo->prepare('INSERT INTO pracownicy_uslugi (pracownik_id, usluga_id) VALUES (?, ?)');
                    foreach ($wybrane as $uid) {
                        $ins->execute([$id, $uid]);
                    }
                    $pdo->commit();

                    adm_log($pdo, 'przypisanie_uslug', 'Pracownik #' . $id . ' ' . $nazwa . ': przypisano usług: ' . count($wybrane));
                    adm_flash('success', 'Usługi pracownika zostały zapisane (' . count($wybrane) . ').');
                }
                adm_przekieruj('admin_pracownicy.php');
            }

            elseif ($akcja === 'dostepnosc') {
                $pracuje = array_values(array_unique(array_map('intval', array_filter((array)($_POST['pracuje'] ?? []), 'is_scalar'))));
                $od = is_array($_POST['od'] ?? null) ? $_POST['od'] : [];
                $do = is_array($_POST['do'] ?? null) ? $_POST['do'] : [];
                $nowe = [];
                $bl = [];

                foreach ($pracuje as $dzien) {
                    if ($dzien < 1 || $dzien > 7) {
                        continue;
                    }
                    $g_od = is_string($od[$dzien] ?? null) ? substr($od[$dzien], 0, 5) : '';
                    $g_do = is_string($do[$dzien] ?? null) ? substr($do[$dzien], 0, 5) : '';
                    $wzor = '/^([01][0-9]|2[0-3]):[0-5][0-9]$/';

                    if (!preg_match($wzor, $g_od) || !preg_match($wzor, $g_do)) {
                        $bl[] = DNI[$dzien] . ': podaj poprawne godziny.';
                    } elseif ($g_od >= $g_do) {
                        $bl[] = DNI[$dzien] . ': godzina zakończenia musi być późniejsza niż rozpoczęcia.';
                    } else {
                        $nowe[$dzien] = [$g_od, $g_do];
                    }
                }

                if (!$bl) {
                    $kolizje = [];
                    foreach (przyszle_rezerwacje($pdo, $id) as $r) {
                        $dzien = (int)date('N', strtotime($r['data_rezerwacji']));
                        if (!isset($nowe[$dzien]) || substr($r['godzina_od'], 0, 5) < $nowe[$dzien][0] || substr($r['godzina_do'], 0, 5) > $nowe[$dzien][1]) {
                            $kolizje[] = $r;
                        }
                    }
                    if ($kolizje) {
                        $bl[] = 'Nowe godziny kolidują z przyszłymi rezerwacjami: ' . opisz_rezerwacje($kolizje) . '. Najpierw je zrealizuj lub anuluj.';
                    }
                }

                if ($bl) {
                    adm_flash('danger', implode(' ', $bl));
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare('DELETE FROM dostepnosc_pracownikow WHERE pracownik_id = ?')->execute([$id]);
                    $ins = $pdo->prepare('INSERT INTO dostepnosc_pracownikow (pracownik_id, dzien_tygodnia, godzina_od, godzina_do) VALUES (?, ?, ?, ?)');
                    foreach ($nowe as $dzien => [$g_od, $g_do]) {
                        $ins->execute([$id, $dzien, $g_od . ':00', $g_do . ':00']);
                    }
                    $pdo->commit();

                    adm_log($pdo, 'zmiana_dostepnosci', 'Pracownik #' . $id . ' ' . $nazwa . ': dni pracy: ' . count($nowe));
                    adm_flash('success', 'Godziny pracy zostały zapisane.');
                }
                adm_przekieruj('admin_pracownicy.php');
            }

            elseif ($akcja === 'urlop_dodaj') {
                $data_od = $_POST['data_od'] ?? '';
                $data_do = $_POST['data_do'] ?? '';
                $powod = adm_tekst($_POST['powod'] ?? '');
                $bl = [];

                if (!adm_data_ok($data_od) || !adm_data_ok($data_do)) {
                    $bl[] = 'Podaj poprawne daty urlopu.';
                } elseif ($data_od > $data_do) {
                    $bl[] = 'Data zakończenia nie może być wcześniejsza niż rozpoczęcia.';
                } elseif ($data_do < date('Y-m-d')) {
                    $bl[] = 'Urlop nie może kończyć się w przeszłości.';
                }
                if (mb_strlen($powod) > 255) {
                    $bl[] = 'Powód może mieć maksymalnie 255 znaków.';
                }

                if (!$bl) {
                    $st = $pdo->prepare('SELECT id FROM urlopy_pracownikow WHERE pracownik_id = ? AND data_od <= ? AND data_do >= ?');
                    $st->execute([$id, $data_do, $data_od]);
                    if ($st->fetch()) {
                        $bl[] = 'W tym okresie pracownik ma już zaplanowany urlop.';
                    }

                    $kolizje = array_filter(przyszle_rezerwacje($pdo, $id), fn($r) => $r['data_rezerwacji'] >= $data_od && $r['data_rezerwacji'] <= $data_do);
                    if ($kolizje) {
                        $bl[] = 'W tym okresie pracownik ma rezerwacje: ' . opisz_rezerwacje(array_values($kolizje)) . '. Najpierw je zrealizuj lub anuluj.';
                    }
                }

                if ($bl) {
                    adm_flash('danger', implode(' ', $bl));
                } else {
                    $pdo->prepare('INSERT INTO urlopy_pracownikow (pracownik_id, data_od, data_do, powod) VALUES (?, ?, ?, ?)')
                        ->execute([$id, $data_od, $data_do, $powod !== '' ? $powod : null]);
                    adm_log($pdo, 'dodanie_urlopu', 'Pracownik #' . $id . ' ' . $nazwa . ': urlop ' . $data_od . ' – ' . $data_do);
                    adm_flash('success', 'Urlop został dodany.');
                }
                adm_przekieruj('admin_pracownicy.php');
            }

            elseif ($akcja === 'urlop_usun') {
                $urlop_id = (int)($_POST['urlop_id'] ?? 0);
                $st = $pdo->prepare('DELETE FROM urlopy_pracownikow WHERE id = ? AND pracownik_id = ?');
                $st->execute([$urlop_id, $id]);
                if ($st->rowCount() > 0) {
                    adm_log($pdo, 'usuniecie_urlopu', 'Pracownik #' . $id . ' ' . $nazwa . ': usunięto urlop #' . $urlop_id);
                    adm_flash('success', 'Urlop został usunięty.');
                } else {
                    adm_flash('danger', 'Taki urlop nie istnieje.');
                }
                adm_przekieruj('admin_pracownicy.php');
            }

            elseif ($akcja === 'przelacz') {
                $byl_aktywny = (int)$p['p_aktywny'] === 1 && (int)$p['u_aktywny'] === 1;
                $nowa = $byl_aktywny ? 0 : 1;

                $pdo->beginTransaction();
                $pdo->prepare('UPDATE pracownicy SET aktywny = ? WHERE id = ?')->execute([$nowa, $id]);
                $pdo->prepare('UPDATE uzytkownicy SET aktywny = ? WHERE id = ?')->execute([$nowa, $p['uzytkownik_id']]);
                $pdo->commit();

                adm_log($pdo, $nowa ? 'aktywacja_pracownika' : 'dezaktywacja_pracownika',
                    ($nowa ? 'Aktywowano' : 'Dezaktywowano') . ' pracownika #' . $id . ' ' . $nazwa);

                if ($nowa) {
                    adm_flash('success', 'Pracownik ' . $nazwa . ' został aktywowany.');
                } else {
                    $ile = count(przyszle_rezerwacje($pdo, $id));
                    adm_flash($ile ? 'warning' : 'success', 'Pracownik ' . $nazwa . ' został dezaktywowany (nie przyjmuje nowych rezerwacji i nie zaloguje się).'
                        . ($ile ? ' Ma jeszcze ' . $ile . ' przyszłych rezerwacji – sprawdź je w zakładce Rezerwacje.' : ''));
                }
                adm_przekieruj('admin_pracownicy.php');
            }

            elseif ($akcja === 'usun') {
                $st = $pdo->prepare('SELECT COUNT(*) FROM rezerwacje WHERE pracownik_id = ?');
                $st->execute([$id]);
                $ile = (int)$st->fetchColumn();

                if ($ile > 0) {
                    adm_flash('warning', 'Nie można usunąć pracownika ' . $nazwa . ' – ma rezerwacje (' . $ile . '). '
                        . 'Zamiast usuwać, dezaktywuj go – historia rezerwacji zostanie zachowana.');
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare('DELETE FROM dostepnosc_pracownikow WHERE pracownik_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM urlopy_pracownikow WHERE pracownik_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM pracownicy_uslugi WHERE pracownik_id = ?')->execute([$id]);
                    $pdo->prepare('DELETE FROM pracownicy WHERE id = ?')->execute([$id]);
                    $pdo->prepare("UPDATE uzytkownicy SET rola = 'klient' WHERE id = ? AND rola = 'pracownik'")->execute([$p['uzytkownik_id']]);
                    $pdo->commit();

                    adm_log($pdo, 'usuniecie_pracownika', 'Usunięto pracownika #' . $id . ' ' . $nazwa . ' (konto zmienione na klienta)');
                    adm_flash('success', 'Pracownik ' . $nazwa . ' został usunięty z personelu (jego konto stało się kontem klienta).');
                }
                adm_przekieruj('admin_pracownicy.php');
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('admin_pracownicy.php: ' . $e->getMessage());
        $kod = $e->errorInfo[1] ?? 0;
        adm_flash('danger', $kod == 1062 ? 'Ten adres e-mail jest już zajęty.' : 'Operacja nie powiodła się. Spróbuj ponownie.');
        adm_przekieruj('admin_pracownicy.php');
    }
}

$st = $pdo->prepare('
    SELECT p.id, p.uzytkownik_id, p.opis, p.aktywny AS p_aktywny,
           u.imie, u.nazwisko, u.email, u.telefon, u.aktywny AS u_aktywny,
           (SELECT COUNT(*) FROM pracownicy_uslugi pu WHERE pu.pracownik_id = p.id) AS liczba_uslug,
           (SELECT COUNT(*) FROM rezerwacje r WHERE r.pracownik_id = p.id) AS liczba_rezerwacji,
           (SELECT COUNT(*) FROM rezerwacje r WHERE r.pracownik_id = p.id AND r.status IN ' . AKTYWNE_STATUSY . ' AND r.data_rezerwacji >= :dzis) AS przyszle
    FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id
    ORDER BY u.nazwisko, u.imie
');
$st->execute([':dzis' => date('Y-m-d')]);
$pracownicy = $st->fetchAll();

$przypisane = [];
foreach ($pdo->query('SELECT pracownik_id, usluga_id FROM pracownicy_uslugi') as $r) {
    $przypisane[$r['pracownik_id']][] = (int)$r['usluga_id'];
}

$dostepnosc = [];
foreach ($pdo->query('SELECT pracownik_id, dzien_tygodnia, godzina_od, godzina_do FROM dostepnosc_pracownikow') as $r) {
    $dostepnosc[$r['pracownik_id']][(int)$r['dzien_tygodnia']] = [substr($r['godzina_od'], 0, 5), substr($r['godzina_do'], 0, 5)];
}

$urlopy = [];
foreach ($pdo->query('SELECT id, pracownik_id, data_od, data_do, powod FROM urlopy_pracownikow ORDER BY data_od DESC') as $r) {
    $urlopy[$r['pracownik_id']][] = $r;
}

$uslugi_wg_kategorii = [];
foreach ($pdo->query('
    SELECT u.id, u.nazwa, u.aktywna, k.nazwa AS kategoria
    FROM uslugi u JOIN kategorie_uslug k ON k.id = u.kategoria_id
    ORDER BY k.nazwa, u.nazwa
') as $r) {
    $uslugi_wg_kategorii[$r['kategoria']][] = $r;
}

function wartosc(array $stare, string $modal, string $pole, $domyslnie)
{
    return ($stare['modal'] ?? '') === $modal ? $stare[$pole] : $domyslnie;
}

function bledy_modala(array $bledy, array $stare, string $modal): void
{
    if (!$bledy || ($stare['modal'] ?? '') !== $modal) {
        return;
    }
    echo '<div class="alert alert-danger py-2"><ul class="mb-0 ps-3">';
    foreach ($bledy as $b) {
        echo '<li>' . adm_h($b) . '</li>';
    }
    echo '</ul></div>';
}

$dzis = date('Y-m-d');
$adm_aktywna = 'pracownicy';
require_once 'header.php';
require_once 'admin_top.php';
?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Zarządzanie pracownikami <span class="badge bg-secondary"><?= count($pracownicy) ?></span></h4>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dodaj">+ Dodaj pracownika</button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Pracownik</th><th>Specjalizacja</th><th class="text-center">Usługi</th>
                    <th>Godziny pracy</th><th class="text-center">Przyszłe wizyty</th><th>Status</th><th class="text-end">Akcje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pracownicy as $e): ?>
                <?php
                $aktywny = (int)$e['p_aktywny'] === 1 && (int)$e['u_aktywny'] === 1;
                $pid = (int)$e['id'];
                $godziny = podsumuj_dostepnosc($dostepnosc[$pid] ?? []);
                ?>
                <tr class="<?= $aktywny ? '' : 'table-secondary' ?>">
                    <td>#<?= $pid ?></td>
                    <td>
                        <strong><?= adm_h($e['imie'] . ' ' . $e['nazwisko']) ?></strong><br>
                        <small class="text-muted"><?= adm_h($e['email']) ?><?= $e['telefon'] ? ' · ' . adm_h($e['telefon']) : '' ?></small>
                    </td>
                    <td class="small"><?= adm_h($e['opis'] ? mb_strimwidth($e['opis'], 0, 80, '…', 'UTF-8') : 'Brak opisu') ?></td>
                    <td class="text-center">
                        <?php if ((int)$e['liczba_uslug'] === 0): ?>
                            <span class="badge bg-warning text-dark" title="Pracownik nie wykonuje żadnej usługi – klient go nie wybierze">0</span>
                        <?php else: ?>
                            <?= (int)$e['liczba_uslug'] ?>
                        <?php endif; ?>
                    </td>
                    <td class="small text-nowrap">
                        <?php if (!$godziny): ?>
                            <span class="badge bg-warning text-dark">brak</span>
                        <?php else: ?>
                            <?= implode('<br>', array_map('adm_h', $godziny)) ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= (int)$e['przyszle'] ?></td>
                    <td><?= $aktywny ? '<span class="badge bg-success">Aktywny</span>' : '<span class="badge bg-secondary">Nieaktywny</span>' ?></td>
                    <td class="text-end">
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edytuj<?= $pid ?>">Edytuj</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uslugi<?= $pid ?>">Usługi</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#dostepnosc<?= $pid ?>">Godziny</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#urlopy<?= $pid ?>">Urlopy</button>

                            <form method="post" action="admin_pracownicy.php" class="d-inline">
                                <?= adm_csrf_pole() ?>
                                <input type="hidden" name="akcja" value="przelacz">
                                <input type="hidden" name="id" value="<?= $pid ?>">
                                <?php if ($aktywny): ?>
                                    <button type="submit" class="btn btn-sm btn-outline-warning">Dezaktywuj</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">Aktywuj</button>
                                <?php endif; ?>
                            </form>

                            <?php if ((int)$e['liczba_rezerwacji'] > 0): ?>
                                <span class="d-inline-block" tabindex="0" title="Pracownik ma rezerwacje – można go tylko dezaktywować">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Usuń</button>
                                </span>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#usunModal"
                                        data-id="<?= $pid ?>" data-nazwa="<?= adm_h($e['imie'] . ' ' . $e['nazwisko']) ?>">Usuń</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pracownicy): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Brak pracowników. Dodaj pierwszego.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="dodaj" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="admin_pracownicy.php" novalidate>
                    <?= adm_csrf_pole() ?>
                    <input type="hidden" name="akcja" value="dodaj">
                    <div class="modal-header">
                        <h5 class="modal-title">Nowy pracownik</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php bledy_modala($bledy, $stare, 'dodaj'); ?>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Imię *</label>
                                <input type="text" name="imie" class="form-control" maxlength="50" required value="<?= adm_h(wartosc($stare, 'dodaj', 'imie', '')) ?>">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Nazwisko *</label>
                                <input type="text" name="nazwisko" class="form-control" maxlength="50" required value="<?= adm_h(wartosc($stare, 'dodaj', 'nazwisko', '')) ?>">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">E-mail *</label>
                            <input type="email" name="email" class="form-control" maxlength="100" required value="<?= adm_h(wartosc($stare, 'dodaj', 'email', '')) ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Telefon</label>
                            <input type="text" name="telefon" class="form-control" maxlength="20" value="<?= adm_h(wartosc($stare, 'dodaj', 'telefon_wpisany', '')) ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Opis / specjalizacja</label>
                            <textarea name="opis" class="form-control" rows="2" maxlength="1000"><?= adm_h(wartosc($stare, 'dodaj', 'opis', '')) ?></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Hasło * (min. 8 znaków)</label>
                            <input type="password" name="haslo" class="form-control" autocomplete="new-password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary btn-sm">Dodaj</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($pracownicy as $e): ?>
        <?php
        $pid = (int)$e['id'];
        $m = 'edytuj' . $pid;
        $moje_uslugi = $przypisane[$pid] ?? [];
        $moje_dni = $dostepnosc[$pid] ?? [];
        ?>

        <div class="modal fade" id="<?= $m ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="admin_pracownicy.php" novalidate>
                        <?= adm_csrf_pole() ?>
                        <input type="hidden" name="akcja" value="edytuj">
                        <input type="hidden" name="id" value="<?= $pid ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Edycja: <?= adm_h($e['imie'] . ' ' . $e['nazwisko']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?php bledy_modala($bledy, $stare, $m); ?>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Imię *</label>
                                    <input type="text" name="imie" class="form-control" maxlength="50" required value="<?= adm_h(wartosc($stare, $m, 'imie', $e['imie'])) ?>">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Nazwisko *</label>
                                    <input type="text" name="nazwisko" class="form-control" maxlength="50" required value="<?= adm_h(wartosc($stare, $m, 'nazwisko', $e['nazwisko'])) ?>">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">E-mail *</label>
                                <input type="email" name="email" class="form-control" maxlength="100" required value="<?= adm_h(wartosc($stare, $m, 'email', $e['email'])) ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Telefon</label>
                                <input type="text" name="telefon" class="form-control" maxlength="20" value="<?= adm_h(wartosc($stare, $m, 'telefon_wpisany', $e['telefon'] ?? '')) ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Opis / specjalizacja</label>
                                <textarea name="opis" class="form-control" rows="2" maxlength="1000"><?= adm_h(wartosc($stare, $m, 'opis', $e['opis'] ?? '')) ?></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Nowe hasło (puste = bez zmiany, min. 8 znaków)</label>
                                <input type="password" name="haslo" class="form-control" autocomplete="new-password">
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

        <div class="modal fade" id="uslugi<?= $pid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post" action="admin_pracownicy.php">
                        <?= adm_csrf_pole() ?>
                        <input type="hidden" name="akcja" value="uslugi">
                        <input type="hidden" name="id" value="<?= $pid ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Usługi: <?= adm_h($e['imie'] . ' ' . $e['nazwisko']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">Zaznacz usługi, które wykonuje ten pracownik.</p>
                            <?php foreach ($uslugi_wg_kategorii as $kategoria => $lista): ?>
                                <h6 class="mt-2"><?= adm_h($kategoria) ?></h6>
                                <?php foreach ($lista as $u): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="uslugi[]" value="<?= (int)$u['id'] ?>"
                                               id="u<?= $pid ?>_<?= (int)$u['id'] ?>" <?= in_array((int)$u['id'], $moje_uslugi, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="u<?= $pid ?>_<?= (int)$u['id'] ?>">
                                            <?= adm_h($u['nazwa']) ?><?= $u['aktywna'] ? '' : ' <span class="badge bg-secondary">nieaktywna</span>' ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <?php if (!$uslugi_wg_kategorii): ?>
                                <p class="text-muted">Brak usług. Dodaj je w zakładce Usługi.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                            <button type="submit" class="btn btn-primary btn-sm">Zapisz usługi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="dostepnosc<?= $pid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="admin_pracownicy.php">
                        <?= adm_csrf_pole() ?>
                        <input type="hidden" name="akcja" value="dostepnosc">
                        <input type="hidden" name="id" value="<?= $pid ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Godziny pracy: <?= adm_h($e['imie'] . ' ' . $e['nazwisko']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">Zaznacz dni pracy i ustaw godziny. Niezaznaczony dzień oznacza brak pracy.</p>
                            <?php foreach (DNI as $nr => $nazwa_dnia): ?>
                                <div class="row g-2 align-items-center mb-2">
                                    <div class="col-5">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="pracuje[]" value="<?= $nr ?>"
                                                   id="d<?= $pid ?>_<?= $nr ?>" <?= isset($moje_dni[$nr]) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="d<?= $pid ?>_<?= $nr ?>"><?= $nazwa_dnia ?></label>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <input type="time" class="form-control form-control-sm" name="od[<?= $nr ?>]" value="<?= adm_h($moje_dni[$nr][0] ?? '09:00') ?>">
                                    </div>
                                    <div class="col-1 text-center">–</div>
                                    <div class="col-3">
                                        <input type="time" class="form-control form-control-sm" name="do[<?= $nr ?>]" value="<?= adm_h($moje_dni[$nr][1] ?? '17:00') ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                            <button type="submit" class="btn btn-primary btn-sm">Zapisz godziny</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="urlopy<?= $pid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Urlopy: <?= adm_h($e['imie'] . ' ' . $e['nazwisko']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($urlopy[$pid])): ?>
                            <p class="text-muted">Brak zaplanowanych urlopów.</p>
                        <?php else: ?>
                            <ul class="list-group mb-3">
                                <?php foreach ($urlopy[$pid] as $url): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center <?= $url['data_do'] < $dzis ? 'text-muted' : '' ?>">
                                        <span>
                                            <?= date('d.m.Y', strtotime($url['data_od'])) ?> – <?= date('d.m.Y', strtotime($url['data_do'])) ?>
                                            <?= $url['powod'] ? ' · ' . adm_h($url['powod']) : '' ?>
                                        </span>
                                        <form method="post" action="admin_pracownicy.php" class="d-inline">
                                            <?= adm_csrf_pole() ?>
                                            <input type="hidden" name="akcja" value="urlop_usun">
                                            <input type="hidden" name="id" value="<?= $pid ?>">
                                            <input type="hidden" name="urlop_id" value="<?= (int)$url['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Usuń</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <form method="post" action="admin_pracownicy.php" class="row g-2 border-top pt-3">
                            <?= adm_csrf_pole() ?>
                            <input type="hidden" name="akcja" value="urlop_dodaj">
                            <input type="hidden" name="id" value="<?= $pid ?>">
                            <div class="col-md-3">
                                <label class="form-label small mb-0">Od *</label>
                                <input type="date" name="data_od" class="form-control form-control-sm" min="<?= $dzis ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0">Do *</label>
                                <input type="date" name="data_do" class="form-control form-control-sm" min="<?= $dzis ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0">Powód</label>
                                <input type="text" name="powod" class="form-control form-control-sm" maxlength="255">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Dodaj urlop</button>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Zamknij</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="modal fade" id="usunModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="admin_pracownicy.php">
                    <?= adm_csrf_pole() ?>
                    <input type="hidden" name="akcja" value="usun">
                    <input type="hidden" name="id" id="usun-id" value="0">
                    <div class="modal-header">
                        <h5 class="modal-title">Usunąć pracownika?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Czy na pewno chcesz usunąć pracownika <strong id="usun-nazwa"></strong> z personelu?
                        Zostaną usunięte jego godziny pracy, urlopy i przypisane usługi, a jego konto zmieni się w konto klienta.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-danger btn-sm">Usuń</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
document.getElementById('usunModal').addEventListener('show.bs.modal', function (e) {
    document.getElementById('usun-id').value = e.relatedTarget.dataset.id;
    document.getElementById('usun-nazwa').textContent = e.relatedTarget.dataset.nazwa;
});
<?php if ($otworz_modal !== ''): ?>
bootstrap.Modal.getOrCreateInstance(document.getElementById('<?= adm_h($otworz_modal) ?>')).show();
<?php endif; ?>
</script>

<?php require_once 'footer.php'; ?>
