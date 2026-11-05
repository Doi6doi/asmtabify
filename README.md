# AsmTabify

Az AsmTabify egy disassemblálást segítő eszköz. Egy assembly
kódot tabulál, hogy a loop-ok, blokk ifek, stb látszódjanak.
Nem módosítja az assembly fájlt, így a lefordított bináris is 
változatlan marad.

## Cli

A program parancssorban futtatható:

php tabify.php <input> [options]

Options:
   -o <output>: Kimeneti fájl. Ha nincs megadva, felülírja a bemenetet
   -t <n>: egy behúzás `n` szóközt jelent (alap:2)
   -m <n>: egy blokk max `n` sor lehet, az ennél nagyobbakra 
      nem csinál behúzást.
   -l <lang>: az assembly nyelve `lang`. (ca65, nasm). (alap:auto) 



