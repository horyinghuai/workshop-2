import sys
import json
import re
import PyPDF2
import docx
import os
import requests
import time
import pickle
from dotenv import load_dotenv
from sklearn.feature_extraction.text import CountVectorizer 
from sklearn.naive_bayes import MultinomialNB 

# --- LOAD ENV VARIABLES ---
load_dotenv()

# --- CONFIGURATION ---
GEMINI_API_KEY = os.getenv('GEMINI_API_KEY', '')
GEMINI_BASE_URL = "https://generativelanguage.googleapis.com/v1beta/models/"
VALID_MODELS = ["gemini-2.0-flash", "gemini-1.5-flash", "gemini-1.5-pro"]

# --- LOAD GENDER MODEL ---
GENDER_VECTORIZER = None
GENDER_MODEL = None

try:
    if os.path.exists('gender_vectorizer.pkl') and os.path.exists('gender_model.pkl'):
        with open('gender_vectorizer.pkl', 'rb') as f:
            GENDER_VECTORIZER = pickle.load(f)
        with open('gender_model.pkl', 'rb') as f:
            GENDER_MODEL = pickle.load(f)
except Exception as e:
    pass

# --- UTILS ---
def update_progress(pid, percent):
    if pid:
        try:
            with open(f"progress_{pid}.txt", "w") as f:
                f.write(str(percent))
        except: pass

def extract_text_from_pdf(file_path):
    text = ""
    try:
        with open(file_path, 'rb') as f:
            reader = PyPDF2.PdfReader(f)
            for page in reader.pages:
                text += page.extract_text() or ""
    except Exception as e:
        return ""
    return text

def extract_text_from_docx(file_path):
    text = ""
    try:
        doc = docx.Document(file_path)
        for para in doc.paragraphs:
            text += para.text + "\n"
    except Exception as e:
        return ""
    return text

def predict_gender(name):
    if not name or not GENDER_VECTORIZER or not GENDER_MODEL:
        return ""
    try:
        first_name = name.split()[0].strip().lower()
        if not first_name: return ""
        name_vec = GENDER_VECTORIZER.transform([first_name])
        prediction = GENDER_MODEL.predict(name_vec)
        return prediction[0]
    except Exception:
        return ""

def extract_local_data(text):
    data = {}
    # Email
    email_match = re.search(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', text)
    data['email'] = email_match.group(0) if email_match else None
    # Contact
    phone_match = re.search(r'(\+?\(?\d{1,4}\)?[\s\.-]?)?(\d{2,5}[\s\.-]?\d{2,5}[\s\.-]?\d{2,5})', text)
    if phone_match:
        raw_number = phone_match.group(0)
        digits_only = re.sub(r'\D', '', raw_number)
        data['contact_number'] = digits_only if len(digits_only) >= 8 else None
    else:
        data['contact_number'] = None
    return data

# --- GEMINI API CALL ---
def call_gemini(prompt):
    headers = {'Content-Type': 'application/json'}
    payload = {
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {"response_mime_type": "application/json"},
        "safetySettings": [
            {"category": "HARM_CATEGORY_HARASSMENT", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_HATE_SPEECH", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold": "BLOCK_NONE"}
        ]
    }
    
    import urllib3
    urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

    for model in VALID_MODELS:
        url = f"{GEMINI_BASE_URL}{model}:generateContent?key={GEMINI_API_KEY}"
        try:
            response = requests.post(url, headers=headers, json=payload, timeout=30, verify=False)
            if response.status_code == 200:
                result = response.json()
                if 'candidates' in result and len(result['candidates']) > 0:
                    return result['candidates'][0]['content']['parts'][0]['text'].strip()
        except:
            continue
    return None

# --- MAIN LOGIC ---
def process_resume(file_path, pid=None):
    update_progress(pid, 10)

    # 1. Read File
    file_ext = file_path.split('.')[-1].lower()
    if file_ext == 'pdf':
        full_text = extract_text_from_pdf(file_path)
    elif file_ext == 'docx':
        full_text = extract_text_from_docx(file_path)
    else:
        return {"error": "Unsupported file type"}

    if not full_text:
        return {"error": "Could not extract text from file"}

    update_progress(pid, 30)

    # 2. Local Extraction (Priority)
    local_data = extract_local_data(full_text)

    update_progress(pid, 50)

    # 3. API Extraction (With Translation)
    clean_text = re.sub(r'\s+', ' ', full_text).strip()[:30000] 

    # --- UPDATED PROMPT FOR TRANSLATION ---
    prompt = f"""
    You are an expert Resume Parser. 
    
    CRITICAL INSTRUCTION: If the resume text is in a language other than English (e.g., Malay, Chinese, Tamil), you MUST TRANSLATE all extracted content into professional English.
    
    Resume Text: "{clean_text}"

    Extract the following fields and format as valid JSON:
    1. "name": Extract full name.
    2. "address": Extract city, state, country OR full address (Translate to English).
    3. "education": List as "Course, University, Grade, Year". Newline for multiple. (Translate to English).
    4. "skills": List key skills (technical & soft), comma-separated. (Translate to English).
    5. "experience": Summarize into short paragraphs. Group by role. (Translate to English).
    6. "language": List languages and proficiency. (Translate to English).
    7. "others": Summarize awards/certs. Return null if empty. (Translate to English).
    8. "objective": Short summary. (Translate to English).

    Return strictly valid JSON.
    """

    api_response = call_gemini(prompt)
    
    update_progress(pid, 80)

    final_data = {
        "name": None, "gender": "", "email": None, "contact_number": None, 
        "address": None, "education": None, "skills": None, "experience": None, 
        "language": None, "others": None, "objective": None, "full_text": full_text
    }

    # 4. Merge API Data
    if api_response:
        try:
            json_str = api_response.replace("```json", "").replace("```", "").strip()
            api_data = json.loads(json_str)
            for key in final_data:
                if key in api_data and key != 'gender': 
                    final_data[key] = api_data[key]
        except json.JSONDecodeError:
            final_data['others'] = "Error parsing AI response"

    # 5. Override with Local Data
    if local_data['email']:
        final_data['email'] = local_data['email']
    
    if local_data['contact_number']:
        final_data['contact_number'] = local_data['contact_number']

    # 6. Predict Gender Locally
    if final_data['name']:
        pred = predict_gender(final_data['name'])
        if pred:
            final_data['gender'] = pred
        else:
            final_data['gender'] = "" 
    else:
        final_data['gender'] = ""

    update_progress(pid, 100)
    return final_data

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No file path provided"}))
        sys.exit(1)

    path = sys.argv[1]
    proc_id = sys.argv[2] if len(sys.argv) > 2 else None

    if not GEMINI_API_KEY:
        print(json.dumps({"error": "GEMINI_API_KEY missing in .env"}))
        sys.exit(1)

    result = process_resume(path, proc_id)
    print(json.dumps(result, indent=4))