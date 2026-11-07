# Feladat: Eszköznyilvántartó REST API

- Azonosító: `20261107-1953-eszkoznyilvantarto-rest-api`
- Indítva: 2026-11-07T19:53:51+01:00
- Ág: `feature/eszkoznyilvantarto-rest-api` (alap: `main` @ `09cb0741a7`)
- Minőségkapuk:
- `make check`

## Az eredeti kérés (szó szerint)

# Készíts el egy eszköznyilvántartó alkalmazást!
 
### A nyilvántartónak rendelkezni kell:

1. szerződés adatok
2. eszközök

 
Egy eszköztípus több szerződésen is rajta lehet, viszont eszközönként más gyári számmal. (N:N)
A feladathoz egy REST-apit kell készíteni, nem kell hozzá felület.
 
### Megvalósítandó feladatok:

1. eszköz létrehozása, módosítása törlése, lekérdezése (lista és egy konkrét elem)
2. szerződés létrehozása, módosítása, törlése, lekérdezése (lista és egy konkrét elem)
3. szerződés lekérdezéskor jelenítsük meg a rajta lévő eszközöket, fontos, hogy listában nem kell szerepeljen
4. eszköz hozzáadása / levétele szerződésről

 
### Követelmények:

1. CRUD REST-api.
2. Egységes hiba kezelés
3. API forgalom: request / response logolása adatbázisban
4. Elérhető OPEN api dokumentáció, egy publikus URL-en BASIC AUTH-al ellátva

 
### Megvalósítással kapcsolatos követelmények:

1. Laravel 13
2. Egy támogatott PHP verzió, lehetőleg php 8.5 https://www.php.net/supported-versions.php
3. MySQL
4. Cleancode (SOLID, DRY)
5. Tartsd be a PSR-4 és PSR-12 standardeket (https://www.php-fig.org/psr/)
6. Docker csomag (docker-compose.yaml) legyen mellékelve a megoldásban
7. Migrációkból fel lehessen állítani a projektet

 
A megoldásodat egy nyilvános git repoban oszd meg velünk.
