<?php

/// Egy assembly nyelv leírása, a nyelvi osztályok (Ca65, Nasm) őse
abstract class Lang {

   /// A támogatott nyelvek: a -l értéke -> osztály
   const LANGS = ['ca65' => 'Ca65', 'nasm' => 'Nasm'];

   /// A nyelv példánya név alapján; "auto" esetén a sorokból felismerve
   static function create(string $name, array $rows): Lang {
      if ('auto' == $name) {
         foreach (self::LANGS as $cls) {
            if ($cls::detect($rows))
               return new $cls();
         }
         throw new Exception("Could not detect the language, use -l");
      }
      $cls = self::LANGS[$name];
      return new $cls();
   }

   /// Igaz, ha a sorok alapján ez a nyelv felismerhető (-l auto)
   abstract static function detect(array $lines): bool;

   /// Egy sor feldarabolása: címke, mnemonik, ugrás, cél, komment
   abstract function parse(string $row): Op;

   /// A kiírt sor: a $tab a címke mögé, címke nélkül elé (üres sor üres)
   abstract function format(string $row, Op $op, string $tab): string;

}
