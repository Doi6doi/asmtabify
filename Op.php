<?php

namespace AsmTabify;

/// Egy feldarabolt assembly sor, a Lang::parse() eredménye
class Op {

   public $label;           // a sorban definiált címke neve, vagy null
   public $op;              // mnemonik vagy direktíva, vagy null
   public $isJump = false;  // ugrás-e (feltételes vagy feltétel nélküli)
   public $isCond = false;  // feltételes ugrás-e
   public $dest;            // az ugrás célcímkéje, vagy null
   public $comment;         // a komment (a jellel együtt), vagy null
   public $tab = 0;         // a behúzás mélysége, a tabulate() növeli

}
