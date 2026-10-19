# System rezerwacji usług serwisu komputerowego

## 1. Informacje o projekcie

Projekt przedstawia aplikację internetową do obsługi rezerwacji usług serwisu komputerowego. System został przygotowany jako projekt semestralny i wykorzystuje architekturę opartą na PHP oraz relacyjnej bazie danych MySQL.

**Autorzy:**
- Aleksander Prokurat
- Kamil Niedziałek
- Grzegorz Płatkowski

**Adres projektu:** https://projektserwiskomputerowy.krewetkowo.pl

## 2. Opis aplikacji

Celem projektu jest stworzenie systemu, który umożliwia obsługę klientów, pracowników oraz administratora serwisu komputerowego.

Aplikacja została zaprojektowana z podziałem na role użytkowników. W zależności od roli użytkownik może uzyskać dostęp do odpowiedniej części systemu.

Projekt obejmuje mechanizmy rejestracji i logowania, zarządzanie profilem użytkownika, kontrolę uprawnień oraz strukturę bazy danych przygotowaną do obsługi usług, pracowników, dostępności i rezerwacji.

## 3. Wykorzystane technologie

- **PHP** – obsługa logiki aplikacji, formularzy, sesji użytkowników oraz komunikacji z bazą danych.
- **HTML** – struktura stron internetowych.
- **CSS** – wygląd i formatowanie interfejsu użytkownika.
- **JavaScript** – obsługa elementów interfejsu po stronie przeglądarki.
- **MySQL** – relacyjna baza danych przechowująca informacje o użytkownikach, usługach, pracownikach i rezerwacjach.
- **PDO** – komunikacja z bazą danych z wykorzystaniem przygotowanych zapytań SQL.
- **Git i GitHub** – przechowywanie kodu źródłowego projektu.

## 4. Zaimplementowane funkcje

### 4.1. Strona główna

Plik `index.php` odpowiada za wyświetlenie strony głównej aplikacji. Użytkownik niezalogowany może przejść do formularza logowania lub rejestracji.

### 4.2. Rejestracja użytkownika

Plik `register.php` obsługuje tworzenie nowego konta klienta.

Formularz rejestracji obejmuje:
- wprowadzenie podstawowych danych użytkownika;
- sprawdzanie poprawności adresu e-mail;
- walidację imienia, nazwiska i numeru telefonu;
- sprawdzanie minimalnej długości hasła;
- potwierdzenie hasła;
- sprawdzanie poprawności danych przed zapisaniem ich w bazie;
- bezpieczne przechowywanie hasła w postaci skrótu;
- wykorzystanie tokenu CSRF do ochrony formularza.

### 4.3. Logowanie i wylogowanie

Pliki `login.php` oraz `wyloguj.php` odpowiadają za obsługę sesji użytkownika.

Mechanizm logowania obejmuje:
- weryfikację adresu e-mail i hasła;
- sprawdzanie hasła za pomocą funkcji weryfikującej jego skrót;
- ograniczenie liczby nieudanych prób logowania;
- blokowanie kolejnych prób po osiągnięciu ustalonego limitu;
- sprawdzanie statusu aktywności konta;
- kontrolę aktywnej sesji użytkownika;
- odświeżenie identyfikatora sesji po zalogowaniu;
- przekierowanie użytkownika do panelu odpowiadającego jego roli.

### 4.4. Kontrola dostępu

Plik `auth.php` zawiera funkcje wykorzystywane do sprawdzania, czy użytkownik jest zalogowany oraz czy posiada odpowiednią rolę.

Dostęp do wybranych stron jest ograniczony dla osób niezalogowanych i użytkowników bez wymaganych uprawnień.

### 4.5. Profil użytkownika

Plik `profil.php` umożliwia edycję wybranych danych osobowych zalogowanego użytkownika.

Podczas zapisywania zmian sprawdzana jest poprawność wprowadzonych danych. Aktualizacja odbywa się za pomocą zapytania przygotowanego, a formularz wykorzystuje ochronę CSRF.

### 4.6. Zmiana hasła

Plik `zmiana_hasla.php` obsługuje zmianę hasła użytkownika.

Funkcja obejmuje:
- weryfikację dotychczasowego hasła;
- sprawdzanie minimalnej długości nowego hasła;
- potwierdzenie nowego hasła;
- sprawdzanie, czy nowe hasło różni się od dotychczasowego;
- zapisanie nowego skrótu hasła w bazie danych;
- odświeżenie identyfikatora sesji po zmianie hasła.

### 4.7. Panele użytkowników

W projekcie znajdują się trzy panele przeznaczone dla różnych ról:

- `panel_klient.php` – panel klienta;
- `panel_pracownik.php` – panel pracownika;
- `panel_admin.php` – panel administratora.

Strony te są zabezpieczone kontrolą logowania i uprawnień. Na obecnym etapie stanowią podstawę do dalszej rozbudowy funkcji właściwych dla poszczególnych ról.

## 5. Baza danych

Struktura bazy danych znajduje się w pliku `database.sql`. Schemat relacji między tabelami przedstawiono na diagramie `ERD.png`.

Baza danych została przygotowana do przechowywania informacji potrzebnych do obsługi serwisu komputerowego.

### 5.1. Najważniejsze tabele

| Tabela | Przeznaczenie |
|---|---|
| `uzytkownicy` | Dane użytkowników, dane logowania i role |
| `pracownicy` | Informacje o pracownikach |
| `kategorie_uslug` | Kategorie oferowanych usług |
| `uslugi` | Dane usług serwisu |
| `pracownicy_uslugi` | Powiązania pracowników z wykonywanymi usługami |
| `dostepnosc_pracownikow` | Dostępność pracowników |
| `urlopy_pracownikow` | Informacje o urlopach pracowników |
| `rezerwacje` | Dane dotyczące rezerwacji usług |
| `historia_statusow_rezerwacji` | Historia zmian statusów rezerwacji |
| `log_administracyjny` | Rejestrowanie wybranych działań administracyjnych |
| `proby_logowania` | Informacje o próbach logowania |

### 5.2. Zastosowane rozwiązania w bazie danych

W strukturze bazy zastosowano:
- klucze główne i obce;
- relacje pomiędzy tabelami;
- ograniczenia integralności danych;
- ograniczenia unikalności, między innymi dla adresów e-mail;
- indeksy wspierające wyszukiwanie danych;
- przykładowe dane ułatwiające sprawdzenie struktury bazy;
- osobne tabele dla historii statusów rezerwacji, urlopów oraz prób logowania.

Powiązanie pracowników z usługami realizowane jest za pomocą tabeli `pracownicy_uslugi`, co pozwala przypisać jednego pracownika do wielu usług oraz jedną usługę do wielu pracowników.

Należy rozróżnić strukturę przygotowaną w bazie danych od funkcji dostępnych w interfejsie aplikacji. Samo istnienie tabeli nie oznacza, że wszystkie operacje związane z daną funkcją są już obsługiwane przez aplikację.

## 6. Struktura plików projektu

| Plik lub katalog | Opis |
|---|---|
| `index.php` | Strona główna |
| `db.php` | Konfiguracja i połączenie z bazą danych |
| `header.php` | Wspólny nagłówek stron |
| `footer.php` | Wspólna stopka stron |
| `register.php` | Rejestracja użytkownika |
| `login.php` | Logowanie użytkownika |
| `wyloguj.php` | Wylogowanie |
| `auth.php` | Kontrola logowania i uprawnień |
| `profil.php` | Edycja profilu użytkownika |
| `zmiana_hasla.php` | Zmiana hasła |
| `panel_klient.php` | Panel klienta |
| `panel_pracownik.php` | Panel pracownika |
| `panel_admin.php` | Panel administratora |
| `database.sql` | Struktura bazy danych i przykładowe dane |
| `ERD.png` | Diagram relacji bazy danych |
| `css/` | Arkusze stylów |
| `img/` | Pliki graficzne |

## 7. Uruchomienie projektu lokalnie

Do uruchomienia aplikacji potrzebne jest środowisko obsługujące PHP oraz serwer bazy danych MySQL.

### Krok 1. Pobranie projektu

Pobierz repozytorium z GitHuba:

https://github.com/qtKreweciarz/Serwis-komputerowy

Możesz pobrać projekt jako archiwum ZIP i rozpakować je w katalogu serwera lokalnego, np. w katalogu `htdocs` środowiska XAMPP.

### Krok 2. Uruchomienie środowiska

Uruchom serwer WWW z obsługą PHP oraz serwer MySQL, np. za pomocą XAMPP.

### Krok 3. Utworzenie bazy danych

W narzędziu do obsługi MySQL, np. phpMyAdmin, utwórz bazę danych przeznaczoną dla projektu.

Następnie zaimportuj plik `database.sql`. Po imporcie powinny zostać utworzone tabele oraz dodane przykładowe dane przewidziane w skrypcie.

### Krok 4. Konfiguracja połączenia

Otwórz plik `db.php` i sprawdź konfigurację połączenia z bazą danych.

Ustaw odpowiednie wartości:
- adres serwera bazy danych;
- nazwę bazy danych;
- nazwę użytkownika MySQL;
- hasło użytkownika MySQL.

Dane połączenia muszą odpowiadać konfiguracji lokalnego środowiska.

### Krok 5. Uruchomienie aplikacji

Umieść pliki projektu w katalogu obsługiwanym przez serwer WWW, a następnie otwórz aplikację w przeglądarce, np.:

`http://localhost/Serwis-komputerowy/`

Dokładny adres zależy od nazwy katalogu, w którym umieszczono projekt.

### Krok 6. Logowanie

Po zaimportowaniu bazy danych można sprawdzić, czy znajdują się w niej przykładowe konta użytkowników. Dane logowania należy ustalić na podstawie rzeczywiście utworzonych kont i zapisanych w bazie skrótów haseł.

Projekt nie powinien być opisywany jako posiadający konkretne testowe dane logowania, dopóki nie zostaną one potwierdzone.

## 8. Dodatkowe rozwiązania

W projekcie uwzględniono również rozwiązania związane z bezpieczeństwem i dalszą rozbudową systemu:

- haszowanie haseł zamiast zapisywania ich w postaci jawnej;
- zapytania przygotowane do komunikacji z bazą danych;
- kontrolę dostępu na podstawie ról;
- ochronę formularzy przed atakami CSRF;
- ograniczanie liczby nieudanych prób logowania;
- historię statusów rezerwacji;
- ewidencję urlopów pracowników;
- rejestrowanie wybranych działań administracyjnych.

Część rozwiązań jest przygotowana na poziomie struktury bazy danych, dlatego ich pełna obsługa może wymagać dalszej implementacji w aplikacji.

## 9. Podsumowanie

Projekt „System rezerwacji usług serwisu komputerowego” stanowi podstawę aplikacji internetowej przeznaczonej do obsługi użytkowników i organizacji usług serwisowych.

Zaimplementowane elementy obejmują rejestrację, logowanie, wylogowanie, zarządzanie podstawowymi danymi profilu, zmianę hasła oraz kontrolę dostępu do paneli użytkowników. Przygotowano również relacyjną bazę danych z tabelami dotyczącymi usług, pracowników, dostępności, rezerwacji i dodatkowych mechanizmów administracyjnych.

Aplikacja może być dalej rozwijana o pełną obsługę rezerwacji, zarządzanie ofertą usług, dostępnością pracowników oraz funkcje administracyjne dostępne z poziomu interfejsu.
