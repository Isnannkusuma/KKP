from pathlib import Path
from unittest.mock import patch

from services.embedding_service import resolve_model_path


def test_resolve_model_path_downloads_when_missing(tmp_path):
    model_dir = tmp_path / "models" / "multilingual-e5-base"

    with patch("services.embedding_service.snapshot_download", return_value=str(model_dir)) as mock_download:
        resolved = resolve_model_path(model_dir)

    assert resolved == str(model_dir)
    mock_download.assert_called_once()
    assert "local_dir" in mock_download.call_args.kwargs
    assert mock_download.call_args.kwargs["local_dir"] == model_dir
