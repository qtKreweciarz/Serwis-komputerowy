<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

if (isset($_SESSION['uzytkownik_id'])) {
    $stmt = $pdo->prepare('UPDATE uzytkownicy SET aktywny = 1, ostatnia_aktywnosc = NULL WHERE id = ?');
    $stmt->execute([$_SESSION['uzytkownik_id']]);
}

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;