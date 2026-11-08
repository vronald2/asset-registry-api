# Terv – A repó címe a README-ben

## Cél

A README „Gyors indítás” szakaszának klónozó parancsa a repó valódi HTTPS-címét tartalmazza, így módosítás nélkül
lefuttatható. A lánc dokumentumai bekerülnek a `docs/review-fixes-4/` mappába, a README „Hogyan készült” szakasza pedig
említi az ötödik láncot.

## Megközelítés

Csak dokumentáció változik. A helyőrző helyére a HTTPS-cím kerül, mert azzal hitelesítés nélkül is klónozható a repó. A
parancs többi része marad, mert a kérés csak a helyőrzőre vonatkozik. A dokumentumok másolása a korábbi láncok mintáját
követi, és a minőségkapuk előtt történik, mert a kapuk a teljes munkafát ujjlenyomatolják.

## Rétegek és fájlok

| Réteg | Fájl | Változás |
|-------|------|----------|
| Dokumentáció | `README.md` | a klónozó parancsban a valódi cím; az ötödik lánc említése a „Hogyan készült” szakaszban |
| Dokumentáció | `docs/review-fixes-4/TASK.md` | új, a lánc dokumentumának bájtra azonos másolata |
| Dokumentáció | `docs/review-fixes-4/REQUIREMENTS.md` | új, a lánc dokumentumának bájtra azonos másolata |
| Dokumentáció | `docs/review-fixes-4/PLAN.md` | új, a jóváhagyott terv bájtra azonos másolata |

## Lépések

1. A `README.md` klónozó parancsában a helyőrzőt a `https://github.com/vronald2/asset-registry-api.git` címre cserélem.
2. A „Hogyan készült” szakasz végére rövid bekezdés kerül az ötödik láncról, a három dokumentum hivatkozásával.
3. A három dokumentumot a jóváhagyás után átmásolom a `docs/review-fixes-4/` mappába, és `cmp`-vel ellenőrzöm.
4. A változott fájlok listáját ellenőrzöm, majd a `devchain check` lefuttatja a teljes minőségkaput.
5. Commitok a lezárás fázisban:
   - `docs: use the real repository URL in the quick start` (a klónozó parancs);
   - `docs: add the workflow documents of the repository URL change` (a `docs/review-fixes-4/` mappa és a „Hogyan készült”
     bekezdés).

## Tesztterv

Az alkalmazás kódja nem változik, ezért új automata teszt nem készül; a meglévő tesztek a minőségkapuban futnak. A
dokumentáció parancssorból ellenőrizhető:

- E1: a `grep -F '<repo-url>' README.md` nem ad találatot.
- E2: a `grep -c 'git clone' README.md` eredménye 1, és a sor a kritériumban megadott parancs.
- E3: a `GIT_TERMINAL_PROMPT=0 git ls-remote https://github.com/vronald2/asset-registry-api.git refs/heads/main` kiírja a
  `refs/heads/main` hivatkozást.
- E4: a `cmp` mindhárom másolatra hibátlanul lefut.
- E5: a README-ben `grep`-pel megtalálható a három hivatkozás és az „ötödik” szó.
- E6: a `git status --short` csak a `README.md` fájlt és a `docs/review-fixes-4/` mappát listázza.
- E7: a `devchain check` zöld.

## Elfogadási kritériumok lefedése

| Kritérium | Megvalósítás | Teszt |
|-----------|--------------|-------|
| E1 | `README.md`, 1. lépés | `grep -F '<repo-url>' README.md`, nincs találat |
| E2 | `README.md`, 1. lépés | `grep -c 'git clone' README.md` és a sor összevetése |
| E3 | `README.md`, 1. lépés | `git ls-remote` a beírt címre, hitelesítés nélkül |
| E4 | `docs/review-fixes-4/`, 3. lépés | `cmp` a három fájlra |
| E5 | `README.md`, 2. lépés | `grep` a három hivatkozásra és az „ötödik” szóra |
| E6 | 1–3. lépés | `git status --short` |
| E7 | teljes munkafa, 4. lépés | `devchain check` (`make check`) |

## Minőségkapuk

A `devchain check` ezeket futtatja, és csak a jelenlegi kódra kapott zöld eredmény számít:
- `make check`

## Dokumentáció

A `README.md` a változás tárgya. Az OpenAPI-leírás és a kód nem érintett.

## Kockázatok és alternatívák

- A lánc másolt `TASK.md` fájlja szó szerint idézi a helyőrzőt, ezért az E1 csak a `README.md` fájlt vizsgálja.
- Ha a repót később átnevezik, a cím elavul; a GitHub ilyenkor átirányít.
- Elvetve: SSH-cím, mert kulcs kellene hozzá. A könyvtárnév elhagyása a parancsból, mert a kérés nem érinti.
