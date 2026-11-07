# Követelmények – Eszköznyilvántartó REST API

<!--
A tisztázás lépés kimenete; a terv és a review ehhez mér.
- Minden «kitöltendő» jelölőt cserélj le tartalomra.
- Az elfogadási kritériumok számozottak (E1, E2, …) és tesztelhetők legyenek.
- A felhasználónak feltett, még megválaszolatlan kérdéseket a „Nyitott kérdések” alatt [NYITOTT] jelöléssel
  vezesd; amíg van ilyen, a `devchain next` nem enged tovább.
-->

## Összefoglaló

Felület nélküli REST API készül szerződések és eszközök (eszköztípusok) nyilvántartására Laravel 13-mal, PHP 8.5-ön,
MySQL-lel, Docker csomagban. A szerződés és az eszköz között N:N kapcsolat van: egy eszköztípus több szerződésen is
szerepelhet, mindegyiken a saját, rendszerszinten egyedi gyári számával. Az API egységes hibaformátumot ad, a
forgalmát (kérés és válasz) adatbázisba naplózza, az OpenAPI-dokumentációja Basic Auth-tal védett URL-en érhető el.
A megoldást az értékelők nyilvános git repóból nézik át, ezért a kódminőség (SOLID, DRY, PSR-4, PSR-12) és a
reprodukálható felállítás (Docker, migrációk) kiemelten fontos.

## Követelmények

### Funkcionális

- **K1 – Eszközök CRUD:** a `/api/v1/devices` erőforráson létrehozás (`POST`), lapozott lista (`GET`), egy elem
  lekérdezése (`GET /{device}`), teljes módosítás (`PUT`), részleges módosítás (`PATCH`) és törlés (`DELETE`).
  Az „eszköz” eszköztípust jelent (pl. Dell Latitude 5440).
- **K2 – Eszköz adatai:** `name` (kötelező, legfeljebb 255 karakter), `manufacturer` (kötelező, legfeljebb 255),
  `model` (kötelező, legfeljebb 255), `description` (opcionális, legfeljebb 5000 karakter); a válaszban ezen felül
  `id`, `created_at`, `updated_at`.
- **K3 – Szerződések CRUD:** a `/api/v1/contracts` erőforráson ugyanazok a műveletek, mint K1-ben.
- **K4 – Szerződés adatai:** `contract_number` (kötelező, legfeljebb 50 karakter, egyedi), `partner_name` (kötelező,
  legfeljebb 255), `start_date` (kötelező dátum, `Y-m-d`), `end_date` (opcionális dátum, nem lehet korábbi a
  `start_date`-nél), `description` (opcionális, legfeljebb 5000 karakter); a válaszban ezen felül `id`,
  `created_at`, `updated_at`.
- **K5 – Eszközök a szerződés lekérdezésében:** a szerződés egy-elemes lekérdezése (`GET /api/v1/contracts/{contract}`)
  tartalmazza a rajta lévő eszközöket (eszközadatok, `serial_number`, a hozzárendelés ideje); a szerződéslista elemei
  nem tartalmaznak eszközadatot.
- **K6 – Eszköz felvétele és levétele:** felvétel: `POST /api/v1/contracts/{contract}/devices` (`device_id`,
  `serial_number`); levétel: `DELETE /api/v1/contracts/{contract}/devices/{device}`.
- **K7 – Kapcsolati szabályok:** a szerződés és az eszköz között N:N kapcsolat van; egy eszköztípus egy szerződésen
  legfeljebb egyszer szerepel; a gyári szám kötelező (legfeljebb 100 karakter) és a teljes rendszerben egyedi. Az
  egyediségeket adatbázis-index is garantálja, nem csak a validáció.
- **K8 – Törlési szabályok:** szerződéshez rendelt eszköz nem törölhető (409 Conflict); szerződés törlésekor a
  szerződés–eszköz kapcsolatai is törlődnek, az eszközök megmaradnak.

### Nem funkcionális

- **K9 – Egységes hibakezelés:** minden `/api/*` hibaválasz RFC 9457 Problem Details formátumú
  (`Content-Type: application/problem+json`; `type`, `title`, `status`, `detail`, validációs hibánál `errors`
  mezőnkénti üzenetekkel), az `Accept` fejléctől függetlenül. Éles módban (`APP_DEBUG=false`) az 500-as válasz nem
  tartalmaz belső részletet.
- **K10 – API-forgalom naplózása adatbázisba:** minden `/api/*` kérés – a hibával végződők és az ismeretlen útvonalra
  érkezők is – egy sort kap egy naplótáblában: HTTP-metódus, útvonal, query-paraméterek, kérésfejlécek, kéréstörzs,
  válaszstátusz, válaszfejlécek, választörzs, időtartam (ms), kliens IP, user agent, időpont. Az érzékeny fejlécek és
  mezők maszkolva, a beállított méretnél nagyobb törzsek csonkolva kerülnek be. A naplózás hibája nem befolyásolja az
  API-választ.
- **K11 – OpenAPI-dokumentáció:** OpenAPI 3.x leírás böngészhető felülettel (`/docs/api`) és gépileg olvasható
  JSON-nal (`/docs/api.json`) az alkalmazás saját URL-jén, HTTP Basic Auth-tal védve; a hitelesítési adatok környezeti
  változókból jönnek. A leírás lefedi az összes API-végpontot, a kérés- és válaszsémákat és a hibaválaszokat.
- **K12 – Technológia:** Laravel 13, PHP 8.5 (a Docker-image-ben), MySQL 8.4.
- **K13 – Docker és felállítás:** a `docker-compose.yaml` tartalmazza az alkalmazást (PHP 8.5-FPM), a webszervert
  (nginx) és a MySQL-t. A projekt a README-ben leírt néhány paranccsal felállítható; a teljes adatbázisséma
  migrációkból épül fel, demo adatok seederrel opcionálisan betölthetők.
- **K14 – Kódminőség:** PSR-4 autoload, PSR-12 kódstílus (Pint `psr12` preset és PHPCS `PSR12` szabvány), Larastan
  max szint, SOLID és DRY szerinti rétegzés: vékony controllerek, a validáció, az üzleti szabályok és a
  válaszformázás külön rétegben, függőséginjektálással.
- **K15 – Minőségkapu:** a `make check` a Docker-konténerben futtatja a Pint-ellenőrzést, a PHPCS-t, a Larastant és a
  PHPUnit-tesztkészletet (MySQL tesztadatbázison). GitHub Actions CI ugyanezt futtatja push és pull request esetén,
  és ellenőrzi a Docker-környezet indulását is.
- **K16 – Nyelv:** a README magyar; az API szövegei (hibaüzenetek, OpenAPI-leírások) angolok; a kód, az azonosítók és
  a mezőnevek angolok.
- **K17 – Commitok:** a munka logikai lépésenként, több commitban kerül a lánc ágára, nem egyetlen nagy commitban
  (a felhasználó kérése).

## Elfogadási kritériumok

### Eszközök

- E1: Adott érvényes eszközadat (`name`, `manufacturer`, `model`, opcionálisan `description`), amikor
  `POST /api/v1/devices`, akkor a válasz 201, a `data` objektum tartalmazza az `id`-t és a mentett mezőket, és az
  eszköz bekerül az adatbázisba.
- E2: Adott hiányzó kötelező mező vagy túl hosszú érték, amikor eszközt hozunk létre vagy módosítunk, akkor a válasz
  422 Problem Details `errors` objektummal, amely a hibás mezőkhöz angol hibaüzenetet ad, és az adatbázis nem változik.
- E3: Adott több eszköz, amikor `GET /api/v1/devices` (opcionálisan `page` és `per_page`), akkor a válasz 200, lapozott
  lista (`data`, `links`, `meta`) `id` szerint növekvő sorrendben; az alapértelmezett oldalméret 15; az 1–100
  tartományon kívüli `per_page` vagy az 1-nél kisebb `page` 422-t ad.
- E4: Adott létező eszköz, amikor `GET /api/v1/devices/{device}`, akkor a válasz 200 az eszköz adataival; nem létező
  azonosítóra 404 Problem Details.
- E5: Adott létező eszköz, amikor `PUT /api/v1/devices/{device}` minden kötelező mezővel, akkor a válasz 200 a mentett
  új értékekkel; ha a `PUT` kérésből kötelező mező hiányzik, 422.
- E6: Adott létező eszköz, amikor `PATCH /api/v1/devices/{device}` egyetlen mezővel, akkor a válasz 200, és csak az a
  mező változik.
- E7: Adott szerződéshez nem rendelt eszköz, amikor `DELETE /api/v1/devices/{device}`, akkor a válasz 204, és az eszköz
  törlődik; nem létező azonosítóra 404.
- E8: Adott legalább egy szerződéshez rendelt eszköz, amikor `DELETE /api/v1/devices/{device}`, akkor a válasz 409
  Problem Details, és az eszköz és a kapcsolatai megmaradnak.

### Szerződések

- E9: Adott érvényes szerződésadat, amikor `POST /api/v1/contracts`, akkor a válasz 201, a `data` tartalmazza az
  `id`-t és a mentett mezőket (a dátumok `Y-m-d` formában), és a szerződés bekerül az adatbázisba.
- E10: Adott érvénytelen szerződésadat (hiányzó kötelező mező, hibás dátumformátum, a `start_date`-nél korábbi
  `end_date`, már létező `contract_number`), amikor szerződést hozunk létre vagy módosítunk, akkor a válasz 422
  Problem Details a hibás mezőkre vonatkozó `errors` objektummal, és az adatbázis nem változik.
- E11: Adott több szerződés, köztük eszközökkel rendelkező is, amikor `GET /api/v1/contracts`, akkor a válasz 200,
  lapozott lista (az E3 szerinti lapozással és sorrenddel), és egyik elem sem tartalmaz `devices` kulcsot.
- E12: Adott egy szerződés két hozzárendelt eszközzel, amikor `GET /api/v1/contracts/{contract}`, akkor a válasz 200,
  és a `devices` tömb mindkét eszközt tartalmazza az eszköz adataival, a `serial_number`-rel és a hozzárendelés
  idejével; eszköz nélküli szerződésnél a `devices` üres tömb; nem létező azonosítóra 404.
- E13: Adott létező szerződés, amikor `PUT` (minden kötelező mezővel) vagy `PATCH` (részleges adattal) módosítjuk,
  akkor a válasz 200 a mentett új értékekkel; a saját `contract_number` megtartása nem ütközik az egyediséggel;
  `PATCH` esetén a beküldött `end_date` a tárolt `start_date`-hez képest is ellenőrződik (korábbi dátum: 422).
- E14: Adott eszközökkel rendelkező szerződés, amikor `DELETE /api/v1/contracts/{contract}`, akkor a válasz 204, a
  szerződés és a kapcsolatai törlődnek, az eszközök megmaradnak; nem létező azonosítóra 404.

### Eszköz felvétele és levétele

- E15: Adott létező szerződés és eszköz, amikor `POST /api/v1/contracts/{contract}/devices` `{device_id, serial_number}`
  törzzsel, akkor a válasz 201, tartalmazza az eszköz adatait és a gyári számot, és az eszköz megjelenik a szerződés
  lekérdezésében.
- E16: Adott egy eszköztípus, amely már szerepel egy szerződésen, amikor egy másik szerződésre egy másik gyári számmal
  vesszük fel, akkor a válasz 201 (N:N kapcsolat).
- E17: Adott egy már használt gyári szám (bármely szerződésen), amikor ugyanezzel a gyári számmal – kis- és
  nagybetűtől függetlenül – veszünk fel eszközt, akkor a válasz 422 `errors.serial_number` hibával, és nem jön létre
  kapcsolat.
- E18: Adott egy eszköz, amely már szerepel egy szerződésen, amikor ugyanarra a szerződésre egy még nem használt gyári
  számmal ismét felvesszük, akkor a válasz 409 Problem Details, és nem jön létre új kapcsolat.
- E19: Adott hiányzó vagy nem létező `device_id`, illetve hiányzó vagy túl hosszú gyári szám, amikor eszközt veszünk
  fel, akkor a válasz 422; nem létező szerződés esetén 404.
- E20: Adott egy szerződésen lévő eszköz, amikor `DELETE /api/v1/contracts/{contract}/devices/{device}`, akkor a válasz
  204, a kapcsolat törlődik, az eszköz és a szerződés megmarad; ha az eszköz nincs a szerződésen vagy nem létezik, 404.

### Egységes hibakezelés

- E21: Adott ismeretlen `/api/...` útvonal, illetve az útvonalon nem támogatott HTTP-metódus, amikor kérést küldünk
  `Accept` fejléc nélkül is, akkor a válasz 404, illetve 405, `application/problem+json` típusú, `type`, `title`,
  `status` és `detail` mezőkkel.
- E22: Adott egy kezeletlen kivétel egy API-végponton éles módban (`APP_DEBUG=false`), amikor kérést küldünk, akkor a
  válasz 500 Problem Details, amely nem tartalmazza a kivétel üzenetét, a stack trace-t vagy fájlútvonalat.
- E23: Adott bármely hibaág (404, 405, 409, 422, 500), amikor a választ vizsgáljuk, akkor a `Content-Type`
  `application/problem+json`, a törzs szerkezete azonos (`type`, `title`, `status`, `detail`), és a `status` mező
  megegyezik a HTTP-státusszal.

### Naplózás

- E24: Adott egy sikeres API-kérés, amikor lefut, akkor a naplótáblában pontosan egy új sor keletkezik a metódussal,
  útvonallal, query-paraméterekkel, kérés- és válaszfejlécekkel, kérés- és választörzzsel, státusszal, időtartammal,
  kliens IP-vel, user agenttel és időponttal.
- E25: Adott egy hibával végződő API-kérés (422, ismeretlen útvonal miatti 404, 500), amikor lefut, akkor a naplósor a
  hibaválasz státuszát és törzsét tartalmazza.
- E26: Adott egy kérés érzékeny adatokkal (`Authorization` és `Cookie` fejléc; `password`, `token`, `secret` jellegű
  mezők, beágyazva is), amikor naplózunk, akkor ezek értéke maszkolva kerül az adatbázisba; a beállított
  méretkorlátnál nagyobb törzs csonkolva, a csonkolás jelölésével.
- E27: Adott, hogy a naplóírás kivételt dob, amikor API-kérést küldünk, akkor a kliens ugyanazt a választ kapja, mint
  naplózás nélkül, és a hiba az alkalmazásnaplóba kerül.
- E28: Adott egy nem API-útvonal (pl. `/docs/api`, `/up`), amikor kérés érkezik rá, akkor nem keletkezik naplósor.

### OpenAPI-dokumentáció

- E29: Adott beállított dokumentációs hitelesítési adatok, amikor `/docs/api` vagy `/docs/api.json` kérés érkezik
  hitelesítés nélkül vagy hibás adatokkal, akkor a válasz 401 `WWW-Authenticate: Basic` fejléccel; ha nincs beállítva
  hitelesítési adat, a hozzáférés akkor is elutasított.
- E30: Adott helyes Basic Auth adatok, amikor `GET /docs/api.json`, akkor a válasz 200 és érvényes OpenAPI 3.x
  dokumentum, amely tartalmazza az összes API-útvonalat a megfelelő metódusokkal (`/api/v1/devices`,
  `/api/v1/devices/{device}`, `/api/v1/contracts`, `/api/v1/contracts/{contract}`,
  `/api/v1/contracts/{contract}/devices`, `/api/v1/contracts/{contract}/devices/{device}`); a `GET /docs/api` pedig
  200-as HTML felületet ad.

### Felállítás és minőség

- E31: Adott egy üres MySQL adatbázis, amikor `php artisan migrate` fut, akkor létrejön a teljes séma (eszközök,
  szerződések, kapcsolótábla idegen kulcsokkal és egyedi indexekkel, naplótábla), és a `php artisan migrate:rollback`
  hibátlanul visszagörgeti.
- E32: Adott egy migrált adatbázis, amikor `php artisan db:seed` fut, akkor demo eszközök, szerződések és
  szerződés–eszköz kapcsolatok jönnek létre, a K7 szabályait betartva.
- E33: Adott a repó friss klónja, amikor a README lépései szerint elindítjuk a Docker-környezetet, akkor az alkalmazás
  PHP 8.5-ön fut, a migrációk lefutnak, és a `GET http://localhost:8080/api/v1/devices` 200-at ad (a CI ezt
  automatikusan ellenőrzi).
- E34: Adott a kódbázis, amikor `make check` fut, akkor a Pint (PSR-12) ellenőrzés, a PHPCS PSR-12 ellenőrzés, a
  Larastan max szintű elemzése és a PHPUnit-tesztek hibátlanul lefutnak; ugyanez fut GitHub Actions CI-ban.
- E35: Adott a lánc ága a lezárás után, amikor a commitokat vizsgáljuk (`git log main..HEAD`), akkor legalább nyolc,
  egy-egy logikai egységet tartalmazó commit van, beszédes commitüzenetekkel.

## Feltételezések

- Az „eszköz” eszköztípust jelent; a gyári szám a szerződés–eszköz kapcsolat adata (felhasználói döntés).
- A végpontok verziózott előtagot kapnak: `/api/v1`.
- Az API végpontjai hitelesítés nélkül hívhatók; a feladat Basic Auth-ot csak a dokumentációhoz kér.
- A kérések és a válaszok JSON formátumúak; a sikeres válaszok `data` burokban érkeznek (Laravel API Resource), a
  listák lapozottak (`page`, `per_page`: alapértelmezés 15, legfeljebb 100), `id` szerint növekvő sorrendben.
- `PUT` teljes módosítás (minden kötelező mező kell), `PATCH` részleges; az ismeretlen mezőket az API figyelmen kívül
  hagyja.
- A dátumok `Y-m-d`, az időbélyegek ISO 8601 formátumúak, UTC-ben.
- A szerződésszám és a gyári szám egyedisége kis- és nagybetű-érzéketlen (MySQL alapértelmezett collation); a
  beküldött szövegek elejéről és végéről a szóközök levágódnak.
- Felvételkor a validáció (422) megelőzi az üzleti szabályt (409): ha a gyári szám foglalt, a válasz 422 akkor is, ha
  az eszköz már a szerződésen van.
- A „publikus URL” az alkalmazás saját, az API-tól független útvonala (`/docs/api`), amelyet Basic Auth véd; a
  hitelesítési adatok a `.env`-ből jönnek, a `.env.example` fejlesztői alapértéket tartalmaz. Külső szerverre
  telepítés nem része a feladatnak.
- A naplózás csak a `/api/*` útvonalakra vonatkozik, a sor a válasz elküldése után íródik. Maszkolt fejlécek:
  `Authorization`, `Cookie`, `Set-Cookie`, `X-Api-Key`, `X-Auth-Token`; maszkolt mezők például `password`, `token`,
  `secret`, `api_key` (kis- és nagybetűtől függetlenül, beágyazott struktúrákban is). A törzsek 64 KB felett
  csonkolódnak (konfigurálható).
- A tesztek a Docker-környezetben, külön MySQL tesztadatbázison futnak (nem SQLite-on), hogy az indexeket és az idegen
  kulcsokat is ellenőrizzék.
- A projekt helye `/Users/vronald/Projects/asset-registry-api`, az alapág `main`. A nyilvános GitHub repó létrehozása
  és a push a lezáráskor, a felhasználó kifejezett kérésére történik.
- A kiegészítő csomagok (OpenAPI-generátor, Larastan, PHPCS) Laravel 13-mal és PHP 8.5-tel kompatibilis, aktuális
  verziók; a konkrét választást a terv rögzíti.

## Hatókörön kívül

- Felhasználókezelés, hitelesítés és jogosultságkezelés az API-n.
- Grafikus felület.
- Telepítés publikus szerverre, hosztolás.
- A naplók lekérdezése API-n, naplómegőrzési szabályok és automatikus takarítás.
- Szűrés, keresés és rendezési paraméterek a listákon.
- Soft delete és változástörténet (a kérés- és válasznaplón túl).
- Ugyanazon eszköztípus több példánya ugyanazon a szerződésen, és a gyári szám módosítása meglévő kapcsolaton
  (helyette levétel és újrafelvétel).
- Az eszköz lekérdezése nem listázza azokat a szerződéseket, amelyeken szerepel.

## Nyitott kérdések

Nincs.
