# Feladat: Hibás JSON és naplómaszkolás javítása

- Azonosító: `20261107-2335-hibas-json-es-naplomaszkolas-javitasa`
- Indítva: 2026-11-07T23:35:13+01:00
- Ág: `feature/hibas-json-es-naplomaszkolas-javitasa` (alap: `feature/maradek-review-pontok-javitasa` @ `b142ad2714`)
- Minőségkapuk:
- `make check`

## Az eredeti kérés (szó szerint)

„Igen, javítsd” – válasz arra a kérdésre, hogy javítsam-e publikálás előtt a javító lánc (feature/maradek-review-pontok-javitasa) 2. review körében jelzett, a változás előtt is fennálló két MINOR pontot:

1. [MINOR] Hibás (nem értelmezhető) vagy érvénytelen UTF-8-at tartalmazó JSON-törzsű kérésnél a Request::json() üres bagot ad. PATCH-nél minden szabály `sometimes`, így a validáció átmegy, az update([]) lefut, és a válasz 200 a változatlan erőforrással – a kliens azt hiheti, hogy a módosítás sikerült. Javaslat: 400 egy api-csoportbeli middleware-ből, ha isJson(), a törzs nem üres és !json_validate().
2. [MINOR] Ha egy kliens a JSON-t rossz Content-Type-pal küldi (pl. a curl -d alapértelmezésével, application/x-www-form-urlencoded), a PHP az egész törzset egyetlen űrlapkulcsnak értelmezi; a data() csak az értéket maszkolja, így a titkot tartalmazó kulcs a request_body-ba kerül. text/plain törzsnél (password=hunter2) a nyers tartalom kerül be.

Ugyanennek a review-nak a NIT-jei: a LogApiTraffic és a config/api-logging.php megjegyzése még „Request::is() patterns”-t említ, pedig az illesztést a RequestPath végzi; a README „minden más 404” mondata nem említi, hogy a hibás kódolású URL 400-at kap; az űrlapmezők naplózáskor kétszer mennek át a data()-n.
