#!/usr/bin/env bash
# Test files WITH GENUINE MAGIC BYTES so MIME sniffing is actually exercised.
# A file of zeros named logo.psd tests nothing.
# Usage: scripts/make-fixtures.sh [size_mb]   (default 5; use 150+ to test chunking)
set -euo pipefail
cd "$(dirname "$0")/.."
mb="${1:-1}"
big="${2:-150}"
out=tests/fixtures; mkdir -p "$out"
pad() { dd if=/dev/urandom bs=1M count="$mb" status=none >> "$1"; }

printf '8BPS\x00\x01\x00\x00\x00\x00\x00\x00\x00\x03'          > "$out/logo.psd"; pad "$out/logo.psd"
printf '%%PDF-1.5\n%%\xe2\xe3\xcf\xd3\n'                       > "$out/logo.ai";  pad "$out/logo.ai"   # .ai sniffs as PDF — the trap
printf '%%!PS-Adobe-3.0 EPSF-3.0\n%%%%BoundingBox: 0 0 100 100\n' > "$out/logo.eps"; pad "$out/logo.eps"
printf 'RIFF\x00\x00\x01\x00CDRAvrsn'                          > "$out/logo.cdr"; pad "$out/logo.cdr"  # RIFF-based (≤X3)
printf '%%PDF-1.4\n'                                           > "$out/logo.pdf"; pad "$out/logo.pdf"
# Permanent XSS regression case — must never render inline anywhere.
printf '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script><rect width="10" height="10"/></svg>' > "$out/xss.svg"
# Real PNGs (a valid 64x64 image) for logo + mockup uploads.
python3 - "$out" <<'PY2'
import sys, zlib, struct
w=h=64; raw=b''.join(b'\x00'+bytes([0xb8,0x89,0x2b])*w for _ in range(h))
def chunk(t,d): return struct.pack('>I',len(d))+t+d+struct.pack('>I',zlib.crc32(t+d)&0xffffffff)
png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',w,h,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(raw,9))+chunk(b'IEND',b'')
for n in ('logo.png','mockup.png'): open(sys.argv[1]+'/'+n,'wb').write(png)
PY2
# The headline case: a layered PSD far above any single-POST limit (our Docker caps PHP at 64M).
printf '8BPS\x00\x01\x00\x00\x00\x00\x00\x00\x00\x03' > "$out/big.psd"; dd if=/dev/urandom bs=1M count="$big" status=none >> "$out/big.psd"
# Wrong content for the extension — must be rejected.
printf 'MZ\x90\x00'                                            > "$out/fake.ai";  pad "$out/fake.ai"

ls -la "$out"; echo; file --mime-type "$out"/*
