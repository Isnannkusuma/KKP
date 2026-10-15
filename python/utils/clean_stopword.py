import re


INDONESIAN_STOPWORDS = {
        'apa', 'itu', 'ini', 'yang', 'dan', 'di', 'ke', 'dari', 'untuk',
        'adalah', 'dengan', 'pada', 'atau', 'juga', 'akan', 'bisa', 'ada',
        'saya', 'kamu', 'anda', 'kami', 'mereka', 'dia', 'nya', 'sih', 'kah',
        'apakah', 'bagaimana', 'mengapa', 'kenapa', 'siapa', 'kapan', 'dimana',
}


def normalize_asn_cpns_query(query_text: str) -> str:
    text = query_text.strip()
    # Perluas akronim Biro SDMAO dan Permen KP agar cocok dengan teks perundang-undangan resmi
    text = re.sub(
        r"\b(?:biro\s+)?sdmao\b",
        "Biro Sumber Daya Manusia Aparatur dan Organisasi",
        text,
        flags=re.IGNORECASE,
    )
    text = re.sub(
        r"\bpermen\s+kp\b",
        "Peraturan Menteri Kelautan dan Perikanan",
        text,
        flags=re.IGNORECASE,
    )
    text = re.sub(
        r"(?<![\w])(?:asn[\s-]+cpns|cpns[\s-]+asn|asn|cpns)(?![\w])",
        "asn cpns",
        text,
        flags=re.IGNORECASE,
    )
    return text.lower()


def clean_query_for_keyword_search(query_text: str) -> str:
    words = query_text.lower().split()
    meaningful_words = [w for w in words if w not in INDONESIAN_STOPWORDS]
    # Kalau semua kata ternyata stopword (jarang terjadi), fallback ke query asli
    return ' '.join(meaningful_words) if meaningful_words else query_text