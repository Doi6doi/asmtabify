<?php

/// Az AsmTabify névtér osztályai ebből a könyvtárból: AsmTabify\Név -> Név.php
spl_autoload_register(function (string $cls) {
   $prefix = 'AsmTabify\\';
   if ( 0 !== strncmp($cls, $prefix, strlen($prefix)) )
      return;
   $file = __DIR__.'/'.substr($cls, strlen($prefix)).'.php';
   if ( is_file($file) )
      require_once($file);
});
