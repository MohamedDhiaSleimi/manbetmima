import msvcrt
from pathlib import Path
import yaml

SCRIPT_DIR = Path(__file__).resolve().parent
CONFIG_FILE = SCRIPT_DIR / "config" / "ftp_config.yaml"

DEFAULT_SYNC_INTERVAL = 5

def load_config() -> dict:
    print("Checking config...")
    print(Path.cwd())
    print(CONFIG_FILE)
    print(CONFIG_FILE.exists())
    print("Press any key to continue...")
    msvcrt.getch()
    if CONFIG_FILE.exists():
        print(f"Loading config from {CONFIG_FILE}")
        with CONFIG_FILE.open("r", encoding="utf-8") as f:
            cfg = yaml.safe_load(f) or {}
    else:
        cfg = {}
        cfg["host"] = input("FTP host: ").strip()
        cfg["username"] = input("FTP user: ").strip()
        cfg["password"] = input("FTP password: ").strip()
        cfg["port"] = int(input("FTP port [21]: ").strip() or 21)
        cfg["sync_interval"] = DEFAULT_SYNC_INTERVAL
        cfg["local_root"] = str(Path("ftp_mirror").resolve())
        cfg["last_remote_dir"] = "/"
        save_config(cfg)
    return cfg

def save_config(cfg):
    with CONFIG_FILE.open("w", encoding="utf-8") as f:
        yaml.safe_dump(cfg, f)
