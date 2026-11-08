# Terv – Hibás JSON és naplómaszkolás javítása

<!--
A tervezés lépés kimenete; a felhasználó ezt hagyja jóvá (/devchain:jovahagyas), a megvalósítás ezt követi,
a review ehhez mér. A jóváhagyás után a fájl zárolt; módosításhoz `devchain replan` és új jóváhagyás kell.
Minden «kitöltendő» jelölőt cserélj le. A projekt meglévő konvencióit (CLAUDE.md, README, a meglévő kód
rétegei) kövesd.
-->

## Cél

A `feature/hibas-json-es-naplomaszkolas-javitasa` ágon:
- a hibás JSON-törzsű `/api/*` kérés minden metódusnál 400-as Problem Details választ kap, így egy hibás PATCH nem
  tűnhet sikeresnek;
- a forgalomnaplóba nem kerülhet nyers, maszkolatlan titok: a rossz Content-Type-pal küldött JSON maszkolva kerül
  be, más, nem értelmezett törzsből csak a méret;
- az űrlapmezők egyszer mennek át a tisztításon;
- a megjegyzések és a README pontosak, a lánc dokumentumai a `docs/review-fixes-2/` alatt vannak.

Minden javításhoz regressziós teszt tartozik, a `make check` zöld, a munka logikai commitokban kerül az ágra (K1–K8,
E1–E9).

## Megközelítés

### Hibás JSON-törzs: 400 (K1)

- Új `App\Http\Middleware\RejectMalformedJson` (`final readonly`): ha `$request->isJson()`, a törzs nem üres, és a
  `json_validate()` hamis, `BadRequestHttpException('The request body is not valid JSON.')`-t dob. A meglévő
  `ProblemDetailsFactory` ebből 400-as Problem Details-t képez. Az érvénytelen UTF-8-at tartalmazó JSON is ide
  tartozik, mert a `json_validate()` arra is hamis.
- Regisztráció a `bootstrap/app.php`-ban az `api` csoport elejére, a `RejectInvalidUtf8Input` mellé:
  `$middleware->api(prepend: [RejectInvalidUtf8Input::class, RejectMalformedJson::class])`. Így a modellkötés és
  a validáció előtt fut; az üres törzsű JSON-kérést (GET, DELETE) nem érinti.
- Külön middleware, nem a meglévő bővítése: mindkettő egy szabályt ellenőriz (SRP), a regisztráció egy sor.

### Naplózás titkok nélkül (K2, K3, K4)

- `ApiTrafficEntryFactory::requestBody()` új sorrendje:
  1. ha a kérés JSON típusú, **vagy** a nyers törzs érvényes JSON (`json_validate()`), a JSON-ág fut
     (`PayloadSanitizer::body($content, expectJson: true)`), így a rossz Content-Type-pal küldött JSON is maszkolva
     kerül be;
  2. ha vannak értelmezett űrlapmezők, `PayloadSanitizer::formBody($fields)`;
  3. minden más nem üres törzsből `PayloadSanitizer::opaqueBody($content)`: csak a méret kerül be.
- `PayloadSanitizer` új metódusai:
  - `formBody(array $fields): string` – egy lépésben maszkol és tisztít (`data()`), kódol és csonkol (K4: nincs
    második dekódolási kör);
  - `opaqueBody(string $content): ?string` – üres törzsnél `null`; érvényes UTF-8-nál `[unparsed body, N bytes]`,
    bináris törzsnél a meglévő `[non-UTF-8 body, N bytes]` jelölő.
- A választörzs kezelése nem változik.

### Megjegyzések és README (K5, K6)

- `LogApiTraffic` és `config/api-logging.php`: „Str::is() patterns of the logged requests, matched by
  App\Http\RequestPath (paths with invalid UTF-8 included).”
- README:
  - „…minden más 404 (a hibás kódolású URL 400, lásd a hibatáblát).”;
  - a 400-as sor kiegészül a nem érvényes JSON-törzzsel;
  - a „Nem értelmezhető JSON” pont szerint a válasz 400, a naplóban a jelölő;
  - új pont: a rossz Content-Type-pal küldött JSON maszkolva, a nem értelmezett törzs csak mérettel kerül be;
  - a „Hogyan készült” szakasz a `docs/review-fixes-2/` dokumentumokra hivatkozik.

### Dokumentumok és commitok (K7, K8)

- A lánc TASK.md, REQUIREMENTS.md és PLAN.md fájlja a dokumentálás fázisban kerül a `docs/review-fixes-2/` alá
  (Read/Write, `cmp`-pel ellenőrizve), az ellenőrzés és a review előtt.
- A lezáráskor három logikai commit készül (lásd Lépések 6.).

## Rétegek és fájlok

| Réteg | Fájl | Változás |
|-------|------|----------|
| Middleware | `app/Http/Middleware/RejectMalformedJson.php` | Új: 400, ha a JSON típusú kérés törzse nem érvényes JSON |
| Bootstrap | `bootstrap/app.php` | Módosítás: `RejectMalformedJson` az `api` csoport elejére |
| Naplózás | `app/Logging/ApiTraffic/ApiTrafficEntryFactory.php` | Módosítás: a kéréstörzs új sorrendje (JSON a Content-Type-tól függetlenül, űrlap, egyéb) |
| Naplózás | `app/Logging/ApiTraffic/PayloadSanitizer.php` | Módosítás: `formBody()` és `opaqueBody()` |
| Middleware | `app/Http/Middleware/LogApiTraffic.php` | Módosítás: megjegyzés (`Str::is()` minták, `RequestPath`) |
| Konfiguráció | `config/api-logging.php` | Módosítás: megjegyzés (`Str::is()` minták, `RequestPath`) |
| Tesztek | `tests/Feature/Api/RejectMalformedJsonTest.php` | Új: 400 hibás JSON-ra (PATCH, POST, érvénytelen UTF-8), az érvényes és üres törzs átmegy |
| Tesztek | `tests/Feature/Api/ApiTrafficLoggingTest.php` | Módosítás: űrlapként küldött JSON maszkolva, `text/plain` törzs csak mérettel; a hibás JSON-törzs most 400 |
| Tesztek | `tests/Unit/Logging/PayloadSanitizerTest.php` | Módosítás: `formBody()`, `opaqueBody()` |
| Dokumentáció | `README.md` | Módosítás: K6 |
| Dokumentáció | `docs/review-fixes-2/TASK.md`, `REQUIREMENTS.md`, `PLAN.md` | Új: a lánc dokumentumainak változatlan másolata |

## Lépések

1. **Hibás JSON: 400.** `RejectMalformedJson` és regisztrációja; `RejectMalformedJsonTest` (E1, E2, E3). A
   meglévő, hibás JSON-törzsről szóló naplózási teszt 422 helyett 400-at vár.
2. **Naplózás.** `PayloadSanitizer::formBody()` és `opaqueBody()`, az `ApiTrafficEntryFactory::requestBody()` új
   sorrendje; tesztek: `PayloadSanitizerTest`, `ApiTrafficLoggingTest` (E4, E5, E6).
3. **Megjegyzések** a `LogApiTraffic`-ban és a `config/api-logging.php`-ben (K5).
4. **Ellenőrzés menet közben.** Célzott tesztek, majd `make check`.
5. **Dokumentálás** (dokumentálás fázis). README (K6), a `docs/review-fixes-2/` másolatok, `cmp` (E7).
6. **Lezárás** (lezárás fázis). Commitok logikai egységenként, ebben a sorrendben:
   1. 400-as válasz hibás JSON-törzsre (1. lépés)
   2. Nyers titok nem kerül a naplóba; egyszeri tisztítás; a megjegyzések (2–3. lépés)
   3. README és a lánc dokumentumai (5. lépés)

   Ellenőrzés: `git log feature/maradek-review-pontok-javitasa..HEAD` legalább három commitot mutat (E9).

## Tesztterv

- **Feature – `RejectMalformedJsonTest`:**
  - PATCH JSON típussal, nem érvényes JSON-törzzsel → 400 Problem Details a `detail`-lel, az eszköz változatlan
    (E1);
  - POST nem érvényes JSON-nal, illetve érvénytelen UTF-8 bájtot tartalmazó JSON-nal → 400, nincs új eszköz (E2);
  - üres törzsű, JSON típusú `GET` és `DELETE`, valamint érvényes JSON-os `POST` → 200, 204, 201 (E3).
- **Feature – `ApiTrafficLoggingTest`:**
  - űrlapként (`application/x-www-form-urlencoded`) küldött, jelszót tartalmazó JSON → a naplóban maszkolt JSON, a
    jelszó sehol (E4). A teszt az űrlapmezőket úgy adja át, ahogy a PHP ezt a törzset értelmezné (egyetlen kulcs).
  - `text/plain` törzs `password=hunter2`-vel → `[unparsed body, 16 bytes]`, a jelszó sehol (E5);
  - a meglévő hibás JSON-teszt: a válasz 400, a naplóban továbbra is `[unparsable JSON body, N bytes]`;
  - a meglévő bináris törzses teszt változatlanul `[non-UTF-8 body, N bytes]`-t vár.
- **Unit – `PayloadSanitizerTest`:**
  - `formBody()`: maszkolás, `?`-re cserélés, csonkolás jelölővel (E6);
  - `opaqueBody()`: üres → `null`, szöveg → `[unparsed body, N bytes]`, bináris → `[non-UTF-8 body, N bytes]`.
- **Regresszió:** a meglévő tesztkészlet zöld; az űrlapos naplózást a `RejectInvalidUtf8InputTest` naplós tesztje
  is lefedi (E8).

## Elfogadási kritériumok lefedése

| Kritérium | Megvalósítás | Teszt |
|-----------|--------------|-------|
| E1 | `RejectMalformedJson`, `bootstrap/app.php` | `RejectMalformedJsonTest::testMalformedJsonPatchIsRejected` |
| E2 | `RejectMalformedJson` (`json_validate`) | `RejectMalformedJsonTest::testMalformedJsonPostIsRejected` |
| E3 | `RejectMalformedJson` (üres törzs kihagyása) | `RejectMalformedJsonTest::testValidAndEmptyJsonBodiesAreAccepted` |
| E4 | `ApiTrafficEntryFactory::requestBody()` (JSON-ág `json_validate`-tel) | `ApiTrafficLoggingTest::testJsonSentAsFormIsLoggedMasked` |
| E5 | `PayloadSanitizer::opaqueBody()` | `ApiTrafficLoggingTest::testUnparsedTextBodyIsLoggedBySizeOnly`, `PayloadSanitizerTest` |
| E6 | `PayloadSanitizer::formBody()` | `PayloadSanitizerTest::testFormBodyIsMaskedScrubbedAndTruncated`, `RejectInvalidUtf8InputTest::testRejectedFormRequestIsLoggedWithScrubbedBody` |
| E7 | `README.md`, `docs/review-fixes-2/*` | `cmp` a dokumentálás fázisban és a commit előtt |
| E8 | minden fenti | `make check` (devchain minőségkapu) |
| E9 | lezárási committerv (Lépések 6.) | `git log feature/maradek-review-pontok-javitasa..HEAD --oneline` |

## Minőségkapuk

A `devchain check` ezeket futtatja, és csak a jelenlegi kódra kapott zöld eredmény számít:
- `make check`

A `make check` a Docker-konténerben futtatja a Pint (PSR-12), a PHPCS (PSR12), a Larastan (level max) és a PHPUnit
(MySQL tesztadatbázis) ellenőrzést; bármelyik hiba vagy figyelmeztetés piros kaput jelent.

## Dokumentáció

- `README.md`: a K6 pontjai (az azonosítókról szóló mondat, a 400-as sor, a naplózási pontok, a „Hogyan készült”
  hivatkozásai).
- `docs/review-fixes-2/`: a lánc három dokumentumának változatlan másolata.
- OpenAPI: nem változik; a hibák leírása általános, a 400-at a dokumentáció leírása is „rosszul formált kérés”
  esetként kezeli.
- CHANGELOG: nincs (a projekt nem vezet ilyet).

## Kockázatok és alternatívák

- **Viselkedésváltozás:** a hibás JSON-törzsű POST eddig 422-t kapott (mezőhiány), mostantól 400-at. Ez a
  helyesebb válasz (rosszul formált kérés), és összhangban van az érvénytelen UTF-8 bemenet kezelésével.
- **A nem értelmezett törzs tartalma elvész a naplóból:** csak a mérete marad meg. Elvetett alternatíva: heurisztikus
  kulcs=érték maszkolás a nyers szövegben. Ez nem lehet teljes (bármilyen formátum érkezhet), így titok maradhatna
  a naplóban.
- **JSON-felismerés a tartalom alapján:** egy érvényes JSON-ként is értelmezhető szövegtörzs (pl. `123`) a JSON-ágon
  megy át; maszkolnivaló nincs benne, így ez ártalmatlan.
- **Elvetett alternatíva a külön middleware helyett:** a `RejectInvalidUtf8Input` bővítése a JSON-ellenőrzéssel. Két
  különböző szabály egy osztályban sértené az SRP-t.
