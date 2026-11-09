<?php
date_default_timezone_set('Europe/Warsaw');

const REZ_KROK_MIN = 15;
const REZ_MAX_DNI_NAPRZOD = 365;
const REZ_STATUS_POCZATKOWY = 'oczekujaca';

function rez_na_minuty(string $czas): int
{
    return (int)substr($czas, 0, 2) * 60 + (int)substr($czas, 3, 2);
}

function rez_z_minut(int $minuty): string
{
    return sprintf('%02d:%02d', intdiv($minuty, 60), $minuty % 60);
}

function rez_nakladaja_sie(int $od1, int $do1, int $od2, int $do2): bool
{
    return $od1 < $do2 && $do1 > $od2;
}

function rez_poprawna_data($tekst): bool
{
    if (!is_string($tekst)) {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $tekst);
    return $d !== false && $d->format('Y-m-d') === $tekst;
}

function rez_poprawna_godzina($tekst): bool
{
    return is_string($tekst) && preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $tekst) === 1;
}

function rez_pobierz_usluge(PDO $pdo, int $usluga_id)
{
    $st = $pdo->prepare('SELECT id, nazwa, czas_trwania, cena FROM uslugi WHERE id = ? AND aktywna = 1');
    $st->execute([$usluga_id]);
    return $st->fetch();
}

function rez_pobierz_pracownika(PDO $pdo, int $pracownik_id)
{
    $st = $pdo->prepare('
        SELECT p.id, u.imie, u.nazwisko
        FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id
        WHERE p.id = ? AND p.aktywny = 1 AND u.aktywny = 1
    ');
    $st->execute([$pracownik_id]);
    return $st->fetch();
}

function rez_pracownik_wykonuje(PDO $pdo, int $pracownik_id, int $usluga_id): bool
{
    $st = $pdo->prepare('SELECT 1 FROM pracownicy_uslugi WHERE pracownik_id = ? AND usluga_id = ?');
    $st->execute([$pracownik_id, $usluga_id]);
    return (bool)$st->fetchColumn();
}

function rez_kontekst_dnia(PDO $pdo, int $pracownik_id, string $data): array
{
    $dzien = (int)date('N', strtotime($data));

    $st = $pdo->prepare('SELECT godzina_od, godzina_do FROM dostepnosc_pracownikow WHERE pracownik_id = ? AND dzien_tygodnia = ?');
    $st->execute([$pracownik_id, $dzien]);
    $godziny = $st->fetch();

    $st = $pdo->prepare('SELECT 1 FROM urlopy_pracownikow WHERE pracownik_id = ? AND data_od <= ? AND data_do >= ?');
    $st->execute([$pracownik_id, $data, $data]);
    $urlop = (bool)$st->fetchColumn();

    $st = $pdo->prepare("SELECT godzina_od, godzina_do FROM rezerwacje WHERE pracownik_id = ? AND data_rezerwacji = ? AND status <> 'anulowana'");
    $st->execute([$pracownik_id, $data]);
    $zajete = [];
    foreach ($st->fetchAll() as $r) {
        $zajete[] = [rez_na_minuty($r['godzina_od']), rez_na_minuty($r['godzina_do'])];
    }

    return [
        'okno'   => $godziny ? [rez_na_minuty($godziny['godzina_od']), rez_na_minuty($godziny['godzina_do'])] : null,
        'urlop'  => $urlop,
        'zajete' => $zajete,
    ];
}

function rez_bledy_terminu(array $kontekst, string $data, int $od, int $do): array
{
    $bledy = [];
    $start = strtotime($data . ' ' . rez_z_minut($od));

    if ($start <= time()) {
        return ['Nie można rezerwować terminu w przeszłości.'];
    }
    if ($data > date('Y-m-d', strtotime('+' . REZ_MAX_DNI_NAPRZOD . ' days'))) {
        return ['Rezerwacja jest możliwa najwyżej ' . REZ_MAX_DNI_NAPRZOD . ' dni naprzód.'];
    }
    if ($kontekst['urlop']) {
        return ['Pracownik ma urlop w tym dniu. Wybierz inny dzień.'];
    }
    if ($kontekst['okno'] === null) {
        return ['Pracownik nie pracuje w tym dniu tygodnia. Wybierz inny dzień.'];
    }

    [$praca_od, $praca_do] = $kontekst['okno'];
    if ($od < $praca_od || $do > $praca_do) {
        return ['Wizyta (' . rez_z_minut($od) . '–' . rez_z_minut($do) . ') wykracza poza godziny pracy pracownika ('
            . rez_z_minut($praca_od) . '–' . rez_z_minut($praca_do) . ').'];
    }

    foreach ($kontekst['zajete'] as [$z_od, $z_do]) {
        if (rez_nakladaja_sie($od, $do, $z_od, $z_do)) {
            return ['Ten termin jest już zajęty. Wybierz inną godzinę.'];
        }
    }

    return $bledy;
}

function rez_dostepne_godziny(PDO $pdo, int $pracownik_id, int $usluga_id, string $data): array
{
    if (!rez_poprawna_data($data)) {
        return [];
    }
    $usluga = rez_pobierz_usluge($pdo, $usluga_id);
    if (!$usluga || !rez_pobierz_pracownika($pdo, $pracownik_id) || !rez_pracownik_wykonuje($pdo, $pracownik_id, $usluga_id)) {
        return [];
    }

    $kontekst = rez_kontekst_dnia($pdo, $pracownik_id, $data);
    if ($kontekst['okno'] === null) {
        return [];
    }

    $czas = (int)$usluga['czas_trwania'];
    [$praca_od, $praca_do] = $kontekst['okno'];
    $godziny = [];

    for ($od = $praca_od; $od + $czas <= $praca_do; $od += REZ_KROK_MIN) {
        if (!rez_bledy_terminu($kontekst, $data, $od, $od + $czas)) {
            $godziny[] = rez_z_minut($od);
        }
    }
    return $godziny;
}

function rez_utworz(PDO $pdo, int $klient_id, array $dane): array
{
    $usluga_id = (int)($dane['usluga_id'] ?? 0);
    $pracownik_id = (int)($dane['pracownik_id'] ?? 0);
    $data = $dane['data'] ?? '';
    $godzina = $dane['godzina'] ?? '';
    $komentarz = is_string($dane['komentarz'] ?? null) ? trim($dane['komentarz']) : '';
    $bledy = [];

    if (!rez_poprawna_data($data)) {
        $bledy[] = 'Podaj poprawną datę.';
    }
    if (!rez_poprawna_godzina($godzina)) {
        $bledy[] = 'Podaj poprawną godzinę (np. 10:30).';
    }
    if (mb_strlen($komentarz) > 500) {
        $bledy[] = 'Komentarz może mieć maksymalnie 500 znaków.';
    }

    $usluga = $usluga_id > 0 ? rez_pobierz_usluge($pdo, $usluga_id) : false;
    if (!$usluga) {
        $bledy[] = 'Wybierz dostępną usługę.';
    }

    $pracownik = $pracownik_id > 0 ? rez_pobierz_pracownika($pdo, $pracownik_id) : false;
    if (!$pracownik) {
        $bledy[] = 'Wybierz dostępnego pracownika.';
    } elseif ($usluga && !rez_pracownik_wykonuje($pdo, $pracownik_id, $usluga_id)) {
        $bledy[] = 'Wybrany pracownik nie wykonuje tej usługi.';
    }

    $st = $pdo->prepare("SELECT 1 FROM uzytkownicy WHERE id = ? AND rola = 'klient' AND aktywny = 1");
    $st->execute([$klient_id]);
    if (!$st->fetchColumn()) {
        $bledy[] = 'Tylko aktywny klient może tworzyć rezerwacje.';
    }

    if ($bledy) {
        return ['ok' => false, 'bledy' => $bledy];
    }

    $od = rez_na_minuty($godzina);
    $do = $od + (int)$usluga['czas_trwania'];
    if ($do > 24 * 60) {
        return ['ok' => false, 'bledy' => ['Wizyta nie może kończyć się po północy.']];
    }

    $bledy = rez_bledy_terminu(rez_kontekst_dnia($pdo, $pracownik_id, $data), $data, $od, $do);
    if ($bledy) {
        return ['ok' => false, 'bledy' => $bledy];
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('SELECT id FROM pracownicy WHERE id = ? FOR UPDATE')->execute([$pracownik_id]);

        $st = $pdo->prepare("
            SELECT id FROM rezerwacje
            WHERE pracownik_id = ? AND data_rezerwacji = ? AND status <> 'anulowana'
              AND godzina_od < ? AND godzina_do > ?
            LIMIT 1 FOR UPDATE
        ");
        $st->execute([$pracownik_id, $data, rez_z_minut($do) . ':00', rez_z_minut($od) . ':00']);
        if ($st->fetch()) {
            $pdo->rollBack();
            return ['ok' => false, 'bledy' => ['Ten termin został właśnie zajęty przez inną osobę. Wybierz inną godzinę.']];
        }

        $pdo->prepare('
            INSERT INTO rezerwacje (klient_id, pracownik_id, usluga_id, data_rezerwacji, godzina_od, godzina_do, status, komentarz)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $klient_id, $pracownik_id, $usluga_id, $data,
            rez_z_minut($od) . ':00', rez_z_minut($do) . ':00', REZ_STATUS_POCZATKOWY, $komentarz !== '' ? $komentarz : null,
        ]);
        $id = (int)$pdo->lastInsertId();

        $pdo->prepare('INSERT INTO historia_statusow_rezerwacji (rezerwacja_id, status_poprzedni, status_nowy, zmienione_przez) VALUES (?, NULL, ?, ?)')
            ->execute([$id, REZ_STATUS_POCZATKOWY, $klient_id]);

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('rez_utworz: ' . $e->getMessage());
        $kod = $e->errorInfo[1] ?? 0;
        $komunikat = in_array($kod, [1205, 1213], true)
            ? 'Serwer jest chwilowo zajęty. Spróbuj ponownie za chwilę.'
            : 'Nie udało się zapisać rezerwacji. Spróbuj ponownie.';
        return ['ok' => false, 'bledy' => [$komunikat]];
    }

    return [
        'ok' => true,
        'id' => $id,
        'usluga' => $usluga['nazwa'],
        'pracownik' => $pracownik['imie'] . ' ' . $pracownik['nazwisko'],
        'data' => $data,
        'od' => rez_z_minut($od),
        'do' => rez_z_minut($do),
    ];
}

function rez_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function rez_csrf_ok(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
