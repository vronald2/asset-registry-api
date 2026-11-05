# Eszköznyilvántartó REST API

REST API eszközök (eszköztípusok) és szerződések nyilvántartására. Az eszközök gyári számmal
kerülnek a szerződésekre: egy eszköztípus több szerződésen is szerepelhet, de minden példány más
gyári számmal (N:N kapcsolat).

**Laravel 13 · PHP 8.5 · MySQL 8.4 · Docker · OpenAPI 3.1**

A tervezési döntések, feltételezések és azok indoklása: [PLAN.md](PLAN.md).

---

## Követelmények teljesítése

| Követelmény | Megoldás |
|-------------|----------|
| Eszköz és szerződés CRUD (lista + egy elem) | `/api/v1/devices`, `/api/v1/contracts` |
| Szerződés lekérdezésekor a rajta lévő eszközök, a listában nem | `GET /api/v1/contracts/{id}` → `devices`; a lista nem tölti be a kapcsolatot |
| Eszköz hozzáadása / levétele szerződésről | `POST` / `DELETE /api/v1/contracts/{id}/devices[/{contractDevice}]` |
| Egységes hibakezelés | minden hiba azonos JSON formátumban, gépi hibakóddal és `request_id`-val |
| Request/response naplózás adatbázisba | `api_request_logs` tábla, érzékeny adatok maszkolva |
| OpenAPI dokumentáció Basic Auth-tal | `/docs/api` (UI), `/docs/api.json` (specifikáció) |
| Laravel 13, PHP 8.5, MySQL | Laravel 13.40, `php:8.5-fpm-alpine`, `mysql:8.4` |
| Clean code (SOLID, DRY), PSR-4, PSR-12 | rétegzett felépítés, Pint + PHP_CodeSniffer (PSR-12), Larastan max szint |
| Docker, migrációkból felállítható | `docker-compose.yaml`, `php artisan migrate --seed` |

---

## Gyors indítás

Előfeltétel: Docker (Compose v2). A `make` opcionális, minden lépés futtatható nélküle is.

```bash
make setup
```

Ugyanez lépésenként:

```bash
cp .env.example .env
```

```bash
docker compose up -d --build
```

```bash
docker compose exec app composer install
```

```bash
docker compose exec app php artisan key:generate
```

```bash
docker compose exec app php artisan migrate --seed
```

A séma kizárólag migrációkból épül, a `--seed` valósághű demó adatokat tölt be (8 eszköztípus,
3 szerződés, 14 gyári számos tétel). Újraépítés: `make fresh`.

| Mi | Hol |
|----|-----|
| API | <http://localhost:8080/api/v1> |
| OpenAPI UI | <http://localhost:8080/docs/api> – Basic Auth: `docs` / `change-me` (`.env`: `API_DOCS_USERNAME`, `API_DOCS_PASSWORD`) |
| OpenAPI JSON | <http://localhost:8080/docs/api.json> (exportálva: [docs/openapi.json](docs/openapi.json)) |
| Health check | <http://localhost:8080/up> |
| MySQL | `localhost:3307` (`asset_registry` / `secret`) |

Portütközés esetén az `APP_PORT` és a `FORWARD_DB_PORT` változtatható a `.env`-ben.
Linuxon a `make setup` a gazdagép felhasználójának UID/GID-jével építi az image-et, így a
konténer által írt fájlok (pl. `vendor/`) a felhasználóé maradnak.

Konténerek: `app` (PHP 8.5-FPM, nem root felhasználóval), `web` (Nginx), `mysql` (MySQL 8.4,
healthcheckkel; az init script a tesztekhez külön adatbázist hoz létre).

---

## Végpontok

| Metódus | URL | Leírás | Siker |
|---------|-----|--------|-------|
| `GET` | `/api/v1/devices` | Eszközök listája (lapozott) | 200 |
| `POST` | `/api/v1/devices` | Eszköz létrehozása | 201 + `Location` |
| `GET` | `/api/v1/devices/{device}` | Egy eszköz | 200 |
| `PUT` | `/api/v1/devices/{device}` | Eszköz módosítása | 200 |
| `DELETE` | `/api/v1/devices/{device}` | Eszköz törlése (ha nincs szerződésen) | 204 |
| `GET` | `/api/v1/contracts` | Szerződések listája (lapozott, eszközök nélkül) | 200 |
| `POST` | `/api/v1/contracts` | Szerződés létrehozása | 201 + `Location` |
| `GET` | `/api/v1/contracts/{contract}` | Egy szerződés a rajta lévő eszközökkel | 200 |
| `PUT` | `/api/v1/contracts/{contract}` | Szerződés módosítása | 200 |
| `DELETE` | `/api/v1/contracts/{contract}` | Szerződés törlése a tételeivel együtt | 204 |
| `POST` | `/api/v1/contracts/{contract}/devices` | Eszköz hozzáadása gyári számmal | 201 |
| `DELETE` | `/api/v1/contracts/{contract}/devices/{contractDevice}` | Eszköz levétele a szerződésről | 204 |

- Listák: `page`, `per_page` (1–100, alapértelmezés 15); válasz: `data`, `links`, `meta`.
- Módosítás `PUT`-tal, teljes reprezentációval (az elhagyott opcionális mezők törlődnek).
- A `{contractDevice}` a szerződés részleteiben szereplő `devices[].id`; csak az adott szerződés
  tétele oldható fel (scoped binding), más szerződés tétele `404`.
- Mezők: eszköz – `name`, `manufacturer`, `category` (`laptop`, `desktop`, `monitor`, `printer`,
  `phone`, `tablet`, `network`, `other`), `description`; szerződés – `contract_number` (egyedi),
  `partner_name`, `start_date`, `end_date` (≥ `start_date`), `description`;
  hozzáadás – `device_id`, `serial_number`.

### Példák

```bash
curl -s -X POST http://localhost:8080/api/v1/devices -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"name":"Latitude 5550","manufacturer":"Dell","category":"laptop"}'
```

```bash
curl -s -X POST http://localhost:8080/api/v1/contracts/1/devices -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"device_id":1,"serial_number":"5CD4211XKZ"}'
```

```bash
curl -s http://localhost:8080/api/v1/contracts/1
```

```bash
curl -s -i -X DELETE http://localhost:8080/api/v1/devices/1
```

Az utolsó `409 device_in_use` hibát ad, mert az 1-es eszköz szerződésen van.

---

## Egységes hibakezelés

Minden `api/*` alatti hiba – az `Accept` fejléctől függetlenül – ugyanabban a formátumban érkezik:

```json
{
  "message": "The given data was invalid.",
  "code": "validation_failed",
  "errors": { "serial_number": ["A unit of this device with the given serial number is already on a contract."] },
  "request_id": "01a1ac12-ff5a-738b-b881-9d3dd96ca433"
}
```

| HTTP | `code` | Mikor |
|------|--------|-------|
| 404 | `resource_not_found` | nem létező erőforrás |
| 404 | `route_not_found` | nem létező végpont |
| 405 | `method_not_allowed` | nem támogatott HTTP ige (`Allow` fejléccel) |
| 409 | `device_in_use` | szerződésen lévő eszköz törlése |
| 409 | `serial_number_already_assigned` | gyári szám ütközés párhuzamos kéréseknél (DB unique index) |
| 409 | `conflict` | egyéb adatbázis szintű egyediségi ütközés |
| 422 | `validation_failed` | validációs hiba (`errors` mezőnként) |
| 429 | `too_many_requests` | rate limit (`Retry-After` fejléccel) |
| 500 | `internal_server_error` | váratlan hiba – belső részlet nélkül (`APP_DEBUG=true` mellett `debug` blokkal) |

Minden válasz `X-Request-Id` fejlécet kap (a kliens által küldött, érvényes azonosítót átveszi);
ugyanez a hibaválasz `request_id` mezője és a forgalomnapló kulcsa, így egy hibajelzés azonnal
visszakereshető. Megvalósítás: `App\Exceptions\ApiExceptionRenderer`, üzleti hibák:
`App\Exceptions\ApiException` leszármazottai.

---

## API forgalom naplózása

Minden `api/*` kérés és a rá adott válasz bekerül az `api_request_logs` táblába – a nem létező
végpontokra érkezők (404, 405) is. Mentett adatok: request id, metódus, URL, route minta, IP, user
agent, fejlécek és törzsek (kérés és válasz), státusz, futási idő.

- Érzékeny értékek (`authorization`, `cookie`, `password`, `token`, …) maszkolva, bármilyen
  mélységben, a query stringben is – a lista a `config/api.php`-ben bővíthető.
- Az írás a válasz elküldése után történik (`terminate`), és a naplózás hibája nem töri el a
  kérést.
- Megőrzés: `API_LOGGING_RETENTION_DAYS` (alapból 30 nap), takarítás: `php artisan model:prune`
  (naponta ütemezve).

A legutóbbi bejegyzések megtekintése:

```bash
make traffic
```

---

## OpenAPI dokumentáció

A specifikációt a [Scramble](https://scramble.dedoc.co) a kódból generálja (FormRequest szabályok,
API Resource-ok, route-ok, PHPDoc), így nem tud elavulni. A hibaválaszok a fenti egységes
formátumban szerepelnek benne (`App\OpenApi\ApiErrorResponseExtension`).

- UI (Stoplight Elements, „Try it” funkcióval): `/docs/api`, specifikáció: `/docs/api.json`.
- Basic Auth: `API_DOCS_USERNAME` / `API_DOCS_PASSWORD`; ha bármelyik üres, a hozzáférés tiltott.
- Export a repóba: `make docs` → [docs/openapi.json](docs/openapi.json).

---

## Minőségbiztosítás

```bash
make check
```

Ez sorban lefuttatja: `composer validate --strict`, `composer dump-autoload --strict-psr` (PSR-4),
Pint `psr12` presettel és PHP_CodeSniffer `PSR12` szabálykészlettel (PSR-12), Larastan **max**
szinten, majd a teljes tesztkészletet. Külön célok: `make test`, `make lint`, `make fix`,
`make analyse`.

- 61 teszt külön MySQL teszt adatbázison: eszköz és szerződés CRUD, hozzáadás/levétel szabályai
  (N:N, gyári szám egyedisége, scoped binding, párhuzamos ütközés), hibaformátum (404, 405, 409, 422,
  429, 500), request id, forgalomnaplózás (maszkolás, csonkolás, hibatűrés), docs Basic Auth.
- Minden saját fájl `declare(strict_types=1)`, a modellek strict módban futnak (lazy loading
  tiltva – N+1 nem csúszhat át).
- CI: [.github/workflows/ci.yml](.github/workflows/ci.yml) – ugyanezek a lépések PHP 8.5-ön és MySQL
  8.4 service konténerrel.

---

## Felépítés

Vékony controllerek → FormRequest (validáció → immutable DTO) → service (üzleti szabályok) →
Eloquent; a kimenetet API Resource-ok állítják elő. Részletesen, a SOLID/DRY megfontolásokkal és
azzal együtt, hogy miért nincs repository réteg: [PLAN.md](PLAN.md).

```
app/
├── Data/                     DeviceData, ContractData, ContractDeviceData (readonly DTO-k)
├── Enums/                    DeviceCategory
├── Exceptions/               ApiException + üzleti kivételek, ErrorCode, ApiExceptionRenderer
├── Http/
│   ├── Controllers/Api/V1/   DeviceController, ContractController, ContractDeviceController
│   ├── Middleware/           AssignRequestId, LogApiTraffic, AuthenticateDocs
│   ├── Requests/             DeviceRequest, ContractRequest, AttachContractDeviceRequest, IndexRequest
│   └── Resources/            DeviceResource, ContractResource, ContractDeviceResource
├── Logging/ApiTraffic/       ApiTrafficLogger (interfész) + DatabaseApiTrafficLogger, rekord, factory, maszkoló
├── Models/                   Device, Contract, ContractDevice, ApiRequestLog
├── OpenApi/                  a hibaformátum dokumentálása a Scramble-ben
└── Services/                 DeviceService, ContractService, ContractDeviceService
config/api.php                lapozás, rate limit, naplózás, docs hitelesítés
docker/                       PHP-FPM Dockerfile, Nginx és MySQL konfiguráció
```

## Feltételezések röviden

- Az „eszköz” eszköztípus (pl. Dell Latitude 5540); a fizikai példányt a szerződés tétele
  azonosítja a gyári számával.
- Egy gyári szám eszköztípuson belül egyedi, így egy fizikai eszköz egyszerre egy szerződésen
  lehet; ugyanaz a típus egy szerződésen többször is szerepelhet.
- Szerződésen lévő eszköztípus nem törölhető (`409`); szerződés törlésekor a tételei is törlődnek.
- Az API-hoz a feladat nem kér authentikációt; csak a dokumentáció védett.

Részletesen: [PLAN.md](PLAN.md) – 2. fejezet.
