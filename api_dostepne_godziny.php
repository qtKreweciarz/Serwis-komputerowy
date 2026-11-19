<?php
/*
 Zwraca w JSON wolne godziny rozpoczęcia wizyty dla pracownika, usługi i dnia.
 Użycie: api_dostepne_godziny.php?pracownik_id=1&usluga_id=2&data=2026-12-01
 Odpowiedź: {"godziny":["09:00","09:15", ...]}
*/
require_once 'auth.php';
wymagaj_roli('klient');
require_once 'db.php';
require_once 'rezerwacje_logika.php';

header('Content-Type: application/json; charset=utf-8');

$godziny = rez_dostepne_godziny(
    $pdo,
    (int)($_GET['pracownik_id'] ?? 0),
    (int)($_GET['usluga_id'] ?? 0),
    is_string($_GET['data'] ?? null) ? $_GET['data'] : ''
);

echo json_encode(['godziny' => $godziny]);
