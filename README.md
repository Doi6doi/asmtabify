# AsmTabify

AsmTabify is a disassemble helping tool. It tabulates
an assembly source so that loops, ifs can be seen better.

It does not modify the assembly apart from tabulating so the
assembled result will be the same.

## Example

A flat 64-bit x86 (NASM) function, as a disassembler or a quick listing
would give it:

```nasm
sum_positive:
    xor     eax, eax
.next:
    mov     edx, [rdi]
    test    edx, edx
    jle     .skip
    add     eax, edx
.skip:
    add     rdi, 4
    dec     rsi
    jnz     .next
    ret
```

After `asmtabify sum.asm` (the default tab is 2 spaces):

```nasm
sum_positive:
  xor     eax, eax
.next:
    mov     edx, [rdi]
    test    edx, edx
    jle     .skip
      add     eax, edx
.skip:
    add     rdi, 4
    dec     rsi
    jnz     .next
  ret
```

The backward jump `jnz .next` makes the lines up to it a loop, the forward
conditional jump `jle .skip` makes `add eax, edx` an `if` body. The assembled
object file is byte for byte the same as before.

## Install

### Option 1: Self-contained binary

Grab a no-dependencies binary for your system from the Releases page.

You may need to rename it and chmod +x on macOS and Linux.

### Option 2: Composer

```
composer global require doi6doi/asmtabify
```

## Usage

```
asmtabify <input> [options]

Options:
   -o <output>: Output file. If not present, the input will be overwritten
   -l <lang>: Assembly language (ca65, nasm, auto)
   -t <n>: Number of spaces for a tab. `tab` means a literal tab
   -m <n>: Blocks longer than `n` will not be tabulated (default: 500)
   -d <n>: Maximum tab depth (default:30)
   -x <range>: Do not tabulate a range. Lines `10-20` or labels `first-last`,
      both ends included. Can be given more than once
   -h, --help: Show the help
```

Data that the disassembler decoded as code (a byte table full of fake
branches) can nest absurdly deep. Exclude such a range with `-x`, or better,
mark it as data in the disassembler.



