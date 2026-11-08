# Feladat: Maradék naplózási review-pontok javítása

- Azonosító: `20261108-1232-maradek-naplozasi-review-pontok-javitasa`
- Indítva: 2026-11-08T12:32:11+01:00
- Ág: `feature/maradek-naplozasi-review-pontok-javitasa` (alap: `feature/hibas-json-es-naplomaszkolas-javitasa` @ `964d7c5a49`)
- Minőségkapuk:
- `make check`

## Az eredeti kérés (szó szerint)

„Igen, javítsd” – válasz arra a kérdésre, hogy javítsam-e publikálás előtt a „Hibás JSON és naplómaszkolás javítása” lezárt lánc (feature/hibas-json-es-naplomaszkolas-javitasa) 2. review körében maradt pontokat:

1. [MINOR, R1] Ritka bemeneteknél a titok a naplóba kerülhet, mert az űrlapágat csak az nyitja meg, hogy minden &-párban van = jel (ApiTrafficEntryFactory::isFormEncoded), és a formBody csak az értéket maszkolja, a mezőnevet nem. Két példa: `curl -d 'Authorization: Basic dXNlcjpwYXNzd29yZA=='` (a PHP-kulcs tartalmazza a base64 hitelesítő adatot), illetve BOM-mal kezdődő JSON, amelyben van = jel (a startsLikeJson nem ugorja át a UTF-8 BOM-ot). A README 474–475. sora ennél többet ígér („a rossz Content-Type-pal küldött törzsből sem kerülhet maszkolatlan titok a naplóba”). Javaslat: az isFormEncoded minden pár nevét (urldecode után) vesse össze egy szigorú mezőnév-mintával (pl. /^[\w.\-]+(\[[\w.\-]*\])*$/), különben opaqueBody; a startsLikeJson ugorja át a vezető BOM-ot; a két törzs kerüljön regressziós adatsorként a bodiesSentAsForm()-ba.
2. [MINOR, R2] Egyetlen feature teszt sem viszi végig a naplózáson a valódi application/x-www-form-urlencoded kérést nem üres név=érték nyers törzzsel (a $this->post() nem ad nyers tartalmat). Javaslat: új teszt az ApiTrafficLoggingTest-be http_build_query-s nyers törzzsel (assertCreated, a password értéke ***, a hunter2 sehol nincs), és kézi füstteszt POST-tal és -X PATCH-csel.
3. [MINOR, R4] Ismert korlát: a `curl -X PATCH -d '{"name":"X"}'` űrlapként érkezik, a request_parse_body() az egész törzset egyetlen kulccsá értelmezi, a middleware nem JSON típus miatt átengedi, a sometimes szabályok átmennek, a válasz 200, és semmi nem változik. Javaslat: 415 Unsupported Media Type, ha egy nem JSON típusú POST/PUT/PATCH törzse {/[-vel kezdődik; erről a felhasználónak kell döntenie.
4. [NIT, R5] A RejectMalformedJson kétszer elemzi a törzset (json_validate, majd json_decode), a startsLikeJson ltrim-je pedig egyetlen karakter miatt lemásolja az egész törzset.
5. [NIT, R6] A PayloadSanitizer::opaqueBody csak a ''-t tekinti üresnek, a body() a csak whitespace-ből állót is, így egy text/plain "\n" törzs [unparsed body, 1 bytes] lesz, a JSON típusú viszont null. Javaslat: itt is trim($content) === '', vagy a docblock rögzítse a különbséget.

A lánc dokumentumai (TASK, REQUIREMENTS, PLAN) a docs/review-fixes-3/ mappába kerüljenek, a README „Hogyan készült” szakasza említse a negyedik láncot. A commitok logikus lépésenként készüljenek.
