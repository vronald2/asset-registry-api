# Terv – Maradék naplózási review-pontok javítása

<!--
A tervezés lépés kimenete; a felhasználó ezt hagyja jóvá (/devchain:jovahagyas), a megvalósítás ezt követi,
a review ehhez mér. A jóváhagyás után a fájl zárolt; módosításhoz `devchain replan` és új jóváhagyás kell.
Minden «kitöltendő» jelölőt cserélj le. A projekt meglévő konvencióit (CLAUDE.md, README, a meglévő kód
rétegei) kövesd.
-->

## Cél

A `feature/maradek-naplozasi-review-pontok-javitasa` ágon:
- az űrlapként küldött törzs „mezőnevében” lévő titok nem kerül a naplóba, a BOM-mal kezdődő JSON sem;
- a JSON-törzs rossz Content-Type-pal 415-ös Problem Details választ kap, így a `curl -X PATCH -d '{…}'` sem tűnhet
  sikeresnek;
- a valódi urlencoded út tesztelve van, a törzs egyszer elemződik, és a csak whitespace-ből álló törzs mindenhol üres;
- a README csak annyit ígér, amennyit a naplózás tud, a lánc dokumentumai a `docs/review-fixes-3/` alatt vannak, és a
  követett fájlokban nincs helyi útvonal.

Minden javításhoz teszt tartozik, a `make check` zöld, a munka logikai commitokban kerül az ágra (K1–K10, E1–E16).

## Megközelítés

### Szabályos mezőnevek a naplóban (K1)

- Az `ApiTrafficEntryFactory::isFormEncoded()` minden nem üres pár nevét (az első `=` előtti részt, `urldecode()`
  után) összeveti egy mintával: `/^[A-Za-z0-9_.-]+(?:\[[A-Za-z0-9_.-]*\])*$/D`. Ha egy pár nem illeszkedik, vagy nincs
  benne `=`, az `opaqueBody()` fut, azaz csak a méret kerül a naplóba.
- A minta a **nyers** nevet vizsgálja, nem a PHP által értelmezett kulcsot: a PHP a kulcsban a szóközt `_`-ra cseréli,
  így a `password hunter2=x` értelmezett kulcsa (`password_hunter2`) szabályosnak látszana.
- A karakterosztály explicit ASCII (`\w` helyett), hogy ne függjön a locale-tól.

### JSON-ra utaló kezdet és 415 (K2, K3)

- Új `App\Http\RequestBody::looksLikeJson(string $content): bool`, a meglévő `RequestPath` mintájára (tiszta, állapot
  nélküli statikus segéd az `App\Http` névtérben). Átugorja a vezető UTF-8 BOM-ot, majd a JSON-whitespace-t
  (`strspn()`, így nem másolja le a törzset: K5), és `{`-t vagy `[`-t vár. Két hívója lesz: a naplózás és az új
  middleware (DRY); az `ApiTrafficEntryFactory::startsLikeJson()` megszűnik.
- Új `App\Http\Middleware\RejectMislabeledJson` (`final readonly`): ha a kérés nem JSON típusú, a metódusa POST, PUT
  vagy PATCH, és a törzs JSON-ra utal, `UnsupportedMediaTypeHttpException`-t dob a K3 szerinti üzenettel. A meglévő
  `ProblemDetailsFactory` ebből 415-ös Problem Details-t képez.
- Regisztráció a `bootstrap/app.php`-ban: `api(prepend: [RejectInvalidUtf8Input, RejectMalformedJson,
  RejectMislabeledJson])`, így a modellkötés és a validáció előtt fut. Külön middleware, mert más szabály és más
  státusz: a `RejectMalformedJson` a JSON típusú kérést nézi, ez a nem JSON típusút (SRP).

### Egyszeri elemzés és üres törzs (K5, K6)

- `RejectMalformedJson`: `json_validate()` helyett egy `json_decode()`, utána `json_last_error() !== JSON_ERROR_NONE`.
- `PayloadSanitizer::body()`: ugyanígy, egy `json_decode($content, true)`.
- `PayloadSanitizer::opaqueBody()`: `trim($content) === ''` esetén `null`, ahogy a `body()`.

### Dokumentáció (K7, K8, K9)

- README: lásd „Dokumentáció”. A `config/scramble.php` „Errors” része egy mondattal bővül.
- `docs/REQUIREMENTS.md`: a helyi útvonal helyett `Projects/asset-registry-api`.
- A lánc három dokumentuma a dokumentálás fázisban kerül a `docs/review-fixes-3/` alá (Read/Write, `cmp`-pel
  ellenőrizve), az ellenőrzés és a review előtt.

## Rétegek és fájlok

| Réteg | Fájl | Változás |
|-------|------|----------|
| HTTP-segéd | `app/Http/RequestBody.php` | Új: `looksLikeJson()` (BOM, JSON-whitespace, `{` vagy `[`) |
| Middleware | `app/Http/Middleware/RejectMislabeledJson.php` | Új: 415 a nem JSON típusú, JSON-ra utaló POST/PUT/PATCH törzsre |
| Middleware | `app/Http/Middleware/RejectMalformedJson.php` | Módosítás: egyszeri elemzés |
| Bootstrap | `bootstrap/app.php` | Módosítás: `RejectMislabeledJson` az `api` csoport elejére |
| Naplózás | `app/Logging/ApiTraffic/ApiTrafficEntryFactory.php` | Módosítás: mezőnév-minta; `RequestBody::looksLikeJson()` a `startsLikeJson()` helyett |
| Naplózás | `app/Logging/ApiTraffic/PayloadSanitizer.php` | Módosítás: egyszeri elemzés; `opaqueBody()` üres-kezelése |
| Konfiguráció | `config/scramble.php` | Módosítás: 415 az „Errors” részben |
| Tesztek | `tests/Unit/Http/RequestBodyTest.php` | Új: a JSON-ra utaló kezdet felismerése |
| Tesztek | `tests/Feature/Api/RejectMislabeledJsonTest.php` | Új: 415 és a nem érintett kérések |
| Tesztek | `tests/Feature/Api/ApiTrafficLoggingTest.php` | Módosítás: szabálytalan mezőnevek, BOM, valódi űrlap; a JSON-ra utaló törzs most 415 |
| Tesztek | `tests/Unit/Logging/PayloadSanitizerTest.php` | Módosítás: whitespace az `opaqueBody()`-ban |
| Dokumentáció | `README.md` | Módosítás: K7 |
| Dokumentáció | `docs/REQUIREMENTS.md` | Módosítás: a helyi útvonal kikerül (K9) |
| Dokumentáció | `docs/review-fixes-3/TASK.md`, `REQUIREMENTS.md`, `PLAN.md` | Új: a lánc dokumentumainak változatlan másolata |

## Lépések

1. **Szabályos mezőnevek.** A mezőnév-minta az `isFormEncoded()`-ban; tesztek: szabálytalan mezőnevek, valódi űrlap
   (E1, E3). A lépés végén a két módosított fájl köztes állapota félrekerül a részleges commithoz.
2. **JSON-ra utaló kezdet és 415.** `RequestBody`, `RejectMislabeledJson` és regisztrációja, a naplózás a `RequestBody`-t
   használja, `config/scramble.php`; tesztek: `RequestBodyTest`, `RejectMislabeledJsonTest`, a JSON-ra utaló törzsek
   a naplózási tesztekben 415-öt várnak, és bekerül a BOM-os eset (E2, E4–E9).
3. **Egyszeri elemzés.** `RejectMalformedJson`, `PayloadSanitizer::body()`; a meglévő tesztek zöldek (E10).
4. **Üres törzs.** `PayloadSanitizer::opaqueBody()`, teszt (E11).
5. **Ellenőrzés menet közben.** Célzott tesztek, majd `make lint analyse`.
6. **Élő füstteszt** a futó stacken, `curl`-lel, a naplósorok ellenőrzésével:
   - JSON űrlapként POST-tal, `-X PATCH`-csel és `-H 'Content-Type:'` fejléccel (415, maszkolt JSON a naplóban);
   - fejlécsor űrlapként POST-tal és `-X PATCH`-csel (`[unparsed body, N bytes]`);
   - valódi űrlap POST-tal és `-X PATCH`-csel (maszkolt mezők).
7. **Dokumentálás** (dokumentálás fázis). README, `docs/REQUIREMENTS.md`, a `docs/review-fixes-3/` másolatok, `cmp`,
   `git grep` (E12–E14).
8. **Lezárás** (lezárás fázis). Commitok logikai egységenként, ebben a sorrendben:
   1. csak szabályos mezőnevű űrlap kerül a naplóba, a valódi űrlap tesztje (1. lépés);
   2. 415 a rossz típusú JSON-törzsre, BOM-felismerés (2. lépés);
   3. a JSON-törzs egyszeri elemzése (3. lépés);
   4. a csak whitespace-ből álló törzs üres (4. lépés);
   5. a helyi útvonal eltávolítása a dokumentációból (K9);
   6. README és a lánc dokumentumai (K7, K8).

   Ellenőrzés: `git log feature/hibas-json-es-naplomaszkolas-javitasa..HEAD` legalább hat commitot mutat (E16).

## Tesztterv

- **Unit – `RequestBodyTest`:**
  - igaz: `{`, `[`, whitespace-szel kezdődő `{`, BOM-mal kezdődő `{`, BOM és whitespace után `[`;
  - hamis: üres, csak whitespace, csak BOM, `"x"`, `123`, `a=b` (E9).
- **Feature – `RejectMislabeledJsonTest`:**
  - POST űrlap típussal, érvényes JSON-nal → 415 Problem Details a K3 szerinti `detail`-lel, 0 eszköz (E4);
  - PATCH és PUT meglévő eszközre → 415, az eszköz változatlan (E5);
  - PATCH nem létező eszközre → 415, nem 404 (E6);
  - POST `text/plain` típussal, Content-Type nélkül, vezető whitespace-szel, BOM-mal, `[`-kezdettel → 415 (E7);
  - GET és DELETE űrlap típussal, JSON-ra utaló törzzsel → 200, illetve 204 (E8).
- **Feature – `ApiTrafficLoggingTest`:**
  - a `bodiesSentAsForm()` új adatsorai: `Authorization: Basic dXNlcjpwYXNzd29yZA==` és `password hunter2=x` →
    `[unparsed body, N bytes]`, a titok sehol (E1);
  - új teszt a hibás JSON-ra űrlapként (záró vessző, illetve BOM és `=` jel a törzsben) → 415, `[unparsable JSON body,
    N bytes]`, a jelszó sehol (E2);
  - új teszt a valódi űrlapra: `http_build_query()` nyers törzs, felső szintű és beágyazott jelszó → 201, a
    `request_body` a mezőket adja, mindkét jelszó `***`, a jelszó sehol (E3);
  - a `testJsonSentAsFormIsLoggedMasked` 415-öt vár, a napló változatlanul a maszkolt JSON (E4);
  - a `testUnparsedTextBodyIsLoggedBySizeOnly` változatlanul 422-t vár (E8).
- **Unit – `PayloadSanitizerTest`:** az `opaqueBody("\n")` és az `opaqueBody(" \t")` → `null` (E11).
- **Regresszió:** a `RejectMalformedJsonTest` és a `PayloadSanitizerTest` meglévő esetei zöldek (E10), a teljes
  tesztkészlet zöld (E15).

## Elfogadási kritériumok lefedése

| Kritérium | Megvalósítás | Teszt |
|-----------|--------------|-------|
| E1 | `ApiTrafficEntryFactory::isFormEncoded()` (mezőnév-minta) | `ApiTrafficLoggingTest::testBodySentAsFormDoesNotLeakSecrets` |
| E2 | `RequestBody::looksLikeJson()` (BOM), `RejectMislabeledJson` | `ApiTrafficLoggingTest::testBrokenJsonSentAsFormDoesNotLeakSecrets` |
| E3 | `isFormEncoded()` (URL-dekódolt név, beágyazott mező) | `ApiTrafficLoggingTest::testRealFormRequestIsLoggedMasked` |
| E4 | `RejectMislabeledJson`, `bootstrap/app.php` | `RejectMislabeledJsonTest::testJsonSentAsFormIsRejected`, `ApiTrafficLoggingTest::testJsonSentAsFormIsLoggedMasked` |
| E5 | `RejectMislabeledJson` | `RejectMislabeledJsonTest::testJsonSentAsFormDoesNotChangeTheDevice` |
| E6 | `bootstrap/app.php` (az `api` csoport eleje) | `RejectMislabeledJsonTest::testJsonSentAsFormIsRejectedBeforeModelBinding` |
| E7 | `RequestBody::looksLikeJson()`, `isJson()` | `RejectMislabeledJsonTest::testMislabeledJsonIsRejected` |
| E8 | `RejectMislabeledJson` (metódusszűrés), `looksLikeJson()` | `RejectMislabeledJsonTest::testReadAndDeleteRequestsAreNotRejected`, `ApiTrafficLoggingTest::testUnparsedTextBodyIsLoggedBySizeOnly` |
| E9 | `RequestBody::looksLikeJson()` | `RequestBodyTest` |
| E10 | `RejectMalformedJson`, `PayloadSanitizer::body()` (egy `json_decode()`) | `RejectMalformedJsonTest`, `PayloadSanitizerTest` (meglévő esetek) |
| E11 | `PayloadSanitizer::opaqueBody()` | `PayloadSanitizerTest::testOpaqueBodyKeepsOnlyTheSize` |
| E12 | `README.md`, `config/scramble.php` | `grep` a dokumentálás fázisban és a review |
| E13 | `docs/review-fixes-3/*` | `cmp` a dokumentálás fázisban és a commit előtt |
| E14 | `docs/REQUIREMENTS.md` | `git grep` a dokumentálás fázisban |
| E15 | minden fenti | `make check` (devchain minőségkapu) |
| E16 | lezárási committerv (Lépések 8.) | `git log feature/hibas-json-es-naplomaszkolas-javitasa..HEAD --oneline` |

## Minőségkapuk

A `devchain check` ezeket futtatja, és csak a jelenlegi kódra kapott zöld eredmény számít:
- `make check`

A `make check` a Docker-konténerben futtatja a Pint (PSR-12), a PHPCS (PSR12), a Larastan (level max) és a PHPUnit
(MySQL tesztadatbázis) ellenőrzést; bármelyik hiba vagy figyelmeztetés piros kaput jelent.

## Dokumentáció

- `README.md`:
  - a hibatábla új sora: 415 (JSON-ra utaló törzs nem JSON Content-Type-pal, a `curl -d` alapértelmezése);
  - a „Más típusú törzsek” pont: csak a szabályos mezőnevű `név=érték` törzs mezői kerülnek be, a BOM-mal kezdődő törzs
    is JSON-ra utal, és az ismert korlát (a csupasz, mezőnévnek is megfelelő token);
  - az architektúra-fa (`RejectMislabeledJson`, `RequestBody.php`) és az „Egy kérés útja” sor;
  - a tesztlista;
  - a „Hogyan készült” szakasz: a harmadik lánc mondatából kikerül a túlzó ígéret, és bekerül a negyedik lánc a
    `docs/review-fixes-3/` hivatkozásokkal.
- `config/scramble.php`: az „Errors” rész említi a 415-öt.
- `docs/REQUIREMENTS.md`: a helyi útvonal kikerül.
- `docs/review-fixes-3/`: a lánc három dokumentumának változatlan másolata.
- CHANGELOG: nincs (a projekt nem vezet ilyet).

## Kockázatok és alternatívák

- **Viselkedésváltozás:** a JSON-ra utaló, rossz típusú törzs eddig POST-nál 422-t, PATCH-nél 200-at kapott, mostantól
  415-öt. A valódi űrlapok nem változnak; ezt az új teszt és a füstteszt igazolja.
- **A nem ASCII nevű mező tartalma nem kerül a naplóba,** csak a törzs mérete. Az API-nak nincs ilyen mezője, és a
  biztonságos irányba téved.
- **Maradék korlát:** a csupasz, mezőnévnek is megfelelő szöveg `=` jellel a végén szintaktikailag űrlapmező, ezért a
  neve a naplóba kerülhet. A README ezt ismert korlátként írja le, és nem ígér mást.
- **A `\u0000`-val kezdődő kulcsú JSON-objektum** az egyszeri elemzés miatt 400-at kap, eddig üresnek számított. Az API
  ilyen kulcsot nem használ.
- **Elvetett alternatívák:**
  - az értelmezett kulcsok vizsgálata a nyers nevek helyett: a PHP cseréi miatt gyengébb, lásd „Megközelítés”;
  - az űrlaptörzsek teljes kihagyása a naplóból: megszüntetné a maradék korlátot, de visszavonná a korábbi láncok
    követelményét, hogy az űrlapmezők maszkolva bekerülnek;
  - a rossz típusú JSON elfogadása JSON-ként: elrejtené a kliens hibáját, és a törzs típusát találgatással döntené el;
  - az új szabály beépítése a `RejectMalformedJson`-ba: más feltétel és más státusz egy osztályban sértené az SRP-t.
