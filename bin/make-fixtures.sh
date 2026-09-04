#!/usr/bin/env bash
# Test files WITH GENUINE MAGIC BYTES so MIME sniffing is actually exercised.
# A file of zeros named logo.psd tests nothing.
# Usage: bin/make-fixtures.sh [size_mb]   (default 5; use 150+ to test chunking)
set -euo pipefail
cd "$(dirname "$0")/.."
mb="${1:-5}"
out=tests/fixtures; mkdir -p "$out"
pad() { dd if=/dev/urandom bs=1M count="$mb" status=none >> "$1"; }

printf '8BPS\x00\x01\x00\x00\x00\x00\x00\x00\x00\x03'          > "$out/logo.psd"; pad "$out/logo.psd"
printf '%%PDF-1.5\n%%\xe2\xe3\xcf\xd3\n'                       > "$out/logo.ai";  pad "$out/logo.ai"   # .ai sniffs as PDF — the trap
printf '%%!PS-Adobe-3.0 EPSF-3.0\n%%%%BoundingBox: 0 0 100 100\n' > "$out/logo.eps"; pad "$out/logo.eps"
printf 'RIFF\x00\x00\x01\x00CDRAvrsn'                          > "$out/logo.cdr"; pad "$out/logo.cdr"  # RIFF-based (≤X3)
printf '%%PDF-1.4\n'                                           > "$out/logo.pdf"; pad "$out/logo.pdf"
# Permanent XSS regression case — must never render inline anywhere.
printf '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script><rect width="10" height="10"/></svg>' > "$out/xss.svg"
# Wrong content for the extension — must be rejected.
printf 'MZ\x90\x00'                                            > "$out/fake.ai";  pad "$out/fake.ai"

ls -la "$out"; echo; file --mime-type "$out"/*
