import sys
import json
import re
import PyPDF2
import docx

def extract_text_from_pdf(file_path):
    """Extracts text from a PDF file."""
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
    """Extracts text from a DOCX file."""
    text = ""
    try:
        doc = docx.Document(file_path)
        for para in doc.paragraphs:
            text += para.text + "\n"
    except Exception as e:
        return f"Error reading DOCX: {e}"
    return text

def extract_details(text):
    """Extracts details using regex and section keywords."""
    
    # Simple regex patterns
    email_pattern = r'[\w\.-]+@[\w\.-]+\.\w+'
    phone_pattern = r'(\(?\+?\d{1,3}\)?[\s\.-]?)?\(?\d{3}\)?[\s\.-]?\d{3}[\s\.-]?\d{4}'
    
    # Find first matches
    email = re.search(email_pattern, text)
    phone = re.search(phone_pattern, text)
    
    # --- Simple Section Extraction ---
    # This is naive. It finds a keyword (e.g., "Skills") and grabs the text 
    # until the next keyword.
    
    # Normalize text for section matching
    lower_text = text.lower()
    
    def get_section(start_keyword, end_keywords):
        try:
            start_index = lower_text.find(start_keyword)
            if start_index == -1:
                return None
            
            # Find the end of the section
            end_index = len(text) # Default to end of document
            for end_key in end_keywords:
                found_end_index = lower_text.find(end_key, start_index + len(start_keyword))
                if found_end_index != -1 and found_end_index < end_index:
                    end_index = found_end_index
                    
            # Get the content of the section
            section_content = text[start_index + len(start_keyword):end_index].strip(": \n")
            # Clean up: remove excessive newlines and whitespace
            return re.sub(r'\s{2,}', ' ', section_content).strip()
        except Exception:
            return None

    all_section_keywords = [
        'objective', 'summary', 'profile', 
        'education', 'qualifications', 
        'skills', 'technical skills',
        'experience', 'work experience', 'employment history',
        'achievements', 'awards',
        'languages'
    ]
    
    # Define keywords that END a section
    education_ends = ['skills', 'experience', 'projects', 'achievements', 'languages']
    skills_ends = ['education', 'experience', 'projects', 'achievements', 'languages']
    experience_ends = ['education', 'skills', 'projects', 'achievements', 'languages']
    objective_ends = ['education', 'skills', 'experience', 'projects']
    
    # Extract sections
    education = get_section('education', education_ends)
    skills = get_section('skills', skills_ends)
    experience = get_section('experience', experience_ends)
    if not experience:
        experience = get_section('work experience', experience_ends)
        
    objective = get_section('objective', objective_ends)
    if not objective:
        objective = get_section('summary', objective_ends)

    # --- Simple Name Extraction ---
    # This is a basic guess: assumes the first line might be the name
    # if it doesn't contain an email or "@".
    first_line = text.split('\n', 1)[0].strip()
    name = first_line if '@' not in first_line and len(first_line) < 50 else None
    if not name:
        # Fallback: Find email, split it, capitalize parts
        if email:
            local_part = email.group(0).split('@')[0]
            name = " ".join([part.capitalize() for part in re.split(r'[\._-]', local_part)])

    
    data = {
        "name": name,
        "email": email.group(0) if email else None,
        "contact_number": phone.group(0) if phone else None,
        "objective": objective,
        "education": education,
        "skills": skills,
        "experience": experience,
        "achievements": get_section('achievements', ['education', 'skills', 'experience']),
        "language": get_section('languages', ['education', 'skills', 'experience']),
        "full_text": text # Full text for debugging or storing in 'others'
    }
    
    return data

if __name__ == "__main__":
    # The first argument (sys.argv[0]) is the script name.
    # The second argument (sys.argv[1]) is the file path from PHP.
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No file path provided."}))
        sys.exit(1)

    file_path = sys.argv[1]
    file_ext = file_path.split('.')[-1].lower()

    full_text = ""
    if file_ext == 'pdf':
        full_text = extract_text_from_pdf(file_path)
    elif file_ext == 'docx':
        full_text = extract_text_from_docx(file_path)
    else:
        print(json.dumps({"error": "Unsupported file type."}))
        sys.exit(1)

    if full_text.startswith("Error"):
        print(json.dumps({"error": full_text}))
        sys.exit(1)

    # Extract details and print as JSON
    extracted_data = extract_details(full_text)
    print(json.dumps(extracted_data, indent=4))