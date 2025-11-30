import pandas as pd
from sklearn.feature_extraction.text import CountVectorizer
from sklearn.naive_bayes import MultinomialNB
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score
import pickle
import os

# --- INSTALLATION INSTRUCTIONS ---
# pip install pandas scikit-learn

def load_and_standardize_data():
    all_data = []

    print("--- Loading Datasets ---")

    # List of your specific files
    files = ["name_arab.csv", "name_british.csv", "name_indian.csv"]

    for file_name in files:
        if os.path.exists(file_name):
            try:
                print(f"Loading {file_name}...")
                # Load CSV
                df = pd.read_csv(file_name)
                
                # Standardize column names
                df.columns = df.columns.str.strip().str.lower()
                
                # Check for required columns and append
                if 'name' in df.columns and 'gender' in df.columns:
                    all_data.append(df[['name', 'gender']])
                else:
                    print(f"Skipping {file_name}: Missing 'name' or 'gender' column.")
            except Exception as e:
                print(f"Error loading {file_name}: {e}")
        else:
            print(f"Warning: {file_name} not found in the current directory.")

    if not all_data:
        print("Error: No datasets loaded.")
        return pd.DataFrame()

    print("--- Merging Data ---")
    full_df = pd.concat(all_data, ignore_index=True)
    
    # Clean Data
    print("--- Cleaning Data ---")
    full_df.dropna(inplace=True)
    full_df['name'] = full_df['name'].astype(str).str.strip().str.lower()
    full_df['gender'] = full_df['gender'].astype(str).str.strip().str.upper()
    
    # Normalize Labels to 'Male' / 'Female'
    gender_map = {
        'M': 'Male', 'MALE': 'Male', 'BOY': 'Male',
        'F': 'Female', 'FEMALE': 'Female', 'GIRL': 'Female'
    }
    full_df['gender'] = full_df['gender'].map(gender_map)
    full_df.dropna(subset=['gender'], inplace=True)
    
    full_df.drop_duplicates(subset=['name'], keep='first', inplace=True)
    
    print(f"Total unique names: {len(full_df)}")
    print(full_df['gender'].value_counts())
    
    return full_df

def train_model(df):
    if df.empty:
        return

    X = df['name']
    y = df['gender']

    # Changed split to 70% Training and 30% Test
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.3, random_state=42)

    print("--- Training Model ---")
    # char_wb analyzer creates character n-grams inside word boundaries
    vectorizer = CountVectorizer(analyzer='char_wb', ngram_range=(2, 4))
    X_train_vec = vectorizer.fit_transform(X_train)
    X_test_vec = vectorizer.transform(X_test)

    clf = MultinomialNB()
    clf.fit(X_train_vec, y_train)

    preds = clf.predict(X_test_vec)
    acc = accuracy_score(y_test, preds)
    print(f"Model Accuracy: {acc * 100:.2f}%")

    # Save to disk
    print("--- Saving Model ---")
    with open('gender_vectorizer.pkl', 'wb') as f:
        pickle.dump(vectorizer, f)
    
    with open('gender_model.pkl', 'wb') as f:
        pickle.dump(clf, f)
        
    print("Done! Files 'gender_vectorizer.pkl' and 'gender_model.pkl' created.")

if __name__ == "__main__":
    data = load_and_standardize_data()
    train_model(data)