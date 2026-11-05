<?php

/// A NASM (Netwide Assembler) x86 assembly nyelve
class Nasm extends Lang {

   // azonosító: címke- és szimbólumnév (a .név helyi címke)
   const NAME = '[A-Za-z_.?][\w.$#@~?]*';
   // kód és komment szétválasztása (a stringben lévő ; nem komment)
   const SPLIT = '/^(?<code>(?:[^;"\'`]|"[^"]*"|\'[^\']*\'|`[^`]*`)*)'
      .'(?<comment>;.*)?$/';
   // címke kettősponttal a sor elején
   const LABEL = '/^\s*(?<label>'.self::NAME.'):\s*(?<rest>.*)$/';
   // címke kettőspont nélkül: csak a sor elején, adatdefinícióval (msg db 1)
   const DATALABEL = '/^(?<label>'.self::NAME.')\s+'
      .'(?<rest>(?:d[bwdqtoyz]|res[bwdqtoyz])\b.*)$/i';
   // sorok, amiket nem húzunk be: %-direktíva, equ és szakasz-direktívák
   const FIXED = '/^\s*(?:%|'.self::NAME.'\s+equ\b|(?:section|segment|global'
      .'|extern|bits|default|cpu|absolute|common|static|required)(?=\s|$))/i';
   // utasítás és operandus
   const INSTR = '/^\s*(?<op>\S+)\s*(?<arg>.*?)\s*$/';
   // célcímke az operandusban (a [cím], a $ és a kifejezések nem azok)
   const DEST = '/^'.self::NAME.'$/';
   const COND = '/^(j(?!mp$)[a-z]+|loop(?:n?[ez])?)$/i';
   const JUMP = '/^jmp$/i';
   // ismert sorkezdet: gyakori x86 mnemonik vagy adatdirektíva
   const KNOWN = '/^(?:mov|movzx|movsx|movsxd|push|pop|add|adc|sub|sbb|mul'
      .'|imul|div|idiv|inc|dec|neg|not|and|or|xor|shl|shr|sal|sar|rol|ror'
      .'|rcl|rcr|cmp|test|call|ret|retn|retf|int|into|iret|iretd|iretq|lea'
      .'|nop|xchg|cdq|cqo|cwd|cwde|cbw|cdqe|leave|enter|syscall|sysenter'
      .'|sysret|hlt|cli|sti|cld|std|clc|stc|cmc|lahf|sahf|bswap|xadd'
      .'|cmpxchg|bt|bts|btr|btc|bsf|bsr|pushad|popad|pusha|popa|pushf|popf'
      .'|pushfd|popfd|pushfq|popfq|in|out|ins|outs|movs[bwdq]?|cmps[bwdq]?'
      .'|scas[bwdq]?|lods[bwdq]?|stos[bwdq]?|rep|repe|repz|repne|repnz|lock'
      .'|cmov[a-z]+|set[a-z]+|loop(?:n?[ez])?'
      .'|j(?:mp|n?[abceglopsz]|ae|be|ge|le|nae|nbe|nge|nle|p[eo]|cxz|ecxz'
      .'|rcxz)|f[a-z0-9]+|v?(?:mov|add|sub|mul|div|and|or|xor|sqrt|cmp|max'
      .'|min)(?:ps|pd|ss|sd)|v?mov(?:dq[au]|[dq]|ups|upd|aps|apd)'
      .'|p(?:add|sub|mul|and|or|xor|cmp|shuf|unpck|pack|sll|srl|sra|max|min'
      .'|avg|extr|insr)[a-z0-9]*|d[bwdqtoyz]|res[bwdqtoyz]|times|align'
      .'|alignb|incbin)(?:\s|$)/i';

   // a helyi (.név) címkék szülője: az utolsó nem helyi címke
   protected $scope = '';

   /// Igaz, ha a kódsor címke, rögzített sor vagy ismert mnemonik
   protected function known(string $code): bool {
      if ( preg_match(self::FIXED, $code) )
         return true;
      if ( preg_match(self::LABEL, $code, $m)
            || preg_match(self::DATALABEL, $code, $m) )
         $code = $m['rest'];
      return '' === $code || 1 === preg_match(self::KNOWN, $code);
   }

   /// A címke teljes neve: a .helyi címke elé a szülő neve kerül
   protected function full(string $name): string {
      if ('.' === $name[0])
         return $this->scope.$name;
      return $name;
   }

   /// Egy sor feldarabolása: címke, mnemonik, ugrás, cél, komment
   function parse(string $row): Op {
      $op = new Op();
      if ( ! preg_match(self::SPLIT, $row, $m) )
         $m = ['code' => $row];
      $code = $m['code'];
      if ( '' !== ($m['comment'] ?? '') )
         $op->comment = $m['comment'];
      if ( preg_match(self::FIXED, $code) )
         return $op;

      if ( preg_match(self::LABEL, $code, $m)
            || preg_match(self::DATALABEL, $code, $m) ) {
         if ('.' !== $m['label'][0])
            $this->scope = $m['label'];
         $op->label = $this->full($m['label']);
         $code = $m['rest'];
      }

      if ( ! preg_match(self::INSTR, $code, $m) )
         return $op;
      $op->op = $m['op'];
      $op->isCond = 1 === preg_match(self::COND, $m['op']);
      $op->isJump = $op->isCond || 1 === preg_match(self::JUMP, $m['op']);
      $arg = preg_replace('/^(?:short|near|far)\s+/i', '', $m['arg']);
      if ( $op->isJump && preg_match(self::DEST, $arg) )
         $op->dest = $this->full($arg);
      return $op;
   }

   /// A kiírt sor: a címke után a behúzás, a címke hosszát levonva
   function format(string $row, Op $op, string $tab): string {
      if ('' === $row)
         return '';
      if ( preg_match(self::FIXED, $row) )
         return $row;
      if (null === $op->label)
         return $tab.$row;

      if ( preg_match(self::LABEL, $row, $m) )
         $head = $m['label'].':';
      elseif ( preg_match(self::DATALABEL, $row, $m) )
         $head = $m['label'];
      else
         return $tab.$row;
      return '' === $m['rest']
         ? $head : $head.$this->pad($tab, strlen($head)).$m['rest'];
   }

}
