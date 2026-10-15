<?php
require_once 'auth.php';
require_once 'db.php';
wymagaj_logowania();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$id = $_SESSION['uzytkownik_id'];

$stmt = $pdo->prepare('SELECT imie, nazwisko, email, telefon, rola FROM uzytkownicy WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: wyloguj.php');
    exit;
}

$imie = $user['imie'];
$nazwisko = $user['nazwisko'];
$telefon = $user['telefon'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Błąd bezpieczeństwa: nieprawidłowy token CSRF.';
    }

    $imie = trim($_POST['imie'] ?? '');
    $nazwisko = trim($_POST['nazwisko'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');

    $wzor_nazwy = "/^\p{L}[\p{L} '\-]{1,49}$/u";

    if (!preg_match($wzor_nazwy, $imie)) {
        $errors[] = 'Imię: 2–50 znaków, tylko litery, spacje, myślnik lub apostrof.';
    }
    if (!preg_match($wzor_nazwy, $nazwisko)) {
        $errors[] = 'Nazwisko: 2–50 znaków, tylko litery, spacje, myślnik lub apostrof.';
    }

    $telefon_do_bazy = null;
    if ($telefon !== '') {
        $czysty = str_replace([' ', '-'], '', $telefon);
        if (!preg_match('/^(\+48)?[0-9]{9}$/', $czysty)) {
            $errors[] = 'Telefon: 9 cyfr, opcjonalnie z prefiksem +48 (spacje i myślniki są dozwolone).';
        } else {
            $telefon_do_bazy = $czysty;
        }
    }

    if (empty($errors)) {
        $upd = $pdo->prepare('UPDATE uzytkownicy SET imie = ?, nazwisko = ?, telefon = ? WHERE id = ?');
        $upd->execute([$imie, $nazwisko, $telefon_do_bazy, $id]);
        $_SESSION['imie'] = $imie;
        $_SESSION['flash'] = 'Dane zostały zapisane.';
        header('Location: profil.php');
        exit;
    }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

require_once 'header.php';
?>

<h1>Mój profil</h1>

<?php if ($flash): ?>
    <p class="success" style="color: green;"><?= htmlspecialchars($flash) ?></p>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
    <p class="error" style="color: red;"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<p>E-mail: <?= htmlspecialchars($user['email']) ?></p>
<p>Rola: <?= htmlspecialchars($user['rola']) ?></p>

<form method="post" action="profil.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <label for="imie">Imię</label>
    <input type="text" id="imie" name="imie" value="<?= htmlspecialchars($imie) ?>" maxlength="50" required>

    <label for="nazwisko">Nazwisko</label>
    <input type="text" id="nazwisko" name="nazwisko" value="<?= htmlspecialchars($nazwisko) ?>" maxlength="50" required>

    <label for="telefon">Telefon (opcjonalnie)</label>
    <input type="text" id="telefon" name="telefon" value="<?= htmlspecialchars($telefon) ?>" maxlength="20">

    <button type="submit">Zapisz</button>
</form>

<p><a href="zmiana_hasla.php">Zmień hasło</a></p>

<?php require_once 'footer.php'; ?>
