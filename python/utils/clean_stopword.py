INDONESIAN_STOPWORDS = {
        'apa', 'itu', 'ini', 'yang', 'dan', 'di', 'ke', 'dari', 'untuk',
        'adalah', 'dengan', 'pada', 'atau', 'juga', 'akan', 'bisa', 'ada',
        'saya', 'kamu', 'anda', 'kami', 'mereka', 'dia', 'nya', 'sih', 'kah',
        'apakah', 'bagaimana', 'mengapa', 'kenapa', 'siapa', 'kapan', 'dimana',
}

def clean_query_for_keyword_search(query_text: str) -> str:
    words = query_text.lower().split()
    meaningful_words = [w for w in words if w not in INDONESIAN_STOPWORDS]
    # Kalau semua kata ternyata stopword (jarang terjadi), fallback ke query asli
    return ' '.join(meaningful_words) if meaningful_words else query_text