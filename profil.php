<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// Zabezpieczenie: tylko dla zalogowanych
if (!isset($_SESSION['uzytkownik_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['uzytkownik_id'];
$message = "";
$error = "";

// Obsługa zapisu zmienionych danych
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $imie = trim($_POST['imie'] ?? '');
    $nazwisko = trim($_POST['nazwisko'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');

    if ($imie === '' || $nazwisko === '') {
        $error = "Imię i nazwisko nie mogą być puste.";
    } else {
        $stmt = $pdo->prepare("UPDATE uzytkownicy SET imie = ?, nazwisko = ?, telefon = ? WHERE id = ?");
        if ($stmt->execute([$imie, $nazwisko, $telefon, $userId])) {
            $_SESSION['imie'] = $imie; // Aktualizacja imienia w sesji
            $message = "Dane profilu zostały pomyślnie zaktualizowane.";
        } else {
            $error = "Wystąpił błąd podczas aktualizacji danych.";
        }
    }
}

// Pobranie aktualnych danych użytkownika z bazy
$stmt = $pdo->prepare("SELECT imie, nazwisko, email, telefon, rola, data_utworzenia FROM uzytkownicy WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

require_once 'header.php';
?>

<div class="row justify-content-center mt-4">
    <div class="col-md-8">
        
        <!-- Karta profilu -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h2 class="mb-4 fw-bold">Mój Profil</h2>

                <?php if ($message): ?>
                    <div class="alert alert-success py-2"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Informacje stałe (tylko do odczytu) -->
                <div class="row mb-3">
                    <div class="col-sm-4 fw-bold text-muted">Adres e-mail:</div>
                    <div class="col-sm-8">
                        <span class="text-dark"><?= htmlspecialchars($user['email']) ?></span>
                        <small class="text-muted d-block">E-mail służy do logowania i nie może być zmieniony.</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-sm-4 fw-bold text-muted">Rola w systemie:</div>
                    <div class="col-sm-8">
                        <span class="badge bg-secondary"><?= htmlspecialchars($user['rola']) ?></span>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-sm-4 fw-bold text-muted">Konto utworzone:</div>
                    <div class="col-sm-8 text-muted">
                        <?= htmlspecialchars($user['data_utworzenia']) ?>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Formularz edycji danych -->
                <form method="post" action="profil.php">
                    <h4 class="mb-3 fs-5 fw-bold">Edycja danych osobowych</h4>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="imie" class="form-label">Imię</label>
                            <input type="text" class="form-control" id="imie" name="imie" value="<?= htmlspecialchars($user['imie']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nazwisko" class="form-label">Nazwisko</label>
                            <input type="text" class="form-control" id="nazwisko" name="nazwisko" value="<?= htmlspecialchars($user['nazwisko']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="telefon" class="form-label">Numer telefonu</label>
                        <input type="text" class="form-control" id="telefon" name="telefon" value="<?= htmlspecialchars($user['telefon'] ?? '') ?>" placeholder="np. 123456789">
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary px-4">Zapisz zmiany</button>
                </form>
            </div>
        </div>

        <!-- Karta bezpieczeństwa / hasła -->
        <div class="card shadow-sm border-0 mb-5">
            <div class="card-body p-4">
                <h4 class="mb-3 fs-5 fw-bold">Bezpieczeństwo</h4>
                <p class="text-muted mb-3">Zarządzaj swoim bezpieczeństwem i dostępem do konta.</p>
                <a href="zmien_haslo.php" class="btn btn-outline-secondary btn-sm">Zmień hasło</a>
            </div>
        </div>

    </div>
</div>

<?php require_once 'footer.php'; ?>