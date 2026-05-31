#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
PDF Page Indexer — extracts text from each page of a PDF and writes a JSON index.

Usage:
    python pdf_page_indexer.py <path_to_pdf>

Output:
    {pdf_stem}_pages.json in the same directory as the source PDF.
"""

import sys
import json
import os
from pathlib import Path

import pdfplumber


def main():
    if len(sys.argv) < 2:
        print("Usage: python pdf_page_indexer.py <pdf_path>", file=sys.stderr)
        sys.exit(1)

    pdf_path = Path(sys.argv[1])

    if not pdf_path.exists():
        print(f"ERROR: File not found: {pdf_path}", file=sys.stderr)
        sys.exit(1)

    pages = []

    try:
        with pdfplumber.open(str(pdf_path)) as pdf:
            total_pages = len(pdf.pages)
            for i, page in enumerate(pdf.pages):
                text = page.extract_text() or ""
                pages.append({
                    "page": i + 1,
                    "text": text
                })
    except Exception as e:
        print(f"ERROR: Failed to read PDF: {e}", file=sys.stderr)
        sys.exit(1)

    index = {
        "source_pdf": pdf_path.name,
        "total_pages": total_pages,
        "pages": pages
    }

    # Write JSON index alongside the PDF
    json_path = pdf_path.parent / f"{pdf_path.stem}_pages.json"

    try:
        with open(str(json_path), "w", encoding="utf-8") as f:
            json.dump(index, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"ERROR: Failed to write JSON: {e}", file=sys.stderr)
        sys.exit(1)

    # Signal completion to PHP caller
    print(f"PAGE_INDEX_DONE:{json_path}")
    sys.exit(0)


if __name__ == "__main__":
    main()
