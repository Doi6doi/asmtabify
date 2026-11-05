  ; Factorial calculator (NASM, x86-64 Linux)
  ; Computes n! for n = 0..20 and compares every value with a table.
  ; The exit code is 0 if all of them are right, 1 otherwise.

bits 64
global _start

section .data
expected:
  dq 1, 1, 2, 6, 24, 120, 720, 5040, 40320, 362880, 3628800, 39916800
  dq 479001600, 6227020800, 87178291200, 1307674368000, 20922789888000
  dq 355687428096000, 6402373705728000, 121645100408832000
  dq 2432902008176640000
count equ 21

section .text

  ; rax = n!, rdi = n
factorial:
  mov     eax, 1
  cmp     rdi, 2
  jb      .done               ; 0! = 1! = 1
    mov     rcx, 2
.next:
      imul    rax, rcx
      inc     rcx
      cmp     rcx, rdi
      jbe     .next               ; while rcx <= n
.done:
  ret

_start:
  xor     r12d, r12d          ; r12 = n
.check:
    mov     rdi, r12
    call    factorial
    cmp     rax, [expected + r12*8]
    jne     .failed
      inc     r12
      cmp     r12, count
      jb      .check
    xor     edi, edi            ; all right
    jmp     .exit
.failed:
  mov     edi, 1
.exit:
  mov     eax, 60             ; sys_exit
  syscall
