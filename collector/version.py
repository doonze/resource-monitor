"""
Resource Monitor — Application Version Constants

Both APP_VERSION and REQUIRED_SCHEMA_VERSION are read from the repo-root
version.json file — single source of truth shared by both the PHP web
layer and the Python collector. To release a new version or add a migration,
bump version.json only.
"""

import json
from pathlib import Path

_version_file = Path(__file__).parent.parent / "version.json"
_version_data: dict = json.loads(_version_file.read_text(encoding="utf-8")) if _version_file.exists() else {}

APP_VERSION: str          = _version_data.get("app_version", "unknown")
REQUIRED_SCHEMA_VERSION: int = int(_version_data.get("schema_version", 0))
