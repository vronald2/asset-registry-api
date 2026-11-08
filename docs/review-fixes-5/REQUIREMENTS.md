# Követelmények – Lapozási linkek titokszivárgásának javítása

## Összefoglaló

A listavégpontok lapozási linkjei ma minden query-paramétert visszaadnak, ezért a query stringben küldött titok a válasz
`links` részén át nyersen bekerül a napló `response_body` oszlopába, bár a `query` oszlopban maszkolva van. A linkek
ezentúl csak a lapozási paramétereket viszik tovább, így a titok sem a válaszba, sem a naplóba nem kerül be.

## Követelmények

- K1: A `GET /api/v1/devices` és a `GET /api/v1/contracts` válaszának lapozási linkjeiben (`links.first`, `links.last`,
  `links.prev`, `links.next`, `meta.links[].url`) csak a `page` és a `per_page` query-paraméter szerepelhet.
- K2: A linkek a kérés tényleges lapméretét viszik tovább a `per_page` paraméterben, a linkekkel a lapozás változatlanul
  működik.
- K3: A query stringben küldött titok listavégponton sem kerül a napló `response_body` oszlopába.
- K4: A README „Lapozás” szakasza és a kód megjegyzése az új viselkedést írja le.
- K5: A lánc TASK, REQUIREMENTS és PLAN dokumentuma a `docs/review-fixes-5/` mappába kerül, bájtra azonos másolatként. A
  README „Hogyan készült” szakasza említi a hatodik láncot, és hivatkozik a három dokumentumára.
- K6: A commitok logikus lépésenként készülnek.

## Elfogadási kritériumok

- E1: Adott több oldalnyi eszköz, amikor a `GET /api/v1/devices?api_token=S3CRET&page=2&per_page=2` kérés érkezik, akkor a
  `links` és a `meta.links` egyik URL-je sem tartalmazza az `api_token` nevet és az `S3CRET` értéket.
- E2: Adott több oldalnyi szerződés, amikor ugyanilyen kérés érkezik a `GET /api/v1/contracts` végpontra, akkor ugyanez
  teljesül.
- E3: Adott az E1 és az E2 kérése, amikor elkészül a naplósor, akkor a `response_body` nem tartalmazza az `S3CRET` értéket,
  a `query` oszlopban pedig az `api_token` értéke `***`.
- E4: Adott több oldalnyi eszköz, amikor a `?page=2&per_page=5` kérés érkezik, akkor a `links.next` tartalmazza a
  `per_page=5` és a `page=3` paramétert.
- E5: Adott több oldalnyi eszköz, amikor a kapott `links.next` URL-re GET kérés érkezik, akkor a következő oldal jön, a kért
  lapmérettel.
- E6: Adott több oldalnyi eszköz, amikor a kérés nem küld `per_page`-et, akkor a linkekben `per_page=15` áll.
- E7: Adott a futó stack, amikor `curl`-lel titkot küldünk query-ben a két listavégpontra, akkor a válaszban és az új
  naplósorok egyik oszlopában sincs benne a titok.
- E8: Adott a `README.md` „Lapozás” szakasza, amikor a linkek viselkedését keressük benne, akkor a szöveg azt írja, hogy a
  linkek csak a `page` és a `per_page` paramétert viszik tovább, és a korábbi, a query-paraméterek megtartását ígérő mondat
  nincs benne.
- E9: Adott a `docs/review-fixes-5/` mappa, amikor a `TASK.md`, `REQUIREMENTS.md` és `PLAN.md` fájlt a lánc dokumentumaival
  összevetjük (`cmp`), akkor mindhárom bájtra azonos.
- E10: Adott a README „Hogyan készült” szakasza, amikor a `docs/review-fixes-5/` hivatkozásait keressük benne, akkor mindhárom
  dokumentumra van hivatkozás, és a szöveg említi a hatodik láncot.
- E11: Adott a lánc ágának kódja, amikor a `make check` lefut, akkor zöld.

## Feltételezések

- A linkek akkor is tartalmazzák a `per_page` értékét, ha a kliens nem küldte. Ez a tényleges lapméret, és így a service-nek
  nem kell a kérésből olvasnia.
- Az API-nak a `page` és a `per_page` az egyetlen query-paramétere, ezért a többi elhagyásával a linkek nem veszítenek
  funkciót.
- Más visszhangzási pont nincs: a Problem Details `instance` tagja és a napló `path` oszlopa csak az útvonalat használja.
- A dokumentumok helye a korábbi javító láncok mintáját követi (`docs/review-fixes*/`).
- A lánc az előző lánc ágáról indult, mert lánc nélkül a hook nem enged ágat váltani. Ennek a tartalma azonos a `main`-nel.

## Hatókörön kívül

- Általános URL-szűrés a választörzsekben: a visszhang a forrásánál szűnik meg.
- A nem maszkolt fejlécek, például a `Referer` tartalma.
- A már tárolt naplósorok utólagos módosítása.
- A többpáros űrlaptörzs hiányzó tesztsora.
- A push és a PR: csak a felhasználó kifejezett kérésére, a lánc lezárása után.

## Nyitott kérdések

Nincs.
