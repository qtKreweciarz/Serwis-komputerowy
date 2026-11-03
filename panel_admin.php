<?php
session_start();
require_once 'db.php';


// 1. KONTROLA DOSTĘPU (Zabezpieczenie przed nieuprawnionym wejściem)

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. AKCJE: BLOKOWANIE / AKTYWACJA UŻYTKOWNIKÓW

$message = "";
if (isset($_GET['action']) && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE users SET active = 1 WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $message = "Konto użytkownika zostało aktywowane.";
    } elseif ($action === 'deactivate') {
        $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $message = "Konto użytkownika zostało zablokowane.";
    }
}


// 3. POBIERANIE STATYSTYK DO DASHBOARDU

$countClients = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'klient'")->fetch_row()[0];
$countEmployees = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'pracownik'")->fetch_row()[0];
$countServices = $conn->query("SELECT COUNT(*) FROM services")->fetch_row()[0];
$countReservations = $conn->query("SELECT COUNT(*) FROM reservations")->fetch_row()[0];

$today = date('Y-m-d');
$stmtToday = $conn->prepare("SELECT COUNT(*) FROM reservations WHERE reservation_date = ?");
$stmtToday->bind_param("s", $today);
$stmtToday->execute();
$countTodayReservations = $stmtToday->get_result()->fetch_row()[0];

// 4. POBIERANIE LISTY WSZYSTKICH UŻYTKOWNIKÓW
$resultUsers = $conn->query("SELECT id, name, surname, email, phone, role, active FROM users ORDER BY id DESC");

include 'header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Panel Administratora - Dashboard</h2>
        <a href="wyloguj.php" class="btn btn-outline-danger">Wyloguj się</a>
    </div>
    <hr>

    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- SEKCJA KAFELKÓW STATYSTYCZNYCH (Dashboard) -->
    <div class="row text-center mb-4">
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <h5 class="card-title">Klienci</h5>
                    <p class="card-text fs-3"><?= $countClients ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card text-white bg-secondary h-100">
                <div class="card-body">
                    <h5 class="card-title">Pracownicy</h5>
                    <p class="card-text fs-3"><?= $countEmployees ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-3">
            <div class="card text-white bg-info h-100">
                <div class="card-body">
                    <h5 class="card-title">Usługi</h5>
                    <p class="card-text fs-3"><?= $countServices ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-white bg-dark h-100">
                <div class="card-body">
                    <h5 class="card-title">Wszystkie wizyty</h5>
                    <p class="card-text fs-3"><?= $countReservations ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title">Rezerwacje na dziś</h5>
                    <p class="card-text fs-3"><?= $countTodayReservations ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- SEKCJA ZARZĄDZANIA UŻYTKOWNIKAMI -->
    <h3 class="mt-5 mb-3">Zarządzanie Użytkownikami</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Imię i Nazwisko</th>
                    <th>E-mail</th>
                    <th>Telefon</th>
                    <th>Rola</th>
                    <th>Status</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $resultUsers->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['name'] . ' ' . $user['surname']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['phone']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($user['role']) ?></span></td>
                        <td>
                            <?php if ($user['active']): ?>
                                <span class="badge bg-success">Aktywny</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Zablokowany</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['role'] !== 'admin'): ?>
                                <?php if ($user['active']): ?>
                                    <a href="panel_admin.php?action=deactivate&id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Czy na pewno chcesz zablokować tego użytkownika?')">Zablokuj</a>
                                <?php else: ?>
                                    <a href="panel_admin.php?action=activate&id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-success">Aktywuj</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <small class="text-muted">Brak akcji</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>