# System rezerwacji usług serwisu komputerowego

## 1. Informacje o projekcie

Projekt przedstawia aplikację internetową do obsługi rezerwacji usług serwisu komputerowego. System został przygotowany jako projekt semestralny i wykorzystuje architekturę opartą na PHP oraz relacyjnej bazie danych MySQL.

**Autorzy:**
- Aleksander Prokurat
- Kamil Niedziałek
- Grzegorz Płatkowski

**Adres projektu:** https://projektserwiskomputerowy.krewetkowo.pl

---

## 2. Opis aplikacji

Celem projektu jest stworzenie systemu, który umożliwia obsługę klientów, pracowników oraz administratora serwisu komputerowego.

Aplikacja została zaprojektowana z podziałem na role użytkowników (`klient`, `pracownik`, `admin`). W zależności od roli użytkownik może uzyskać dostęp do odpowiedniej części systemu.

Projekt obejmuje mechanizmy rejestracji i logowania, zarządzanie profilem użytkownika, kontrolę uprawnień oraz strukturę bazy danych przygotowaną do obsługi usług, pracowników, dostępności i rezerwacji. Interfejs aplikacji został dostosowany do wymogów nowoczesnego i responsywnego wyglądu (Bootstrap 5).

---

## 3. Wykorzystane technologie

- **PHP (PDO)** – obsługa logiki aplikacji, formularzy, sesji użytkowników oraz bezpiecznej komunikacji z bazą danych za pomocą zapytań przygotowanych.
- **HTML5 & CSS3** – struktura oraz wyglądy i formatowanie interfejsu użytkownika.
- **Bootstrap 5 (CDN)** – responsywny layout, komponenty interfejsu (karty, nawigacja, modale, zakładki).
- **JavaScript** – obsługa elementów interfejsu po stronie przeglądarki (Bootstrap bundle JS).
- **MySQL / MariaDB** – relacyjna baza danych przechowująca informacje o użytkownikach, usługach, pracownikach i rezerwacjach.
- **Git i GitHub** – przechowywanie kodu źródłowego projektu oraz kontrola wersji.

---

## 4. Zaimplementowane funkcje

### 4.1. Strona główna

Plik `index.php` odpowiada za wyświetlenie strony głównej aplikacji. Użytkownik niezalogowany może przejść do formularza logowania lub rejestracji, natomiast użytkownik zalogowany widzi odnośniki dostosowane do jego roli.

### 4.2. Rejestracja użytkownika

Plik `register.php` obsługuje tworzenie nowego konta klienta.

Formularz rejestracji obejmuje:
- wprowadzenie podstawowych danych użytkownika (imię, nazwisko, e-mail, telefon);
- sprawdzanie poprawności adresu e-mail;
- walidację imienia, nazwiska i numeru telefonu;
- sprawdzanie minimalnej długości hasła;
- bezpieczne przechowywanie hasła w postaci skrótu (`password_hash`);
- estetyczny układ oparty na kartach Bootstrap 5.

### 4.3. Logowanie i wylogowanie

Pliki `login.php` oraz `wyloguj.php` odpowiadają za obsługę sesji użytkownika.

Mechanizm logowania obejmuje:
- weryfikację adresu e-mail i hasła;
- sprawdzanie hasła za pomocą funkcji weryfikującej jego skrót (`password_verify`);
- ograniczenie liczby nieudanych prób logowania (tabela `proby_logowania`);
- blokowanie kolejnych prób po osiągnięciu ustalonego limitu (max 5 prób na 15 minut);
- sprawdzanie statusu aktywności konta (`aktywny = 1`);
- odświeżenie identyfikatora sesji po zalogowaniu (`session_regenerate_id`);
- przekierowanie użytkownika do panelu odpowiadającego jego roli (`panel_klient.php`, `panel_pracownik.php`, `panel_admin.php`).

### 4.4. Kontrola dostępu

Pliki `auth.php` oraz `admin_guard.php` zawierają funkcje wykorzystywane do sprawdzania, czy użytkownik jest zalogowany oraz czy posiada odpowiednią rolę. Dostęp do wybranych stron jest rygorystycznie ograniczony dla osób niezalogowanych i użytkowników bez wymaganych uprawnień.

### 4.5. Profil użytkownika

Plik `profil.php` umożliwia edycję wybranych danych osobowych zalogowanego użytkownika (imię, nazwisko, numer telefonu) w przejrzystej karcie Bootstrapa, oddzielając stałe dane (adres e-mail, rolę w systemie) od pól edycyjnych.

### 4.6. Zmiana hasła

Plik `zmiana_hasla.php` obsługuje zmianę hasła użytkownika.

Funkcja obejmuje:
- weryfikację dotychczasowego hasła;
- sprawdzanie minimalnej długości nowego hasła;
- zapisanie nowego skrótu hasła w bazie danych.

### 4.7. Panele użytkowników

W projekcie znajdują się trzy panele przeznaczone dla różnych ról:

- `panel_klient.php` – panel klienta do przeglądania własnych rezerwacji;
- `panel_pracownik.php` – panel pracownika;
- `panel_admin.php` – rozbudowany panel administratora podzielony na zakładki (`Bootstrap Nav Tabs`):
  - **Statystyki**: podsumowanie liczby klientów, pracowników, usług i rezerwacji.
  - **Zarządzanie wizytami**: zmiana statusu rezerwacji (potwierdzona, zrealizowana, anulowana) z rejestracją w tabeli `log_administracyjny`.
  - **Zarządzanie użytkownikami**: aktywacja, blokowanie, usuwanie oraz edycja danych użytkowników i ich haseł (z ochroną kont z rolą `admin` przed modyfikacją przez innych adminów).
  - **Katalog usług**: pełny CRUD (dodawanie nowych usług, zmiana cen, czasu trwania, opisu i kategorii oraz usuwanie).
  - **Zarządzanie pracownikami**: edycja specjalizacji/opisu pracownika, zmiana danych osobowych oraz haseł.
  - **Utrzymanie aktywnej zakładki**: parametry URL (`?tab=...`) zapobiegają skakaniu widoku do pierwszej zakładki po wykonaniu akcji.

---

## 5. Baza danych

Struktura bazy danych znajduje się w pliku `database.sql`. Schemat relacji między tabelami przedstawiono na diagramie `ERD.png`.

### 5.1. Najważniejsze tabele

| Tabela | Przeznaczenie |
|---|---|
| `uzytkownicy` | Dane użytkowników, dane logowania i role (`klient`, `pracownik`, `admin`) |
| `pracownicy` | Informacje o pracownikach i ich opisy/specjalizacje |
| `kategorie_uslug` | Kategorie oferowanych usług |
| `uslugi` | Dane usług serwisu (cena, czas trwania, opis) |
| `pracownicy_uslugi` | Powiązania pracowników z wykonywanymi usługami |
| `dostepnosc_pracownikow` | Dostępność pracowników |
| `urlopy_pracownikow` | Informacje o urlopach pracowników |
| `rezerwacje` | Dane dotyczące rezerwacji usług |
| `historia_statusow_rezerwacji` | Historia zmian statusów rezerwacji |
| `log_administracyjny` | Rejestrowanie wybranych działań administracyjnych |
| `proby_logowania` | Informacje o nieudanych próbach logowania |

### 5.2. Zastosowane rozwiązania w bazie danych

W strukturze bazy zastosowano:
- klucze główne i obce z więzami integralności;
- ograniczenia unikalności (np. dla pola `email`);
- indeksy wspierające wyszukiwanie danych;
- relacje wiele-do-wielu (np. `pracownicy_uslugi`);
- przykładowe dane ułatwiające testowanie aplikacji;
- osobne tabele dla historii statusów, logów administracyjnych oraz prób logowania.

---

## 6. Struktura plików projektu

| Plik lub katalog | Opis |
|---|---|
| `index.php` | Strona główna aplikacji |
| `db.php` | Konfiguracja i połączenie z bazą danych (PDO) |
| `header.php` | Wspólny nagłówek (zawiera CDN Bootstrap 5 oraz nawigację) |
| `footer.php` | Wspólna stopka stron |
| `register.php` | Formularz i obsługa rejestracji użytkownika |
| `login.php` | Formularz i obsługa logowania |
| `wyloguj.php` | Skrypt wylogowania (niszczenie sesji) |
| `auth.php` | Kontrola logowania i uprawnień |
| `profil.php` | Edycja profilu użytkownika |
| `zmiana_hasla.php` | Formularz zmiany hasła |
| `panel_admin.php` | Główny panel administratora (zakładki, statystyki, obsługa modalowa) |
| `admin_guard.php` | Weryfikacja i zabezpieczenie uprawnień administratora |
| `admin_helpers.php` | Funkcje pomocnicze dla panelu administracyjnego |
| `admin_kategorie.php` | Moduł zarządzania kategoriami usług |
| `admin_nav.php` | Nawigacja panelu administratora |
| `admin_uslugi.php` | Moduł zarządzania katalogiem usług |
| `panel_pracownik.php` | Panel dedykowany dla pracowników serwisu |
| `panel_klient.php` | Panel klienta do podglądu rezerwacji |
| `database.sql` | Skrypt SQL tworzący strukturę bazy danych i przykładowe dane |
| `ERD.png` | Diagram relacyjny bazy danych |
| `css/` | Katalog z plikami stylów (`style.css`) |
| `img/` | Katalog z plikami graficznymi |

---

## 7. Uruchomienie projektu lokalnie

### Krok 1. Pobranie projektu

Pobierz repozytorium z GitHuba:
https://github.com/qtKreweciarz/Serwis-komputerowy

Rozpakuj projekt w katalogu serwera lokalnego, np. w `htdocs` środowiska XAMPP.

### Krok 2. Uruchomienie środowiska

Uruchom serwer WWW (Apache) oraz serwer bazy danych MySQL (np. za pomocą panelu XAMPP).

### Krok 3. Utworzenie bazy danych

W narzędziu phpMyAdmin (`http://localhost/phpmyadmin/`) utwórz bazę danych przeznaczoną dla projektu (np. `srv106720_projekt6`).
Zaimportuj plik `database.sql`.

### Krok 4. Konfiguracja połączenia

Otwórz plik `db.php` i upewnij się, że dane dostępowe (host, nazwa bazy, użytkownik, hasło) zgadzają się z Twoim środowiskiem lokalnym.

### Krok 5. Uruchomienie aplikacji

Otwórz przeglądarkę i wpisz adres:
`http://localhost/Serwis-komputerowy/`

---

## 8. Dane logowania do testów (Dla Nauczyciela / Egzaminatora)

Poniżej znajdują się dane dostępowe do konta z rolą **administratora**, umożliwiające przetestowanie pełnej funkcjonalności paneli:

- **E-mail:** `[admin@gmail.com]`
- **Hasło:** `[Admin!Admin]`

---

## 9. Podsumowanie

Projekt „System rezerwacji usług serwisu komputerowego” stanowi kompletną aplikację internetową z zaawansowaną kontrolą ról, nowoczesnym i responsywnym interfejsem Bootstrap 5 oraz bezpieczną bazą danych. 

Aplikacja wspiera pełne zarządzanie użytkownikami, usługami, pracownikami oraz statusami rezerwacji z zachowaniem najwyższych standardów bezpieczeństwa (haszowanie haseł, ochrona kont admina, limity prób logowania, zapytania przygotowane PDO).