<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// 1. BEZPIECZEŃSTWO: Kontrola sesji i ról
if (!isset($_SESSION['uzytkownik_id']) || $_SESSION['rola'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$currentAdminId = (int)$_SESSION['uzytkownik_id'];
$message = "";
$error = "";

// Określenie aktywnej zakładki na podstawie URL
$activeTab = $_GET['tab'] ?? 'reservations';
$przekierowania = [
    'reservations' => 'admin_rezerwacje.php',
    'employees'    => 'admin_pracownicy.php',
    'services'     => 'admin_uslugi.php',
];
if (is_string($activeTab) && isset($przekierowania[$activeTab])) {
    header('Location: ' . $przekierowania[$activeTab]);
    exit();
}
$activeTab = 'users';

// 2. AKCJE DLA UŻYTKOWNIKÓW (Blokowanie, Aktywacja, Usuwanie)
if (isset($_GET['user_action']) && isset($_GET['user_id'])) {
    $targetUserId = (int)$_GET['user_id'];
    $action = $_GET['user_action'];

    $stmtCheck = $pdo->prepare("SELECT rola FROM uzytkownicy WHERE id = ?");
    $stmtCheck->execute([$targetUserId]);
    $targetUser = $stmtCheck->fetch();

    if ($targetUser && $targetUser['rola'] === 'admin') {
        $error = "Operacja niedozwolona! Nie można modyfikować kont innych administratorów.";
    } else {
        if ($action === 'activate') {
            $pdo->prepare("UPDATE uzytkownicy SET aktywny = 1 WHERE id = ?")->execute([$targetUserId]);
            $message = "Konto zostało aktywowane.";
        } elseif ($action === 'deactivate') {
            $pdo->prepare("UPDATE uzytkownicy SET aktywny = 0 WHERE id = ?")->execute([$targetUserId]);
            $message = "Konto zostało zablokowane.";
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM uzytkownicy WHERE id = ?")->execute([$targetUserId]);
            $message = "Użytkownik został usunięty.";
        }
    }
    header("Location: panel_admin.php?tab=users&msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit();
}

// 3. EDYCJA UŻYTKOWNIKA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $uid = (int)$_POST['user_id'];
    $imie = trim($_POST['imie']);
    $nazwisko = trim($_POST['nazwisko']);
    $email = trim($_POST['email']);
    $telefon = trim($_POST['telefon']);
    $rola = $_POST['rola'];
    $nowe_haslo = $_POST['nowe_haslo'];

    if ($imie !== '' && $email !== '') {
        if (!empty($nowe_haslo)) {
            $hash = password_hash($nowe_haslo, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE uzytkownicy SET imie=?, nazwisko=?, email=?, telefon=?, rola=?, haslo=? WHERE id=?");
            $stmt->execute([$imie, $nazwisko, $email, $telefon, $rola, $hash, $uid]);
        } else {
            $stmt = $pdo->prepare("UPDATE uzytkownicy SET imie=?, nazwisko=?, email=?, telefon=?, rola=? WHERE id=?");
            $stmt->execute([$imie, $nazwisko, $email, $telefon, $rola, $uid]);
        }

        if ($rola === 'pracownik') {
            $chkPrac = $pdo->prepare("SELECT id FROM pracownicy WHERE uzytkownik_id = ?");
            $chkPrac->execute([$uid]);
            if (!$chkPrac->fetch()) {
                $insPrac = $pdo->prepare("INSERT INTO pracownicy (uzytkownik_id, opis, aktywny) VALUES (?, 'Technik / Pracownik serwisu', 1)");
                $insPrac->execute([$uid]);
            }
        } else {
            $pdo->prepare("DELETE FROM pracownicy WHERE uzytkownik_id = ?")->execute([$uid]);
        }

        $message = "Dane użytkownika zostały zaktualizowane.";
    } else {
        $error = "Imię i e-mail są wymagane.";
    }
    header("Location: panel_admin.php?tab=users&msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit();
}

// 4-6. Pracownicy i rezerwacje są obsługiwane w osobnych stronach: admin_pracownicy.php i admin_rezerwacje.php

// Komunikaty z URL
if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];

// Statystyki
$countClients = $pdo->query("SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'klient'")->fetchColumn();
$countEmployees = $pdo->query("SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'pracownik'")->fetchColumn();
$countServices = $pdo->query("SELECT COUNT(*) FROM uslugi")->fetchColumn();
$countReservations = $pdo->query("SELECT COUNT(*) FROM rezerwacje")->fetchColumn();

// Pobieranie danych
$users = $pdo->query("SELECT * FROM uzytkownicy ORDER BY id DESC")->fetchAll();


require_once 'header.php';
?>

<div class="container my-4">
    <div class="mb-3">
        <h2>Panel Administratora</h2>
    </div>
    <hr>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- STATYSTYKI -->
    <div class="row text-center mb-4">
        <div class="col-md-3 mb-2">
            <div class="card bg-primary text-white p-2">
                <small>Klienci</small>
                <div class="fs-4 fw-bold"><?= $countClients ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card bg-secondary text-white p-2">
                <small>Pracownicy</small>
                <div class="fs-4 fw-bold"><?= $countEmployees ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card bg-info text-white p-2">
                <small>Usługi</small>
                <div class="fs-4 fw-bold"><?= $countServices ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card bg-dark text-white p-2">
                <small>Rezerwacje</small>
                <div class="fs-4 fw-bold"><?= $countReservations ?></div>
            </div>
        </div>
    </div>

    <!-- NAWIGACJA W ZAKŁADKACH -->
    <ul class="nav nav-tabs nav-fill mb-4" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link fw-bold" href="admin_rezerwacje.php">📅 Rezerwacje</a>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'users' ? 'active' : '' ?> fw-bold" id="users-tab" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" role="tab">👥 Użytkownicy</button>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link fw-bold" href="admin_uslugi.php">🛠️ Usługi</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link fw-bold" href="admin_kategorie.php">🗂️ Kategorie</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link fw-bold" href="admin_pracownicy.php">👨‍🔧 Pracownicy</a>
        </li>
    </ul>

    <div class="tab-content" id="adminTabsContent">
        
        <!-- ZAKŁADKA 2: UŻYTKOWNICY -->
        <div class="tab-pane fade <?= $activeTab === 'users' ? 'show active' : '' ?>" id="tab-users" role="tabpanel">
            <h4 class="mb-3">Zarządzanie kontami użytkowników</h4>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th><th>Imię i Nazwisko</th><th>E-mail</th><th>Rola</th><th>Status</th><th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['imie'] . ' ' . $u['nazwisko']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge bg-dark"><?= $u['rola'] ?></span></td>
                                <td>
                                    <?= $u['aktywny'] ? '<span class="badge bg-success">Aktywny</span>' : '<span class="badge bg-danger">Zablokowany</span>' ?>
                                </td>
                                <td>
                                    <?php if ($u['rola'] === 'admin'): ?>
                                        <span class="badge bg-warning text-dark">Ochrona Admina</span>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>">Edytuj</button>

                                        <?php if ($u['aktywny']): ?>
                                            <a href="panel_admin.php?tab=users&user_action=deactivate&user_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-warning">Zablokuj</a>
                                        <?php else: ?>
                                            <a href="panel_admin.php?tab=users&user_action=activate&user_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-success">Aktywuj</a>
                                        <?php endif; ?>
                                        <a href="panel_admin.php?tab=users&user_action=delete&user_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Usunąć konto użytkownika?')">Usuń</a>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- MODAL EDYCJI UŻYTKOWNIKA -->
                            <?php if ($u['rola'] !== 'admin'): ?>
                            <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post" action="panel_admin.php?tab=users">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edycja użytkownika: #<?= $u['id'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <div class="mb-2">
                                                    <label class="form-label">Imię</label>
                                                    <input type="text" name="imie" class="form-control" value="<?= htmlspecialchars($u['imie']) ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Nazwisko</label>
                                                    <input type="text" name="nazwisko" class="form-control" value="<?= htmlspecialchars($u['nazwisko']) ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">E-mail</label>
                                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Telefon</label>
                                                    <input type="text" name="telefon" class="form-control" value="<?= htmlspecialchars($u['telefon'] ?? '') ?>">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Rola</label>
                                                    <select name="rola" class="form-select">
                                                        <option value="klient" <?= $u['rola'] === 'klient' ? 'selected' : '' ?>>klient</option>
                                                        <option value="pracownik" <?= $u['rola'] === 'pracownik' ? 'selected' : '' ?>>pracownik</option>
                                                    </select>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Nowe hasło (zostaw puste, aby nie zmieniać)</label>
                                                    <input type="password" name="nowe_haslo" class="form-control" placeholder="Wpisz nowe hasło...">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                                                <button type="submit" name="update_user" class="btn btn-primary btn-sm">Zapisz zmiany</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php require_once 'footer.php'; ?>