# Serwis komputerowy – system rezerwacji usług

Projekt zaliczeniowy z przedmiotu Tworzenie stron i aplikacji internetowych. Aplikacja webowa do zarządzania serwisem komputerowym – klienci mogą umawiać się na usługi (np. naprawa, diagnostyka), a pracownicy i admin mają swój panel do ogarniania rezerwacji, grafiku i statusów zleceń.

Strona projektu: projektserwiskomputerowy.krewetkowo.pl

## Autorzy

- Aleksander Prokurat
- Kamil Niedziałek
- Grzegorz Płatkowski

## Stack technologiczny

- **PHP** – logika backendowa
- **HTML / CSS / JavaScript** – frontend
- **MySQL** – baza danych
- **Git / GitHub** – wersjonowanie i praca zespołowa (branch → PR → main)

## Struktura bazy danych

Baza (`database.sql`) opiera się na kilku powiązanych tabelach:

- `uzytkownicy` – konta klientów, pracowników i admina
- `pracownicy` – dane pracowników serwisu
- `kategorie_uslug` i `uslugi` – katalog usług podzielony na kategorie
- `pracownicy_uslugi` – które usługi wykonuje dany pracownik
- `rezerwacje` – właściwe zamówienia/rezerwacje klientów
- `historia_statusow_rezerwacji` – historia zmian statusu danej rezerwacji (np. przyjęto → w trakcie → zakończono)
- `dostepnosc_pracownikow` i `urlopy_pracownikow` – grafik i urlopy, żeby nie dało się zarezerwować terminu, którego nie ma
- `log_administracyjny` – log akcji wykonywanych przez admina, do rozliczalności

Do tabel dodane są indeksy oraz ograniczenia `UNIQUE` / `CHECK`, żeby baza sama pilnowała spójności danych (np. żeby nie dało się dodać rezerwacji na nieistniejącego pracownika czy wpisać ujemnej ceny usługi).

Diagram encji (ERD) znajduje się w pliku `ERD.png`.

## CI/CD – automatyczna recenzja kodu i deploy

W repo mamy skonfigurowany workflow GitHub Actions (`.github/workflows/ai-review.yml`), który odpala się przy każdym pushu i przy pull requestach do `main`/`develop`.

Co się dzieje krok po kroku:

1. Workflow robi diff między commitami/PR-em.
2. Diff leci do **Gemini API** (Google), które generuje krótką recenzję kodu po polsku – co się zmieniło, czy nie ma jakichś oczywistych błędów itp.
3. Recenzja jest automatycznie wrzucana jako komentarz do PR-a (albo do commita, jeśli to zwykły push) przez GitHub API.
4. Jeśli po drodze Gemini akurat leży (błąd 503/przeciążenie), workflow nie wywala się na czerwono, tylko po prostu informuje, że recenzja się nie udała i można odpalić ją ręcznie jeszcze raz.

Drugi etap workflowa to **deploy przez webhook** – po pushu na `main` wysyłane jest zapytanie POST na webhook (`DEPLOY_WEBHOOK_URL`), który uruchamia aktualizację strony na serwerze, więc zmiany z GitHuba trafiają na produkcję bez ręcznego wgrywania plików przez FTP. 
Klucze API i adres webhooka trzymane są jako sekrety w ustawieniach repozytorium (GitHub Secrets), nie w kodzie.

## Jak uruchomić lokalnie

1. Zaimportować `database.sql` do MySQL (np. przez phpMyAdmin/XAMPP).
2. Wrzucić pliki projektu na serwer z PHP (np. lokalnie XAMPP/WAMP).
3. Uzupełnić dane do połączenia z bazą w kodzie PHP.
4. Odpalić `index.html` / stronę główną przez serwer lokalny.

## Status projektu

Etap 1 (projekt, konfiguracja GitHuba, struktura bazy) – w trakcie / zakończony.
Kolejne etapy: podpięcie nowych tabel pod logikę PHP, panel pracownika i admina, historia statusów rezerwacji w praktyce.
