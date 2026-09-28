from __future__ import annotations

import pathlib
import shutil
import subprocess
import sys
from datetime import datetime, timezone


MARKER = "# BEGIN TAKEAT MARGIN GUARDIAN"
LOCATION = """    # BEGIN TAKEAT MARGIN GUARDIAN
    location = /margin-guardian {
        return 301 /margin-guardian/;
    }

    location ^~ /margin-guardian/ {
        proxy_pass http://127.0.0.1:18100;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    # END TAKEAT MARGIN GUARDIAN

"""


def main() -> int:
    candidates = []
    for directory in (pathlib.Path("/etc/nginx/sites-enabled"), pathlib.Path("/etc/nginx/conf.d")):
        if directory.exists():
            for candidate in directory.iterdir():
                try:
                    if candidate.is_file() and "server_name united.atisolution.com.br" in candidate.read_text():
                        candidates.append(candidate.resolve())
                except (OSError, UnicodeDecodeError):
                    continue
    unique = list(dict.fromkeys(candidates))
    if len(unique) != 1:
        raise SystemExit(f"expected one United nginx file, found {len(unique)}")
    target = unique[0]
    original = target.read_text()
    if MARKER in original:
        print(f"route already present in {target}")
        return 0
    needle = "    location / {"
    if needle not in original:
        raise SystemExit(f"location / block not found in {target}")
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    backup = target.with_name(f"{target.name}.pre-margin-guardian-{stamp}")
    shutil.copy2(target, backup)
    target.write_text(original.replace(needle, LOCATION + needle, 1))
    try:
        subprocess.run(["nginx", "-t"], check=True)
        subprocess.run(["systemctl", "reload", "nginx"], check=True)
    except Exception:
        shutil.copy2(backup, target)
        subprocess.run(["nginx", "-t"], check=False)
        raise
    print(f"route installed in {target}; backup={backup}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
