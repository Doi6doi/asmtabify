<?php

/// A ca65 (cc65 csomag) assembly nyelve
class Ca65 extends Lang {

   // kód és komment szétválasztása (a stringben lévő ; nem komment)
   const SPLIT = '/^(?<code>(?:[^;"\']|"[^"]*"|\'[^\']*\')*)(?<comment>;.*)?$/';
   // címke: név és kettőspont a sor elején (a := nem címke)
   const LABEL = '/^\s*(?<label>[A-Za-z_@][\w@]*):(?!=)\s*(?<rest>.*)$/';
   // egyenlőség: név := érték, vagy név = érték
   const EQUATE = '/^\s*[A-Za-z_@][\w@]*\s*:?=/';
   // utasítás és operandus
   const INSTR = '/^(?<op>\S+)\s*(?<arg>.*?)\s*$/';
   // célcímke az operandusban (a (cím), :+ és kifejezések nem azok)
   const DEST = '/^[A-Za-z_@][\w@]*$/';
   const COND = '/^b(cc|cs|eq|mi|ne|pl|vc|vs)$/i';
   const JUMP = '/^(jmp|bra)$/i';
   // felismeréshez: ca65-re jellemző direktíva, illetve 6502 utasítás
   const DIRECTIVE = '/^\s*\.(org|setcpu|segment|proc|endproc|import|importzp'
      .'|export|exportzp|macro|endmacro|scope|endscope|byte|word|addr)\b/i';
   const MNEMONIC = '/^\s+(lda|ldx|ldy|sta|stx|sty|jsr|rts|jmp|bne|beq'
      .'|inx|iny|dex|dey)\b/i';
   // a tab karakter szélessége a címke utáni igazításhoz
   const TABW = 8;

   /// Igaz, ha ca65-re jellemző direktíva vagy elég 6502 utasítás van
   static function detect(array $lines): bool {
      $n = 0;
      foreach ($lines as $row) {
         if ( preg_match(self::DIRECTIVE, $row) )
            return true;
         if ( preg_match(self::MNEMONIC, $row) && ++$n >= 3 )
            return true;
      }
      return false;
   }

   /// Egy sor feldarabolása: címke, mnemonik, ugrás, cél, komment
   function parse(string $row): Op {
      $op = new Op();
      if ( ! preg_match(self::SPLIT, $row, $m) )
         $m = ['code' => $row];
      $code = $m['code'];
      if ( '' !== ($m['comment'] ?? '') )
         $op->comment = $m['comment'];

      if ( preg_match(self::LABEL, $code, $m) ) {
         $op->label = $m['label'];
         $code = $m['rest'];
      } elseif ( preg_match(self::EQUATE, $code) )
         return $op;

      if ( ! preg_match(self::INSTR, $code, $m) )
         return $op;
      $op->op = $m['op'];
      $op->isCond = 1 === preg_match(self::COND, $m['op']);
      $op->isJump = $op->isCond || 1 === preg_match(self::JUMP, $m['op']);
      if ( $op->isJump && preg_match(self::DEST, $m['arg']) )
         $op->dest = $m['arg'];
      return $op;
   }

   /// A kiírt sor: a címke után a behúzás, a címke hosszát levonva
   function format(string $row, Op $op, string $tab): string {
      if ('' === $row)
         return '';
      if (null === $op->label)
         return preg_match(self::EQUATE, $row) ? $row : $tab.$row;

      $head = $op->label.':';
      $rest = ltrim(substr($row, strlen($head)));
      return '' === $rest ? $head : $head.$this->pad($tab, strlen($head)).$rest;
   }

   /// A címke utáni szóköz/tab: a címke hosszát levonja a behúzásból
   protected function pad(string $tab, int $len): string {
      if (' ' === $tab[0])
         return str_repeat(' ', max(1, strlen($tab) - $len));
      return str_repeat("\t", max(1, strlen($tab) - intdiv($len, self::TABW)));
   }

}
