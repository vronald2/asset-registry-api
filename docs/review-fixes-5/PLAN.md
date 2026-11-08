# Terv – Lapozási linkek titokszivárgásának javítása

## Cél

A `GET /api/v1/devices` és a `GET /api/v1/contracts` lapozási linkjei csak a `page` és a `per_page` paramétert viszik
tovább, így a query stringben küldött titok nem kerül a válaszba, és a napló `response_body` oszlopába sem.

## Megközelítés

A hiba a forrásánál szűnik meg: az `EloquentCrudService::paginate()` a `withQueryString()` helyett csak a lapméretet
fűzi a linkekhez (`appends('per_page', $perPage)`). A `page` paramétert a paginátor maga adja hozzá. Mindkét listavégpont
ezt a service-metódust használja, így a javítás egy helyen történik. A lapméret a metódus paramétere, a service-nek
továbbra sem kell a kérést ismernie.

## Rétegek és fájlok

| Réteg | Fájl | Változás |
|-------|------|----------|
| Service | `app/Services/EloquentCrudService.php` | `withQueryString()` helyett `appends('per_page', $perPage)`, a docblock pontosítása |
| Teszt | `tests/Feature/Api/V1/PaginationLinksTest.php` | új: a linkek tartalma mindkét listavégponton, a lapméret, a következő oldal |
| Teszt | `tests/Feature/Api/ApiTrafficLoggingTest.php` | új teszt adatsorokkal: a query-ben küldött titok nem kerül a naplóba |
| Dokumentáció | `README.md` | „Lapozás” szakasz, tesztlista, a hatodik lánc a „Hogyan készült” szakaszban |
| Dokumentáció | `docs/review-fixes-5/TASK.md` | új, a lánc dokumentumának bájtra azonos másolata |
| Dokumentáció | `docs/review-fixes-5/REQUIREMENTS.md` | új, a lánc dokumentumának bájtra azonos másolata |
| Dokumentáció | `docs/review-fixes-5/PLAN.md` | új, a jóváhagyott terv bájtra azonos másolata |

## Lépések

1. Megírom a regressziós teszteket, és lefuttatom őket: a javítás előtt bukniuk kell.
2. A service-ben kicserélem a `withQueryString()` hívást, pontosítom a docblockot, és a tesztek zöldek.
3. Élő próba a futó stacken: `curl` a két listavégpontra query-ben küldött próbatitokkal, utána a válasz és az új
   naplósorok ellenőrzése.
4. Dokumentáció: a README „Lapozás” szakasza és a tesztlista. A köztes README-t félreteszem a külön commithoz.
5. A „Hogyan készült” bekezdés és a három dokumentum másolása a `docs/review-fixes-5/` mappába, `cmp`-vel ellenőrizve.
6. A `devchain check` lefuttatja a teljes minőségkaput.
7. Commitok a lezárás fázisban:
   - `fix: keep only the page size in the pagination links` (a service és a tesztek);
   - `docs: describe which parameters the pagination links carry` (a README „Lapozás” szakasza és a tesztlista);
   - `docs: add the workflow documents of the pagination link fix` (a `docs/review-fixes-5/` mappa és a „Hogyan készült”
     bekezdés).

## Tesztterv

Új `PaginationLinksTest`, mindkét listavégpontra adatsorral, ahol a viselkedés közös:

- `testLinksCarryOnlyThePaginationParameters`: `?api_token=S3CRET&page=2&per_page=2` után a `links` és a `meta.links`
  minden nem üres URL-jének query-kulcsai pontosan `page` és `per_page`, és egyikben sincs benne az `S3CRET`.
- `testNextLinkKeepsThePageSize`: `?page=2&per_page=5` után a `links.next` query-je `page=3` és `per_page=5`.
- `testNextLinkLeadsToTheNextPage`: a kapott `links.next` URL-re küldött GET a következő oldalt adja, a kért lapmérettel.
- `testLinksCarryTheDefaultPageSize`: `per_page` nélkül a linkekben `per_page=15` áll.

`ApiTrafficLoggingTest::testSecretInTheQueryStringIsNotLoggedThroughPaginationLinks`, két adatsorral (eszközök,
szerződések): a naplósor egyik oszlopa sem tartalmazza a titkot, a `query` oszlopban az `api_token` értéke `***`.

A meglévő `DeviceApiTest` lapozási tesztje változatlanul zöld marad. A dokumentáció parancssorból ellenőrizhető.

## Elfogadási kritériumok lefedése

| Kritérium | Megvalósítás | Teszt |
|-----------|--------------|-------|
| E1 | `EloquentCrudService::paginate()` | `PaginationLinksTest::testLinksCarryOnlyThePaginationParameters`, eszközök |
| E2 | `EloquentCrudService::paginate()` | `PaginationLinksTest::testLinksCarryOnlyThePaginationParameters`, szerződések |
| E3 | `EloquentCrudService::paginate()` | `ApiTrafficLoggingTest::testSecretInTheQueryStringIsNotLoggedThroughPaginationLinks` |
| E4 | `EloquentCrudService::paginate()` | `PaginationLinksTest::testNextLinkKeepsThePageSize` |
| E5 | `EloquentCrudService::paginate()` | `PaginationLinksTest::testNextLinkLeadsToTheNextPage` |
| E6 | `EloquentCrudService::paginate()` | `PaginationLinksTest::testLinksCarryTheDefaultPageSize` |
| E7 | 3. lépés, a futó stack | `curl` és SQL a naplósorokra, az eredmény a `NOTES.md`-ben |
| E8 | `README.md`, 4. lépés | `grep` az új szövegre és a régi mondat hiányára |
| E9 | `docs/review-fixes-5/`, 5. lépés | `cmp` a három fájlra |
| E10 | `README.md`, 5. lépés | `grep` a három hivatkozásra és a „hatodik” szóra |
| E11 | teljes munkafa, 6. lépés | `devchain check` (`make check`) |

## Minőségkapuk

A `devchain check` ezeket futtatja, és csak a jelenlegi kódra kapott zöld eredmény számít:
- `make check`

## Dokumentáció

A `README.md` „Lapozás” szakasza a linkek új viselkedését írja le, a tesztlista megemlíti a lapozási linkeket, a „Hogyan
készült” szakasz a hatodik láncot. A service docblockja is frissül. Az OpenAPI-leírás a linkek query-paramétereiről nem
állít semmit, ezért nem változik.

## Kockázatok és alternatívák

- Viselkedésváltozás: a `per_page` akkor is megjelenik a linkekben, ha a kliens nem küldte, és az ismeretlen
  query-paraméterek eltűnnek belőlük. Az API-nak nincs más query-paramétere, így ez funkciót nem érint.
- A már tárolt naplósorok nem változnak.
- Elvetve: a titok kiszűrése a naplózásnál a választörzs URL-jeiből, mert bonyolultabb, és a titok a kliensnek továbbra is
  visszamenne. A `per_page` átadása a kérésből a két controllerben, mert kétszer ugyanaz a kód lenne.
