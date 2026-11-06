<?php
/*
 Wspólny górny blok stron admina: nagłówek, komunikaty, kafelki ze statystykami i zakładki.
 Przed dołączeniem ustaw $adm_aktywna (rezerwacje, uzytkownicy, uslugi, kategorie, pracownicy).
 Strona, która go dołącza, musi na końcu zamknąć jeden </div> (kontener).
*/
$adm_aktywna = $adm_aktywna ?? '';

$countClients = $pdo->query("SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'klient'")->fetchColumn();
$countEmployees = $pdo->query("SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'pracownik'")->fetchColumn();
$countServices = $pdo->query("SELECT COUNT(*) FROM uslugi")->fetchColumn();
$countReservations = $pdo->query("SELECT COUNT(*) FROM rezerwacje")->fetchColumn();

$adm_zakladki = [
    'rezerwacje'  => ['admin_rezerwacje.php', '📅 Rezerwacje'],
    'uzytkownicy' => ['panel_admin.php?tab=users', '👥 Użytkownicy'],
    'uslugi'      => ['admin_uslugi.php', '🛠️ Usługi'],
    'kategorie'   => ['admin_kategorie.php', '🗂️ Kategorie'],
    'pracownicy'  => ['admin_pracownicy.php', '👨‍🔧 Pracownicy'],
];
?>
<div class="container my-4">
    <div class="mb-3">
        <h2>Panel Administratora</h2>
    </div>
    <hr>

    <?php adm_flash_wypisz(); ?>

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

    <ul class="nav nav-tabs nav-fill mb-4">
        <?php foreach ($adm_zakladki as $klucz => [$url, $etykieta]): ?>
            <li class="nav-item">
                <a class="nav-link fw-bold <?= $adm_aktywna === $klucz ? 'active' : '' ?>" href="<?= $url ?>"><?= $etykieta ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
