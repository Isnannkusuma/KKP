from utils.clean_stopword import normalize_asn_cpns_query


def test_asn_and_cpns_questions_normalize_to_the_same_search_query():
    assert normalize_asn_cpns_query("kapan seleksi CPNS dimulai?") == (
        normalize_asn_cpns_query("Kapan seleksi ASN dimulai?")
    )
    assert normalize_asn_cpns_query("kapan seleksi CPNS dimulai?") == (
        "kapan seleksi asn cpns dimulai?"
    )


def test_asn_and_cpns_are_normalized_only_as_complete_terms():
    assert normalize_asn_cpns_query("informasi CPNS terkait ASN") == (
        "informasi asn cpns terkait asn cpns"
    )
    assert normalize_asn_cpns_query("pegawai negeri") == "pegawai negeri"


def test_sdmao_and_permen_kp_are_expanded():
    assert normalize_asn_cpns_query("Fungsi Biro SDMAO") == (
        "fungsi biro sumber daya manusia aparatur dan organisasi"
    )
    assert normalize_asn_cpns_query("Permen KP 2 tahun 2025") == (
        "peraturan menteri kelautan dan perikanan 2 tahun 2025"
    )
