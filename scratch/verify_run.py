import subprocess
import json
import os
import sys

# Reconfigure stdout to use utf-8 to prevent encoding errors on Windows
if sys.stdout.encoding != 'utf-8':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except:
        pass

print("--- 1. Testing news_nlp.py analyze mode ---")
cmd_news = ["python", "news_nlp.py", "analyze", "台積電今天宣布與綠電廠商達成大額太陽能採購承諾，為淨零碳排跨出重要一步。"]
res_news = subprocess.run(cmd_news, capture_output=True, text=True, encoding='utf-8')
print("Exit code:", res_news.returncode)
print("Output stdout:", res_news.stdout)

print("\n--- 2. Testing finbert.py execution ---")
pdf_path = os.path.abspath(os.path.join("uploads", "1103_嘉泥_2024.pdf"))
cmd_finbert = ["python", "finbert.py", pdf_path]
res_finbert = subprocess.run(cmd_finbert, capture_output=True, text=True, encoding='utf-8')
print("Exit code:", res_finbert.returncode)

stdout_clean = res_finbert.stdout.encode('utf-8', errors='replace').decode('utf-8')
stdout_lines = stdout_clean.split('\n')
print(f"Stdout total lines: {len(stdout_lines)}")
print("Stdout preview (first 15 lines):")
print('\n'.join(stdout_lines[:15]))
print("Stdout preview (last 15 lines):")
print('\n'.join(stdout_lines[-15:]))

if res_finbert.stderr:
    print("Stderr output:")
    print(res_finbert.stderr.encode('utf-8', errors='replace').decode('utf-8'))
