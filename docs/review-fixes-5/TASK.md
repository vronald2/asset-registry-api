# Feladat: Lapozási linkek titokszivárgásának javítása

- Azonosító: `20261108-2200-lapozasi-linkek-titokszivargasanak-javit`
- Indítva: 2026-11-08T22:00:43+01:00
- Ág: `feature/lapozasi-linkek-titokszivargasanak-javit` (alap: `feature/a-repo-cime-a-readme-ben` @ `fa0250205a`)
- Minőségkapuk:
- `make check`

## Az eredeti kérés (szó szerint)

„Igen, javítsd” – válasz arra a kérdésre, hogy javítsam-e a lapozási linkek szivárgását. A `main` (e1aa7d6) élő füsttesztje talált egy hibát: a listavégpontokon (`GET /api/v1/devices`, `GET /api/v1/contracts`) a query stringben küldött titok nyersen a naplóba kerül. Reprodukció: `GET /api/v1/devices?api_token=hunter2&per_page=1` után az `api_request_logs.query` oszlopban `"api_token": "***"` áll, de a `response_body` lapozási linkjeiben (`links.first` stb.) ott a nyers érték. Oka: az `app/Services/EloquentCrudService.php` `withQueryString()` hívása minden query-paramétert a linkekbe másol, a maszkolás pedig a mezőnév alapján dolgozik, és itt a mező neve `first`. Javaslat: a linkek csak a lapozási paramétert vigyék tovább, regressziós teszttel. A lánc dokumentumai (TASK, REQUIREMENTS, PLAN) a docs/review-fixes-5/ mappába kerüljenek, a README „Hogyan készült” szakasza említse a hatodik láncot. A commitok logikus lépésenként készüljenek.
