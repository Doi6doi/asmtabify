# PLAN.md

Az AsmTabify: assembly forrás behúzása az ugrások alapján (ciklus, elágazás).
Csak whitespace változik. A fókusz a címke + ugrás típusú nyelveken van.

## Kész

- `asmtabify.php`: a parancssori belépő (betölti a `Cli`-t és futtatja).
- `Cli`: parancssor (`-o`, `-t`, `-m`, `-d`, `-l`, `-x`), beolvasás, tabulálás,
  kiírás. Az `-x` tartományt (sorszám vagy címke) kizár a tabulálásból.
- Minden osztály az `AsmTabify` névtérben van, az autoload az `autoload.php`-ban
  (Composer nélküli használatra és a PHAR-hoz).
- `Lang`: közös ős (`create`, `detect` pontszámmal, `pad`), `Op`: egy sor részei.
- `Ca65`, `Nasm`: `parse`, `format`, `known`. A `Nasm` valódi `nasm`-mal
  ellenőrizve (bájtra azonos objektum).
- `-l auto`: nyelvfelismerés, a legjobb `detect()` nyer, 0,85 alatt nincs találat.
- `build/build.php`: `local` (az aktuális gépre: `build/asmtabify(.exe)`),
  `all` (mind: `build/asmtabify-<cél>`), `phar`, `clean` (mindent töröl, amit a
  build készített vagy letöltött). Minden a `build` könyvtárban, alkönyvtár
  nélkül. PHAR és önálló binárisok (static-php-cli `micro.sfx` + PHAR) három célra (Linux x86_64,
  macOS Apple Silicon, Windows x86_64), egy gépen. Más célt a `TARGETS`
  konstans bővítésével bárki hozzáadhat.
- `LICENSE` (Free LLM License).
- `composer.json`: PSR-4 autoload (`AsmTabify\` a gyökérből), `bin/asmtabify`
  (a `vendor/bin/asmtabify` parancs), `scripts`: `build` (az aktuális gépre),
  `package` (mind), `clean`. A `license`: `LicenseRef-Free-LLM-License` (érvényes
  SPDX-hivatkozás egyedi licenchez). `composer validate --strict` rendben, egy
  teszt-projektbe telepítve működik. PHP >= 7.4 (a PHPStan `phpVersion: 70400`
  mellett nincs verziós hiba; valódi 7.4-en nem futtattuk).
- Tesztek: `test/run.php` (`php test/run.php`). Nyelvenként egy könyvtár
  (`test/ca65`, `test/nasm`), bennük egy faktoriális program és az elvárt
  kimenete (`*.expected.*`). Minden esetnél ellenőrzi: az elvárt kimenet, a
  második futás ugyanazt adja, csak whitespace változott, és a nyelv saját
  assemblerével (ca65+ld65, nasm) lefordítva bájtra azonos. Ha az assembler
  nincs a gépen, azt a lépést kihagyja és jelzi.

## Következik

- Packagist: a `name` vendor része most `your-vendor` (cserélni kell), Git-repó,
  a verziók címkékből (`v0.1.0`), webhook az automatikus frissítéshez.
- A macOS és Windows binárisok futtatása nincs kipróbálva (csak Linuxon). A
  `.gitignore`-ba: `build/asmtabify*`, `build/micro-*.sfx`.
- README: telepítés (a Releases-ből és Composerrel), támogatott nyelvek és a
  felismerés, korlátok, ellenőrzés, hozzájárulás.
- Több teszt: a kapcsolók (`-t tab`, `-m`, `-d`, `-x`), a hibaesetek, a
  nyelvfelismerés; nyelvenként további minták (helyi címkék, adatsorok, stringek).
  A `composer test` script (`php test/run.php`).
- Nyelvi osztályok (címke + ugrás), megnézendő:
  - GAS (GNU `as`) és az LLVM integrált assembler (GAS-szintaxis)
  - MASM (`ml`/`ml64`)
  - ARM armasm / Keil (címke az oszlop 0-ban, kettőspont nélkül)
  - smali (Android, Dalvik): `:címke`, `goto`, `if-*`
  - JVM: Jasmin, Krakatau, a `javap -c` kimenete
  - .NET CIL (`ilasm`/`ildasm`): `IL_0000:`, `br*`
  - LLVM IR (`.ll`): `név:`, `br`
- Új nyelvnél: `LANGS` bővítése, `SPLIT`/`LABEL`/`KNOWN` konstansok, ellenőrzés
  a nyelv saját assemblerével (újrafordítva bájtra azonos).

## Egyelőre kimarad

- Strukturált vezérlésű formátumok (WebAssembly WAT, SPIR-V): a blokkok ott
  kifejezettek, ehhez az `Op`-nak nyitó/záró jelzés kellene.
- Retro assemblerek (ACME, DASM, 64tass, Z80): a `ca65` csak a Lode Runner miatt van.

## Nyitott kérdések

- A `detect()` küszöbe (`Lang::MIN_SCORE`, most 0,85) és a ritka mnemonikok.
- Ugyanaz a forrás több assemblerrel is lefordulhat: a pontszámok döntetlen esetén
  az első nyer, ez még nincs kezelve.
- A `LICENSE`/`NOTICE` szétválasztása (engedély és vitaindító közlemény).
