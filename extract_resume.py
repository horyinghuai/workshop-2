import sys
import json
import re
import PyPDF2
import docx
import spacy
import os
import pickle

# --- PROGRESS FUNCTION ---
def update_progress(pid, percent):
    """Writes progress to a text file"""
    if pid:
        try:
            with open(f"progress_{pid}.txt", "w") as f:
                f.write(str(percent))
        except: pass

try:
    nlp = spacy.load('en_core_web_sm')
except OSError:
    nlp = None

# --- LOAD ML MODEL ---
ml_model = None
ml_vectorizer = None
try:
    # Look for model files in the same directory
    base_dir = os.path.dirname(os.path.abspath(__file__))
    model_path = os.path.join(base_dir, 'gender_model.pkl')
    vec_path = os.path.join(base_dir, 'gender_vectorizer.pkl')
    
    if os.path.exists(model_path) and os.path.exists(vec_path):
        with open(model_path, 'rb') as f:
            ml_model = pickle.load(f)
        with open(vec_path, 'rb') as f:
            ml_vectorizer = pickle.load(f)
except Exception as e:
    # Silent fail, fallback to dictionary
    pass

# Section Keywords
SECTION_MAP = {
    'objective': ('objective', 'summary', 'professional summary', 'profile', 'about me'),
    'education': ('education', 'qualifications', 'academic background', 'education & certifications'),
    'skills': ('skills', 'technical skills', 'proficiencies', 'competencies', 'technologies'),
    'experience': ('experience', 'work experience', 'employment history', 'professional experience', 'work history'),
    'language': ('languages', 'language proficiency'),
    'address': ('address', 'location', 'contact information') 
}

# Fallback Gender Database
GENDER_DB = {
    'male': ['james', 'john', 'robert', 'michael', 'william', 'david', 'richard', 'joseph', 'thomas', 'charles', 'christopher', 'daniel', 'matthew', 'anthony', 'donald', 'mark', 'paul', 'steven', 'andrew', 'kenneth', 'george', 'joshua', 'kevin', 'brian', 'edward', 'ronald', 'timothy', 'jason', 'jeffrey', 'ryan', 'jacob', 'gary', 'nicholas', 'eric', 'stephen', 'jonathan', 'larry', 'justin', 'scott', 'brandon', 'frank', 'benjamin', 'gregory', 'samuel', 'raymond', 'patrick', 'alexander', 'jack', 'dennis', 'jerry', 'tyler', 'aaron', 'jose', 'adam', 'nathan', 'henry', 'douglas', 'zachary', 'peter', 'kyle', 'walter', 'ethan', 'jeremy', 'harold', 'keith', 'christian', 'roger', 'noah', 'gerald', 'carl', 'terry', 'sean', 'austin', 'arthur', 'lawrence', 'jesse', 'dylan', 'bryan', 'joe', 'jordan', 'billy', 'bruce', 'albert', 'willie', 'gabriel', 'logan', 'alan', 'juan', 'wayne', 'roy', 'ralph', 'randy', 'eugene', 'vincent', 'russell', 'louis', 'philip', 'bobby', 'johnny', 'bradley', 'ying', 'huai', 'wei', 'jie', 'jun', 'hong', 'ming', 'seng', 'chong', 'ali', 'abu', 'ahmad', 'mohamad', 'muhammad', 'ismail', 'hassan', 'hussein', 'yusof', 'ibrahim', 'abdullah', 'rahim', 'rahman', 'razak'],
    'female': ['mary', 'patricia', 'jennifer', 'linda', 'elizabeth', 'barbara', 'susan', 'jessica', 'sarah', 'karen', 'nancy', 'lisa', 'betty', 'margaret', 'sandra', 'ashley', 'kimberly', 'emily', 'donna', 'michelle', 'dorothy', 'carol', 'amanda', 'melissa', 'deborah', 'stephanie', 'rebecca', 'laura', 'sharon', 'cynthia', 'kathleen', 'amy', 'shirley', 'angela', 'helen', 'anna', 'brenda', 'pamela', 'nicole', 'samantha', 'katherine', 'emma', 'ruth', 'christine', 'catherine', 'debra', 'rachel', 'carolyn', 'janet', 'virginia', 'maria', 'heather', 'diane', 'julie', 'joyce', 'victoria', 'olivia', 'kelly', 'christina', 'lauren', 'joan', 'evelyn', 'judith', 'megan', 'cheryl', 'andrea', 'hannah', 'martha', 'jacqueline', 'frances', 'gloria', 'ann', 'teresa', 'kathryn', 'sara', 'janice', 'jean', 'alice', 'madison', 'doris', 'julia', 'judy', 'grace', 'denise', 'amber', 'marilyn', 'beverly', 'danielle', 'theresa', 'sophia', 'marie', 'diana', 'brittany', 'natalie', 'isabella', 'charlotte', 'rose', 'alexis', 'kayla', 'mei', 'ling', 'siew', 'yee', 'hui', 'xin', 'jia', 'yi', 'siti', 'nur', 'nor', 'fatimah', 'aisyah', 'aminah', 'mariam', 'zainab', 'farah', 'norshila']
}

def extract_text_from_pdf(file_path):
    text = ""
    try:
        with open(file_path, 'rb') as f:
            reader = PyPDF2.PdfReader(f)
            for page in reader.pages:
                text += page.extract_text() or ""
    except Exception as e:
        return f"Error reading PDF: {e}"
    return text

def extract_text_from_docx(file_path):
    text = ""
    try:
        doc = docx.Document(file_path)
        for para in doc.paragraphs:
            text += para.text + "\n"
    except Exception as e:
        return f"Error reading DOCX: {e}"
    return text

def process_name(name_text):
    if not name_text: return None
    # Fix wide spacing like "N A M E" -> "NAME"
    name_text = re.sub(r'([A-Z])\s+([A-Z])', r'\1\2', name_text)
    # Remove extra spaces
    name_text = re.sub(r'\s+', ' ', name_text).strip()
    return name_text.title()

def extract_name_better(text):
    """Refined name extraction logic to find the whole name."""
    
    # 1. Look for 'Name:' prefix first (Strongest signal)
    name_match = re.search(r"Name\s*[:\-]\s*([A-Za-z\s\.]+)", text, re.IGNORECASE)
    if name_match:
        name = name_match.group(1).strip()
        if len(name.split()) >= 2:
            return process_name(name)

    lines = [l.strip() for l in text.split('\n') if l.strip()]
    possible_names = []

    # 2. Heuristic Scan with NLP
    if nlp:
        for line in lines[:20]: # Check first 20 lines
            if '@' in line: continue
            if re.search(r'\d', line): continue
            if len(line.split()) > 5: continue
            if line.lower() in ['resume', 'curriculum vitae', 'cv', 'bio', 'profile', 'contact']: continue

            doc = nlp(line)
            for ent in doc.ents:
                if ent.label_ == "PERSON":
                    clean_name = ent.text.strip()
                    if len(clean_name.split()) >= 2:
                        if len(clean_name) / len(line) > 0.7:
                             return process_name(clean_name)
                        possible_names.append(clean_name)

    # 3. Regex Fallback
    for line in lines[:10]:
        if '@' in line or re.search(r'\d', line): continue
        if len(line.split()) > 4: continue
        if line.lower() in ['resume', 'curriculum vitae', 'cv', 'bio']: continue
        
        if re.match(r"^[A-Z][a-z]+(\s[A-Z][a-z]+)+$", line):
            return process_name(line)
            
    if possible_names:
        return process_name(possible_names[0])

    return None

def predict_gender(name):
    """Predicts gender using ML model if available, else dictionary heuristic."""
    if not name: return "Unknown"
    
    name_clean = name.strip()
    # Use first name for prediction
    first_name = name_clean.split()[0].lower()
    
    # 1. ML Prediction
    if ml_model and ml_vectorizer:
        try:
            vectorized_name = ml_vectorizer.transform([first_name])
            prediction = ml_model.predict(vectorized_name)
            return prediction[0] # Returns 'Male' or 'Female'
        except Exception:
            pass # Fallback to rule-based if ML fails

    # 2. Dictionary Fallback
    if first_name in GENDER_DB['male']: return "Male"
    if first_name in GENDER_DB['female']: return "Female"
    
    # Check second name
    parts = name_clean.lower().split()
    if len(parts) > 1:
        second_name = parts[1]
        if second_name in GENDER_DB['male']: return "Male"
        if second_name in GENDER_DB['female']: return "Female"
        
    # Cultural Indicators
    lower_full = name_clean.lower()
    if "bin " in lower_full or "mr." in lower_full: return "Male"
    if "binti " in lower_full or "ms." in lower_full or "mrs." in lower_full or "miss" in lower_full: return "Female"

    return "Unknown"

def extract_address_better(text):
    """Refined address extraction."""
    
    # 1. Explicit Label
    addr_match = re.search(r"Address\s*[:\-]\s*(.+)", text, re.IGNORECASE)
    if addr_match:
        return addr_match.group(1).strip()

    lines = [l.strip() for l in text.split('\n') if l.strip()]
    
    # 2. Heuristic Scan
    locations = [
        "Malaysia", "MYS", "MY",
        "Johor", "JHR", "Johor Bahru", "JB",
        "Kedah", "KDH", "Alor Setar",
        "Kelantan", "KTN", "Kota Bharu",
        "Melaka", "Malacca", "MK", 
        "Negeri Sembilan", "NSN", "Seremban",
        "Pahang", "PHG", "Kuantan",
        "Penang", "Pulau Pinang", "PNG", "Georgetown", "Butterworth",
        "Perak", "PRK", "Ipoh", "Chemor", "Taiping",
        "Perlis", "PLS", "Kangar",
        "Sabah", "SBH", "Kota Kinabalu", "KK",
        "Sarawak", "SWK", "Kuching", "Miri",
        "Selangor", "SGR", "Shah Alam", "Petaling Jaya", "PJ", "Subang Jaya", "Klang",
        "Terengganu", "TRG", "Kuala Terengganu",
        "Kuala Lumpur", "KL", "KUL", 
        "Putrajaya", "PJY", 
        "Labuan", "LBN",
        "Singapore", "SGP", "SG",
        "United States", "USA", "US", "New York", "NY", "California", "CA", "Texas", "TX", "Ohio", "OH", "Dublin",
        "United Kingdom", "UK", "London",
        "Australia", "Sydney", "Melbourne",
        "India", "China", "Indonesia", "Thailand", "Vietnam", "Philippines"
    ]
    
    location_regex = r'\b(' + '|'.join(re.escape(loc) for loc in locations) + r')\b'
    
    for line in lines[:25]:
        if '@' in line and len(line.split()) < 3: continue 
        
        # Postcode Check
        if re.search(r'\b\d{5}\b', line):
            return line.strip()
        
        # Location Match Check
        if re.search(location_regex, line, re.IGNORECASE):
            if len(line.split()) < 20: 
                return line.strip()

    return None

def extract_details(text, pid=None):
    if nlp is None:
         return {"error": "SpaCy model not found."}
    
    update_progress(pid, 20) 
    
    final_data = {}
    exclusion_ranges = []

    # Entities
    email_match = re.search(r'[\w\.-]+@[\w\.-]+\.\w+', text)
    phone_match = re.search(r'(\(?\+?\d{1,3}\)?[\s\.-]?)?\(?\d{3}\)?[\s\.-]?\d{3}[\s\.-]?\d{4}', text)
    
    if email_match:
        final_data['email'] = email_match.group(0)
    if phone_match:
        final_data['contact_number'] = phone_match.group(0)

    update_progress(pid, 30) 

    final_data['name'] = extract_name_better(text)
    
    # Predict Gender
    final_data['gender'] = predict_gender(final_data['name'])

    final_data['address'] = extract_address_better(text)

    # Sections
    update_progress(pid, 50) 

    lower_text = text.lower()
    found_sections = []

    for section_name, keywords in SECTION_MAP.items():
        for keyword in keywords:
            pattern = r'\b' + re.escape(keyword) + r'[:\n]'
            match = re.search(pattern, lower_text)
            if match:
                found_sections.append((match.start(), section_name, match.end() - match.start()))
                break 
    
    found_sections.sort(key=lambda x: x[0])

    update_progress(pid, 70) 

    raw_sections = {}

    for i, (start, name, length) in enumerate(found_sections):
        content_start = start + length
        if i + 1 < len(found_sections):
            content_end = found_sections[i+1][0]
        else:
            content_end = len(text)
            
        raw_content = text[content_start:content_end].strip(": \n")
        raw_sections[name] = raw_content
        exclusion_ranges.append((start, content_end))

    final_data['objective'] = raw_sections.get('objective')
    final_data['education'] = raw_sections.get('education')
    final_data['skills'] = raw_sections.get('skills')
    final_data['experience'] = raw_sections.get('experience')
    final_data['language'] = raw_sections.get('language')
    
    if raw_sections.get('address'):
        final_data['address'] = raw_sections.get('address')
        
    final_data["full_text"] = text 
    
    # Construct Others
    exclusion_ranges.sort(key=lambda x: x[0])
    current_idx = 0
    clean_others = []
    
    for (exc_start, exc_end) in exclusion_ranges:
        if current_idx < exc_start:
            clean_others.append(text[current_idx:exc_start])
        current_idx = max(current_idx, exc_end)
        
    if current_idx < len(text):
        clean_others.append(text[current_idx:])
        
    others_text = "\n".join(clean_others)

    if final_data.get('email'): others_text = others_text.replace(final_data['email'], "")
    if final_data.get('contact_number'): others_text = others_text.replace(final_data['contact_number'], "")
    if final_data.get('name'): others_text = others_text.replace(final_data['name'], "")
    if final_data.get('address'): others_text = others_text.replace(final_data['address'], "")

    cleaned_lines = []
    for line in others_text.split('\n'):
        if len(line.strip()) > 3: 
            cleaned_lines.append(line.strip())

    final_others = "\n".join(cleaned_lines)
    
    if not final_others or len(final_others.strip()) == 0:
        final_data['others'] = None 
    else:
        final_data['others'] = final_others
    
    all_keys = ["name", "email", "contact_number", "address", "objective", "education", "skills", "experience", "language", "others", "full_text", "gender"]
    for key in all_keys:
        if key not in final_data: final_data[key] = None

    update_progress(pid, 90) 
    return final_data

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No file path."}))
        sys.exit(1)

    file_path = sys.argv[1]
    pid = sys.argv[2] if len(sys.argv) > 2 else None
    
    update_progress(pid, 5) 

    file_ext = file_path.split('.')[-1].lower()
    full_text = ""
    
    if file_ext == 'pdf':
        full_text = extract_text_from_pdf(file_path)
    elif file_ext == 'docx':
        full_text = extract_text_from_docx(file_path)
    else:
        print(json.dumps({"error": "Unsupported file type."}))
        sys.exit(1)

    update_progress(pid, 10) 

    if full_text.startswith("Error"):
        print(json.dumps({"error": full_text}))
        sys.exit(1)

    extracted_data = extract_details(full_text, pid)
    
    update_progress(pid, 100) 
    print(json.dumps(extracted_data, indent=4))