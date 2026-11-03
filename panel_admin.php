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

// 7. DODAWANIE USŁUGI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $nazwa = trim($_POST['nazwa'] ?? '');
    $kategoria_id = (int)($_POST['kategoria_id'] ?? 1);
    $cena = (float)($_POST['cena'] ?? 0);
    $czas = (int)($_POST['czas_trwania'] ?? 30);
    $opis = trim($_POST['opis'] ?? '');

    if ($nazwa !== '' && $cena > 0) {
        $stmtIns = $pdo->prepare("INSERT INTO uslugi (kategoria_id, nazwa, opis, czas_trwania, cena, aktywna) VALUES (?, ?, ?, ?, ?, 1)");
        $stmtIns->execute([$kategoria_id, $nazwa, $opis, $czas, $cena]);
        $message = "Nowa usługa została dodana.";
    } else {
        $error = "Podaj poprawną nazwę i cenę usługi.";
    }
    header("Location: panel_admin.php?tab=services&msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit();
}

// 8. EDYCJA USŁUGI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
    $sid = (int)$_POST['service_id'];
    $nazwa = trim($_POST['nazwa']);
    $kategoria_id = (int)$_POST['kategoria_id'];
    $cena = (float)$_POST['cena'];
    $czas = (int)$_POST['czas_trwania'];
    $opis = trim($_POST['opis']);

    if ($nazwa !== '' && $cena > 0) {
        $stmt = $pdo->prepare("UPDATE uslugi SET nazwa=?, kategoria_id=?, cena=?, czas_trwania=?, opis=? WHERE id=?");
        $stmt->execute([$nazwa, $kategoria_id, $cena, $czas, $opis, $sid]);
        $message = "Usługa została zaktualizowana.";
    } else {
        $error = "Błędne dane usługi.";
    }
    header("Location: panel_admin.php?tab=services&msg=" . urlencode($message) . "&err=" . urlencode($error));
    exit();
}

// 9. USUWANIE USŁUGI
if (isset($_GET['delete_service'])) {
    $serviceId = (int)$_GET['delete_service'];
    $pdo->prepare("DELETE FROM uslugi WHERE id = ?")->execute([$serviceId]);
    $message = "Usługa została usunięta.";
    header("Location: panel_admin.php?tab=services&msg=" . urlencode($message));
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
$services = $pdo->query("SELECT u.*, k.nazwa AS kategoria FROM uslugi u LEFT JOIN kategorie_uslug k ON u.kategoria_id = k.id ORDER BY u.id DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM kategorie_uslug")->fetchAll();

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
            <button class="nav-link <?= $activeTab === 'services' ? 'active' : '' ?> fw-bold" id="services-tab" data-bs-toggle="tab" data-bs-target="#tab-services" type="button" role="tab">🛠️ Usługi</button>
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

        <!-- ZAKŁADKA 3: USŁUGI -->
        <div class="tab-pane fade <?= $activeTab === 'services' ? 'show active' : '' ?>" id="tab-services" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Katalog usług</h4>
                <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addServiceForm">+ Dodaj nową usługę</button>
            </div>

            <!-- Formularz dodawania usługi -->
            <div class="collapse mb-4" id="addServiceForm">
                <div class="card card-body bg-light">
                    <h5>Dodawanie nowej usługi</h5>
                    <form method="post" action="panel_admin.php?tab=services">
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Nazwa usługi</label>
                                <input type="text" name="nazwa" class="form-control" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Kategoria</label>
                                <select name="kategoria_id" class="form-select">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nazwa']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Cena (zł)</label>
                                <input type="number" step="0.01" name="cena" class="form-control" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Czas trwania (min)</label>
                                <input type="number" name="czas_trwania" class="form-control" value="30" required>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Opis usługi</label>
                            <textarea name="opis" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" name="add_service" class="btn btn-success btn-sm">Zapisz usługę</button>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th><th>Nazwa usługi</th><th>Kategoria</th><th>Czas</th><th>Cena</th><th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $s): ?>
                            <tr>
                                <td><?= $s['id'] ?></td>
                                <td><strong><?= htmlspecialchars($s['nazwa']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($s['opis'] ?? '') ?></small></td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($s['kategoria'] ?? 'Brak') ?></span></td>
                                <td><?= $s['czas_trwania'] ?> min</td>
                                <td><?= $s['cena'] ?> zł</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editServiceModal<?= $s['id'] ?>">Edytuj</button>
                                    <a href="panel_admin.php?tab=services&delete_service=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Usunąć tę usługę?')">Usuń</a>
                                </td>
                            </tr>

                            <!-- MODAL EDYCJI USŁUGI -->
                            <div class="modal fade" id="editServiceModal<?= $s['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post" action="panel_admin.php?tab=services">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edycja usługi: #<?= $s['id'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                                                <div class="mb-2">
                                                    <label class="form-label">Nazwa</label>
                                                    <input type="text" name="nazwa" class="form-control" value="<?= htmlspecialchars($s['nazwa']) ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Kategoria</label>
                                                    <select name="kategoria_id" class="form-select">
                                                        <?php foreach ($categories as $cat): ?>
                                                            <option value="<?= $cat['id'] ?>" <?= $s['kategoria_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nazwa']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-2">
                                                        <label class="form-label">Cena (zł)</label>
                                                        <input type="number" step="0.01" name="cena" class="form-control" value="<?= $s['cena'] ?>" required>
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <label class="form-label">Czas (min)</label>
                                                        <input type="number" name="czas_trwania" class="form-control" value="<?= $s['czas_trwania'] ?>" required>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Opis</label>
                                                    <textarea name="opis" class="form-control" rows="2"><?= htmlspecialchars($s['opis'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                                                <button type="submit" name="update_service" class="btn btn-primary btn-sm">Zapisz zmiany</button>
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