<?php
include 'header.php';
?>

<h1>Witaj w naszym Serwisie Komputerowym!</h1>
<p>Zarządzaj swoimi zgłoszeniami serwisowymi, sprawdzaj status napraw i korzystaj z naszych usług.</p>

<?php if (!isset($_SESSION['uzytkownik_id'])): ?>
    <p>
        <a href="register.php" style="padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">Załóż konto</a>
        <a href="login.php" style="padding: 10px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;">Zaloguj się</a>
    </p>
<?php endif; ?>

<?php include 'footer.php'; ?>