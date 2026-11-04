<?php
// Wspólna nawigacja panelu administratora.
// Przed dołączeniem ustaw:  $adm_aktywna = 'uslugi';  (rezerwacje | uzytkownicy | uslugi | kategorie | pracownicy)
$adm_aktywna = $adm_aktywna ?? '';
$adm_linki = [
    'rezerwacje'  => ['panel_admin.php?tab=reservations', '📅 Rezerwacje'],
    'uzytkownicy' => ['panel_admin.php?tab=users',        '👥 Użytkownicy'],
    'uslugi'      => ['admin_uslugi.php',                 '🛠️ Usługi'],
    'kategorie'   => ['admin_kategorie.php',              '🗂️ Kategorie'],
    'pracownicy'  => ['panel_admin.php?tab=employees',    '👨‍🔧 Pracownicy'],
];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
    <h2 class="mb-2 mb-md-0">Panel Administratora</h2>
    <a href="panel_admin.php" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
</div>
<ul class="nav nav-pills mb-4 flex-wrap gap-1">
    <?php foreach ($adm_linki as $klucz => [$url, $etykieta]): ?>
        <li class="nav-item">
            <a class="nav-link <?= $adm_aktywna === $klucz ? 'active' : '' ?>" href="<?= $url ?>"><?= $etykieta ?></a>
        </li>
    <?php endforeach; ?>
</ul>
