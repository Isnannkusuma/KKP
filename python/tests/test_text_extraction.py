import pytest

from services.extraction_service import ExtractionService


@pytest.mark.asyncio
async def test_extracts_utf8_transcript_as_question_answer_pairs(tmp_path):
    file_path = tmp_path / "conversation.txt"
    text = (
        "You: Bagaimana cara mendaftar?\n"
        "Bot: Pendaftaran dibuka secara daring.\nInformasi tersedia di situs resmi.\n"
        "You: Kapan pendaftaran dibuka?\n"
        "Bot: Jadwal resmi akan diumumkan kemudian."
    )
    file_path.write_text(f"\ufeff{text}", encoding="utf-8")

    elements = await ExtractionService().extract(str(file_path))

    assert elements == [
        {
            "type": "qa_pair",
            "text": "Pertanyaan: Bagaimana cara mendaftar?\nJawaban: Pendaftaran dibuka secara daring.\nInformasi tersedia di situs resmi.",
            "needs_ocr": False,
        },
        {
            "type": "qa_pair",
            "text": "Pertanyaan: Kapan pendaftaran dibuka?\nJawaban: Jadwal resmi akan diumumkan kemudian.",
            "needs_ocr": False,
        },
    ]
    assert ExtractionService().split_qa_pair(elements[0]["text"]) == {
        "question": "Bagaimana cara mendaftar?",
        "answer": "Pendaftaran dibuka secara daring.\nInformasi tersedia di situs resmi.",
    }


def test_extracts_completed_pairs_when_transcript_ends_with_unanswered_question():
    text = (
        "You: Pertanyaan pertama\n"
        "Bot: Jawaban pertama\n"
        "You: Pertanyaan kedua belum dijawab"
    )

    assert ExtractionService().extract_qa_pairs(text) == [
        ("Pertanyaan pertama", "Jawaban pertama")
    ]


@pytest.mark.asyncio
async def test_extracts_spreadsheet_question_answer_columns(tmp_path):
    import pandas as pd

    file_path = tmp_path / "faq.xlsx"
    pd.DataFrame([
        {"Pertanyaan": "Bagaimana cara cuti?", "Jawaban": "Ajukan melalui sistem HR."},
        {"Pertanyaan": "Siapa yang menyetujui?", "Jawaban": "Atasan langsung."},
    ]).to_excel(file_path, index=False)

    elements = await ExtractionService().extract(str(file_path))

    assert [ExtractionService().split_qa_pair(item["text"]) for item in elements] == [
        {"question": "Bagaimana cara cuti?", "answer": "Ajukan melalui sistem HR."},
        {"question": "Siapa yang menyetujui?", "answer": "Atasan langsung."},
    ]


@pytest.mark.asyncio
async def test_keeps_unlabelled_text_as_regular_text(tmp_path):
    file_path = tmp_path / "notes.txt"
    text = "Catatan biasa.\nBaris kedua."
    file_path.write_text(text, encoding="utf-8")

    elements = await ExtractionService().extract(str(file_path))

    assert elements == [
        {
            "type": "text",
            "text": text,
            "needs_ocr": False,
        }
    ]
