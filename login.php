<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Podaj e-mail i hasło.';
    } else {
        $stmt = $pdo->prepare('SELECT id, imie, haslo, rola, aktywny FROM uzytkownicy WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['aktywny'] == 1 && password_verify($password, $user['haslo'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['imie'];
            $_SESSION['role'] = $user['rola'];
            header('Location: index.php');
            exit;
        }

        $errors[] = 'Nieprawidłowy e-mail lub hasło.';
    }
}
require_once 'header.php';
?>

<h1>Logowanie</h1>

<?php foreach ($errors as $error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>
<form method="post" action="login.php">
    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

    <label for="password">Hasło</label>
    <input type="password" id="password" name="password" required>

    <button type="submit">Zaloguj się</button>
</form>

<?php require_once 'footer.php'; ?>