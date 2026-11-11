<?php
require_once 'auth.php';
wymagaj_roli('klient');
require_once 'db.php';
require_once 'rezerwacje_logika.php';

$kategorie = $pdo->query("SELECT * FROM kategorie_uslug ORDER BY nazwa")->fetchAll();
$uslugi = $pdo->query("SELECT u.*, k.nazwa as kategoria_nazwa FROM uslugi u JOIN kategorie_uslug k ON u.kategoria_id = k.id WHERE u.aktywna = 1 ORDER BY k.nazwa, u.nazwa")->fetchAll();

$bledy = $_SESSION['rez_bledy'] ?? [];
$komunikat = $_SESSION['rez_komunikat'] ?? null;
$stare = $_SESSION['rez_stare'] ?? [];
unset($_SESSION['rez_bledy'], $_SESSION['rez_komunikat'], $_SESSION['rez_stare']);

require_once 'header.php';
?>

<div class="container my-4">
    <h2>Zarezerwuj wizytę</h2>
    <hr>

    <?php if ($komunikat): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($komunikat) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($bledy)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach ($bledy as $b): ?>
                    <li><?= htmlspecialchars($b) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form action="rezerwacja_zapis.php" method="POST" class="card card-body bg-light shadow-sm">
        <input type="hidden" name="csrf_token" value="<?= rez_csrf_token() ?>">
        <input type="hidden" name="powrot" value="rezerwuj.php">

        <div class="mb-3">
            <label for="usluga_id" class="form-label fw-bold">1. Wybierz usługę:</label>
            <select name="usluga_id" id="usluga_id" class="form-select" required onchange="pobierzPracownikow()">
                <option value="">-- Wybierz usługę --</option>
                <?php foreach ($kategorie as $kat): ?>
                    <optgroup label="<?= htmlspecialchars($kat['nazwa']) ?>">
                        <?php foreach ($uslugi as $usl): ?>
                            <?php if ($usl['kategoria_id'] == $kat['id']): ?>
                                <option value="<?= $usl['id'] ?>" <?= (($stare['usluga_id'] ?? 0) == $usl['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($usl['nazwa']) ?> (<?= $usl['czas_trwania'] ?> min, <?= number_format($usl['cena'], 2, ',', ' ') ?> zł)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="pracownik_id" class="form-label fw-bold">2. Wybierz pracownika:</label>
            <select name="pracownik_id" id="pracownik_id" class="form-select" required onchange="pobierzDostepneGodziny()">
                <option value="">Najpierw wybierz usługę...</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="data" class="form-label fw-bold">3. Wybierz datę:</label>
            <input type="date" name="data" id="data" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($stare['data'] ?? '') ?>" onchange="pobierzDostepneGodziny()">
        </div>

        <div class="mb-3">
            <label for="godzina" class="form-label fw-bold">4. Wybierz godzinę:</label>
            <select name="godzina" id="godzina" class="form-select" required>
                <option value="">Najpierw wybierz pracownika i datę...</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="komentarz" class="form-label">Komentarz do rezerwacji (opcjonalnie):</label>
            <textarea name="komentarz" id="komentarz" class="form-control" rows="2" maxlength="500"><?= htmlspecialchars($stare['komentarz'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-success" id="btnZapisz">Potwierdź i zarezerwuj</button>
    </form>
</div>

<script>
function pobierzPracownikow() {
    const uslugaId = document.getElementById('usluga_id').value;
    const pracownikSelect = document.getElementById('pracownik_id');
    const godzinaSelect = document.getElementById('godzina');
    
    pracownikSelect.innerHTML = '<option value="">Ładowanie pracowników...</option>';
    godzinaSelect.innerHTML = '<option value="">Najpierw wybierz pracownika i datę...</option>';

    if (!uslugaId) {
        pracownikSelect.innerHTML = '<option value="">Najpierw wybierz usługę...</option>';
        return;
    }

    fetch('api_pracownicy_uslugi.php?usluga_id=' + uslugaId)
        .then(response => response.json())
        .then(data => {
            pracownikSelect.innerHTML = '<option value="">-- Wybierz pracownika --</option>';
            if (data.length === 0) {
                pracownikSelect.innerHTML = '<option value="">Brak pracowników dla tej usługi</option>';
                return;
            }
            data.forEach(p => {
                pracownikSelect.innerHTML += `<option value="${p.id}">${p.imie} ${p.nazwisko}</option>`;
            });
        })
        .catch(err => {
            console.error('Błąd:', err);
            pracownikSelect.innerHTML = '<option value="">Błąd ładowania pracowników</option>';
        });
}

function pobierzDostepneGodziny() {
    const pracownikId = document.getElementById('pracownik_id').value;
    const uslugaId = document.getElementById('usluga_id').value;
    const dataRezerwacji = document.getElementById('data').value;
    const godzinaSelect = document.getElementById('godzina');
    
    if (!pracownikId || !dataRezerwacji) return;

    godzinaSelect.innerHTML = '<option value="">Ładowanie wolnych godzin...</option>';

    fetch(`api_dostepne_godziny.php?pracownik_id=${pracownikId}&usluga_id=${uslugaId}&data=${dataRezerwacji}`)
        .then(response => response.json())
        .then(data => {
            godzinaSelect.innerHTML = '<option value="">-- Wybierz godzinę --</option>';
            if (!data.godziny || data.godziny.length === 0) {
                godzinaSelect.innerHTML = '<option value="">Brak wolnych terminów w tym dniu</option>';
                return;
            }
            data.godziny.forEach(g => {
                godzinaSelect.innerHTML += `<option value="${g}">${g}</option>`;
            });
        })
        .catch(err => {
            console.error('Błąd:', err);
            godzinaSelect.innerHTML = '<option value="">Błąd ładowania godzin</option>';
        });
}
</script>

<?php require_once 'footer.php'; ?>