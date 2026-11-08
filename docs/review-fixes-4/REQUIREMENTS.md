# Követelmények – A repó címe a README-ben

## Összefoglaló

A README „Gyors indítás” szakaszában a klónozó parancs még helyőrzőt tartalmaz, pedig a repó már nyilvános a GitHubon.
A helyőrző helyére a valódi cím kerül, hogy a repót megnyitó értékelő a parancsot módosítás nélkül lefuttathassa. A lánc
dokumentumai a korábbiakhoz hasonlóan bekerülnek a repóba.

## Követelmények

- K1: A `README.md` „Gyors indítás” szakaszának klónozó parancsában a helyőrző helyett a repó valódi HTTPS-címe áll:
  `https://github.com/vronald2/asset-registry-api.git`. A parancs többi része nem változik.
- K2: A lánc TASK, REQUIREMENTS és PLAN dokumentuma a `docs/review-fixes-4/` mappába kerül, bájtra azonos másolatként.
- K3: A README „Hogyan készült” szakasza említi az ötödik láncot, és hivatkozik a három dokumentumára.
- K4: Az alkalmazás kódja, a tesztek és a CI nem változnak.
- K5: A commitok logikus lépésenként készülnek.

## Elfogadási kritériumok

- E1: Adott a `README.md`, amikor a `<repo-url>` helyőrzőt keressük benne, akkor nincs találat.
- E2: Adott a `README.md`, amikor a klónozó parancsot keressük benne, akkor pontosan egy találat van, és az
  `git clone https://github.com/vronald2/asset-registry-api.git asset-registry-api`.
- E3: Adott a beírt cím, amikor hitelesítés nélkül lekérdezzük (`GIT_TERMINAL_PROMPT=0 git ls-remote <cím> refs/heads/main`),
  akkor a válasz tartalmazza a `refs/heads/main` hivatkozást.
- E4: Adott a `docs/review-fixes-4/` mappa, amikor a `TASK.md`, `REQUIREMENTS.md` és `PLAN.md` fájlt a lánc
  dokumentumaival összevetjük (`cmp`), akkor mindhárom bájtra azonos.
- E5: Adott a README „Hogyan készült” szakasza, amikor a `docs/review-fixes-4/` hivatkozásait keressük benne, akkor mindhárom
  dokumentumra van hivatkozás, és a szöveg említi az ötödik láncot.
- E6: Adott a lánc változása, amikor a változott fájlokat listázzuk az alaphoz képest, akkor csak a `README.md` és a
  `docs/review-fixes-4/` alatti fájlok szerepelnek.
- E7: Adott a lánc ágának kódja, amikor a `make check` lefut, akkor zöld.

## Feltételezések

- A HTTPS-cím kerül be, mert azzal hitelesítés nélkül is klónozható a repó, az SSH-címhez kulcs kellene.
- A parancs utolsó, könyvtárnevet megadó része marad, mert a kérés csak a helyőrzőre vonatkozik.
- A dokumentumok helye a korábbi javító láncok mintáját követi (`docs/review-fixes*/`).
- A lánc a `feature/maradek-naplozasi-review-pontok-javitasa` ágról indult, mert lánc nélkül a hook nem enged ágat váltani.
  Ennek a tartalma azonos a `main`-nel (`9fd6e8d`), így a `main`-re nyitott PR-ban csak ennek a láncnak a változása látszik.

## Hatókörön kívül

- A korábbi láncok és ennek a láncnak a `docs/` alatti másolatai szó szerintiek maradnak, a helyőrző idézete bennük nem változik.
- Az alkalmazás kódja, a tesztek, a CI és a README többi része.
- A többpáros űrlaptörzs hiányzó tesztsora.
- A push és a PR: csak a felhasználó kifejezett kérésére, a lánc lezárása után.

## Nyitott kérdések

Nincs.
