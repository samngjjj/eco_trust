import torch
from transformers import AutoTokenizer, AutoModel

model_id = "valuesimplex-ai-lab/FinBERT2-base"
try:
    print(f"Loading tokenizer for {model_id}...")
    tokenizer = AutoTokenizer.from_pretrained(model_id)
    print("Loading model...")
    model = AutoModel.from_pretrained(model_id)
    print("Success!")
    print(model.config)
except Exception as e:
    print(f"Error: {e}")
