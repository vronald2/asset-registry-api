# Követelmények – Hibás JSON és naplómaszkolás javítása

<!--
A tisztázás lépés kimenete; a terv és a review ehhez mér.
- Minden «kitöltendő» jelölőt cserélj le tartalomra.
- Az elfogadási kritériumok számozottak (E1, E2, …) és tesztelhetők legyenek.
- A felhasználónak feltett, még megválaszolatlan kérdéseket a „Nyitott kérdések” alatt [NYITOTT] jelöléssel
  vezesd; amíg van ilyen, a `devchain next` nem enged tovább.
-->

## Összefoglaló

A javító lánc 2. review köre két, a korábbi változásoktól független MINOR pontot jelzett.
- Egy hibás JSON-törzsű PATCH kérés 200-at kap, és nem módosít semmit, így a kliens sikeresnek hiheti.
- A rossz Content-Type-pal küldött JSON- vagy szövegtörzsben lévő titok a forgalomnaplóba kerülhet.

A cél, hogy publikálás előtt a hibás JSON-törzs minden API-végponton 400-as Problem Details választ kapjon, a naplóba
pedig ne kerülhessen nyers, maszkolatlan titok. Ugyanennek a review-nak a három NIT-jét is rendbe tesszük
(megjegyzések, README-mondat, kétszeres tisztítás).

## Követelmények

- **K1 – Hibás JSON-törzs:** ha egy `/api/*` kérés JSON típusú (`isJson()`), a törzse nem üres, és nem érvényes
  JSON (ide tartozik az érvénytelen UTF-8-at tartalmazó törzs is), a válasz 400 Bad Request Problem Details
  („The request body is not valid JSON.”), még a modellkötés és a validáció előtt, minden metódusra.
- **K2 – Rossz Content-Type-pal küldött JSON a naplóban:** ha egy kérés törzse érvényes JSON, de a Content-Type nem
  JSON (pl. a `curl -d` alapértelmezése), a naplóba a törzs a JSON-maszkolás szerint kerül (`***` a titkok helyén),
  nem az űrlapként értelmezett, maszkolatlan kulcs.
- **K3 – Egyéb törzsek a naplóban:** a nem JSON és nem űrlapként értelmezett, nem üres kéréstörzsből (pl.
  `text/plain`) csak a méret kerül a naplóba, `[unparsed body, N bytes]` jelölővel. A titkai ugyanis nem
  ismerhetők fel. A bináris (nem UTF-8) törzs jelölője továbbra is `[non-UTF-8 body, N bytes]`.
- **K4 – Egyszeri tisztítás:** az űrlapmezők naplózáskor egyszer mennek át a maszkoláson és a tisztításon, utána
  kódolás és csonkolás következik (a `PayloadSanitizer` erre szolgáló metódusa).
- **K5 – Megjegyzések:** a `LogApiTraffic` és a `config/api-logging.php` megjegyzése szerint az útvonalminták
  `Str::is()` minták, amelyeket a `RequestPath` illeszt (az érvénytelen UTF-8 útvonalakat is).
- **K6 – README:**
  - az azonosítókról szóló mondat jelzi, hogy a hibás kódolású URL 400-at kap;
  - a hibatábla 400-as sora a hibás JSON-törzset is említi;
  - a naplózási szakasz leírja a K2–K3 viselkedést;
  - a „Hogyan készült” szakasz a `docs/review-fixes-2/` dokumentumokra hivatkozik.
- **K7 – Dokumentumok:** a lánc TASK.md, REQUIREMENTS.md és PLAN.md fájlja változatlan másolatként a
  `docs/review-fixes-2/` mappába kerül.
- **K8 – Minőség és commitok:** minden javításhoz regressziós teszt tartozik; a `make check` zöld; a változások
  logikai egységenként, több commitban kerülnek a lánc ágára.

## Elfogadási kritériumok

- E1: Adott egy létező eszköz, amikor `PATCH /api/v1/devices/{device}` kérés érkezik `Content-Type: application/json`
  fejléccel és nem érvényes JSON-törzzsel, akkor a válasz 400 Problem Details („The request body is not valid
  JSON.”), és az eszköz nem változik.
- E2: Adott egy `POST /api/v1/devices` kérés JSON típussal, amikor a törzse nem érvényes JSON, illetve érvénytelen
  UTF-8 bájtot tartalmaz, akkor a válasz mindkét esetben 400 Problem Details, és nem jön létre eszköz.
- E3: Adott egy JSON típusú kérés üres törzzsel (pl. `GET`, `DELETE`) vagy érvényes JSON-törzzsel, amikor lefut, akkor
  a middleware nem utasítja el: a válasz a végpont szokásos válasza (200, 201, 204).
- E4: Adott egy `POST /api/v1/devices` kérés `application/x-www-form-urlencoded` típussal és jelszót tartalmazó,
  érvényes JSON-törzzsel, amikor naplózzuk, akkor a naplósor `request_body` mezője a maszkolt JSON (`"password":
  "***"`), és a jelszó sehol nincs a naplósorban.
- E5: Adott egy `text/plain` típusú kérés `password=hunter2` törzzsel, amikor naplózzuk, akkor a `request_body`
  `[unparsed body, 16 bytes]`, és a jelszó sehol nincs a naplósorban.
- E6: Adott egy űrlapos kérés, amikor naplózzuk, akkor a törzs a maszkolt és tisztított mezők JSON-ja, csonkolva, ha
  kell. A `PayloadSanitizer` ehhez való metódusa ezt egy lépésben adja (unit teszt: maszkolás, `?`-re cserélés,
  csonkolás).
- E7: Adott a lánc vége, amikor a `docs/review-fixes-2/` mappát és a README-t vizsgáljuk, akkor a három dokumentum
  bájtra azonos a lánc dokumentumaival (`cmp`), és a README tartalmazza a K6 változásait.
- E8: Adott a kódbázis, amikor `make check` fut, akkor a Pint, a PHPCS, a Larastan max és a PHPUnit hibátlanul
  lefut.
- E9: Adott a lánc ága a lezárás után, amikor a commitokat vizsgáljuk (`git log <alap>..HEAD`), akkor legalább
  három, logikai egységenként elkülönülő, beszédes commit van.

## Feltételezések

- A hibás JSON-törzs is 400-at kap, összhangban a felhasználó előző döntésével (rosszul formált bemenet: 400). Ez
  felülírja az előző lánc hatókörön kívüli pontját, amely szerint ilyenkor a validáció válaszol (422).
- Az ellenőrzés egy új, egyfeladatos middleware (`RejectMalformedJson`) az `api` middleware-csoport elején, a
  `RejectInvalidUtf8Input` mellett; az ismeretlen útvonalak 404-e nem változik.
- A „rossz Content-Type-pal küldött JSON” felismerése: a nyers törzs érvényes JSON (`json_validate()`). Ilyenkor a
  naplózás a JSON-ágat használja, függetlenül attól, hogy a Laravel űrlapként is értelmezte.
- A nyers szövegtörzsekben nincs heurisztikus kulcs=érték maszkolás: az ilyen törzs tartalma nem kerül a naplóba.
- A dokumentumok helye a `docs/review-fixes-2/`, az előző javító lánc mintájára (felhasználói döntés volt a külön
  almappa).

## Hatókörön kívül

- A rossz Content-Type-pal küldött JSON elutasítása: ezt a Laravel űrlapként értelmezi, a validáció válaszol, ahogy
  eddig.
- Naplómegőrzés, a naplók lekérdezése API-n.
- A `docs/REQUIREMENTS.md`-ben szereplő helyi útvonal cseréje.
- A nyilvános GitHub-repó létrehozása, a push és a PR; ezek a felhasználó külön kérésére történnek.

## Nyitott kérdések

Nincs.
