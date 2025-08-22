from pathlib import Path
import shutil, os, time
from utils import ensure_dir, hardlink_or_copy, log_message

BACKUPS_ROOT=Path("ftp_backups")

def latest_snapshot_dir(root: Path=BACKUPS_ROOT):
    if not root.exists(): return None
    snaps=[p for p in root.iterdir() if p.is_dir() and p.name.startswith("backup_")]
    return sorted(snaps)[-1] if snaps else None

def snapshot(local_root: Path, backups_root: Path=BACKUPS_ROOT):
    ensure_dir(backups_root)
    ts=time.strftime("%Y%m%d_%H%M%S")
    target=backups_root/f"backup_{ts}"
    ensure_dir(target)
    _ =latest_snapshot_dir(backups_root)
    for root, _, files in os.walk(local_root):
        rel_root=Path(root).relative_to(local_root)
        dst_root=target/rel_root
        dst_root.mkdir(parents=True,exist_ok=True)
        for f in files:
            src_file=Path(root)/f
            dst_file=dst_root/f
            hardlink_or_copy(src_file,dst_file)
    log_message(f"Snapshot {target} created")

def list_backups(backups_root: Path =BACKUPS_ROOT ):
    ensure_dir(backups_root)
    return sorted([p for p in backups_root.iterdir() if p.is_dir()])

def restore_backup(snapshot_dir: Path, local_root: Path):
    for root, _, files in os.walk(snapshot_dir):
        rel_root=Path(root).relative_to(snapshot_dir)
        dst_root=local_root/rel_root
        dst_root.mkdir(parents=True,exist_ok=True)
        for f in files:
            shutil.copy2(Path(root)/f,dst_root/f)
    log_message(f"Restored backup {snapshot_dir}")
