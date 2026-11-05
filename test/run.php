<?php

/// Az AsmTabify tesztjei: test/<nyelv>/<név>.<kit> és <név>.expected.<kit>
class Test {

   // a ca65 teszteknél az ld65 betöltési címe (a forrásokban: .org $c000)
   const CA65_CFG = "MEMORY { M: start = \$c000, size = \$1000, file = %O; }\n"
      ."SEGMENTS { CODE: load = M, type = rw; }\n";

   protected $root;
   protected $tmp;
   protected $total = 0;
   protected $fails = 0;

   /// A tesztek futtatása, a kilépési kód 0, ha mind sikeres
   static function main(): int {
      $test = new Test();
      $test->run();
      echo "\n".($test->total - $test->fails)."/".$test->total." passed\n";
      return $test->fails ? 1 : 0;
   }

   /// A projekt és az ideiglenes könyvtár
   function __construct() {
      $this->root = dirname(__DIR__);
      $this->tmp = sys_get_temp_dir().'/asmtabify-test-'.getmypid();
      $this->remove($this->tmp);
      mkdir($this->tmp);
   }

   /// Az ideiglenes könyvtár törlése
   function __destruct() {
      $this->remove($this->tmp);
   }

   /// Minden test/<nyelv>/<név>.<kit> fájl tesztelése (az .expected. nélkül)
   function run() {
      foreach (glob(__DIR__.'/'.'*'.'/'.'*') as $file) {
         if ( false === strpos(basename($file), '.expected.') )
            $this->check(basename(dirname($file)), $file);
      }
   }

   /// Egy teszteset: elvárt kimenet, ismételt futtatás, whitespace, fordítás
   protected function check(string $lang, string $file) {
      $this->total++;
      $name = basename($file);
      $expected = preg_replace('/(\.[^.]+)$/', '.expected$1', $file);
      $errors = [];
      $note = '';
      try {
         if ( ! is_file($expected) )
            throw new Exception("missing ".basename($expected));
         $src = file_get_contents($file);
         $want = file_get_contents($expected);
         $case = "$lang-$name";
         $orig = $this->put("$case/orig", $name, $src);
         $res = $this->put("$case/res", $name, $src);
         $got = $this->tabulate($lang, $res);
         if ($got !== $want)
            $errors[] = "output differs: ".$this->firstDiff($want, $got);
         $res2 = $this->put("$case/res2", $name, $got);
         if ($this->tabulate($lang, $res2) !== $got)
            $errors[] = "running it again changes the output";
         $ws = '/\s+/';
         if ( preg_replace($ws, '', $src) !== preg_replace($ws, '', $got) )
            $errors[] = "more than whitespace changed";
         $a = $this->assemble($lang, $orig);
         if (null === $a)
            $note = "assembler not found, byte comparison skipped";
         elseif ($a !== $this->assemble($lang, $res))
            $errors[] = "the assembled result differs";
      } catch (Exception $e) {
         $errors[] = $e->getMessage();
      }
      $this->report("$lang/$name", $errors, $note);
   }

   /// Egy teszteset eredményének kiírása
   protected function report(string $name, array $errors, string $note) {
      if ($errors)
         $this->fails++;
      echo ($errors ? "FAIL " : "ok   ").$name."\n";
      foreach ($errors as $e)
         echo "     $e\n";
      if ($note)
         echo "     ($note)\n";
   }

   /// A szöveg fájlba írása az ideiglenes könyvtár alkönyvtárába
   protected function put(string $sub, string $name, string $text): string {
      $dir = $this->tmp.'/'.$sub;
      mkdir($dir, 0777, true);
      file_put_contents($dir.'/'.$name, $text);
      return $dir.'/'.$name;
   }

   /// A fájl tabulálása az eszközzel (helyben), az eredmény szövege
   protected function tabulate(string $lang, string $path): string {
      $cmd = escapeshellarg(PHP_BINARY).' '
         .escapeshellarg($this->root.'/asmtabify.php').' '
         .escapeshellarg($path).' -l '.escapeshellarg($lang).' 2>&1';
      exec($cmd, $out, $rc);
      if ($rc)
         throw new Exception("asmtabify failed: ".implode(" ", $out));
      return file_get_contents($path);
   }

   /// A fájl lefordított bájtjai a nyelv assemblerével, vagy null
   protected function assemble(string $lang, string $path): ?string {
      $dir = dirname($path);
      $name = escapeshellarg(basename($path));
      switch ($lang) {
         case 'ca65':
            if ( ! $this->has('ca65') || ! $this->has('ld65') )
               return null;
            file_put_contents($dir.'/raw.cfg', self::CA65_CFG);
            $cmd = "ca65 -o x.o $name && ld65 -C raw.cfg -o x.bin x.o";
            $out = 'x.bin';
            break;
         case 'nasm':
            if ( ! $this->has('nasm') )
               return null;
            $cmd = "nasm -f elf64 $name -o x.o";
            $out = 'x.o';
            break;
         default:
            return null;
      }
      exec('cd '.escapeshellarg($dir)." && $cmd 2>&1", $lines, $rc);
      if ($rc)
         throw new Exception("$lang failed: ".implode(" ", $lines));
      return file_get_contents($dir.'/'.$out);
   }

   /// Igaz, ha a program elérhető a PATH-on
   protected function has(string $tool): bool {
      exec('command -v '.escapeshellarg($tool).' 2>/dev/null', $out, $rc);
      return 0 === $rc;
   }

   /// Az első eltérő sor a két szövegben
   protected function firstDiff(string $want, string $got): string {
      $w = explode("\n", $want);
      $g = explode("\n", $got);
      for ($i = 0; $i < max(count($w), count($g)); $i++) {
         if ( ($w[$i] ?? null) !== ($g[$i] ?? null) )
            return "line ".($i + 1).": expected '".($w[$i] ?? '<none>')
               ."', got '".($g[$i] ?? '<none>')."'";
      }
      return "no difference";
   }

   /// Könyvtár rekurzív törlése (ha van)
   protected function remove(string $dir) {
      if ( ! is_dir($dir) )
         return;
      $it = new RecursiveIteratorIterator(
         new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
         RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($it as $f) {
         if ( $f->isDir() && ! $f->isLink() )
            rmdir($f->getPathname());
         else
            unlink($f->getPathname());
      }
      rmdir($dir);
   }

}

exit(Test::main());
