# Terv – Eszköznyilvántartó REST API

<!--
A tervezés lépés kimenete; a felhasználó ezt hagyja jóvá (/devchain:jovahagyas), a megvalósítás ezt követi,
a review ehhez mér. A jóváhagyás után a fájl zárolt; módosításhoz `devchain replan` és új jóváhagyás kell.
Minden «kitöltendő» jelölőt cserélj le. A projekt meglévő konvencióit (CLAUDE.md, README, a meglévő kód
rétegei) kövesd.
-->

## Cél

A `feature/eszkoznyilvantarto-rest-api` ágon elkészül egy Dockerben futó Laravel 13 (PHP 8.5, MySQL 8.4) REST API,
amely teljesíti a REQUIREMENTS.md K1–K17 követelményeit. Tartalma: eszközök (eszköztípusok) és szerződések CRUD-ja
`/api/v1` alatt; eszköz felvétele és levétele szerződésről rendszerszinten egyedi gyári számmal; a szerződés
részletezője a rajta lévő eszközökkel; RFC 9457 szerinti egységes hibaválaszok; a teljes `/api/*` forgalom maszkolt,
csonkolt naplózása MySQL-be; Basic Auth-tal védett OpenAPI 3.1 dokumentáció (`/docs/api`, `/docs/api.json`). A
projekt `make up`-pal vagy `docker compose up -d`-vel felállítható, a séma migrációkból épül fel. A `make check`
(Pint PSR-12, PHPCS PSR-12, Larastan max, PHPUnit MySQL-en) és a GitHub Actions CI zöld. Az E1–E35 kritériumokat
automata tesztek, illetve a CI ellenőrzik, a munka pedig logikai lépésenkénti commitokban kerül az ágra.

## Megközelítés

### Alapváz és rétegek

- Friss `laravel/laravel` ^13 váz. A projekten kívül lefuttatott próbán ez framework 13.40-et és PHPUnit 12.5-öt
  adott, és PHP 8.5-ön a tervezett fejlesztői eszközök is feloldódtak.
- API-only kialakítás: a frontend (Vite, npm, welcome nézet), a példatesztek és a vázban lévő AI-ügynökfájlok
  (`CLAUDE.md`, `AGENTS.md`) nem kerülnek a repóba, a `/` a dokumentációra irányít. A keretrendszer alap migrációi
  (users, cache, jobs) maradnak, mert az alapértelmezett session-, cache- és queue-driverek használják őket.
- `composer.json`: `"php": "^8.5"`. Új függőség a `dedoc/scramble` ^0.13, fejlesztői függőség a
  `larastan/larastan` ^3.14 és a `squizlabs/php_codesniffer` ^4.0.
- Laravel-rétegek a PSR-4 `App\` névtérben, vékony szolgáltatásréteggel:
  `route → controller (vékony) → FormRequest (validáció) → service (üzleti szabály) → Eloquent modell →
  API Resource (kimenet)`.
- Repository-réteget nem vezetek be, mert itt az Eloquent maga az adatelérési absztrakció, egy újabb interfész csak
  indirekciót hozna. Interfészt ott használok, ahol tényleg kell cserélhetőség vagy tesztbeli helyettesítés (naplóíró).
- A konfigurációs értékeket konstruktorban kapják az osztályok, a Laravel `#[Config]` konténer-attribútumával, így a
  szolgáltatásokban nincs `config()` hívás. A modelleket Laravel 13 attribútumok konfigurálják (`#[Fillable]`). Nem
  éles környezetben `Model::shouldBeStrict()` jelzi a lusta betöltést és a hibás tömeges értékadást.

### Adatmodell

- `devices`: `id`, `name` (255), `manufacturer` (255), `model` (255), `description` (text, null), időbélyegek.
- `contracts`: `id`, `contract_number` (50, egyedi), `partner_name` (255), `start_date` (date), `end_date` (date,
  null), `description` (text, null), időbélyegek.
- `contract_device` kapcsolótábla (`ContractDevice` pivot modell): `id`, `contract_id` (FK, `cascadeOnDelete`),
  `device_id` (FK, `restrictOnDelete`), `serial_number` (100, egyedi), időbélyegek, egyedi index
  (`contract_id`, `device_id`). Így a K7 egyediségeit és a K8 törlési szabályait az adatbázis is garantálja.
- `api_request_logs`: `id`, `method`, `path`, `route` (null), `query` (json), `request_headers` (json),
  `request_body` (mediumText, null), `response_status`, `response_headers` (json), `response_body` (mediumText,
  null), `duration_ms`, `ip`, `user_agent`, `created_at` (indexelve). Csak `created_at` van, a sor nem módosul.
- `Contract::devices()` és `Device::contracts()`: BelongsToMany a `ContractDevice` pivottal
  (`withPivot('serial_number')`, `withTimestamps()`), a szerződés eszközei a hozzárendelés sorrendjében jönnek.

### Validáció és a PUT/PATCH szemantika

- Közös absztrakt `ResourceWriteRequest`: POST és PUT esetén a teljes szabálykészlet érvényes, PATCH-nél minden
  szabály elé `sometimes` kerül. Így egy szabálydefiníció szolgálja ki mindhárom metódust (DRY).
- `ContractRequest`: a `contract_number` egyediségénél a saját rekord kimarad; az `end_date` `after_or_equal`
  szabálya a beküldött `start_date`-hez, PATCH-nél ennek hiányában a tárolt kezdődátumhoz mér; dátumformátum
  `date_format:Y-m-d`.
- `IndexRequest` (közös a két listához): `page` ≥ 1, `per_page` 1–100 (alapértelmezés 15); a lapozó linkek megtartják
  a query-paramétereket.
- `AttachDeviceRequest`: `device_id` (integer, létező eszköz), `serial_number` (string, legfeljebb 100, egyedi a
  kapcsolótáblában), típusos getterekkel. A validáció (422) megelőzi az üzleti szabályt (409).

### Üzleti szabályok (szolgáltatások)

- `DeviceService::delete`: ha az eszköz szerződésen van, `DeviceInUseException` (409). Ha egy párhuzamos felvétel
  miatt mégis az FK-megszorítás (MySQL 1451) akad meg, azt is erre a kivételre fordítja.
- `ContractService::delete`: a kapcsolatokat az FK `cascadeOnDelete` törli, az eszközök megmaradnak.
- `ContractDeviceService::attach`: ha az eszköz már a szerződésen van, `DeviceAlreadyAttachedException` (409).
  `detach`: ha nincs ilyen kapcsolat, `DeviceNotAttachedException` (404). Versenyhelyzetben az egyedi indexek
  `UniqueConstraintViolationException`-t dobnak, ezt az egységes hibakezelés 409-re fordítja.

### Egységes hibakezelés (RFC 9457)

- `ProblemDetails` értékobjektum (`type`, `title`, `status`, `detail`, `instance` és kiterjesztések), a válasz
  `Content-Type`-ja `application/problem+json`.
- A `type` mindig `about:blank`, ezért az RFC szerint a `title` a HTTP-státusz szövege. A konkrét eset leírása a
  `detail`-be kerül, az `instance` a kérés útvonala. Validációs hibánál `errors` tag jön (mezőnkénti üzenetek),
  `APP_DEBUG=true` mellett `debug` tag (a kivétel osztálya, üzenete, helye); éles módban ez nincs.
- `ProblemDetailsFactory` (Throwable → ProblemDetails):
  - `ValidationException` → 422 és `errors`;
  - `NotFoundHttpException` → 404; ha mögötte `ModelNotFoundException` áll, „Device not found.” jellegű szöveggel,
    osztálynév nélkül;
  - egyéb `HttpExceptionInterface` → a saját státusza és fejlécei (pl. 405 és `Allow`);
  - az `ApiException` leszármazottai (saját domain kivételek, a státuszt statikus metódus adja) → 409 vagy 404;
  - `UniqueConstraintViolationException` → 409;
  - minden más → 500, általános üzenettel.
- `ProblemDetailsRenderer`: a `bootstrap/app.php` `withExceptions` render-callbackjében fut, csak `api/*` kérésekre
  válaszol, az `Accept` fejléctől függetlenül. A `HttpResponseException`-t érintetlenül hagyja.
- A domain kivételek `ShouldntReport`-ok, így nem kerülnek a hibanaplóba; a váratlan (500-as) hibák igen.

### API-forgalom naplózása

- Globális, a lánc elejére tett `LogApiTraffic` middleware. A `handle` csak a kezdési időt teszi a kérés
  attribútumai közé; a `terminate` a válasz elküldése után építi fel és írja ki a naplósort, így a válaszidőt nem
  rontja. A Laravel a `terminate`-hez új middleware-példányt hoz létre, ezért az állapot a kérésen utazik, nem a
  példányon.
- Mivel a middleware globális, az ismeretlen útvonalra érkező és a hibával végződő kérések is naplózódnak: mire a
  `terminate` lefut, a Laravel a kivételt már válasszá alakította.
- A felelősségek külön osztályokban vannak:
  - `ApiTrafficEntryFactory`: Request és Response → `ApiTrafficEntry` DTO;
  - `PayloadSanitizer`: fejlécek és mezők maszkolása kis- és nagybetűtől függetlenül, rekurzívan, valamint bájtbiztos
    (`mb_strcut`) csonkolás jelölővel;
  - `ApiTrafficLogger` interfész és `DatabaseApiTrafficLogger` implementáció (Eloquent `ApiRequestLog`).
- Hibatűrés: a `terminate` minden kivételt elkap, és a PSR-3 loggerrel az alkalmazásnaplóba írja; a kliens válaszát
  ez nem érinti.
- Beállítások a `config/api-logging.php`-ben: be/ki kapcsolás, naplózott útvonalminták (`api/*`), maszkolt fejlécek
  és mezők, maszk (`***`), `max_body_bytes` (65536).

### OpenAPI-dokumentáció

- Scramble: kódból generált OpenAPI 3.1 az útvonalak, a FormRequest-szabályok, a Resource-ok és a `@throws`
  annotációk alapján, így a dokumentáció nem csúszhat el a kódtól. UI: `/docs/api` (Stoplight Elements), JSON:
  `/docs/api.json`. A dokumentum teljes útvonalakat tartalmaz (`/api/v1/...`).
- A Scramble alapértelmezett hibaválasz-leírásait saját `ExceptionToResponseExtension`-ök írják felül (a Scramble a
  később regisztrált kiterjesztést választja), így a dokumentáció is `application/problem+json` sémát mutat. A
  sémát egy közös `ProblemDetailsSchema` segédosztály építi (DRY).
- Védelem: a Scramble útvonalainak middleware-je az `AuthenticateApiDocs` (HTTP Basic Auth, `hash_equals`). Az
  adatok a `.env`-ből jönnek (`API_DOCS_USERNAME`, `API_DOCS_PASSWORD`); ha nincsenek beállítva, minden kérést
  elutasít (401 és `WWW-Authenticate: Basic`).

### Docker és fejlesztői munkafolyamat

- `docker-compose.yaml`:
  - `app`: saját image `php:8.5-fpm-alpine` alapon (pdo_mysql, git, unzip, composer). A hoszt UID/GID-jével fut, így
    a bind mountolt fájlok jogosultsága Linuxon (CI) is rendben van.
  - `web`: `nginx:1.29-alpine`, a 8080-as porton.
  - `db`: `mysql:8.4`, healthcheckkel, tartós volume-mal, és egy init scripttel, amely létrehozza az
    `asset_registry_testing` tesztadatbázist.
- Az `app` entrypointja induláskor elvégzi a felállítást: `.env` másolása, ha hiányzik; `composer install`;
  `key:generate`, ha nincs kulcs; `migrate --force`. Utána jelzi a készenlétet a healthchecknek. Így
  `docker compose up -d` után a séma migrációkból felépül.
- `Makefile`: `up`, `down`, `logs`, `shell`, `migrate`, `seed`, `fresh`, `test`, `lint`, `fix`, `analyse`, `check`.
  Mind a konténerben fut, a hoszton csak Docker és make kell.

### Minőségkapuk

- `pint.json`: `psr12` preset, kiegészítve a `declare_strict_types`, a `concat_space: one`, valamint az
  importrendezés és a használatlan importok szabályaival. A próbán kiderült, hogy `concat_space` nélkül a Pint és a
  PHPCS PSR-12 ütközik a `.` operátor körüli szóközön.
- `phpcs.xml`: PSR12 szabvány az `app`, `bootstrap/app.php`, `config`, `database`, `routes`, `tests` útvonalakon. A
  figyelmeztetések is buktatnak, ezért a váz egy 120 karakternél hosszabb sorát (`config/mail.php`) tördelem.
- `phpstan.neon`: Larastan, `level: max`, ugyanezeken az útvonalakon. A váz egyetlen hibáját
  (`config/filesystems.php`, `rtrim` argumentumtípus) javítom.
- `phpunit.xml`: MySQL tesztadatbázis (`asset_registry_testing`, `force="true"`). A tesztmetódusok camelCase nevűek,
  ahogy a PSR-1 előírja.
- Composer scriptek: `lint`, `fix`, `analyse`, `test`, `check`. A `make check` = `docker compose up -d --build --wait`,
  majd `composer check` a konténerben.
- CI (GitHub Actions): `make check`, utána füstteszt: PHP 8.5 a konténerben, `/up` és `/api/v1/devices` 200,
  `/docs/api` 401 hitelesítés nélkül.

### Commitok (K17)

A devchain csak a lezárás fázisban enged commitolni, ezért ott, a végleges munkafából készülnek a commitok,
logikai egységenként, a Lépések 14. pontjában rögzített sorrendben. A több egységet érintő fájlok (composer.json és
composer.lock, `bootstrap/app.php`, `routes/api.php`, `AppServiceProvider`) végleges állapotukban abba a commitba
kerülnek, amelyikhez a leginkább tartoznak.

## Rétegek és fájlok

| Réteg | Fájl | Változás |
|-------|------|----------|
| Alap | `composer.json`, `composer.lock` | Új: Laravel 13 váz, `php ^8.5`, scramble, larastan, phpcs; scriptek (`lint`, `fix`, `analyse`, `test`, `check`); npm-lépések törlése |
| Alap | `.env.example` | Új: alkalmazásnév, `APP_URL` (8080), MySQL-kapcsolat a `db` szolgáltatásra, `API_DOCS_*` és `API_LOG_*` változók |
| Alap | `artisan`, `public/*`, `storage/*`, `bootstrap/providers.php`, `bootstrap/cache/.gitignore`, `.gitignore`, `.editorconfig`, `.gitattributes` | Új: a váz fájljai (Pint-formázással) |
| Alap | `config/*.php` (váz) | Új: a váz konfigurációi; `filesystems.php` típusjavítás, `mail.php` sortördelés |
| Alap | frontend- és AI-ügynökfájlok, példatesztek | Nem kerülnek a repóba: `package.json`, `vite.config.js`, `.npmrc`, `resources/css`, `resources/js`, `resources/views/welcome.blade.php`, `CLAUDE.md`, `AGENTS.md`, `tests/*/ExampleTest.php` |
| Bootstrap | `bootstrap/app.php` | Új: `api` útvonalfájl, `LogApiTraffic` globális middleware (prepend), Problem Details render-callback `api/*`-ra |
| Bootstrap | `app/Providers/AppServiceProvider.php` | Módosítás: `ApiTrafficLogger` → `DatabaseApiTrafficLogger` kötés; `Model::shouldBeStrict()` nem éles környezetben |
| Útvonalak | `routes/api.php` | Új: `v1` előtag, `apiResource('devices')`, `apiResource('contracts')`, `POST contracts/{contract}/devices`, `DELETE contracts/{contract}/devices/{device}` |
| Útvonalak | `routes/web.php` | Módosítás: `/` átirányít a `/docs/api`-ra (welcome nézet helyett) |
| Controller | `app/Http/Controllers/Api/V1/DeviceController.php` | Új: index, store (201), show, update, destroy (204); docblockok és `@throws` a dokumentációhoz |
| Controller | `app/Http/Controllers/Api/V1/ContractController.php` | Új: index (eszközök nélkül), store, show (eszközökkel), update, destroy |
| Controller | `app/Http/Controllers/Api/V1/ContractDeviceController.php` | Új: store (felvétel, 201), destroy (levétel, 204) |
| Validáció | `app/Http/Requests/Api/V1/ResourceWriteRequest.php` | Új: absztrakt alap, PATCH-nél `sometimes` minden szabály elé |
| Validáció | `app/Http/Requests/Api/V1/DeviceRequest.php` | Új: az eszközmezők szabályai |
| Validáció | `app/Http/Requests/Api/V1/ContractRequest.php` | Új: szerződésmezők, egyedi szám (a saját rekord kihagyva), dátumszabályok |
| Validáció | `app/Http/Requests/Api/V1/AttachDeviceRequest.php` | Új: `device_id`, `serial_number`, típusos getterek |
| Validáció | `app/Http/Requests/Api/V1/IndexRequest.php` | Új: `page`, `per_page`, `perPage()` |
| Kimenet | `app/Http/Resources/Api/V1/DeviceResource.php` | Új: az eszköz JSON-alakja (ISO 8601 időbélyegek) |
| Kimenet | `app/Http/Resources/Api/V1/ContractResource.php` | Új: a szerződés JSON-alakja; `devices` csak betöltött kapcsolatnál (`whenLoaded`) |
| Kimenet | `app/Http/Resources/Api/V1/ContractDeviceResource.php` | Új: a `DeviceResource` kiterjesztése `serial_number`-rel és `attached_at`-tal |
| Middleware | `app/Http/Middleware/LogApiTraffic.php` | Új: kezdési idő rögzítése, naplóírás `terminate`-ben, hibatűrően |
| Middleware | `app/Http/Middleware/AuthenticateApiDocs.php` | Új: Basic Auth a dokumentációs útvonalakra, fail-closed |
| Hibakezelés | `app/Http/ProblemDetails/ProblemDetails.php` | Új: RFC 9457 értékobjektum, `toArray()`, `toResponse()` |
| Hibakezelés | `app/Http/ProblemDetails/ProblemDetailsFactory.php` | Új: kivétel → ProblemDetails leképezés; debug-információ csak `APP_DEBUG` mellett |
| Hibakezelés | `app/Http/ProblemDetails/ProblemDetailsRenderer.php` | Új: az `api/*` kérések hibáinak renderelése |
| Szolgáltatás | `app/Services/DeviceService.php` | Új: lapozás, létrehozás, módosítás, törlés foglaltság-ellenőrzéssel |
| Szolgáltatás | `app/Services/ContractService.php` | Új: lapozás, létrehozás, módosítás, törlés, eszközök betöltése |
| Szolgáltatás | `app/Services/ContractDeviceService.php` | Új: a felvétel és a levétel üzleti szabályai |
| Modell | `app/Models/Device.php` | Új: kitölthető mezők, `contracts()` kapcsolat, factory |
| Modell | `app/Models/Contract.php` | Új: kitölthető mezők, dátum castok, `devices()` kapcsolat, factory |
| Modell | `app/Models/ContractDevice.php` | Új: pivot modell (`contract_device`) |
| Modell | `app/Models/ApiRequestLog.php` | Új: naplósor (csak `created_at`), JSON castok |
| Kivétel | `app/Exceptions/ApiException.php` | Új: absztrakt alap (statikus `status()`, `ShouldntReport`) |
| Kivétel | `app/Exceptions/DeviceInUseException.php` | Új: 409, az eszköz szerződésen van |
| Kivétel | `app/Exceptions/DeviceAlreadyAttachedException.php` | Új: 409, az eszköz már a szerződésen van |
| Kivétel | `app/Exceptions/DeviceNotAttachedException.php` | Új: 404, az eszköz nincs a szerződésen |
| Naplózás | `app/Logging/ApiTraffic/ApiTrafficEntry.php` | Új: readonly DTO |
| Naplózás | `app/Logging/ApiTraffic/ApiTrafficEntryFactory.php` | Új: Request/Response → DTO |
| Naplózás | `app/Logging/ApiTraffic/PayloadSanitizer.php` | Új: maszkolás és csonkolás |
| Naplózás | `app/Logging/ApiTraffic/ApiTrafficLogger.php` | Új: interfész |
| Naplózás | `app/Logging/ApiTraffic/DatabaseApiTrafficLogger.php` | Új: MySQL-implementáció |
| Konfiguráció | `config/api-logging.php` | Új: naplózási beállítások |
| Konfiguráció | `config/api-docs.php` | Új: dokumentációs felhasználónév, jelszó, realm |
| Konfiguráció | `config/scramble.php` | Új: útvonalminta, info (cím, verzió, leírás), middleware, kiterjesztések |
| OpenAPI | `app/OpenApi/ProblemDetailsSchema.php` | Új: közös `application/problem+json` válaszséma |
| OpenAPI | `app/OpenApi/Extensions/ValidationProblemExtension.php` | Új: 422 Problem Details leírása |
| OpenAPI | `app/OpenApi/Extensions/HttpProblemExtension.php` | Új: HttpException-ök leírása |
| OpenAPI | `app/OpenApi/Extensions/NotFoundProblemExtension.php` | Új: 404 a modellkötéshez |
| OpenAPI | `app/OpenApi/Extensions/ApiProblemExtension.php` | Új: a saját domain kivételek leírása |
| Adatbázis | `database/migrations/2026_11_07_100000_create_devices_table.php` | Új |
| Adatbázis | `database/migrations/2026_11_07_100100_create_contracts_table.php` | Új |
| Adatbázis | `database/migrations/2026_11_07_100200_create_contract_device_table.php` | Új: FK-k, egyedi indexek |
| Adatbázis | `database/migrations/2026_11_07_100300_create_api_request_logs_table.php` | Új |
| Adatbázis | `database/factories/DeviceFactory.php`, `database/factories/ContractFactory.php` | Új |
| Adatbázis | `database/seeders/DemoSeeder.php` | Új: idempotens demo adatok (eszközök, szerződések, kapcsolatok) |
| Adatbázis | `database/seeders/DatabaseSeeder.php` | Módosítás: a `DemoSeeder` hívása a User-seed helyett |
| Docker | `docker-compose.yaml` | Új: `app`, `web`, `db` szolgáltatás, healthcheckek, volume |
| Docker | `docker/php/Dockerfile`, `docker/php/entrypoint.sh`, `docker/php/conf.d/app.ini` | Új: PHP 8.5-FPM image, induláskori felállítás, PHP-beállítások |
| Docker | `docker/nginx/default.conf` | Új: nginx → PHP-FPM |
| Docker | `docker/mysql/init/01-create-testing-database.sh` | Új: tesztadatbázis és jogosultság |
| Eszközök | `Makefile` | Új: a fejlesztői és minőségkapu-célok |
| Minőség | `pint.json`, `phpcs.xml`, `phpstan.neon` | Új |
| Minőség | `phpunit.xml` | Módosítás: MySQL tesztadatbázis |
| CI | `.github/workflows/ci.yml` | Új: `make check` és füstteszt |
| Tesztek | `tests/TestCase.php` | Módosítás: `assertProblemDetails()` segédmetódus |
| Tesztek | `tests/Feature/Api/V1/DeviceApiTest.php`, `ContractApiTest.php`, `ContractDeviceApiTest.php` | Új |
| Tesztek | `tests/Feature/Api/ProblemDetailsTest.php`, `tests/Feature/Api/ApiTrafficLoggingTest.php` | Új |
| Tesztek | `tests/Feature/ApiDocumentationTest.php` | Új |
| Tesztek | `tests/Feature/Database/MigrationTest.php`, `tests/Feature/Database/DemoSeederTest.php` | Új |
| Tesztek | `tests/Unit/Logging/PayloadSanitizerTest.php`, `tests/Unit/Http/ProblemDetailsFactoryTest.php` | Új |
| Dokumentáció | `README.md` | Új: magyar nyelvű leírás (a váz README-je helyett) |

## Lépések

1. **Alapváz.** Laravel 13 váz generálása a `composer:2` konténerben egy ideiglenes könyvtárba, átmásolás a repóba;
   API-only takarítás; `composer.json` (PHP-verzió, függőségek, scriptek), `.env.example`.
   Ellenőrzés: `php artisan --version` 13.x a konténerben.
2. **Docker-környezet.** Dockerfile, entrypoint, PHP ini, nginx, MySQL init, `docker-compose.yaml`, `Makefile`.
   Ellenőrzés: `make up` után a `GET /up` 200, a tesztadatbázis létezik.
3. **Minőségi eszközök.** `pint.json`, `phpcs.xml`, `phpstan.neon`, `phpunit.xml`, composer scriptek, a váz két
   kapuhibájának javítása, `TestCase::assertProblemDetails()`.
   Ellenőrzés: `make lint` és `make analyse` zöld.
4. **Egységes hibakezelés.** `ProblemDetails`, `ProblemDetailsFactory`, `ProblemDetailsRenderer`, `ApiException`,
   regisztráció a `bootstrap/app.php`-ban; unit tesztek és az E21–E23 feature tesztjei.
   Ellenőrzés: `make test`.
5. **Adatmodell.** Migrációk, modellek, pivot modell, factoryk; `MigrationTest` (E31).
6. **Eszköz API.** `IndexRequest`, `ResourceWriteRequest`, `DeviceRequest`, `DeviceResource`, `DeviceService`,
   `DeviceInUseException`, `DeviceController`, `routes/api.php`; `DeviceApiTest` (E1–E8).
7. **Szerződés API.** `ContractRequest`, `ContractResource`, `ContractDeviceResource`, `ContractService`,
   `ContractController`; `ContractApiTest` (E9–E14).
8. **Eszköz felvétele és levétele.** `AttachDeviceRequest`, `ContractDeviceService`, a két kapcsolati kivétel,
   `ContractDeviceController`, útvonalak; `ContractDeviceApiTest` (E15–E20).
9. **Naplózás.** Migráció, `ApiRequestLog`, `config/api-logging.php`, `PayloadSanitizer`, `ApiTrafficEntryFactory`,
   naplóíró interfész és MySQL-implementáció, `LogApiTraffic`, konténerkötés; `PayloadSanitizerTest` és
   `ApiTrafficLoggingTest` (E24–E28).
10. **OpenAPI.** Scramble-konfiguráció, `AuthenticateApiDocs` és `config/api-docs.php`, a Problem Details
    kiterjesztések, controller-docblockok; `ApiDocumentationTest` (E29–E30).
    Kézi ellenőrzés: a `/docs/api` felület megjelenik a böngészőben.
11. **Demo adatok.** `DemoSeeder`, `DatabaseSeeder`; `DemoSeederTest` (E32).
12. **CI.** `.github/workflows/ci.yml`: `make check` és füstteszt (E33, E34).
    Helyben: `make check`, majd a füstteszt parancsai.
13. **Dokumentálás** (dokumentálás fázis). Magyar README, angol OpenAPI-leírások, `.env.example` kommentek.
14. **Lezárás** (lezárás fázis). Commitok logikai egységenként, ebben a sorrendben:
    1. Laravel 13 alapváz, API-only kialakítás
    2. Docker-környezet (PHP 8.5-FPM, nginx, MySQL 8.4) és Makefile
    3. Minőségkapuk: Pint (PSR-12), PHPCS, Larastan max, PHPUnit MySQL-en
    4. Egységes hibakezelés RFC 9457 Problem Details formátumban
    5. Adatmodell: eszközök, szerződések, kapcsolótábla
    6. Eszközök CRUD végpontjai
    7. Szerződések CRUD végpontjai
    8. Eszköz felvétele és levétele szerződésről
    9. API-forgalom naplózása adatbázisba
    10. OpenAPI-dokumentáció Basic Auth védelemmel
    11. Demo adatok seederrel
    12. CI: GitHub Actions
    13. README

    Ellenőrzés: `git log main..HEAD` legalább nyolc beszédes commitot mutat (E35).

## Tesztterv

**Környezet.** PHPUnit 12 a Docker-környezetben, külön MySQL 8.4 tesztadatbázison, `RefreshDatabase`-szel (a
migrációs teszt kivételével), factorykkal. A tesztek a `make test` és a `make check` részei, a metódusnevek
camelCase-ek. A hibaválaszokat a közös `assertProblemDetails()` segéd ellenőrzi: `Content-Type`, kötelező tagok,
`status` = HTTP-státusz.

**Feature tesztek (HTTP-szint: valós routing, middleware, kivételkezelés):**

- `DeviceApiTest` (E1–E8): létrehozás és adatbázis-állapot; validációs hibaágak data providerrel (hiányzó mező, 255
  karakternél hosszabb név, 5000 karakternél hosszabb leírás) létrehozásnál és módosításnál; lista sorrendje,
  `links`/`meta`, alapértelmezett oldalméret, hibás `page`/`per_page`; egy elem és 404; PUT teljes csere és hiányzó
  mező; PATCH egy mezővel (a többi változatlan); törlés és 404; törlés szerződéshez rendelt eszközre (409, semmi nem
  törlődik).
- `ContractApiTest` (E9–E14): létrehozás (dátumformátum `Y-m-d`); validációs hibaágak data providerrel (hiányzó mező,
  hibás dátum, korábbi `end_date`, foglalt szerződésszám, kis- és nagybetű-eltéréssel is); lista `devices` kulcs
  nélkül; részletező két eszközzel, eszköz nélkül (üres tömb) és 404; PUT, PATCH, saját szám megtartása, PATCH-es
  `end_date` a tárolt `start_date`-hez; törlés: a kapcsolatok törlődnek, az eszközök maradnak.
- `ContractDeviceApiTest` (E15–E20): felvétel és megjelenés a részletezőben; ugyanaz a típus másik szerződésen; foglalt
  gyári szám (kis- és nagybetű-eltéréssel is), új kapcsolat nem jön létre; dupla felvétel (409); hibás bemenetek data
  providerrel (hiányzó vagy nem létező `device_id`, nem egész `device_id`, hiányzó vagy 100 karakternél hosszabb
  gyári szám); ismeretlen szerződés (404); levétel; levétel nem hozzárendelt és nem létező eszközre (404).
- `ProblemDetailsTest` (E21–E23): ismeretlen útvonal (404) és nem támogatott metódus (405 és `Allow`) `Accept`
  fejléc nélkül; 500 éles módban egy tesztben regisztrált, kivételt dobó útvonallal (a válaszban nincs kivételüzenet,
  stack trace, fájlútvonal); közös szerkezet data providerrel (404, 405, 409, 422, 500).
- `ApiTrafficLoggingTest` (E24–E28): sikeres kérés minden mezővel (metódus, útvonal, query, fejlécek, törzsek,
  státusz, időtartam, IP, user agent, időpont); hibás kérések (422, 404 ismeretlen útvonalon, 500); maszkolás
  (`Authorization`, `Cookie`, beágyazott `password`/`api_token`/`Secret`); csonkolás kis `max_body_bytes`
  beállítással, jelölővel; dobó naplóíró: a válasz azonos a normál válasszal, és `Log::error` hívódik; a `/up` és a
  `/docs/api` nem naplózódik.
- `ApiDocumentationTest` (E29–E30): 401 és `WWW-Authenticate: Basic` hitelesítés nélkül és hibás adatokkal, mindkét
  dokumentációs útvonalon; 401 beállítatlan hitelesítési adatoknál; helyes adatokkal a JSON `openapi` 3.x verziójú,
  van `info`-ja, és tartalmazza mind a hat útvonalat a megfelelő metódusokkal; a `POST /api/v1/devices` 422-es
  válasza `application/problem+json`; a UI 200-as HTML.
- `MigrationTest` (E31, `RefreshDatabase` nélkül, mert DDL): `migrate:fresh` után a táblák és oszlopok, az egyedi
  indexek (`serial_number`, (`contract_id`, `device_id`), `contract_number`) és az FK-k törlési szabályai
  (`cascade`, `restrict`) a `Schema::getIndexes()` és `Schema::getForeignKeys()` alapján; `migrate:reset`, majd
  `migrate` hibátlan.
- `DemoSeederTest` (E32): kétszeri futtatás (idempotencia), darabszámok, gyári számok egyedisége, minden kapcsolat
  érvényes.

**Unit tesztek:**

- `PayloadSanitizerTest`: fejlécmaszkolás kis- és nagybetűtől függetlenül; rekurzív mezőmaszkolás részszó-egyezéssel;
  a nem érzékeny adatok változatlanok; csonkolás jelölővel, a határon pontosan; több bájtos UTF-8 karakter nem törik
  ketté; nem JSON törzs; üres vagy null törzs.
- `ProblemDetailsFactoryTest`: `ValidationException` → 422 és `errors`; `ModelNotFoundException` mögötti 404
  modellnévvel, osztálynév nélkül; 405 a fejlécekkel; az `ApiException` leszármazottai; `UniqueConstraintViolationException`
  → 409; ismeretlen kivétel → 500 általános üzenettel; `debug` tag csak debug módban.

**CI és füstteszt (E33, E34):** a GitHub Actions friss gépen futtatja a `make check`-et, majd ellenőrzi, hogy a
konténerben PHP 8.5 fut, a `/up` és a `/api/v1/devices` 200-at ad, a `/docs/api` hitelesítés nélkül 401-et.
Ugyanezt helyben is lefuttatom az ellenőrzés fázisban.

**Commitok (E35):** a lezáráskor `git log main..HEAD --oneline`.

## Elfogadási kritériumok lefedése

| Kritérium | Megvalósítás | Teszt |
|-----------|--------------|-------|
| E1 | `DeviceController::store`, `DeviceRequest`, `DeviceService::create`, `DeviceResource` | `DeviceApiTest::testStoreCreatesDevice` |
| E2 | `DeviceRequest` szabályai, `ProblemDetailsFactory` (422 + `errors`) | `DeviceApiTest::testStoreRejectsInvalidPayload`, `testUpdateRejectsInvalidPayload` |
| E3 | `IndexRequest`, `DeviceService::paginate` (`orderBy id`, `withQueryString`), `DeviceResource::collection` | `DeviceApiTest::testIndexReturnsPaginatedDevicesOrderedById`, `testIndexRejectsInvalidPaginationParameters` |
| E4 | `DeviceController::show`, modellkötés, Problem Details 404 | `DeviceApiTest::testShowReturnsDevice`, `testShowReturnsNotFoundForUnknownDevice` |
| E5 | `ResourceWriteRequest` (PUT: teljes szabálykészlet), `DeviceService::update` | `DeviceApiTest::testPutReplacesDevice`, `testPutRequiresAllMandatoryFields` |
| E6 | `ResourceWriteRequest` (PATCH: `sometimes`) | `DeviceApiTest::testPatchUpdatesOnlyGivenField` |
| E7 | `DeviceController::destroy`, `DeviceService::delete` | `DeviceApiTest::testDestroyDeletesUnassignedDevice`, `testDestroyReturnsNotFoundForUnknownDevice` |
| E8 | `DeviceService::delete` → `DeviceInUseException` (409), FK `restrictOnDelete` | `DeviceApiTest::testDestroyRejectsDeviceAssignedToContract` |
| E9 | `ContractController::store`, `ContractRequest`, `ContractService::create`, `ContractResource` | `ContractApiTest::testStoreCreatesContract` |
| E10 | `ContractRequest` (egyediség, `date_format`, `after_or_equal`) | `ContractApiTest::testStoreRejectsInvalidPayload`, `testUpdateRejectsDuplicateContractNumber` |
| E11 | `ContractController::index`, `ContractResource` (`whenLoaded('devices')`) | `ContractApiTest::testIndexListsContractsWithoutDevices` |
| E12 | `ContractController::show`, `ContractService::loadDevices`, `ContractDeviceResource` | `ContractApiTest::testShowIncludesAssignedDevices`, `testShowReturnsEmptyDeviceListForContractWithoutDevices`, `testShowReturnsNotFoundForUnknownContract` |
| E13 | `ResourceWriteRequest`, `ContractRequest` (saját rekord kihagyása, tárolt `start_date`), `ContractService::update` | `ContractApiTest::testPutReplacesContract`, `testPatchUpdatesOnlyGivenFields`, `testUpdateKeepsOwnContractNumber`, `testPatchValidatesEndDateAgainstStoredStartDate` |
| E14 | `ContractService::delete`, FK `cascadeOnDelete` | `ContractApiTest::testDestroyDeletesContractAndAssignmentsButKeepsDevices`, `testDestroyReturnsNotFoundForUnknownContract` |
| E15 | `ContractDeviceController::store`, `AttachDeviceRequest`, `ContractDeviceService::attach`, `ContractDeviceResource` | `ContractDeviceApiTest::testAttachAddsDeviceToContract` |
| E16 | N:N kapcsolótábla, `ContractDeviceService::attach` | `ContractDeviceApiTest::testSameDeviceCanBeAttachedToAnotherContract` |
| E17 | `AttachDeviceRequest` (`unique`), egyedi index, `utf8mb4_unicode_ci` collation | `ContractDeviceApiTest::testAttachRejectsSerialNumberAlreadyInUse` |
| E18 | `ContractDeviceService::attach` → `DeviceAlreadyAttachedException` (409), egyedi index | `ContractDeviceApiTest::testAttachRejectsDeviceAlreadyOnContract` |
| E19 | `AttachDeviceRequest` (`required`, `integer`, `exists`, `max:100`), modellkötés | `ContractDeviceApiTest::testAttachRejectsInvalidPayload`, `testAttachReturnsNotFoundForUnknownContract` |
| E20 | `ContractDeviceController::destroy`, `ContractDeviceService::detach` → `DeviceNotAttachedException` (404) | `ContractDeviceApiTest::testDetachRemovesDeviceFromContract`, `testDetachReturnsNotFoundWhenDeviceIsNotOnContract`, `testDetachReturnsNotFoundForUnknownDevice` |
| E21 | `ProblemDetailsRenderer` (`api/*`, `Accept`-től független), `ProblemDetailsFactory` (HttpException + fejlécek) | `ProblemDetailsTest::testUnknownApiRouteReturnsProblemDetails`, `testUnsupportedMethodReturnsProblemDetails` |
| E22 | `ProblemDetailsFactory` (500 általános üzenet, `debug` csak `APP_DEBUG` mellett) | `ProblemDetailsTest::testUnhandledExceptionHidesInternalsInProduction`, `ProblemDetailsFactoryTest` |
| E23 | `ProblemDetails`, `ProblemDetailsFactory`, `ProblemDetailsRenderer` | `ProblemDetailsTest::testErrorResponsesShareProblemDetailsStructure` |
| E24 | `LogApiTraffic`, `ApiTrafficEntryFactory`, `DatabaseApiTrafficLogger`, `ApiRequestLog`, migráció | `ApiTrafficLoggingTest::testSuccessfulRequestIsLogged` |
| E25 | globális, legelőre tett `LogApiTraffic` (`terminate` a renderelt hibaválaszt kapja) | `ApiTrafficLoggingTest::testErrorResponsesAreLogged` |
| E26 | `PayloadSanitizer`, `config/api-logging.php` | `ApiTrafficLoggingTest::testSensitiveDataIsMasked`, `testOversizedBodyIsTruncated`, `PayloadSanitizerTest` |
| E27 | `LogApiTraffic::terminate` (try/catch, PSR-3 logger), `ApiTrafficLogger` interfész | `ApiTrafficLoggingTest::testLoggingFailureDoesNotAffectResponse` |
| E28 | `LogApiTraffic` útvonalszűrés (`api/*`) | `ApiTrafficLoggingTest::testNonApiRoutesAreNotLogged` |
| E29 | `AuthenticateApiDocs`, `config/api-docs.php`, Scramble middleware-konfiguráció | `ApiDocumentationTest::testDocsRequireValidBasicAuth`, `testDocsAreClosedWhenCredentialsAreNotConfigured` |
| E30 | Scramble-konfiguráció, `app/OpenApi/*` kiterjesztések, controller-docblockok | `ApiDocumentationTest::testOpenApiDocumentDescribesAllEndpoints`, `testErrorResponsesAreDocumentedAsProblemDetails`, `testDocsUiIsServed` |
| E31 | a négy új migráció (FK-k, egyedi indexek, `down()` metódusok) | `MigrationTest::testMigrationsCreateExpectedSchema`, `testMigrationsCanBeRolledBack` |
| E32 | `DemoSeeder` (idempotens), `DatabaseSeeder` | `DemoSeederTest::testDemoSeederCreatesConsistentData` |
| E33 | `docker-compose.yaml`, Dockerfile, entrypoint (automatikus migráció), nginx | CI füstteszt (`.github/workflows/ci.yml`), helyi `make up` és `curl` az ellenőrzés fázisban |
| E34 | `Makefile` `check` célja, composer scriptek, `pint.json`, `phpcs.xml`, `phpstan.neon`, `phpunit.xml` | `make check` (devchain minőségkapu), CI `make check` lépés |
| E35 | lezárási committerv (Lépések 14.) | `git log main..HEAD --oneline` a lezáráskor |

## Minőségkapuk

A `devchain check` ezeket futtatja, és csak a jelenlegi kódra kapott zöld eredmény számít:
- `make check`

A `make check` felhúzza (szükség esetén felépíti) a Docker-környezetet, majd a konténerben a `composer check`-et
futtatja, ebben a sorrendben: `pint --test` (PSR-12) → `phpcs` (PSR12) → `phpstan analyse` (Larastan, level max) →
`php artisan test` (PHPUnit, MySQL tesztadatbázis). Bármelyik hiba vagy figyelmeztetés esetén a kapu piros.

## Dokumentáció

- `README.md` (magyar, a váz README-je helyett):
  - áttekintés és technológiák;
  - gyors indítás (`make up`, illetve `docker compose up -d`), URL-ek (API, dokumentáció, health), a dokumentáció
    hitelesítési adatai;
  - Make-célok;
  - végpontok táblázata, `curl`-példák, lapozás;
  - hibaformátum példával;
  - naplózás (mezők, maszkolás, csonkolás);
  - adatmodell (mermaid ER-diagram);
  - architektúra és rétegek;
  - minőségkapuk és CI, tesztek;
  - tervezési döntések, feltételezések, hatókörön kívüli elemek.
- OpenAPI (angol): a Scramble `info.description`-je (hibaformátum, lapozás, verziózás) és a controller-docblockok
  (műveletek összefoglalója), mezőleírások, ahol a név önmagában nem elég.
- `.env.example`: kommentek az új változókhoz (`API_DOCS_*`, `API_LOG_*`).
- Docblockok a nyilvános osztályokon és a nem magától értetődő metódusokon (angolul).
- CHANGELOG: nincs, mert ez az első verzió; a változások a logikai commitokban követhetők.

## Kockázatok és alternatívák

- **Scramble 0.x (1.0 előtti):** a minor verziók között változhat az API. A verzió ^0.13-ra van rögzítve, a generált
  dokumentumot az `ApiDocumentationTest` ellenőrzi. Ha egy hibaválaszt nem következtet ki, `@throws` annotáció vagy
  `#[Response]` attribútum pótolja. Elvetett alternatíva: L5-Swagger és swagger-php attribútumok; ez explicitebb, de
  sok kézzel karbantartott annotációt jelent, ami elcsúszhat a kódtól.
- **Larastan max a teszteken és a konfigon is:** több explicit típus és assert kell (pl. a pivot elérése a
  resource-ban). A váz próbája alapján ez kezelhető (egyetlen hiba volt).
- **Docker-jogosultságok:** a bind mountolt kód miatt az `app` a hoszt UID/GID-jével fut. A Makefile ezt
  automatikusan átadja; sima `docker compose` esetén Linuxon az 1000/1000 az alapértelmezés (README).
- **Első indítás ideje:** az image-építés és a `composer install` az első `make check`-nél néhány perc, ez belefér a
  devchain 1500 másodperces korlátjába.
- **MySQL init script:** csak új volume-nál fut le. Régi volume esetén `docker compose down -v` kell (README).
- **Naplózás:** a törzsek személyes adatot is tartalmazhatnak (pl. partnernév); a maszkolás csak a titkokra
  vonatkozik. A tábla korlátlanul nő, megőrzési szabály nincs (hatókörön kívül; a README megemlíti, és a
  `created_at` index segíti a későbbi takarítást). Ha a PHP-folyamat végzetes hibával áll le (pl. memória
  elfogyása), a `terminate` nem fut le, így naplósor sem keletkezik.
- **Kis- és nagybetű-érzéketlen egyediség:** a Laravel alapértelmezett `utf8mb4_unicode_ci` collationjére épül
  (dokumentálva).
- **Versenyhelyzetek:** az egyedi indexek és az FK-k a végső biztosítékok; az ezekből jövő kivételek 409-re
  fordulnak.
- **Köztes commitok:** a közös fájlok végleges állapotukban kerülnek be, ezért a köztes commitok nem garantáltan
  futtathatók önállóan; a kapuk és a CI a végső állapotot ellenőrzik.
- **A dokumentációs felület CDN-ről tölti a Stoplight Elements-et:** a böngészőnek internetkapcsolat kell; a JSON
  (`/docs/api.json`) enélkül is elérhető.
- **Elvetett alternatívák:**
  - SQLite-os tesztek: gyorsabbak, de nem ellenőrzik a MySQL indexeit, FK-it és collationjét;
  - repository-minta: felesleges absztrakció az Eloquent fölött;
  - action-osztályok szolgáltatások helyett: sok apró osztály ennyi műveletnél;
  - összetett elsődleges kulcs a kapcsolótáblán: gyengébb Eloquent-támogatás;
  - soft delete: hatókörön kívül;
  - esetenként saját `type` URI a Problem Details-ben: az `about:blank` RFC-konform és egyszerűbb, a konkrét eset
    a `detail`-ből derül ki.
