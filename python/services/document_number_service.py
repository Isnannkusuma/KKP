import re
from typing import List, Dict

def extract_regulation_numbers(text: str) -> Dict[str, List[str]]:
    patterns = {
        "UU": r"Undang-Undang\s+(?:Nomor\s+)?(\d+\s+Tahun\s+\d+)",
        "PP": r"Peraturan\s+Pemerintah\s+(?:Nomor\s+)?(\d+\s+Tahun\s+\d+)",
        "Permen": r"Peraturan\s+Menteri\s+[^\s]+\s+(?:Nomor\s+)?(\d+\s+Tahun\s+\d+)",
        "SK": r"(?:Surat\s+Keputusan|SK)\s+(?:Nomor\s+)?([A-Z0-9/\.\-]+)"
    }
    
    results = {}
    for doc_type, pattern in patterns.items():
        matches = re.findall(pattern, text, re.IGNORECASE)
        if matches:
            results[doc_type] = list(set(matches))
            
    return results