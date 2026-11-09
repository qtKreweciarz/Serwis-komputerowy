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
$errors = [];
$success = "";

// Generowanie tokena CSRF, jeśli nie istnieje
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sprawdzenie tokena CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Błąd bezpieczeństwa: Nieprawidłowy token CSRF.";
    }

    $stare_haslo = $_POST['stare_haslo'] ?? '';
    $nowe_haslo = $_POST['nowe_haslo'] ?? '';
    $powtorz_haslo = $_POST['powtorz_haslo'] ?? '';

    if (empty($stare_haslo) || empty($nowe_haslo) || empty($powtorz_haslo)) {
        $errors[] = "Wszystkie pola są wymagane.";
    } elseif (strlen($nowe_haslo) < 8) {
        $errors[] = "Nowe hasło musi mieć co najmniej 8 znaków.";
    } elseif ($nowe_haslo !== $powtorz_haslo) {
        $errors[] = "Nowe hasła muszą się zgadzać.";
    } else {
        // Pobranie obecnego hasła z bazy
        $stmt = $pdo->prepare("SELECT haslo FROM uzytkownicy WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($stare_haslo, $user['haslo'])) {
            // Zmiana hasła
            $nowy_hash = password_hash($nowe_haslo, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE uzytkownicy SET haslo = ? WHERE id = ?");
            if ($update->execute([$nowy_hash, $userId])) {
                $success = "Hasło zostało pomyślnie zmienione!";
                // Odświeżenie identyfikatora sesji ze względów bezpieczeństwa
                session_regenerate_id(true);
                // Nowy token CSRF
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $errors[] = "Wystąpił błąd podczas aktualizacji hasła w bazie danych.";
            }
        } else {
            $errors[] = "Podane stare hasło jest niepoprawne.";
        }
    }
}

require_once 'header.php';
?>

<div class="row justify-content-center mt-4 mb-5">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4 fw-bold">Zmiana hasła</h2>

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
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="zmiana_hasla.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-3">
                        <label for="stare_haslo" class="form-label">Stare hasło *</label>
                        <input type="password" class="form-control" id="stare_haslo" name="stare_haslo" required>
                    </div>

                    <div class="mb-3">
                        <label for="nowe_haslo" class="form-label">Nowe hasło (min. 8 znaków) *</label>
                        <input type="password" class="form-control" id="nowe_haslo" name="nowe_haslo" required>
                    </div>

                    <div class="mb-3">
                        <label for="powtorz_haslo" class="form-label">Powtórz nowe hasło *</label>
                        <input type="password" class="form-control" id="powtorz_haslo" name="powtorz_haslo" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary py-2">Zmień hasło</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <a href="profil.php" class="text-decoration-none text-muted">← Wróć do profilu</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>