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
            $stmt = $pdo->prepare('SELECT id, imie, haslo, rola, aktywny, ostatnia_aktywnosc FROM uzytkownicy WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['haslo'])) {
                if ($user['aktywny'] == 0) {
                    $errors[] = 'To konto zostało zablokowane lub jest nieaktywne.';
                } else {
                    $pdo->prepare('DELETE FROM proby_logowania WHERE email = ?')->execute([$email]);
                    
                    $update = $pdo->prepare('UPDATE uzytkownicy SET ostatnia_aktywnosc = NOW() WHERE id = ?');
                    $update->execute([$user['id']]);

                    session_regenerate_id(true);
                    $_SESSION['uzytkownik_id'] = $user['id'];
                    $_SESSION['imie'] = $user['imie'];
                    $_SESSION['rola'] = $user['rola'];
                    header('Location: ' . cel_przekierowania($user['rola'], $panele));
                    exit;
                }
            } else {
                $pdo->prepare('INSERT INTO proby_logowania (email, ip) VALUES (?, ?)')->execute([$email, $_SERVER['REMOTE_ADDR']]);
                $errors[] = 'Nieprawidłowy e-mail lub hasło.';
            }
        }
    }
}
require_once 'header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4 fw-bold">Logowanie</h2>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger py-2" role="alert">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endforeach; ?>

                <form method="post" action="login.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Hasło</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">Zaloguj się</button>
                </form>

                <div class="text-center mt-3">
                    <small class="text-muted">Nie masz konta? <a href="register.php">Zarejestruj się</a></small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>