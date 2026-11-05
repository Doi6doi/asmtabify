<?php

/// Assembly forrás behúzása az ugrások alapján, a program belépési pontja.
class AsmTabify {

   /// Teljes program futtatás
   static function runProgram(array $argv) {
      spl_autoload_register([self::class, 'autoload']);
      (new AsmTabify())->run( $argv );
   }

   /// autoload ebben a könyvtárban
   static function autoload(string $cls): void {
      require_once(__DIR__."/$cls.php");
   }

   protected $inFile;
   protected $outFile;
   protected $nTab;
   protected $maxBlock;
   protected $maxDepth;
   protected $lang;
   protected $rows;
   protected $ops;
   protected $labs;

   /// Egyelőre nem csinál semmit, a futtatás később kerül ide.
   function run(array $argv) {
      $this->getParams($argv);
      $this->read();
      $this->parse();
      $this->collectLabels();
      $this->tabulate();
      $this->write();
   }

   /// paraméterek beolvasása
   protected function getParams($argv) {
      try {
         $this->defParams();
         $this->arg($argv);
         while ( $this->getParam($argv) )
            ;
         $this->checkParams();
      } catch (Exception $e) {
         $this->usage($e->getMessage());
      }
   }

   /// egy paraméter olvasása
   protected function arg(&$argv) {
      if ( ! $argv )
         throw new Exception("Required argument missing");
      return array_shift($argv);
   }

   /// default paraméterek
   protected function defParams() {
      $this->nTab = 2;
      $this->maxDepth = 30;
      $this->maxBlock = 500;
      $this->lang = "auto";
   }

   /// paraméterek ellenőrzése
   protected function checkParams() {
      if (! $this->inFile)
         throw new Exception("Input file missing");
      if ( ! ("tab" == $this->nTab || intval($this->nTab)))
         throw new Exception("Invalid tab: ".$this->nTab );
      if ( ! intval( $this->maxBlock ))
         throw new Exception("Invalid max block: ".$this->maxBlock );
      if ( ! intval( $this->maxDepth ))
         throw new Exception("Invalid max depth: ".$this->maxDepth );
      if ( "auto" != $this->lang
            && ! array_key_exists($this->lang, Lang::LANGS) )
         throw new Exception("Unknown language: ".$this->lang );
   }

   /// Egy paraméter beolvasása
   protected function getParam(&$argv) {
      if ( ! $argv ) return false;
      $a = $this->arg($argv);
      switch ($a) {
         case "-o": $this->outFile = $this->arg($argv); break;
         case "-t": $this->nTab = $this->arg($argv); break;
         case "-m": $this->maxBlock = $this->arg($argv); break;
         case "-d": $this->maxDepth = $this->arg($argv); break;
         case '-l': $this->lang = $this->arg($argv); break;
         default:
            if ( str_starts_with($a, '-') )
               throw new Exception("Unknown option: $a");
            if ( $this->inFile )
               throw new Exception("Only one input file is allowed");
            $this->inFile = $a;
      }
      return true;
   }

   /// A bemeneti fájl soraiból tömb
   protected function read() {
      if ( false === ($text = @file_get_contents($this->inFile)))
         throw new Exception("Could not read ".$this->inFile);
      $this->rows = explode("\n", $text);
   }

   /// A sorok feldarabolása az $ops-ba (a $rows-zal azonos indexekkel)
   protected function parse() {
      $this->lang = Lang::create( $this->lang, $this->rows );
      $this->ops = [];
      foreach ($this->rows as $i => $row)
         $this->ops[$i] = $this->lang->parse($row);
   }

   /// A címkék összegyűjtése a $labs-ba: név -> sorindex
   protected function collectLabels() {
      $this->labs = [];
      foreach ($this->ops as $i => $op) {
         if ( null !== $op->label )
            $this->labs[$op->label] = $i;
      }
   }

   /// Az ugrások alapján az $ops tab mezőinek beállítása
   protected function tabulate() {
      foreach ($this->ops as $i => $op) {
         if ( ! $op->isJump || ! isset($this->labs[$op->dest]) )
            continue;
         $j = $this->labs[$op->dest];
         if ($j < $i) {
            $from = $j + 1;      // visszafelé ugrás: ciklus
            $to = $i;
         } elseif ($j > $i && $op->isCond) {
            $from = $i + 1;      // előre feltételes ugrás: elágazás
            $to = $j - 1;
         } else
            continue;
         $size = $to - $from + 1;
         if ($size < 1 || ($this->maxBlock && $size > $this->maxBlock))
            continue;
         for ($k = $from; $k <= $to; $k++) {
            $o = $this->ops[$k];
            if ( $o->tab < $this->maxDepth )
               $o->tab++;
         }
      }
   }

   /// A sorok kiírása a kimeneti fájlba (alapból a bemenetbe)
   protected function write() {
      $out = $this->outFile ?: $this->inFile;
      $unit = "tab" == $this->nTab ? "\t" : str_repeat(" ", (int)$this->nTab);
      $rows = [];
      foreach ($this->rows as $i => $row) {
         $row = trim($row);
         $op = $this->ops[$i];
         $tab = str_repeat($unit, 1 + $op->tab);
         $rows[] = $this->lang->format($row, $op, $tab);
      }
      $text = implode("\n", $rows );
      if (false === @file_put_contents($out, $text) )
         throw new Exception("Could not write ".$out );
   }

   /// Hibaüzenet (ha van) és használati útmutató kiírása, majd kilépés
   protected function usage($msg) {
      $ret = [
         "",
         "Usage: php asmtabify.php <input> [options]",
         "",
         "Options:",
         "  -o <output>: Output file",
         "  -l <lang>: Assembly language (`ca65`, `nasm`, `auto`)",
         "  -t <n>: Tabs with `n` spaces or ith `tab`",
         "  -m <n>: A block's maximum size is <n> lines (default:500)",
         "  -d <n>: Maximum nesting depth is <n> tabs (default: 30)",
         "",
         ""
      ];
      fwrite(STDERR, implode("\n",$ret));
      if ($msg) {
         fwrite(STDERR, "Error: $msg\n\n");
         exit(1);
      }
   }

}

AsmTabify::runProgram($argv);
