# Követelmények – Maradék naplózási review-pontok javítása

<!--
A tisztázás lépés kimenete; a terv és a review ehhez mér.
- Minden «kitöltendő» jelölőt cserélj le tartalomra.
- Az elfogadási kritériumok számozottak (E1, E2, …) és tesztelhetők legyenek.
- A felhasználónak feltett, még megválaszolatlan kérdéseket a „Nyitott kérdések” alatt [NYITOTT] jelöléssel
  vezesd; amíg van ilyen, a `devchain next` nem enged tovább.
-->

## Összefoglaló

A „Hibás JSON és naplómaszkolás javítása” lánc 2. review köre PASS volt, de apró pontok maradtak.
- Az űrlapként küldött törzs „mezőnevében” ritkán még titok kerülhet a naplóba (élőben reprodukálva).
- A `curl -X PATCH -d '{"name":"X"}'` 200-at kap úgy, hogy semmi nem változik.
- A valódi urlencoded út nincs tesztelve, és két apró következetlenség is maradt.

A cél, hogy ezek publikálás előtt lezáruljanak, a README pedig csak annyit ígérjen, amennyit a naplózás tud. A review
pontjainak jelölése (R1–R6) a fenti lánc 2. köréből való.

## Követelmények

- **K1 – Szabályos mezőnevek a naplóban (R1):** az űrlapmezők csak akkor kerülnek a naplóba (maszkolva), ha a nyers
  törzs minden nem üres párja `név=érték` alakú, és a név URL-dekódolás után szabályos mezőnév: ASCII betűk, számok,
  `_`, `.` és `-`, utána tetszőleges számú `[kulcs]` vagy `[]`. Minden más törzsből csak a méret kerül be
  (`[unparsed body, N bytes]`). A nyers tartalom nélküli (multipart) törzs továbbra is űrlapként kerül be.
- **K2 – JSON-ra utaló kezdet (R1):** a törzs JSON-ra utal, ha a vezető UTF-8 BOM és a JSON-whitespace (szóköz, tab,
  LF, CR) után `{` vagy `[` következik. Az ilyen törzs a naplóban a JSON-ágra kerül; a BOM-mal kezdődő törzs nem
  érvényes JSON, ezért a `[unparsable JSON body, N bytes]` jelölő kerül a helyére.
- **K3 – 415 a rossz típusú JSON-törzsre (R4):** ha egy `/api/*` POST, PUT vagy PATCH kérés nem JSON típusú (vagy nincs
  Content-Type fejléce), és a törzse a K2 szerint JSON-ra utal, a válasz 415 Unsupported Media Type Problem Details,
  még a modellkötés és a validáció előtt, ezzel a `detail` értékkel: „The request body looks like JSON: send it with
  the Content-Type: application/json header.” A kérés naplózódik. A valódi űrlapok, a JSON típusú kérések, valamint
  a GET és a DELETE viselkedése nem változik.
- **K4 – A valódi űrlap tesztje (R2):** feature teszt viszi végig a naplózáson a valódi
  `application/x-www-form-urlencoded` kérést nem üres, `név=érték` nyers törzzsel, beágyazott mezővel is (URL-kódolt
  szögletes zárójelekkel, ahogy a `http_build_query()` küldi).
- **K5 – Egyszeri elemzés (R5):** a `RejectMalformedJson` és a `PayloadSanitizer::body()` a törzset egyszer elemzi, a
  JSON-ra utaló kezdet felismerése pedig nem másolja le a törzset.
- **K6 – Üres törzs (R6):** a `PayloadSanitizer::opaqueBody()` a csak whitespace-ből álló törzset is üresnek veszi,
  ahogy a `body()`; ilyenkor `null` kerül a naplóba.
- **K7 – Dokumentáció:**
  - a README hibatáblája tartalmazza a 415-öt;
  - a README naplózási szakasza leírja a K1–K2 viselkedést és a maradék korlátot (lásd „Hatókörön kívül”);
  - a README architektúra-leírása és tesztlistája említi az új middleware-t és teszteket;
  - a README „Hogyan készült” szakaszából kikerül a túlzó ígéret (a rossz Content-Type-pal küldött törzsből „sem
    kerülhet” maszkolatlan titok a naplóba), és a szakasz említi a negyedik láncot a `docs/review-fixes-3/`
    hivatkozásokkal;
  - a `config/scramble.php` „Errors” része említi a 415-öt.
- **K8 – A lánc dokumentumai:** a lánc TASK.md, REQUIREMENTS.md és PLAN.md fájlja változatlan másolatként a
  `docs/review-fixes-3/` mappába kerül.
- **K9 – Helyi útvonal:** a `docs/REQUIREMENTS.md` helyi útvonala helyett a `Projects/asset-registry-api` áll. Ez a
  felhasználó döntése; ez az egy sor szándékosan eltér az első lánc eredeti dokumentumától.
- **K10 – Minőség:** a `make check` zöld (Pint, PHPCS PSR-12, Larastan max, PHPUnit). A commitok logikus lépésenként
  készülnek, angol Conventional Commits üzenettel.

## Elfogadási kritériumok

- E1: Adott egy űrlapként küldött törzs, amelynek egy „mezőneve” nem szabályos (`Authorization: Basic
  dXNlcjpwYXNzd29yZA==`, illetve `password hunter2=x`), amikor naplózódik, akkor a `request_body` az
  `[unparsed body, N bytes]` jelölő, és a titok a naplósorban sehol nincs.
- E2: Adott egy űrlapként küldött, BOM-mal kezdődő JSON, amelyben van `=` jel és jelszó, amikor beérkezik, akkor a
  válasz 415, a `request_body` az `[unparsable JSON body, N bytes]` jelölő, és a jelszó a naplósorban sehol nincs.
- E3: Adott egy valódi urlencoded POST kérés `http_build_query()`-vel készült nyers törzzsel, jelszómezővel és
  beágyazott jelszómezővel, amikor beérkezik, akkor a válasz 201, a `request_body` a mezőket tartalmazza, mindkét
  jelszó helyén `***` áll, és a jelszó a naplósorban sehol nincs.
- E4: Adott egy POST kérés űrlap típussal és érvényes JSON-törzzsel, amikor beérkezik, akkor a válasz 415 Problem
  Details a K3 szerinti `detail` értékkel, nem jön létre eszköz, és a naplóba a maszkolt JSON kerül.
- E5: Adott egy meglévő eszköz, amikor PATCH vagy PUT kérés érkezik rá űrlap típussal és JSON-törzzsel, akkor a válasz
  415, és az eszköz nem változik.
- E6: Adott egy nem létező eszköz, amikor PATCH kérés érkezik rá űrlap típussal és JSON-törzzsel, akkor a válasz 415,
  nem 404.
- E7: Adott egy POST kérés JSON-ra utaló törzzsel, amikor a típusa `text/plain`, vagy nincs Content-Type fejléce, vagy
  a törzs whitespace-szel, BOM-mal vagy `[`-vel kezdődik, akkor a válasz 415.
- E8: Adott egy GET és egy DELETE kérés űrlap típussal és JSON-ra utaló törzzsel, amikor beérkezik, akkor a válasz 200,
  illetve 204; egy `text/plain` POST, amelynek törzse nem JSON-ra utal, továbbra is 422-t kap.
- E9: Adott a JSON-ra utaló kezdet felismerése, amikor a bemenet `{`, `[`, whitespace-szel vagy BOM-mal (és
  whitespace-szel) kezdődő `{`, akkor igaz; amikor üres, csak whitespace, csak BOM, `"x"`, `123` vagy `a=b`, akkor hamis.
- E10: Adott a `RejectMalformedJson`, amikor a törzs hibás JSON, nem objektum, üres, `null` vagy `[]`, akkor a válaszok
  megegyeznek a korábbiakkal (a `RejectMalformedJsonTest` meglévő esetei zöldek), és a törzs egyszer elemződik.
- E11: Adott a `PayloadSanitizer::opaqueBody()`, amikor a bemenet `"\n"` vagy `" \t"`, akkor `null`; amikor nem üres
  szöveg, akkor továbbra is a méretjelölő.
- E12: Adott a README és a `config/scramble.php`, amikor a K7 pontjait nézzük, akkor mindegyik teljesül, a „sem
  kerülhet” ígéret pedig nem szerepel.
- E13: Adott a lezárás előtti munkafa, amikor a `docs/review-fixes-3/` három fájlját a lánc dokumentumaival vetjük össze
  (`cmp`), akkor bájtra azonosak.
- E14: Adott a követett fájlok köre, amikor a helyi felhasználónévre és a kezdőkönyvtár útvonalára keresünk
  (`git grep`), akkor nincs találat.
- E15: Adott a lánc ága, amikor a `make check` lefut, akkor zöld.
- E16: Adott a lánc lezárása, amikor a commitokat nézzük, akkor a változások logikus lépésekre oszlanak, és minden
  üzenet angol Conventional Commits formátumú.

## Feltételezések

- A felhasználó döntései: az R4-re 415 a válasz, a `docs/REQUIREMENTS.md` helyi útvonala kikerül.
- Az előző lánc szűkítése (a nem objektum JSON-törzs 400-at kap) marad: a felhasználó a tájékoztatás után nem kérte a
  visszaállítását.
- Az API minden mezőneve ASCII, ezért a mezőnév-minta is az. A nem ASCII nevű mezőt tartalmazó űrlapból csak a méret
  kerül a naplóba.
- A multipart törzsnek nincs nyers tartalma, a mezőneveit a kliens szándékosan adja meg, ezért a kezelése nem változik.
- Valódi űrlap nem kezdődhet `{`-vel vagy `[`-vel: az API-nak nincs ilyen mezője.
- A PHP a `\u0000`-val kezdődő kulcsú JSON-objektumot nem tudja objektummá alakítani. Az egyszeri elemzés miatt az ilyen
  törzs 400-at kap (eddig üresnek számított).

## Hatókörön kívül

- A csupasz, mezőnévnek is megfelelő szöveg `=` jellel a végén (pl. egy `dXNlcjpwYXNz=` token). Ez szintaktikailag nem
  különböztethető meg egy űrlapmezőtől; a README ismert korlátként jelzi.
- A GET és a DELETE törzse: ezek a végpontok a törzset nem használják.
- A nem JSON típusú kérések általános tiltása: a valódi űrlapok továbbra is működnek.
- A push, a PR és a publikálás: csak a felhasználó kifejezett kérésére.

## Nyitott kérdések

Nincs.
