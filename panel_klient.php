<?php
require_once 'auth.php';
wymagaj_roli('klient');
require_once 'header.php';
?>

<h1>Panel klienta</h1>
<p>Zalogowano jako: <?= htmlspecialchars($_SESSION['imie']) ?></p>
<p><a href="wyloguj.php">Wyloguj</a></p>

<?php require_once 'footer.php'; ?>
