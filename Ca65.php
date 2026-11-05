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
   const INSTR = '/^\s*(?<op>\S+)\s*(?<arg>.*?)\s*$/';
   // célcímke az operandusban (a (cím), :+ és kifejezések nem azok)
   const DEST = '/^[A-Za-z_@][\w@]*$/';
   const COND = '/^b(cc|cs|eq|mi|ne|pl|vc|vs)$/i';
   const JUMP = '/^(jmp|bra)$/i';
   // ismert sorkezdet: direktíva (.név) vagy 6502/65C02 mnemonik
   const KNOWN = '/^(?:\.[A-Za-z]\w*|(?:adc|and|asl|bcc|bcs|beq|bit|bmi|bne'
      .'|bpl|brk|bvc|bvs|clc|cld|cli|clv|cmp|cpx|cpy|dec|dex|dey|eor|inc'
      .'|inx|iny|jmp|jsr|lda|ldx|ldy|lsr|nop|ora|pha|php|pla|plp|rol|ror'
      .'|rti|rts|sbc|sec|sed|sei|sta|stx|sty|tax|tay|tsx|txa|txs|tya'
      .'|bra|phx|phy|plx|ply|stz|trb|tsb))(?:\s|$)/i';

   /// Igaz, ha a kódsor címke, egyenlőség, direktíva vagy ismert mnemonik
   protected function known(string $code): bool {
      if ( preg_match(self::LABEL, $code, $m) )
         $code = $m['rest'];
      elseif ( preg_match(self::EQUATE, $code) )
         return true;
      return '' === $code || 1 === preg_match(self::KNOWN, $code);
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

}
