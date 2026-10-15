<?php
require_once 'auth.php';
require_once 'db.php';
wymagaj_logowania();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Błąd bezpieczeństwa: nieprawidłowy token CSRF.';
    }

    $stare = $_POST['stare_haslo'] ?? '';
    $nowe = $_POST['nowe_haslo'] ?? '';
    $powtorz = $_POST['powtorz_haslo'] ?? '';

    if ($stare === '' || $nowe === '' || $powtorz === '') {
        $errors[] = 'Wypełnij wszystkie pola.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT haslo FROM uzytkownicy WHERE id = ?');
        $stmt->execute([$_SESSION['uzytkownik_id']]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($stare, $hash)) {
            $errors[] = 'Stare hasło jest nieprawidłowe.';
        }
    }

    if (empty($errors)) {
        if (strlen($nowe) < 8) {
            $errors[] = 'Nowe hasło musi mieć co najmniej 8 znaków.';
        }
        if ($nowe !== $powtorz) {
            $errors[] = 'Nowe hasła muszą być identyczne.';
        }
        if ($nowe === $stare) {
            $errors[] = 'Nowe hasło musi być inne niż stare.';
        }
    }

    if (empty($errors)) {
        $upd = $pdo->prepare('UPDATE uzytkownicy SET haslo = ? WHERE id = ?');
        $upd->execute([password_hash($nowe, PASSWORD_DEFAULT), $_SESSION['uzytkownik_id']]);
        session_regenerate_id(true);
        $success = 'Hasło zostało zmienione.';
    }
}

require_once 'header.php';
?>

<h1>Zmiana hasła</h1>

<?php foreach ($errors as $error): ?>
    <p class="error" style="color: red;"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if ($success): ?>
    <p class="success" style="color: green;"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<form method="post" action="zmiana_hasla.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <label for="stare_haslo">Stare hasło</label>
    <input type="password" id="stare_haslo" name="stare_haslo" required>

    <label for="nowe_haslo">Nowe hasło (min. 8 znaków)</label>
    <input type="password" id="nowe_haslo" name="nowe_haslo" required>

    <label for="powtorz_haslo">Powtórz nowe hasło</label>
    <input type="password" id="powtorz_haslo" name="powtorz_haslo" required>

    <button type="submit">Zmień hasło</button>
</form>

<p><a href="profil.php">Wróć do profilu</a></p>

<?php require_once 'footer.php'; ?>
