# Feladat: Maradék review-pontok javítása

- Azonosító: `20261107-2248-maradek-review-pontok-javitasa`
- Indítva: 2026-11-07T22:48:32+01:00
- Ág: `feature/maradek-review-pontok-javitasa` (alap: `feature/eszkoznyilvantarto-rest-api` @ `ce48f5f742`)
- Minőségkapuk:
- `make check`

## Az eredeti kérés (szó szerint)

„Igen, javítsd” – válasz arra a kérdésre, hogy javítsam-e publikálás előtt az eszköznyilvántartó REST API (feature/eszkoznyilvantarto-rest-api) lezárt láncának 2. review körében maradt 6 apró megállapítást:

1. [MINOR] Érvénytelen UTF-8 bájtsort tartalmazó /api/* útvonalnál (pl. /api/v1/%FF) nem keletkezik naplósor, és a 404 nem Problem Details: a Request::is() /u módosítós regexe érvénytelen UTF-8-ra nem illeszkedik (LogApiTraffic, ProblemDetailsRenderer). Ilyenkor a ProblemDetails JSON-kódolása is elbukhat (JSON_INVALID_UTF8_SUBSTITUTE hiányzik).
2. [MINOR] A naplótábla method oszlopa varchar(10), de a gyár nem csonkolja; egy hosszabb, felülírt metódusnál (X-HTTP-Method-Override: VERSIONCONTROL) az INSERT elbukik, nem keletkezik naplósor.
3. [MINOR] Nem dekódolható JSON-törzs (pl. hiányzó záró kapcsos zárójel) maszkolás nélkül kerül a naplóba, így egy benne lévő jelszó olvasható marad.
4. [MINOR] Űrlap- vagy query-bemenetben az érvénytelen UTF-8 átmegy a validáción, és a MySQL írás 500-as hibát ad 422/400 helyett.
5. [NIT] Űrlapos kérésnél érvénytelen UTF-8 mezőértékkel a request_body csendben null lesz (json_encode hiba), jelölő nélkül.
6. [NIT] Az OpenAPI-példák (5CG2341XYZ, SZ-2026-001) megegyeznek a DemoSeeder adataival, így seedelt adatbázison a dokumentáció „Try it” funkciója a példával 422-t kap.
