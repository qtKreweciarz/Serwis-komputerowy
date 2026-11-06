<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

$errors = [];
$success = '';
$imie = '';
$nazwisko = '';
$email = '';
$telefon = '';

// Generowanie tokena CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Błąd bezpieczeństwa: Nieprawidłowy token CSRF.";
    }

    $imie = trim($_POST['imie'] ?? '');
    $nazwisko = trim($_POST['nazwisko'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $telefon = trim($_POST['telefon'] ?? '');

    if (empty($imie) || empty($nazwisko) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "Wszystkie wymagane pola muszą być wypełnione.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Niepoprawny format adresu e-mail.";
    }

    $wzor_nazwy = "/^\p{L}[\p{L} '\-]{1,49}$/u";
    if ($imie !== '' && !preg_match($wzor_nazwy, $imie)) {
        $errors[] = "Imię: 2–50 znaków, tylko litery, spacje, myślnik lub apostrof.";
    }
    if ($nazwisko !== '' && !preg_match($wzor_nazwy, $nazwisko)) {
        $errors[] = "Nazwisko: 2–50 znaków, tylko litery, spacje, myślnik lub apostrof.";
    }
    if (strlen($email) > 100) {
        $errors[] = "E-mail może mieć maksymalnie 100 znaków.";
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = "Hasło musi mieć co najmniej 6 znaków.";
    }

    $telefon_do_bazy = null;
    if ($telefon !== '') {
        $czysty = str_replace([' ', '-'], '', $telefon);
        if (!preg_match('/^(\+48)?[0-9]{9}$/', $czysty)) {
            $errors[] = "Telefon: 9 cyfr, opcjonalnie z prefiksem +48 (spacje i myślniki są dozwolone).";
        } else {
            $telefon_do_bazy = $czysty;
        }
    }

    if ($password !== $confirm_password) {
        $errors[] = "Hasła muszą być identyczne.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM uzytkownicy WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Ten adres e-mail jest już zarejestrowany w systemie.";
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, telefon, rola, aktywny) VALUES (?, ?, ?, ?, ?, 'klient', 1)");
        
        if ($stmt->execute([$imie, $nazwisko, $email, $hashed_password, $telefon_do_bazy])) {
            $success = 'Rejestracja przebiegła pomyślnie! Możesz się teraz <a href="login.php">zalogować</a>.';
            unset($_SESSION['csrf_token']);
            // Wyczyszczenie zmiennych formularza po sukcesie
            $imie = $nazwisko = $email = $telefon = '';
        } else {
            $errors[] = "Wystąpił błąd podczas rejestracji. Spróbuj ponownie później.";
        }
    }
}

require_once 'header.php';
?>

<div class="row justify-content-center mt-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4 fw-bold">Rejestracja konta</h2>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success py-2" role="alert">
                        <?= $success ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="register.php">
                    <!-- Ukryte pole z tokenem CSRF zapobiegające błędom bezpieczeństwa -->
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="imie" class="form-label">Imię *</label>
                            <input type="text" class="form-control" id="imie" name="imie" value="<?= htmlspecialchars($imie) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nazwisko" class="form-label">Nazwisko *</label>
                            <input type="text" class="form-control" id="nazwisko" name="nazwisko" value="<?= htmlspecialchars($nazwisko) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="telefon" class="form-label">Telefon (opcjonalnie)</label>
                        <input type="text" class="form-control" id="telefon" name="telefon" value="<?= htmlspecialchars($telefon) ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Hasło (min. 6 znaków) *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Powtórz hasło *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">Zarejestruj się</button>
                </form>

                <div class="text-center mt-3">
                    <small class="text-muted">Masz już konto? <a href="login.php">Zaloguj się</a></small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>