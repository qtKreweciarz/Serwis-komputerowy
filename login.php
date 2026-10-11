<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
$errors = [];
$email = '';

require_once 'header.php';
?>

<h1>Logowanie</h1>

<form method="post" action="login.php">
    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

    <label for="password">Hasło</label>
    <input type="password" id="password" name="password" required>

    <button type="submit">Zaloguj się</button>
</form>

<?php require_once 'footer.php'; ?>