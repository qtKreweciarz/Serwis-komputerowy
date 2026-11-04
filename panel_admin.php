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
if ($activeTab === 'services') {
    header('Location: admin_uslugi.php');
    exit();
}

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

// 4. EDYCJA PRACOWNIKA (Pełna: Dane + Opis + Hasło)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee_full'])) {
    $user_id = (int)$_POST['user_id'];
    $pracownik_id = (int)$_POST['pracownik_id'];
    $imie = trim($_POST['imie']);
    $nazwisko = trim($_POST['nazwisko']);
    $email = trim($_POST['email']);
    $telefon = trim($_POST['telefon']);
    $opis = trim($_POST['opis']);
    $nowe_haslo = $_POST['nowe_haslo'];

    if ($imie !== '' && $email !== '') {
        if (!empty($nowe_haslo)) {
            $hash = password_hash($nowe_haslo, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE uzytkownicy SET imie=?, nazwisko=?, email=?, telefon=?, haslo=? WHERE id=?");
            $stmt->execute([$imie, $nazwisko, $email, $telefon, $hash, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE uzytkownicy SET imie=?, nazwisko=?, email=?, telefon=? WHERE id=?");
            $stmt->execute([$imie, $nazwisko, $email, $telefon, $user_id]);
        }

        $stmtPrac = $pdo->prepare("UPDATE pracownicy SET opis=? WHERE id=?");
        $stmtPrac->execute([$opis, $pracownik_id]);

        $message = "Dane pracownika zostały pomyślnie zaktualizowane.";
    } else {
        $error = "Imię i e-mail są wymagane.";
    }
    header("Location: panel_admin.php?tab=employees&msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit();
}

// 5. AKCJE DLA PRACOWNIKÓW (Zablokuj / Aktywuj / Usuń)
if (isset($_GET['emp_action']) && isset($_GET['emp_id'])) {
    $targetEmpId = (int)$_GET['emp_id'];
    $action = $_GET['emp_action'];

    $stmtU = $pdo->prepare("SELECT uzytkownik_id FROM pracownicy WHERE id = ?");
    $stmtU->execute([$targetEmpId]);
    $empRow = $stmtU->fetch();

    if ($empRow) {
        $uId = $empRow['uzytkownik_id'];
        if ($action === 'activate') {
            $pdo->prepare("UPDATE uzytkownicy SET aktywny = 1 WHERE id = ?")->execute([$uId]);
            $message = "Konto pracownika zostało aktywowane.";
        } elseif ($action === 'deactivate') {
            $pdo->prepare("UPDATE uzytkownicy SET aktywny = 0 WHERE id = ?")->execute([$uId]);
            $message = "Konto pracownika zostało zablokowane.";
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM pracownicy WHERE id = ?")->execute([$targetEmpId]);
            $pdo->prepare("UPDATE uzytkownicy SET rola = 'klient' WHERE id = ?")->execute([$uId]);
            $message = "Pracownik został usunięty z personelu.";
        }
    }
    header("Location: panel_admin.php?tab=employees&msg=" . urlencode($message));
    exit();
}

// 6. AKCJE DLA REZERWACJI
if (isset($_GET['res_action']) && isset($_GET['res_id'])) {
    $resId = (int)$_GET['res_id'];
    $newStatus = $_GET['res_action'];
    $allowed = ['oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana'];

    if (in_array($newStatus, $allowed)) {
        $pdo->prepare("UPDATE rezerwacje SET status = ? WHERE id = ?")->execute([$newStatus, $resId]);
        
        $log = $pdo->prepare("INSERT INTO log_administracyjny (admin_id, akcja, opis) VALUES (?, 'zmiana_statusu', ?)");
        $log->execute([$currentAdminId, "Zmiana statusu rezerwacji #$resId na $newStatus"]);
        
        $message = "Status rezerwacji #$resId zmieniony na: $newStatus.";
    }
    header("Location: panel_admin.php?tab=reservations&msg=" . urlencode($message));
    exit();
}


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

$reservations = $pdo->query("
    SELECT r.*, k.imie AS k_imie, k.nazwisko AS k_nazwisko,
           pu.imie AS p_imie, pu.nazwisko AS p_nazwisko,
           u.nazwa AS usluga_nazwa, u.cena
    FROM rezerwacje r
    JOIN uzytkownicy k ON r.klient_id = k.id
    JOIN pracownicy pr ON r.pracownik_id = pr.id
    JOIN uzytkownicy pu ON pr.uzytkownik_id = pu.id
    JOIN uslugi u ON r.usluga_id = u.id
    ORDER BY r.data_rezerwacji DESC
")->fetchAll();

$employees = $pdo->query("
    SELECT p.id AS pracownik_id, u.id AS uzytkownik_id, u.imie, u.nazwisko, u.email, u.telefon, u.aktywny, p.opis 
    FROM pracownicy p 
    JOIN uzytkownicy u ON p.uzytkownik_id = u.id
    ORDER BY p.id DESC
")->fetchAll();

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
            <button class="nav-link <?= $activeTab === 'reservations' ? 'active' : '' ?> fw-bold" id="res-tab" data-bs-toggle="tab" data-bs-target="#tab-reservations" type="button" role="tab">📅 Rezerwacje</button>
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
            <button class="nav-link <?= $activeTab === 'employees' ? 'active' : '' ?> fw-bold" id="emp-tab" data-bs-toggle="tab" data-bs-target="#tab-employees" type="button" role="tab">👨‍🔧 Pracownicy</button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabsContent">
        
        <!-- ZAKŁADKA 1: REZERWACJE -->
        <div class="tab-pane fade <?= $activeTab === 'reservations' ? 'show active' : '' ?>" id="tab-reservations" role="tabpanel">
            <h4 class="mb-3">Zarządzanie wizytami</h4>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th><th>Klient</th><th>Usługa</th><th>Pracownik</th><th>Data</th><th>Status</th><th>Akcja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $r): ?>
                            <tr>
                                <td>#<?= $r['id'] ?></td>
                                <td><strong><?= htmlspecialchars($r['k_imie'] . ' ' . $r['k_nazwisko']) ?></strong></td>
                                <td><?= htmlspecialchars($r['usluga_nazwa']) ?> (<?= $r['cena'] ?> zł)</td>
                                <td><?= htmlspecialchars($r['p_imie'] . ' ' . $r['p_nazwisko']) ?></td>
                                <td><?= $r['data_rezerwacji'] ?> <br><small class="text-muted"><?= substr($r['godzina_od'], 0, 5) ?></small></td>
                                <td><span class="badge bg-secondary"><?= $r['status'] ?></span></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="panel_admin.php?tab=reservations&res_action=potwierdzona&res_id=<?= $r['id'] ?>" class="btn btn-outline-success">Potwierdź</a>
                                        <a href="panel_admin.php?tab=reservations&res_action=zrealizowana&res_id=<?= $r['id'] ?>" class="btn btn-outline-info">Zrealizuj</a>
                                        <a href="panel_admin.php?tab=reservations&res_action=anulowana&res_id=<?= $r['id'] ?>" class="btn btn-outline-danger">Anuluj</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

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

        <!-- ZAKŁADKA 4: PRACOWNICY -->
        <div class="tab-pane fade <?= $activeTab === 'employees' ? 'show active' : '' ?>" id="tab-employees" role="tabpanel">
            <h4 class="mb-3">Zarządzanie pracownikami serwisu</h4>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th><th>Imię i Nazwisko</th><th>E-mail</th><th>Telefon</th><th>Specjalizacja</th><th>Status</th><th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $e): ?>
                            <tr>
                                <td>#<?= $e['pracownik_id'] ?></td>
                                <td><strong><?= htmlspecialchars($e['imie'] . ' ' . $e['nazwisko']) ?></strong></td>
                                <td><?= htmlspecialchars($e['email']) ?></td>
                                <td><?= htmlspecialchars($e['telefon'] ?? 'Brak') ?></td>
                                <td><?= htmlspecialchars($e['opis'] ?? 'Brak opisu') ?></td>
                                <td>
                                    <?= $e['aktywny'] ? '<span class="badge bg-success">Aktywny</span>' : '<span class="badge bg-danger">Zablokowany</span>' ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editEmployeeModal<?= $e['pracownik_id'] ?>">Edytuj</button>

                                    <?php if ($e['aktywny']): ?>
                                        <a href="panel_admin.php?tab=employees&emp_action=deactivate&emp_id=<?= $e['pracownik_id'] ?>" class="btn btn-sm btn-outline-warning">Zablokuj</a>
                                    <?php else: ?>
                                        <a href="panel_admin.php?tab=employees&emp_action=activate&emp_id=<?= $e['pracownik_id'] ?>" class="btn btn-sm btn-outline-success">Aktywuj</a>
                                    <?php endif; ?>
                                    <a href="panel_admin.php?tab=employees&emp_action=delete&emp_id=<?= $e['pracownik_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Usunąć tego pracownika z personelu?')">Usuń</a>
                                </td>
                            </tr>

                            <!-- MODAL EDYCJI PRACOWNIKA -->
                            <div class="modal fade" id="editEmployeeModal<?= $e['pracownik_id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post" action="panel_admin.php?tab=employees">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edycja pracownika: <?= htmlspecialchars($e['imie'] . ' ' . $e['nazwisko']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="user_id" value="<?= $e['uzytkownik_id'] ?>">
                                                <input type="hidden" name="pracownik_id" value="<?= $e['pracownik_id'] ?>">

                                                <div class="mb-2">
                                                    <label class="form-label">Imię</label>
                                                    <input type="text" name="imie" class="form-control" value="<?= htmlspecialchars($e['imie']) ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Nazwisko</label>
                                                    <input type="text" name="nazwisko" class="form-control" value="<?= htmlspecialchars($e['nazwisko']) ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">E-mail</label>
                                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($e['email']) ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Telefon</label>
                                                    <input type="text" name="telefon" class="form-control" value="<?= htmlspecialchars($e['telefon'] ?? '') ?>">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Opis / Specjalizacja zawodowa</label>
                                                    <textarea name="opis" class="form-control" rows="2" required><?= htmlspecialchars($e['opis'] ?? '') ?></textarea>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Nowe hasło (zostaw puste, aby nie zmieniać)</label>
                                                    <input type="password" name="nowe_haslo" class="form-control" placeholder="Wpisz nowe hasło...">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                                                <button type="submit" name="update_employee_full" class="btn btn-primary btn-sm">Zapisz zmiany</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php require_once 'footer.php'; ?>