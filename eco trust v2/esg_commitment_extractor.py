import pymupdf4llm
import pathlib
import os

# 修正 1：使用 r 前綴避免路徑轉義錯誤
folder_path = str(pathlib.Path(__file__).resolve().parent)

# 確保資料夾路徑存在
if not os.path.exists(folder_path):
    print(f"找不到資料夾：{folder_path}")
else:
    for file in os.listdir(folder_path):
        if file.endswith(".pdf"):
            pdf_path = os.path.join(folder_path, file)
            
            # 修正 2：確保輸出的 md 檔名包含完整資料夾路徑
            md_file_name = file.replace(".pdf", ".md")
            md_output_path = os.path.join(folder_path, md_file_name)
            
            print(f"正在轉換: {file}...")
            
            try:
                # 執行轉換
                md_text = pymupdf4llm.to_markdown(pdf_path)
                
                # 儲存為 .md 檔案
                pathlib.Path(md_output_path).write_bytes(md_text.encode("utf-8"))
            except Exception as e:
                print(f"轉換 {file} 時發生錯誤: {e}")

    print("---")
    print("所有檔案轉換完成！")