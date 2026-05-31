import torch
from transformers import AutoTokenizer, AutoModelForSequenceClassification

def test_model(model_id):
    print(f"\n--- Testing model: {model_id} ---")
    try:
        tokenizer = AutoTokenizer.from_pretrained(model_id)
        model = AutoModelForSequenceClassification.from_pretrained(model_id)
        model.eval()
        
        text = "公司承諾在2030年前減少30%的碳排放，這是一項非常積極的綠色轉型計畫。"
        inputs = tokenizer(text, return_tensors="pt", truncation=True, max_length=128)
        with torch.no_grad():
            outputs = model(**inputs)
        probs = torch.softmax(outputs.logits, dim=1).tolist()[0]
        
        print("Labels mapping (id2label):", model.config.id2label)
        print("Probabilities:", probs)
        print("Success!")
    except Exception as e:
        print(f"Failed: {e}")

test_model("yiyanghkust/finbert-tone-chinese")
test_model("valuesimplex-ai-lab/FinBERT2-base")
