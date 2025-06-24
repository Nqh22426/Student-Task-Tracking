import sys
import json
import torch
from transformers import AutoTokenizer, AutoModelForSequenceClassification
import numpy as np
import re
import argparse

def clean_text(text):
    """Clean and prepare text for AI detection"""
    if not text or not isinstance(text, str):
        return ""
    
    # Handle encoding issues
    if isinstance(text, bytes):
        try:
            text = text.decode('utf-8')
        except UnicodeDecodeError:
            try:
                text = text.decode('latin-1')
            except UnicodeDecodeError:
                text = text.decode('utf-8', errors='ignore')
    
    # Ensure text is valid UTF-8
    if not isinstance(text, str):
        return ""
    
    # Keep only printable ASCII and common Unicode characters
    cleaned = re.sub(r'[^\w\s\.,!?;:()\-\'"]+', ' ', text)
    
    # Normalize whitespace
    cleaned = ' '.join(cleaned.split())
    
    return cleaned.strip()

def predict_single_text(text, model, tokenizer, device, max_len=512):
    """Predict whether text is AI-generated using the RoBERTa model"""
    encoded = tokenizer(
        text,
        padding='max_length',
        truncation=True,
        max_length=max_len,
        return_tensors='pt'
    )
    input_ids = encoded['input_ids'].to(device)
    attention_mask = encoded['attention_mask'].to(device)

    model.eval()
    with torch.no_grad():
        outputs = model(input_ids=input_ids, attention_mask=attention_mask)
        logits = outputs.logits
        
        # Apply softmax to get probabilities
        probabilities = torch.softmax(logits, dim=1)
        
        # For this model, class 0 = human-written, class 1 = AI-generated
        prob_human = probabilities[0, 0].item()  # Index 0 - Human
        prob_ai = probabilities[0, 1].item()     # Index 1 - AI
        
        return prob_ai

def detect_ai_content(text):
    """
    AI detection using OpenAI's RoBERTa large model - more balanced detection
    
    Args:
        text (str): Input text to analyze
        
    Returns:
        dict: Detection results with calibrated score
    """
    try:
        cleaned_text = clean_text(text)
        if not cleaned_text or len(cleaned_text.strip()) < 5:
            return {
                'ai_probability': 0.0,
                'ai_percentage': 0.0,
                'error': 'Text too short for reliable AI detection',
                'method': 'RoBERTa Large OpenAI Detector',
                'status': 'error'
            }
        
        # Limit text length to avoid tensor size issues
        max_length = 5000  # Conservative limit
        if len(cleaned_text) > max_length:
            cleaned_text = cleaned_text[:max_length]
        
        # Model configuration
        model_name = "openai-community/roberta-large-openai-detector"
        
        # Load model and tokenizer with error handling
        try:
            tokenizer = AutoTokenizer.from_pretrained(model_name)
            model = AutoModelForSequenceClassification.from_pretrained(model_name)
        except Exception as e:
            return {
                'ai_probability': 0.0,
                'ai_percentage': 0.0,
                'error': f'Model loading failed: {str(e)}',
                'method': 'RoBERTa Large OpenAI Detector',
                'status': 'error'
            }
        
        # Set up device
        device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
        model.to(device)
        
        # Get prediction with error handling
        try:
            probability = predict_single_text(cleaned_text, model, tokenizer, device)
        except Exception as e:
            return {
                'ai_probability': 0.0,
                'ai_percentage': 0.0,
                'error': f'Model inference failed: {str(e)}',
                'method': 'RoBERTa Large OpenAI Detector',
                'status': 'error'
            }
        
        return {
            'error': None,
            'ai_probability': probability,
            'ai_percentage': probability * 100,
            'method': 'RoBERTa Large OpenAI Detector',
            'status': 'success',
            'details': {
                'model_name': model_name,
                'architecture': 'RoBERTa large fine-tuned for AI detection',
                'purpose': 'Balanced AI detection with higher accuracy',
                'note': 'Trained on GPT-2 outputs, more accurate for general use',
                'text_length': len(cleaned_text),
                'device_used': str(device)
            }
        }
    
    except Exception as e:
        return {
            'error': f'AI detection failed: {str(e)}',
            'ai_probability': 0.0,
            'ai_percentage': 0.0,
            'method': 'RoBERTa Large OpenAI Detector',
            'status': 'error',
            'details': {
                'error_type': type(e).__name__,
                'error_message': str(e)
            }
        }

def safe_read_file(filepath):
    """Safely read file with multiple encoding attempts"""
    encodings = ['utf-8', 'utf-8-sig', 'latin-1', 'cp1252', 'iso-8859-1']
    
    for encoding in encodings:
        try:
            with open(filepath, 'r', encoding=encoding) as f:
                return f.read().strip()
        except UnicodeDecodeError:
            continue
        except Exception as e:
            break
    
    # If all encodings fail, try binary read and decode with errors='ignore'
    try:
        with open(filepath, 'rb') as f:
            content = f.read()
            return content.decode('utf-8', errors='ignore').strip()
    except Exception as e:
        raise Exception(f"Could not read file {filepath}: {str(e)}")

def main():
    """
    Main function for command-line usage
    """
    parser = argparse.ArgumentParser(description='Detect AI-generated text using RoBERTa large model')
    parser.add_argument('text', nargs='?', help='Text to analyze')
    parser.add_argument('--file', type=str, help='Read text from file')
    parser.add_argument('--json', action='store_true', help='Output in JSON format')
    parser.add_argument('--verbose', action='store_true', help='Include detailed analysis')
    
    args = parser.parse_args()
    
    # Get text input
    if args.file:
        # Read from file with robust encoding handling
        try:
            input_text = safe_read_file(args.file)
        except Exception as e:
            error_result = {
                'error': str(e),
                'ai_probability': 0.0,
                'ai_percentage': 0.0,
                'method': 'RoBERTa Large OpenAI Detector',
                'status': 'error'
            }
            if args.json:
                print(json.dumps(error_result, indent=2, ensure_ascii=False))
            else:
                print(f"Error: {str(e)}", file=sys.stderr)
            sys.exit(1)
    elif args.text:
        input_text = args.text
    else:
        # Read from stdin if no text provided
        try:
            input_text = sys.stdin.read().strip()
        except Exception as e:
            error_result = {
                'error': f'Error reading from stdin: {str(e)}',
                'ai_probability': 0.0,
                'ai_percentage': 0.0,
                'method': 'RoBERTa Large OpenAI Detector',
                'status': 'error'
            }
            if args.json:
                print(json.dumps(error_result, indent=2, ensure_ascii=False))
            else:
                print(f"Error reading from stdin: {str(e)}", file=sys.stderr)
            sys.exit(1)
    
    if not input_text:
        error_result = {
            'error': 'No text provided for analysis',
            'ai_probability': 0.0,
            'ai_percentage': 0.0,
            'method': 'RoBERTa Large OpenAI Detector',
            'status': 'error'
        }
        if args.json:
            print(json.dumps(error_result, indent=2, ensure_ascii=False))
        else:
            print("Error: No text provided for analysis", file=sys.stderr)
        sys.exit(1)
    
    # Perform AI detection
    result = detect_ai_content(input_text)
    
    # Output results
    if args.json:
        print(json.dumps(result, indent=2, ensure_ascii=False))
    else:
        if result['status'] == 'success':
            print(f"AI Detection Results:")
            print(f"AI Probability: {result['ai_percentage']:.1f}%")
            print(f"Method: {result['method']}")
            
            if args.verbose and 'details' in result:
                details = result['details']
                print(f"\nDetailed Analysis:")
                print(f"Model: {details['model_name']}")
                print(f"Architecture: {details['architecture']}")
                print(f"Purpose: {details['purpose']}")
                print(f"Note: {details['note']}")
                print(f"Text Length: {details['text_length']} characters")
                print(f"Device Used: {details['device_used']}")
        else:
            print(f"Error: {result['error']}", file=sys.stderr)
            sys.exit(1)

if __name__ == "__main__":
    main() 