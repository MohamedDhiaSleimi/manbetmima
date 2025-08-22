from pathlib import Path
import shutil
import os

LOGFILE = Path("logs/ftp_sync.log")

def ensure_dir(p: Path):
    p.mkdir(parents=True,exist_ok=True)

def hardlink_or_copy(src: Path, dst: Path):
    try:
        os.link(src,dst)
    except:
        shutil.copy2(src,dst)

def log_message(msg:str, logfile: Path=LOGFILE):
    Path(logfile).parent.mkdir(exist_ok=True)
    with open(logfile,"a",encoding="utf-8") as f:
        f.write(msg+"\n")
