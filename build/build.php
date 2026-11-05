<?php

/// Az AsmTabify build szkriptje: PHAR és önálló binárisok készítése
class Build {

   // a micro.sfx (statikus PHP + PHAR-futtató) letöltési helye
   const URL = 'https://dl.static-php.dev/static-php-cli/';

   // cél => [a micro.sfx archívum útvonala, a kimeneti fájl kiterjesztése]
   const TARGETS = [
      'linux-x86_64' => ['minimal/php-8.4.23-micro-linux-x86_64.tar.gz', ''],
      'macos-aarch64' => ['minimal/php-8.4.23-micro-macos-aarch64.tar.gz', ''],
      'windows-x86_64' => ['windows/spc-min/php-8.4.22-micro-win.zip', '.exe'],
   ];

   // a futtató gép: PHP_OS_FAMILY és php_uname('m') -> a célnév részei
   const OSES = ['Linux' => 'linux', 'Darwin' => 'macos',
      'Windows' => 'windows'];
   const ARCHS = ['x86_64' => 'x86_64', 'amd64' => 'x86_64',
      'arm64' => 'aarch64', 'aarch64' => 'aarch64'];

   protected $root;
   protected $dir;

   /// A parancssor feldolgozása és a parancs futtatása
   static function main(array $argv): int {
      try {
         (new Build())->run(array_slice($argv, 1));
         return 0;
      } catch (Exception $e) {
         fwrite(STDERR, "Error: ".$e->getMessage()."\n");
         return 1;
      }
   }

   /// A projekt és a build könyvtára (minden ide kerül, alkönyvtár nélkül)
   function __construct() {
      $this->root = dirname(__DIR__);
      $this->dir = __DIR__;
   }

   /// A parancs végrehajtása: local, all, phar vagy clean
   function run(array $args) {
      $cmd = array_shift($args) ?? 'local';
      if ( in_array($cmd, ['local', 'all', 'phar']) )
         $this->needWritablePhar();
      switch ($cmd) {
         case 'local': $this->bin([$this->current()], true); break;
         case 'all': $this->bin(array_keys(self::TARGETS), false); break;
         case 'phar': $this->phar(); break;
         case 'clean': $this->clean(); break;
         default: throw new Exception($this->usage($cmd));
      }
   }

   /// A PHAR íráshoz phar.readonly=0 kell: ha nincs, újraindítja magát
   protected function needWritablePhar() {
      global $argv;
      if ( ! ini_get('phar.readonly') )
         return;
      if ( getenv('ASMTABIFY_REEXEC') )
         throw new Exception("phar.readonly could not be turned off");
      putenv('ASMTABIFY_REEXEC=1');
      $cmd = escapeshellarg(PHP_BINARY).' -d phar.readonly=0 '
         .implode(' ', array_map('escapeshellarg', $argv));
      passthru($cmd, $rc);
      exit($rc);
   }

   /// A futtató gép célneve (pl. linux-x86_64), ha van hozzá micro.sfx
   protected function current(): string {
      $os = self::OSES[PHP_OS_FAMILY] ?? '';
      $arch = self::ARCHS[strtolower(php_uname('m'))] ?? '';
      if ( ! isset(self::TARGETS["$os-$arch"]) )
         throw new Exception("No target for this platform ($os-$arch)");
      return "$os-$arch";
   }

   /// Minden előállított fájl törlése: binárisok, PHAR, micro.sfx, archívumok
   protected function clean() {
      foreach (['asmtabify*', 'micro-*.sfx', '*.zip', '*.tar.gz'] as $pattern) {
         foreach (glob($this->dir.'/'.$pattern) as $f)
            unlink($f);
      }
   }

   /// A projekt .php fájljaiból az asmtabify.phar
   protected function phar() {
      $file = $this->dir.'/asmtabify.phar';
      @unlink($file);
      $phar = new Phar($file);
      $phar->startBuffering();
      foreach (glob($this->root.'/'.'*.php') as $f)
         $phar->addFile($f, basename($f));
      $phar->setDefaultStub('asmtabify.php');
      $phar->stopBuffering();
      echo "phar: $file (".filesize($file)." bytes)\n";
   }

   /// A PHAR és célonként a micro.sfx összefűzése; $short: asmtabify(.exe)
   protected function bin(array $targets, bool $short) {
      $this->phar();
      $phar = file_get_contents($this->dir.'/asmtabify.phar');
      foreach ($targets as $t) {
         $name = $short ? 'asmtabify' : "asmtabify-$t";
         $dest = $this->dir.'/'.$name.self::TARGETS[$t][1];
         file_put_contents($dest, file_get_contents($this->sfx($t)).$phar);
         chmod($dest, 0755);
         echo "bin: $dest (".filesize($dest)." bytes)\n";
      }
   }

   /// A cél micro.sfx fájlja a build könyvtárból (ha nincs, letölti)
   protected function sfx(string $target): string {
      $sfx = $this->dir."/micro-$target.sfx";
      if ( is_file($sfx) )
         return $sfx;
      $tmp = sys_get_temp_dir().'/asmtabify-'.$target.'-'.getmypid();
      $this->remove($tmp);
      mkdir($tmp);
      $url = self::URL.self::TARGETS[$target][0];
      $archive = $tmp.'/'.basename($url);
      echo "download: $url\n";
      if ( ! @copy($url, $archive) )
         throw new Exception("Could not download $url");
      (new PharData($archive))->extractTo($tmp.'/x', 'micro.sfx', true);
      rename($tmp.'/x/micro.sfx', $sfx);
      $this->remove($tmp);
      return $sfx;
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

   /// Hibás parancs esetén a hibaüzenet és a használati útmutató
   protected function usage(string $cmd): string {
      return "Unknown command: $cmd\n\n"
         ."Usage: php build/build.php [command]\n\n"
         ."Commands:\n"
         ."  local    this machine: build/asmtabify(.exe), the default\n"
         ."  all      binaries for every target: build/asmtabify-<target>\n"
         ."  phar     only build/asmtabify.phar\n"
         ."  clean    remove everything built or downloaded (binaries, phar,\n"
         ."           micro.sfx files, zip and tar.gz archives)\n\n"
         ."Targets: ".implode(', ', array_keys(self::TARGETS))."\n";
   }

}

exit(Build::main($argv));
