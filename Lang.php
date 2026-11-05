<?php

/// Egy assembly nyelv leírása, a nyelvi osztályok (Ca65, Nasm) őse
abstract class Lang {

   /// A támogatott nyelvek: a -l értéke -> osztály
   const LANGS = ['ca65' => 'Ca65', 'nasm' => 'Nasm'];

   /// A felismeréshez szükséges legkisebb detect() érték
   const MIN_SCORE = 0.85;

   /// A tab karakter szélessége a címke utáni igazításhoz
   const TABW = 8;

   /// A nyelv példánya név alapján; "auto" esetén a legjobb detect() nyer
   static function create(string $name, array $rows): Lang {
      if ('auto' == $name) {
         $best = null;
         $max = 0.0;
         foreach (self::LANGS as $cls) {
            $score = $cls::detect($rows);
            if ($score >= self::MIN_SCORE && $score > $max) {
               $best = $cls;
               $max = $score;
            }
         }
         if (null === $best)
            throw new Exception("Could not detect the language, use -l");
         return new $best();
      }
      $cls = self::LANGS[$name];
      return new $cls();
   }

   /// A kódsorok mekkora részét érti a nyelv: 0 (semmit) .. 1 (mindet)
   static function detect(array $lines): float {
      $lang = new static();
      $total = 0;
      $known = 0;
      foreach ($lines as $row) {
         $code = trim(preg_match(static::SPLIT, $row, $m) ? $m['code'] : $row);
         if ('' === $code)
            continue;
         $total++;
         if ( $lang->known($code) )
            $known++;
      }
      return $total ? $known / $total : 0.0;
   }

   /// Igaz, ha a (komment nélküli, vágott) kódsort érti a nyelv
   abstract protected function known(string $code): bool;

   /// A címke utáni szóköz/tab: a címke hosszát levonja a behúzásból
   protected function pad(string $tab, int $len): string {
      if (' ' === $tab[0])
         return str_repeat(' ', max(1, strlen($tab) - $len));
      return str_repeat("\t", max(1, strlen($tab) - intdiv($len, self::TABW)));
   }

   /// Egy sor feldarabolása: címke, mnemonik, ugrás, cél, komment
   abstract function parse(string $row): Op;

   /// A kiírt sor: a $tab a címke mögé, címke nélkül elé (üres sor üres)
   abstract function format(string $row, Op $op, string $tab): string;

}
