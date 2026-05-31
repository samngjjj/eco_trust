import os
import shutil
from transformers import AutoTokenizer, AutoModelForSequenceClassification

model_id = "yiyanghkust/finbert-tone-chinese"
output_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "finbert_model"))

print(f"Target directory: {output_dir}")

# Ensure clean download by clearing output dir if it exists
if os.path.exists(output_dir):
    print("Clearing existing model files...")
    # Clean files but keep the directory
    for item in os.listdir(output_dir):
        item_path = os.path.join(output_dir, item)
        if os.path.isfile(item_path):
            os.unlink(item_path)
        elif os.path.isdir(item_path):
            shutil.rmtree(item_path)
else:
    os.makedirs(output_dir, exist_ok=True)

try:
    print(f"Downloading tokenizer for {model_id}...")
    tokenizer = AutoTokenizer.from_pretrained(model_id)
    tokenizer.save_pretrained(output_dir)
    print("Tokenizer downloaded and saved.")
    
    print(f"Downloading sequence classification model for {model_id}...")
    model = AutoModelForSequenceClassification.from_pretrained(model_id)
    model.save_pretrained(output_dir)
    print("Model downloaded and saved.")
    
    print("Download completed successfully!")
except Exception as e:
    print(f"Error during downloading/saving model: {e}")
