<?php
require_once 'db.php';
include 'header.php';

$errors = [];
$success = '';

//Generowanie tokena CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Błąd bezpieczeństwa: Nieprawidłowy token CSRF.";
    }

    $imie = trim($_POST['imie'] ?? '');
    $nazwisko = trim($_POST['nazwisko'] ?? '');
    $email = trim($_POST['email'] ?? '');
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
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = "Hasło musi mieć co najmniej 8 znaków.";
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
            $errors[] = "Ten adres e-mail jest już zarejestrowany.";
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO uzytkownicy (imie, nazwisko, email, haslo, telefon, rola, aktywny) VALUES (?, ?, ?, ?, ?, 'klient', 1)");
        
        if ($stmt->execute([$imie, $nazwisko, $email, $hashed_password, $telefon_do_bazy])) {
            $success = "Rejestracja przebiegła pomyślnie! Możesz się teraz zalogować.";
            unset($_SESSION['csrf_token']);
        } else {
            $errors[] = "Wystąpił błąd podczas rejestracji. Spróbuj ponownie później.";
        }
    }
}
?>

<h2>Rejestracja użytkownika</h2>

<?php if (!empty($errors)): ?>
    <div style="color: red; background: #ffe6e6; padding: 10px; margin-bottom: 15px; border: 1px solid red;">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div style="color: green; background: #e6ffe6; padding: 10px; margin-bottom: 15px; border: 1px solid green;">
        <?= htmlspecialchars($success) ?><br>
        <a href="login.php">Przejdź do logowania</a>
    </div>
<?php else: ?>
    <form action="register.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div style="margin-bottom: 10px;">
            <label for="imie">Imię:</label><br>
            <input type="text" id="imie" name="imie" value="<?= htmlspecialchars($_POST['imie'] ?? '') ?>" required style="width: 300px; padding: 5px;">
        </div>

        <div style="margin-bottom: 10px;">
            <label for="nazwisko">Nazwisko:</label><br>
            <input type="text" id="nazwisko" name="nazwisko" value="<?= htmlspecialchars($_POST['nazwisko'] ?? '') ?>" required style="width: 300px; padding: 5px;">
        </div>

        <div style="margin-bottom: 10px;">
            <label for="email">E-mail:</label><br>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required style="width: 300px; padding: 5px;">
        </div>

        <div style="margin-bottom: 10px;">
            <label for="telefon">Telefon (opcjonalnie):</label><br>
            <input type="text" id="telefon" name="telefon" value="<?= htmlspecialchars($_POST['telefon'] ?? '') ?>" style="width: 300px; padding: 5px;">
        </div>

        <div style="margin-bottom: 10px;">
            <label for="password">Hasło:</label><br>
            <input type="password" id="password" name="password" required style="width: 300px; padding: 5px;">
        </div>

        <div style="margin-bottom: 15px;">
            <label for="confirm_password">Powtórz hasło:</label><br>
            <input type="password" id="confirm_password" name="confirm_password" required style="width: 300px; padding: 5px;">
        </div>

        <button type="submit" style="padding: 8px 15px;">Zarejestruj się</button>
    </form>
<?php endif; ?>

<?php include 'footer.php'; ?>