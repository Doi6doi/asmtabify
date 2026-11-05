  ; Factorial calculator (ca65, 6502): fills a table with n! for n = 0..8

  .setcpu "6502"
  .org    $c000

result  = $02                   ; 16 bit result, low byte first
tmp     = $04                   ; copy of the previous result
count   = $06                   ; multiplication counter

start:
  lda     #1              ; result = 0! = 1
  sta     result
  lda     #0
  sta     result+1
  ldy     #0              ; y = n
store:
    lda     result          ; table[n] = n!
    sta     table_lo,y
    lda     result+1
    sta     table_hi,y
    iny                     ; n = n + 1
    cpy     #9
    bcs     finish          ; 8! was the last one
      jsr     multiply        ; result = result * n
      jmp     store
finish:
  rts

  ; result = result * y by repeated addition, keeps y
multiply:
  lda     result
  sta     tmp
  lda     result+1
  sta     tmp+1
  lda     #0
  sta     result
  sta     result+1
  sty     count
add:
    clc
    lda     result
    adc     tmp
    sta     result
    lda     result+1
    adc     tmp+1
    sta     result+1
    dec     count
    bne     add
  rts

table_lo:
  .res    9
table_hi:
  .res    9
