-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Wrz 08, 2026 at 07:22 PM
-- Wersja serwera: 10.4.32-MariaDB
-- Wersja PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rezerwacje_uslug`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `dostepnosc_pracownikow`
--

CREATE TABLE `dostepnosc_pracownikow` (
  `id` int(11) NOT NULL,
  `pracownik_id` int(11) NOT NULL,
  `dzien_tygodnia` tinyint(4) NOT NULL,
  `godzina_od` time NOT NULL,
  `godzina_do` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dostepnosc_pracownikow`
--

INSERT INTO `dostepnosc_pracownikow` (`id`, `pracownik_id`, `dzien_tygodnia`, `godzina_od`, `godzina_do`) VALUES
(1, 1, 1, '09:00:00', '17:00:00'),
(2, 1, 2, '09:00:00', '17:00:00'),
(3, 1, 3, '09:00:00', '17:00:00');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `kategorie_uslug`
--

CREATE TABLE `kategorie_uslug` (
  `id` int(11) NOT NULL,
  `nazwa` varchar(100) NOT NULL,
  `opis` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategorie_uslug`
--

INSERT INTO `kategorie_uslug` (`id`, `nazwa`, `opis`) VALUES
(1, 'Naprawa sprzętu', 'Naprawy komputerów, laptopów i podzespołów'),
(2, 'Diagnostyka', 'Diagnoza usterek sprzętowych i programowych'),
(3, 'Instalacja i konfiguracja', 'Instalacja systemów, sterowników i oprogramowania');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `pracownicy`
--

CREATE TABLE `pracownicy` (
  `id` int(11) NOT NULL,
  `uzytkownik_id` int(11) NOT NULL,
  `opis` text DEFAULT NULL,
  `aktywny` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pracownicy`
--

INSERT INTO `pracownicy` (`id`, `uzytkownik_id`, `opis`, `aktywny`) VALUES
(1, 2, 'Technik komputerowy z 5 letnim doświadczeniem', 1);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `pracownicy_uslugi`
--

CREATE TABLE `pracownicy_uslugi` (
  `pracownik_id` int(11) NOT NULL,
  `usluga_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pracownicy_uslugi`
--

INSERT INTO `pracownicy_uslugi` (`pracownik_id`, `usluga_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `rezerwacje`
--

CREATE TABLE `rezerwacje` (
  `id` int(11) NOT NULL,
  `klient_id` int(11) NOT NULL,
  `pracownik_id` int(11) NOT NULL,
  `usluga_id` int(11) NOT NULL,
  `data_rezerwacji` date NOT NULL,
  `godzina_od` time NOT NULL,
  `godzina_do` time NOT NULL,
  `status` enum('oczekujaca','potwierdzona','zrealizowana','anulowana') NOT NULL DEFAULT 'oczekujaca',
  `komentarz` text DEFAULT NULL,
  `data_utworzenia` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rezerwacje`
--

INSERT INTO `rezerwacje` (`id`, `klient_id`, `pracownik_id`, `usluga_id`, `data_rezerwacji`, `godzina_od`, `godzina_do`, `status`, `komentarz`, `data_utworzenia`) VALUES
(1, 3, 1, 1, '2026-09-30', '10:00:00', '10:45:00', 'potwierdzona', 'brak uwag', '2026-09-08 17:03:55');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `uslugi`
--

CREATE TABLE `uslugi` (
  `id` int(11) NOT NULL,
  `kategoria_id` int(11) NOT NULL,
  `nazwa` varchar(100) NOT NULL,
  `opis` text DEFAULT NULL,
  `czas_trwania` int(11) NOT NULL,
  `cena` decimal(10,2) NOT NULL,
  `aktywna` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uslugi`
--

INSERT INTO `uslugi` (`id`, `kategoria_id`, `nazwa`, `opis`, `czas_trwania`, `cena`, `aktywna`) VALUES
(1, 1, 'Wymiana dysku SSD', 'Montaż nowego dysku i przeniesienie danych', 45, 100.00, 1),
(2, 1, 'Czyszczenie laptopa z kurzu', 'Czyszczenie wnętrza i wymiana pasty termicznej', 60, 80.00, 1),
(3, 2, 'Diagnostyka komputera', 'Pełna diagnoza sprzętu i systemu', 30, 50.00, 1),
(4, 3, 'Instalacja systemu Windows', 'Czysta instalacja systemu z aktualizacjami', 90, 120.00, 1);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `uzytkownicy`
--

CREATE TABLE `uzytkownicy` (
  `id` int(11) NOT NULL,
  `imie` varchar(50) NOT NULL,
  `nazwisko` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `haslo` varchar(255) NOT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `rola` enum('klient','pracownik','admin') NOT NULL DEFAULT 'klient',
  `aktywny` tinyint(1) NOT NULL DEFAULT 1,
  `data_utworzenia` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uzytkownicy`
--

INSERT INTO `uzytkownicy` (`id`, `imie`, `nazwisko`, `email`, `haslo`, `telefon`, `rola`, `aktywny`, `data_utworzenia`) VALUES
(1, 'Jan', 'Kowalski', 'admin@test.pl', '$2y$10$wu8D70JB8QC.xSlR2m.yueOzi3PzOQhGzXM7ioktTGhT03PFK4TvC', '111222333', 'admin', 1, '2026-09-08 17:03:55'),
(2, 'Marek', 'Zieliński', 'pracownik@test.pl', '$2y$10$wu8D70JB8QC.xSlR2m.yueOzi3PzOQhGzXM7ioktTGhT03PFK4TvC', '222333444', 'pracownik', 1, '2026-09-08 17:03:55'),
(3, 'Piotr', 'Wiśniewski', 'klient@test.pl', '$2y$10$wu8D70JB8QC.xSlR2m.yueOzi3PzOQhGzXM7ioktTGhT03PFK4TvC', '333444555', 'klient', 1, '2026-09-08 17:03:55');

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `dostepnosc_pracownikow`
--
ALTER TABLE `dostepnosc_pracownikow`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pracownik_id` (`pracownik_id`);

--
-- Indeksy dla tabeli `kategorie_uslug`
--
ALTER TABLE `kategorie_uslug`
  ADD PRIMARY KEY (`id`);

--
-- Indeksy dla tabeli `pracownicy`
--
ALTER TABLE `pracownicy`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uzytkownik_id` (`uzytkownik_id`);

--
-- Indeksy dla tabeli `pracownicy_uslugi`
--
ALTER TABLE `pracownicy_uslugi`
  ADD PRIMARY KEY (`pracownik_id`,`usluga_id`),
  ADD KEY `usluga_id` (`usluga_id`);

--
-- Indeksy dla tabeli `rezerwacje`
--
ALTER TABLE `rezerwacje`
  ADD PRIMARY KEY (`id`),
  ADD KEY `klient_id` (`klient_id`),
  ADD KEY `pracownik_id` (`pracownik_id`),
  ADD KEY `usluga_id` (`usluga_id`);

--
-- Indeksy dla tabeli `uslugi`
--
ALTER TABLE `uslugi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategoria_id` (`kategoria_id`);

--
-- Indeksy dla tabeli `uzytkownicy`
--
ALTER TABLE `uzytkownicy`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dostepnosc_pracownikow`
--
ALTER TABLE `dostepnosc_pracownikow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `kategorie_uslug`
--
ALTER TABLE `kategorie_uslug`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pracownicy`
--
ALTER TABLE `pracownicy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rezerwacje`
--
ALTER TABLE `rezerwacje`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `uslugi`
--
ALTER TABLE `uslugi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `uzytkownicy`
--
ALTER TABLE `uzytkownicy`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dostepnosc_pracownikow`
--
ALTER TABLE `dostepnosc_pracownikow`
  ADD CONSTRAINT `dostepnosc_pracownikow_ibfk_1` FOREIGN KEY (`pracownik_id`) REFERENCES `pracownicy` (`id`);

--
-- Constraints for table `pracownicy`
--
ALTER TABLE `pracownicy`
  ADD CONSTRAINT `pracownicy_ibfk_1` FOREIGN KEY (`uzytkownik_id`) REFERENCES `uzytkownicy` (`id`);

--
-- Constraints for table `pracownicy_uslugi`
--
ALTER TABLE `pracownicy_uslugi`
  ADD CONSTRAINT `pracownicy_uslugi_ibfk_1` FOREIGN KEY (`pracownik_id`) REFERENCES `pracownicy` (`id`),
  ADD CONSTRAINT `pracownicy_uslugi_ibfk_2` FOREIGN KEY (`usluga_id`) REFERENCES `uslugi` (`id`);

--
-- Constraints for table `rezerwacje`
--
ALTER TABLE `rezerwacje`
  ADD CONSTRAINT `rezerwacje_ibfk_1` FOREIGN KEY (`klient_id`) REFERENCES `uzytkownicy` (`id`),
  ADD CONSTRAINT `rezerwacje_ibfk_2` FOREIGN KEY (`pracownik_id`) REFERENCES `pracownicy` (`id`),
  ADD CONSTRAINT `rezerwacje_ibfk_3` FOREIGN KEY (`usluga_id`) REFERENCES `uslugi` (`id`);

--
-- Constraints for table `uslugi`
--
ALTER TABLE `uslugi`
  ADD CONSTRAINT `uslugi_ibfk_1` FOREIGN KEY (`kategoria_id`) REFERENCES `kategorie_uslug` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
