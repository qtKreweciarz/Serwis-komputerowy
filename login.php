<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
$errors = [];
$email = '';

$panele = [
    'klient' => 'panel_klient.php',
    'pracownik' => 'panel_pracownik.php',
    'admin' => 'panel_admin.php'
];

function cel_przekierowania($rola, $panele) {
    $plik = $panele[$rola] ?? 'index.php';
    return file_exists(__DIR__ . '/' . $plik) ? $plik : 'index.php';
}

if (isset($_SESSION['uzytkownik_id'])) {
    header('Location: ' . cel_przekierowania($_SESSION['rola'], $panele));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Podaj e-mail i hasło.';
    } else {
        $limit = $pdo->prepare('SELECT COUNT(*) FROM proby_logowania WHERE email = ? AND data_proby > (NOW() - INTERVAL 15 MINUTE)');
        $limit->execute([$email]);

        if ($limit->fetchColumn() >= 5) {
            $errors[] = 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za 15 minut.';
        } else {
            $stmt = $pdo->prepare('SELECT id, imie, haslo, rola, aktywny FROM uzytkownicy WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['aktywny'] == 1 && password_verify($password, $user['haslo'])) {
                $pdo->prepare('DELETE FROM proby_logowania WHERE email = ?')->execute([$email]);
                session_regenerate_id(true);
                $_SESSION['uzytkownik_id'] = $user['id'];
                $_SESSION['imie'] = $user['imie'];
                $_SESSION['rola'] = $user['rola'];
                header('Location: ' . cel_przekierowania($user['rola'], $panele));
                exit;
            }

            $pdo->prepare('INSERT INTO proby_logowania (email, ip) VALUES (?, ?)')->execute([$email, $_SERVER['REMOTE_ADDR']]);
            $errors[] = 'Nieprawidłowy e-mail lub hasło.';
        }
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