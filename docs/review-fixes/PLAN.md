# Terv – Maradék review-pontok javítása

<!--
A tervezés lépés kimenete; a felhasználó ezt hagyja jóvá (/devchain:jovahagyas), a megvalósítás ezt követi,
a review ehhez mér. A jóváhagyás után a fájl zárolt; módosításhoz `devchain replan` és új jóváhagyás kell.
Minden «kitöltendő» jelölőt cserélj le. A projekt meglévő konvencióit (CLAUDE.md, README, a meglévő kód
rétegei) kövesd.
-->

## Cél

A `feature/maradek-review-pontok-javitasa` ágon az API a szándékosan hibás kéréseket is helyesen kezeli:
- az érvénytelen UTF-8 útvonal, a túl hosszú felülírt metódus és a nem dekódolható JSON-törzs is naplózódik, titkok
  nélkül, Problem Details válasszal;
- az érvénytelen UTF-8 query- vagy űrlapbemenet 400-as Problem Details választ kap az eddigi 500 helyett;
- az OpenAPI-példák seedelt adatbázison is használhatók;
- a lánc dokumentumai a `docs/review-fixes/` alatt vannak, és a README leírja az új viselkedést.

Minden javításhoz regressziós teszt tartozik, a `make check` zöld, és a munka logikai commitokban kerül az ágra
(K1–K8, E1–E10).

## Megközelítés

### Bájtbiztos útvonal-illesztés (K1)

- Új `App\Http\RequestPath::matches(Request $request, string ...$patterns): bool`: a `Request::is()` mintájára a
  `Str::is()`-szel illeszt, de a dekódolt útvonalat előbb `mb_scrub`-bal érvényes UTF-8-ra hozza. A
  `Request::is()` érvénytelen bájtsorra azért hamis, mert a `Str::is()` `/u` módosítós regexe ilyenkor nem illeszt.
- A `LogApiTraffic::terminate()` és a `ProblemDetailsRenderer::render()` is ezt használja, így egy helyen van a
  logika (DRY).
- A `ProblemDetails::toResponse()` a `JsonResponse`-t `JSON_INVALID_UTF8_SUBSTITUTE` opcióval hozza létre. Ha a
  `detail` vagy az `instance` nyers, érvénytelen bájtot tartalmaz, a kódolás nem dob kivételt, a bájt helyére
  U+FFFD kerül.

### Naplózás (K2, K3, K5)

- `ApiTrafficEntryFactory`:
  - a `method` mező `MAX_METHOD_LENGTH = 10` karakterre csonkolódik, ahogy az útvonal, a route és a user agent is;
  - a kéréstörzsnél átadja a `PayloadSanitizer::body()`-nak, hogy JSON-t vár-e (`$request->isJson()`);
  - az űrlapmezőket kódolás előtt átengedi a `PayloadSanitizer::data()`-n (maszkolás és `?`-re cserélés), így a
    `json_encode` nem bukik el, és a törzs nem lesz `null`.
- `PayloadSanitizer::body(string $content, bool $expectJson = false)`: a meglévő UTF-8-ellenőrzés után, ha JSON
  várható, de a `json_validate()` hamis, a `[unparsable JSON body, N bytes]` jelölőt adja vissza. A választörzs
  hívása nem változik (JSON-t nem vár, mindig az API állítja elő).

### Érvénytelen UTF-8 bemenet: 400 (K4)

- Új `App\Http\Middleware\RejectInvalidUtf8Input`: ha a query- vagy az űrlapparaméterek (kulcsok és értékek,
  rekurzívan; az `mb_check_encoding` tömbre ezt maga elvégzi) nem érvényes UTF-8-ak,
  `BadRequestHttpException('The request contains invalid UTF-8 data.')`-t dob. A meglévő `ProblemDetailsFactory`
  ezt 400-as Problem Details-re képezi le, új hibakezelő kód nem kell.
- Regisztráció a `bootstrap/app.php`-ban: `$middleware->api(prepend: [...])`. Így a létező API-útvonalakon a
  modellkötés és a validáció előtt fut; az ismeretlen útvonal 404 marad. A JSON-törzs érvénytelen UTF-8-ja a
  JSON-dekódolásnál már elbukik, arra a meglévő viselkedés marad.

### OpenAPI-példák (K6)

- `ContractRequest`: a `contract_number` példája `SZ-2026-010`; `AttachDeviceRequest`: a `serial_number` példája
  `SN-EXAMPLE-0001`. Egyik sem szerepel a `DemoSeeder` adatai között.

### Dokumentáció és commitok (K7, K8)

- A README hibatáblája kap egy 400-as sort, a naplózás szakasza a nem dekódolható JSON jelölőjét, a „Hogyan
  készült” szakasza a `docs/review-fixes/` dokumentumokat.
- A lánc TASK.md, REQUIREMENTS.md és PLAN.md fájlja a dokumentálás fázisban kerül a `docs/review-fixes/` alá,
  Read/Write eszközzel (a hook tiltja a `.devchain/`-t érintő shellparancsot), `cmp`-pel ellenőrizve. Ez még az
  ellenőrzés és a review előtt történik.
- A lezáráskor öt logikai commit készül (lásd Lépések 7.).

## Rétegek és fájlok

| Réteg | Fájl | Változás |
|-------|------|----------|
| HTTP-segéd | `app/Http/RequestPath.php` | Új: bájtbiztos útvonal-illesztés (`mb_scrub` + `Str::is`) |
| Middleware | `app/Http/Middleware/LogApiTraffic.php` | Módosítás: a `RequestPath::matches()`-t használja |
| Middleware | `app/Http/Middleware/RejectInvalidUtf8Input.php` | Új: 400, ha a query- vagy űrlapbemenet nem érvényes UTF-8 |
| Hibakezelés | `app/Http/ProblemDetails/ProblemDetailsRenderer.php` | Módosítás: a `RequestPath::matches()`-t használja |
| Hibakezelés | `app/Http/ProblemDetails/ProblemDetails.php` | Módosítás: `JSON_INVALID_UTF8_SUBSTITUTE` a válaszban |
| Naplózás | `app/Logging/ApiTraffic/ApiTrafficEntryFactory.php` | Módosítás: metódus-csonkolás, JSON-elvárás átadása, űrlapmezők tisztítása kódolás előtt |
| Naplózás | `app/Logging/ApiTraffic/PayloadSanitizer.php` | Módosítás: `body()` a nem dekódolható JSON-t jelölőre cseréli |
| Bootstrap | `bootstrap/app.php` | Módosítás: `RejectInvalidUtf8Input` az `api` csoport elejére |
| Validáció | `app/Http/Requests/Api/V1/ContractRequest.php` | Módosítás: `contract_number` példája `SZ-2026-010` |
| Validáció | `app/Http/Requests/Api/V1/AttachDeviceRequest.php` | Módosítás: `serial_number` példája `SN-EXAMPLE-0001` |
| Tesztek | `tests/Unit/Http/RequestPathTest.php` | Új |
| Tesztek | `tests/Unit/Http/ProblemDetailsResponseTest.php` | Új |
| Tesztek | `tests/Unit/Logging/PayloadSanitizerTest.php` | Módosítás: nem dekódolható JSON |
| Tesztek | `tests/Feature/Api/ProblemDetailsTest.php` | Módosítás: érvénytelen UTF-8 útvonal |
| Tesztek | `tests/Feature/Api/ApiTrafficLoggingTest.php` | Módosítás: hosszú felülírt metódus, nem dekódolható JSON-törzs |
| Tesztek | `tests/Feature/Api/RejectInvalidUtf8InputTest.php` | Új: 400 űrlap- és query-bemenetre, naplózott törzs |
| Tesztek | `tests/Feature/ApiDocumentationTest.php` | Módosítás: a példák nem ütköznek a demo adatokkal |
| Dokumentáció | `README.md` | Módosítás: 400-as sor, a JSON-jelölő, hivatkozás a `docs/review-fixes/`-re |
| Dokumentáció | `docs/review-fixes/TASK.md`, `REQUIREMENTS.md`, `PLAN.md` | Új: a lánc dokumentumainak változatlan másolata |

## Lépések

1. **Bájtbiztos illesztés.** `RequestPath`, használata a `LogApiTraffic`-ban és a `ProblemDetailsRenderer`-ben,
   `JSON_INVALID_UTF8_SUBSTITUTE` a `ProblemDetails`-ben. Tesztek: `RequestPathTest`,
   `ProblemDetailsResponseTest`, `ProblemDetailsTest::testInvalidUtf8PathReturnsProblemDetailsAndIsLogged` (E1, E2).
2. **Naplózás.** Metódus-csonkolás, nem dekódolható JSON jelölője, űrlapmezők tisztítása kódolás előtt. Tesztek:
   `PayloadSanitizerTest`, `ApiTrafficLoggingTest` (E3, E4).
3. **400 érvénytelen UTF-8 bemenetre.** `RejectInvalidUtf8Input` és regisztrációja. Teszt:
   `RejectInvalidUtf8InputTest` (E5, E6).
4. **OpenAPI-példák.** A két `@example` cseréje. Teszt: `ApiDocumentationTest` (E7).
5. **Ellenőrzés menet közben.** Célzott tesztek, majd a megvalósítás végén `make check`.
6. **Dokumentálás** (dokumentálás fázis). README-frissítés, a `docs/review-fixes/` másolatok és a `cmp`
   ellenőrzés (E8).
7. **Lezárás** (lezárás fázis). Commitok logikai egységenként, ebben a sorrendben:
   1. Az érvénytelen UTF-8 útvonalú kérések naplózása és Problem Details válasza (1. lépés)
   2. Minden API-kérés naplózható marad: metódus, nem dekódolható JSON, űrlaptörzs (2. lépés)
   3. 400-as válasz érvénytelen UTF-8 bemenetre (3. lépés)
   4. A demo adatokkal nem ütköző OpenAPI-példák (4. lépés)
   5. README és a lánc dokumentumai (6. lépés)

   Ellenőrzés: `git log feature/eszkoznyilvantarto-rest-api..HEAD` legalább három commitot mutat (E10).

## Tesztterv

- **Unit:**
  - `RequestPathTest`: érvényes útvonal illeszkedik; az érvénytelen UTF-8 bájtot tartalmazó `api/v1/%FF` is
    illeszkedik az `api/*` mintára; a nem illeszkedő útvonal hamis; több minta közül bármelyik elég.
  - `ProblemDetailsResponseTest`: érvénytelen bájtot tartalmazó `detail` és `instance` mellett a válasz érvényes
    JSON, az érvénytelen bájt helyén U+FFFD.
  - `PayloadSanitizerTest`: JSON-elvárásnál a nem dekódolható tartalom jelölője a bájtszámmal; érvényes JSON
    JSON-elvárással a meglévő maszkolással megy át; JSON-elvárás nélkül a nem-JSON szöveg változatlan marad.
- **Feature:**
  - `ProblemDetailsTest`: `GET /api/v1/%FF` `Accept` fejléc nélkül → 404 Problem Details, és pontosan egy
    naplósor `/api/v1/%FF` útvonallal (E1).
  - `ApiTrafficLoggingTest`:
    - `POST` `X-HTTP-Method-Override: VERSIONCONTROL` fejléccel → 405 Problem Details, egy naplósor `VERSIONCON`
      metódussal (E3);
    - hibás JSON-törzs jelszóval → a naplóban a jelölő, a jelszó sehol (E4).
  - `RejectInvalidUtf8InputTest`:
    - űrlapos `POST /api/v1/devices` érvénytelen UTF-8 mezővel → 400 Problem Details, és nem jön létre eszköz;
    - `PATCH /api/v1/devices/{device}?name=%FF` → 400, és az eszköz változatlan;
    - érvénytelen UTF-8 kulcs is 400;
    - az érvényes űrlap- és query-bemenet nem 400 (E5);
    - a 400-as kérés naplósorában a törzs nem `null`, és `?` áll az érvénytelen bájt helyén (E6).
  - `ApiDocumentationTest`: seedelt adatbázison a dokumentált `contract_number` és `serial_number` példa nem
    foglalt (E7).
- **Regresszió:** a meglévő tesztkészlet változatlanul zöld (E9).

## Elfogadási kritériumok lefedése

| Kritérium | Megvalósítás | Teszt |
|-----------|--------------|-------|
| E1 | `RequestPath`, `LogApiTraffic`, `ProblemDetailsRenderer` | `ProblemDetailsTest::testInvalidUtf8PathReturnsProblemDetailsAndIsLogged`, `RequestPathTest` |
| E2 | `ProblemDetails::toResponse()` (`JSON_INVALID_UTF8_SUBSTITUTE`) | `ProblemDetailsResponseTest::testInvalidUtf8IsSubstitutedInTheResponse` |
| E3 | `ApiTrafficEntryFactory` (`MAX_METHOD_LENGTH`) | `ApiTrafficLoggingTest::testLongOverriddenMethodIsLogged` |
| E4 | `PayloadSanitizer::body()` (`json_validate`), `ApiTrafficEntryFactory` (`isJson()`) | `ApiTrafficLoggingTest::testUnparsableJsonBodyIsReplacedWithMarker`, `PayloadSanitizerTest` |
| E5 | `RejectInvalidUtf8Input`, `bootstrap/app.php`, `ProblemDetailsFactory` (400) | `RejectInvalidUtf8InputTest` |
| E6 | `ApiTrafficEntryFactory` (űrlapmezők `data()`-n át) | `RejectInvalidUtf8InputTest::testRejectedFormRequestIsLoggedWithScrubbedBody` |
| E7 | `ContractRequest`, `AttachDeviceRequest` (`@example`) | `ApiDocumentationTest::testDocumentedExamplesAreFreeInTheDemoData` |
| E8 | `docs/review-fixes/*`, `README.md` | `cmp` a dokumentálás fázisban és a commit előtt |
| E9 | minden fenti | `make check` (devchain minőségkapu) |
| E10 | lezárási committerv (Lépések 7.) | `git log feature/eszkoznyilvantarto-rest-api..HEAD --oneline` |

## Minőségkapuk

A `devchain check` ezeket futtatja, és csak a jelenlegi kódra kapott zöld eredmény számít:
- `make check`

A `make check` a Docker-konténerben futtatja a Pint (PSR-12), a PHPCS (PSR12), a Larastan (level max) és a PHPUnit
(MySQL tesztadatbázis) ellenőrzést; bármelyik hiba vagy figyelmeztetés piros kaput jelent.

## Dokumentáció

- `README.md`: a hibatábla 400-as sora (érvénytelen UTF-8 bemenet), a naplózás szakaszban a
  `[unparsable JSON body, N bytes]` jelölő, a „Hogyan készült” szakaszban a `docs/review-fixes/` dokumentumok.
- `docs/review-fixes/`: a lánc TASK.md, REQUIREMENTS.md és PLAN.md fájljának változatlan másolata (felhasználói
  döntés).
- OpenAPI: a Scramble a `ProblemDetailsFactory`-n keresztül a 400-at nem következteti ki automatikusan; a
  dokumentáció leírásában a hibák szakasza általános, nem kell módosítani.
- CHANGELOG: nincs (a projekt nem vezet ilyet; a változások a commitokban követhetők).

## Kockázatok és alternatívák

- **`mb_scrub` a mintaillesztésben:** az érvénytelen bájt `?` lesz, ezért egy `api/?` alakú útvonal is illeszkedhet
  az `api/*` mintára. Ez a szándék: minden `/api/` kezdetű kérés API-kérésként kezelendő.
- **A 400-as middleware csak az `api` csoportban fut:** az ismeretlen útvonalakra nem, de azok úgyis 404-et kapnak,
  és nem írnak adatbázisba.
- **A metódus csonkolása információt veszít** (`VERSIONCONTROL` → `VERSIONCON`). A valódi kérésmetódus és a
  felülíró fejléc a naplósor fejlécei között megmarad. Elvetett alternatíva: az oszlop szélesítése migrációval, de a
  felülírt metódus hossza nem korlátos, csonkolni akkor is kellene.
- **Elvetett alternatíva a 400 helyett:** 422-es, mezőnkénti hiba; a felhasználó a 400-at választotta (rosszul
  formált kérés, egy helyen kezelve).
- **A hibás JSON-törzs 422 marad** (hatókörön kívül); a naplóban már nem maradhat benne titok.
