# Követelmények – Maradék review-pontok javítása

<!--
A tisztázás lépés kimenete; a terv és a review ehhez mér.
- Minden «kitöltendő» jelölőt cserélj le tartalomra.
- Az elfogadási kritériumok számozottak (E1, E2, …) és tesztelhetők legyenek.
- A felhasználónak feltett, még megválaszolatlan kérdéseket a „Nyitott kérdések” alatt [NYITOTT] jelöléssel
  vezesd; amíg van ilyen, a `devchain next` nem enged tovább.
-->

## Összefoglaló

Az eszköznyilvántartó REST API lezárt láncának 2. review köre hat apró megállapítást hagyott nyitva (4 MINOR,
2 NIT). Mindegyik szándékosan hibás kérések szélsőesete: érvénytelen UTF-8 az útvonalban vagy a bemenetben,
túl hosszú felülírt HTTP-metódus, nem dekódolható JSON-törzs, illetve a demo adatokkal ütköző OpenAPI-példák. A
cél, hogy publikálás előtt ezek is helyesen működjenek: minden ilyen kérés egységes Problem Details választ kapjon,
500-as hiba nélkül, és naplósor keletkezzen róla, titkok nélkül. A dokumentáció „Try it” példái seedelt adatbázison
is működjenek.

## Követelmények

- **K1 – Bájtbiztos útvonal-illesztés:** a forgalomnaplózás és a Problem Details renderelés az érvénytelen UTF-8
  bájtsort tartalmazó `/api/*` útvonalakat is felismeri. Az ilyen kérés naplózódik, a 404-es válasz Problem Details.
  A Problem Details JSON-kódolása érvénytelen UTF-8 tartalommal sem bukik el (az érvénytelen bájt helyére U+FFFD
  kerül).
- **K2 – Metódus a naplóban:** a naplósor `method` mezője az oszlop szélességére (10 karakter) csonkolódik, így a
  hosszabb, felülírt metódusú kérések is naplózódnak.
- **K3 – Nem dekódolható JSON-törzs:** ha a kérés JSON típusú, de a törzse nem érvényes JSON, a naplóba a nyers
  tartalom helyett `[unparsable JSON body, N bytes]` jelölő kerül.
- **K4 – Érvénytelen UTF-8 bemenet:** ha egy `/api/*` végpont query- vagy űrlapparamétere (kulcsa vagy értéke,
  beágyazva is) érvénytelen UTF-8 bájtsort tartalmaz, a válasz 400 Bad Request Problem Details, még a validáció
  előtt. A kérést egy közös middleware szűri, minden végpontra egyformán; 500-as hiba nem keletkezik.
- **K5 – Űrlaptörzs a naplóban:** egy űrlapos kérés érvénytelen UTF-8 mezőértékei a naplóban `?`-re cserélődnek, a
  `request_body` nem lesz csendben `null`.
- **K6 – OpenAPI-példák:** a dokumentáció példái nem ütköznek a demo seeder adataival: a szerződésszám-példa
  `SZ-2026-010`, a gyári szám példa `SN-EXAMPLE-0001`.
- **K7 – Dokumentumok:** a lánc TASK.md, REQUIREMENTS.md és PLAN.md fájlja változatlan másolatként a
  `docs/review-fixes/` mappába kerül (felhasználói döntés), a README „Hogyan készült” szakasza hivatkozik rájuk.
  A README leírja az új viselkedést (400-as válasz, a nem dekódolható JSON jelölője).
- **K8 – Minőség és commitok:** minden javításhoz regressziós teszt tartozik; a `make check` (Pint, PHPCS, Larastan
  max, PHPUnit) zöld; a változások logikai egységenként, több commitban kerülnek a lánc ágára.

## Elfogadási kritériumok

- E1: Adott egy `GET /api/v1/%FF` kérés `Accept` fejléc nélkül, amikor lefut, akkor a válasz 404
  `application/problem+json`, és pontosan egy naplósor keletkezik `/api/v1/%FF` útvonallal és 404 státusszal.
- E2: Adott egy Problem Details, amelynek `detail` mezője érvénytelen UTF-8 bájtot tartalmaz, amikor válasszá
  alakítjuk, akkor kivétel nélkül érvényes JSON jön létre, az érvénytelen bájt helyén U+FFFD karakterrel.
- E3: Adott egy `POST /api/v1/devices` kérés `X-HTTP-Method-Override: VERSIONCONTROL` fejléccel, amikor lefut,
  akkor a válasz 405 Problem Details, és pontosan egy naplósor keletkezik `VERSIONCON` metódussal.
- E4: Adott egy `Content-Type: application/json` kérés, amelynek törzse nem érvényes JSON, és jelszót tartalmaz,
  amikor lefut, akkor a naplósor `request_body` mezője `[unparsable JSON body, N bytes]` (N a törzs bájtszáma), és a
  jelszó sehol nincs a naplósorban.
- E5: Adott egy `/api/*` kérés, amelynek űrlapmezője (`POST /api/v1/devices`) vagy query-paramétere
  (`PATCH /api/v1/devices/{device}`) érvénytelen UTF-8 bájtsort tartalmaz, amikor lefut, akkor a válasz 400
  Problem Details (angol `detail`), az adatbázis nem változik, és nem keletkezik 500-as hiba.
- E6: Adott egy E5 szerinti űrlapos kérés, amikor naplózzuk, akkor a `request_body` nem `null`, a mezők benne
  vannak, és az érvénytelen bájt helyén `?` áll.
- E7: Adott a demo seederrel feltöltött adatbázis, amikor lekérjük az OpenAPI-dokumentumot, akkor a
  `contract_number` példája (`SZ-2026-010`) és a `serial_number` példája (`SN-EXAMPLE-0001`) nem foglalt az
  adatbázisban.
- E8: Adott a lánc vége, amikor a `docs/review-fixes/` mappát vizsgáljuk, akkor a TASK.md, a REQUIREMENTS.md és a
  PLAN.md bájtra azonos a lánc dokumentumaival (`cmp`), és a README „Hogyan készült” szakasza mindhármat
  belinkeli.
- E9: Adott a kódbázis, amikor `make check` fut, akkor a Pint, a PHPCS, a Larastan max és a PHPUnit (a meglévő és
  az új tesztekkel) hibátlanul lefut.
- E10: Adott a lánc ága a lezárás után, amikor a commitokat vizsgáljuk (`git log <alap>..HEAD`), akkor legalább
  három, logikai egységenként elkülönülő, beszédes commit van.

## Feltételezések

- A bájtbiztos illesztés a dekódolt útvonalat `mb_scrub`-bal megtisztítja, mielőtt a `Str::is()` mintákat illeszti.
  Egy közös segédosztály végzi, amelyet a naplózó middleware és a renderer is használ (DRY).
- A `method` oszlop szélessége (10) marad, nem kell migráció; a metódust a bejegyzés-építő csonkolja, ahogy az
  útvonalat és a user agentet is.
- A nem dekódolható JSON felismerése: a kérés `isJson()`, és a `json_validate()` hamis. Ez csak a kéréstörzsre
  vonatkozik; a választörzs mindig az API által előállított, érvényes JSON.
- A 400-at adó middleware az `api` middleware-csoportban fut, így csak a létező API-útvonalakat érinti; az ismeretlen
  útvonal 404 marad. A `detail` angol, pl. „The request contains invalid UTF-8 data.”.
- A JSON-törzs érvénytelen UTF-8-ja a JSON-dekódolásnál már elbukik; erre a meglévő viselkedés (a naplóban jelölő,
  a válasz 422) marad.
- A javítások a lezárt lánc ágára épülnek (`feature/maradek-review-pontok-javitasa`, alap:
  `feature/eszkoznyilvantarto-rest-api`).

## Hatókörön kívül

- A szintaktikailag hibás JSON-törzs külön, 400-as kezelése: ilyenkor továbbra is a validáció válaszol (422).
- A `method` oszlop szélesítése.
- A `docs/REQUIREMENTS.md`-ben szereplő helyi útvonal cseréje.
- A nyilvános GitHub-repó létrehozása, a push és a PR; ezek a felhasználó külön kérésére történnek.

## Nyitott kérdések

Nincs.
