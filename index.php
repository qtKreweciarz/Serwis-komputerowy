<?php
include __DIR__ . '/header.php';

$rola = is_string($_SESSION['rola'] ?? null) ? $_SESSION['rola'] : '';
if ($rola === 'klient') {
    $linkDoRezerwacji = 'rezerwuj.php';
    $tekstRezerwacji = 'Umów wizytę';
    $tekstWyboruUslugi = 'Wybierz usługę';
} elseif ($rola === 'pracownik') {
    $linkDoRezerwacji = 'panel_pracownik.php';
    $tekstRezerwacji = 'Otwórz panel pracownika';
    $tekstWyboruUslugi = 'Przejdź do panelu';
} elseif ($rola === 'admin') {
    $linkDoRezerwacji = 'panel_admin.php';
    $tekstRezerwacji = 'Otwórz panel administratora';
    $tekstWyboruUslugi = 'Przejdź do panelu';
} else {
    $linkDoRezerwacji = 'login.php';
    $tekstRezerwacji = 'Zaloguj się i umów wizytę';
    $tekstWyboruUslugi = 'Zaloguj, aby wybrać';
}
?>

<div class="home-page">
    <section class="home-hero" aria-labelledby="home-title">
        <div class="home-hero-copy">
            <p class="home-eyebrow"><span class="home-eyebrow-mark"></span> Serwis komputerowy <span class="eyebrow-divider">/</span> rezerwacje online</p>
            <h1 id="home-title">Sprzęt ma działać.<br><span>My pomagamy do tego wrócić.</span></h1>
            <p class="home-lead">Wybierz usługę i wolny termin. Szczegóły wizyty oraz jej status sprawdzisz później w swoim panelu.</p>

            <div class="home-actions">
                <a class="btn btn-primary home-primary-action" href="<?= htmlspecialchars($linkDoRezerwacji, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($tekstRezerwacji, ENT_QUOTES, 'UTF-8') ?>
                    <span aria-hidden="true">↗</span>
                </a>
                <a class="home-secondary-action" href="#oferta">Zobacz ofertę <span aria-hidden="true">↓</span></a>
            </div>

            <div class="home-hero-index">
                <span>DIAGNOSTYKA</span>
                <span class="home-index-line" aria-hidden="true"></span>
                <span>NAPRAWA</span>
                <span class="home-index-line" aria-hidden="true"></span>
                <span>KONSERWACJA</span>
            </div>
        </div>

        <div class="home-hero-art" aria-hidden="true">
            <div class="hero-art-caption"><span>PRACOWNIA / 01</span><span>SPRZĘT • SERWIS • POWRÓT DO PRACY</span></div>
            <div class="circuit-board">
                <div class="circuit-trace trace-one"></div>
                <div class="circuit-trace trace-two"></div>
                <div class="circuit-trace trace-three"></div>
                <div class="circuit-chip chip-main"><span>SC</span></div>
                <div class="circuit-chip chip-small"></div>
                <div class="circuit-node node-one"></div>
                <div class="circuit-node node-two"></div>
                <div class="circuit-node node-three"></div>
                <div class="circuit-cross"></div>
            </div>
            <div class="hero-art-note">
                <span class="note-index">01 — 03</span>
                <span class="note-title">Od usterki<br>do sprawnego sprzętu.</span>
                <span class="note-rule"></span>
                <span class="note-small">Wybierz usługę. Zarezerwuj termin.</span>
            </div>
            <span class="hero-art-stamp">SERWIS<br>TECHNICZNY</span>
        </div>
    </section>

    <section class="home-offer" id="oferta" aria-labelledby="offer-title">
        <div class="home-section-heading">
            <div>
                <p class="home-eyebrow home-eyebrow-dark">W czym możemy pomóc</p>
                <h2 id="offer-title">Konkretna usterka.<br><span>Konkretny kierunek.</span></h2>
            </div>
            <p class="section-intro">Wybierz usługę, która najlepiej opisuje problem. Dostępne terminy i pracowników zobaczysz w formularzu rezerwacji.</p>
        </div>

        <div class="offer-grid">
            <article class="offer-item">
                <span class="offer-number">01</span>
                <div class="offer-mark offer-mark-diagnostic" aria-hidden="true"><span></span></div>
                <h3>Diagnostyka komputera</h3>
                <p>Gdy sprzęt działa wolno, nie uruchamia się albo zachowuje inaczej niż zwykle.</p>
                <a href="<?= htmlspecialchars($linkDoRezerwacji, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tekstWyboruUslugi, ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">↗</span></a>
            </article>
            <article class="offer-item">
                <span class="offer-number">02</span>
                <div class="offer-mark offer-mark-cleaning" aria-hidden="true"><span></span></div>
                <h3>Czyszczenie laptopa</h3>
                <p>Usługa konserwacyjna dla laptopa, który wymaga czyszczenia z kurzu.</p>
                <a href="<?= htmlspecialchars($linkDoRezerwacji, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tekstWyboruUslugi, ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">↗</span></a>
            </article>
            <article class="offer-item">
                <span class="offer-number">03</span>
                <div class="offer-mark offer-mark-upgrade" aria-hidden="true"><span></span></div>
                <h3>Wymiana dysku SSD</h3>
                <p>Umów wymianę dysku i sprawdź dostępny termin oraz czas trwania usługi.</p>
                <a href="<?= htmlspecialchars($linkDoRezerwacji, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tekstWyboruUslugi, ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">↗</span></a>
            </article>
            <article class="offer-item">
                <span class="offer-number">04</span>
                <div class="offer-mark offer-mark-system" aria-hidden="true"><span></span></div>
                <h3>Instalacja systemu</h3>
                <p>Wybierz instalację systemu Windows z listy usług i zarezerwuj wizytę.</p>
                <a href="<?= htmlspecialchars($linkDoRezerwacji, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tekstWyboruUslugi, ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">↗</span></a>
            </article>
        </div>
    </section>

    <section class="home-process" id="jak-to-dziala" aria-labelledby="process-title">
        <div class="process-heading">
            <p class="home-eyebrow">Prosto, od początku do końca</p>
            <h2 id="process-title">Wizyta bez<br><span>telefonicznej układanki.</span></h2>
            <p>Rezerwację i jej podstawowe informacje masz w jednym miejscu.</p>
            <a class="process-link" href="<?= htmlspecialchars($linkDoRezerwacji, ENT_QUOTES, 'UTF-8') ?>">
                Przejdź do rezerwacji <span aria-hidden="true">↗</span>
            </a>
        </div>
        <ol class="process-steps">
            <li>
                <span class="step-number">01</span>
                <div><h3>Wybierz usługę</h3><p>Sprawdź zakres i orientacyjny czas trwania w formularzu.</p></div>
            </li>
            <li>
                <span class="step-number">02</span>
                <div><h3>Dobierz termin</h3><p>Zobacz dostępnego pracownika i wybierz wolną godzinę.</p></div>
            </li>
            <li>
                <span class="step-number">03</span>
                <div><h3>Sprawdź status</h3><p>Wróć do panelu klienta, aby zobaczyć aktualny status wizyty.</p></div>
            </li>
        </ol>
    </section>

    <section class="home-bottom-callout">
        <div>
            <p class="home-eyebrow home-eyebrow-dark">Masz już wybraną usługę?</p>
            <h2>Znajdź termin,<br><span>który Ci pasuje.</span></h2>
        </div>
        <a class="btn btn-primary home-primary-action" href="<?= htmlspecialchars($linkDoRezerwacji, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($tekstRezerwacji, ENT_QUOTES, 'UTF-8') ?>
            <span aria-hidden="true">↗</span>
        </a>
    </section>
</div>

<?php include __DIR__ . '/footer.php'; ?>
