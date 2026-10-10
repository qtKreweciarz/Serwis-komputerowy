<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Serwis Komputerowy</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <nav style="background: #f4f4f4; padding: 10px; margin-bottom: 20px;">
            <a href="index.php">Strona główna</a> | 
            <?php if (isset($_SESSION['uzytkownik_id'])): ?>
                <a href="profil.php">Mój profil</a> | 
                <a href="wyloguj.php">Wyloguj (<?= htmlspecialchars($_SESSION['rola'] ?? '') ?>)</a>
            <?php else: ?>
                <a href="register.php">Rejestracja</a> | 
                <a href="login.php">Logowanie</a>
            <?php endif; ?>
        </nav>
    </header>
    <main style="padding: 0 20px;">