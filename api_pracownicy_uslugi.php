<?php
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

$usluga_id = (int)($_GET['usluga_id'] ?? 0);
if ($usluga_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.id, u.imie, u.nazwisko 
    FROM pracownicy p 
    JOIN uzytkownicy u ON p.uzytkownik_id = u.id 
    JOIN pracownicy_uslugi pu ON p.id = pu.pracownik_id 
    WHERE pu.usluga_id = ? AND p.aktywny = 1
");
$stmt->execute([$usluga_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));