#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ftp_sync_backup_ascii.py
-----------------------
Recursive FTP browser + two-way sync + timestamped backups with hard-link
deduplication. Credentials stored in plain YAML. Includes ASCII dashboard.
"""

import ftplib
import os
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, List, Tuple, Optional
import yaml  # pip install pyyaml
import shutil
import threading
from hashlib import sha1
from shutil import copy2

# ---------------------------
# Constants
# ---------------------------

APP_NAME = "FTP-Sync-Backup"
CONFIG_FILE = Path("C:\\Users\\user_one\\Desktop\\programing\\manbetMima\\ftp_config.yaml")
DEFAULT_SYNC_INTERVAL = 5  # seconds
BACKUPS_ROOT = Path("ftp_backups")

# ---------------------------
# Utilities
# ---------------------------

def human_time(ts: float) -> str:
    return datetime.fromtimestamp(ts).strftime("%Y-%m-%d %H:%M:%S")

def ensure_dir(p: Path) -> None:
    p.mkdir(parents=True, exist_ok=True)

def sha1_of_file(path: Path) -> str:
    h = sha1()
    with path.open("rb") as f:
        while True:
            b = f.read(8192)
            if not b:
                break
            h.update(b)
    return h.hexdigest()

# ---------------------------
# Config
# ---------------------------

def load_config() -> dict:
    print(f"{CONFIG_FILE}")
    if CONFIG_FILE.exists():
        print(f"Loading config from {CONFIG_FILE}")
        with CONFIG_FILE.open("r", encoding="utf-8") as f:
            cfg = yaml.safe_load(f) or {}
        print(f"Config loaded. Host: {cfg.get('host','')}, User: {cfg.get('username','')}, Local root: {cfg.get('local_root','')}")
    else:
        cfg = {}

    if not cfg.get("host") or not cfg.get("username") or not cfg.get("password"):
        print("First-time setup ✨")
        host = input("FTP Host: ").strip()
        port_in = input("FTP Port (Enter for 21): ").strip()
        port = int(port_in) if port_in else 21
        username = input("FTP Username: ").strip()
        pwd = input("FTP Password: ").strip()
        local_root = Path(input("Local mirror path: ").strip()).expanduser().resolve()
        ensure_dir(local_root)
        interval_in = input(f"Sync interval seconds (Enter for {DEFAULT_SYNC_INTERVAL}): ").strip()
        interval = int(interval_in) if interval_in else DEFAULT_SYNC_INTERVAL

        cfg = {
            "host": host,
            "port": port,
            "username": username,
            "password": pwd,
            "sync_interval": interval,
            "local_root": str(local_root),
            "last_remote_dir": "/",
        }
        with CONFIG_FILE.open("w", encoding="utf-8") as f:
            yaml.safe_dump(cfg, f)
        print(f"\nSaved config to {CONFIG_FILE} (password stored in plain text).")
    return cfg

def save_config(cfg: dict) -> None:
    with CONFIG_FILE.open("w", encoding="utf-8") as f:
        yaml.safe_dump(cfg, f)

# ---------------------------
# FTP Helper
# ---------------------------

class FTPClient:
    def __init__(self, host: str, port: int, user: str, pwd: str):
        self.ftp = ftplib.FTP()
        self.ftp.connect(host, port, timeout=30)
        self.ftp.login(user=user, passwd=pwd)
        self.ftp.set_pasv(True)

    def close(self):
        try:
            self.ftp.quit()
        except Exception:
            pass

    def list_dir(self, path: str) -> List[Tuple[str, dict]]:
        out = []
        try:
            self.ftp.cwd(path)
        except Exception as e:
            raise RuntimeError(f"Cannot cwd to '{path}': {e}")

        try:
            feats = []
            self.ftp.retrlines("FEAT", feats.append)
            use_mlsd = any("MLSD" in s.upper() for s in feats)
        except:
            use_mlsd = False

        if use_mlsd:
            for name, facts in self.ftp.mlsd(facts=["type","modify","size"]):
                out.append((name, facts))
        else:
            lines = []
            self.ftp.retrlines("LIST", lines.append)
            for line in lines:
                parts = line.split(maxsplit=8)
                if len(parts) < 9:
                    continue
                mode = parts[0]
                name = parts[8]
                facts = {"type": "dir" if mode.startswith("d") else "file"}
                out.append((name, facts))
        return out

    def is_dir(self, parent: str, name: str) -> bool:
        cur = self.ftp.pwd()
        try:
            self.ftp.cwd(parent + ("" if parent.endswith("/") else "/") + name)
            self.ftp.cwd(cur)
            return True
        except Exception:
            return False

    def pwd(self) -> str:
        return self.ftp.pwd()

    def mdtm(self, remotepath: str) -> Optional[float]:
        try:
            resp = self.ftp.sendcmd(f"MDTM {remotepath}")
            ts = resp[4:].strip()
            dt = datetime.strptime(ts, "%Y%m%d%H%M%S").replace(tzinfo=timezone.utc)
            return dt.timestamp()
        except:
            return None

    def download_file(self, remotepath: str, localpath: Path) -> None:
        ensure_dir(localpath.parent)
        with localpath.open("wb") as f:
            self.ftp.retrbinary(f"RETR {remotepath}", f.write)

    def upload_file(self, localpath: Path, remotepath: str) -> None:
        parts = [p for p in remotepath.split("/") if p]
        accum = ""
        for p in parts[:-1]:
            accum += "/" + p
            try:
                self.ftp.mkd(accum)
            except Exception:
                pass
        with localpath.open("rb") as f:
            self.ftp.storbinary(f"STOR {remotepath}", f)

    def walk(self, root: str) -> dict:
        results = {}
        def _walk(base_remote: str, rel: str):
            listing = self.list_dir(base_remote)
            for name, facts in listing:
                if name in (".", ".."):
                    continue
                rtype = facts.get("type", "")
                rel_path = f"{rel}/{name}".lstrip("/")
                abs_path = f"{base_remote.rstrip('/')}/{name}"
                if rtype == "dir":
                    results[rel_path] = {"type":"dir"}
                    _walk(abs_path, rel_path)
                else:
                    mtime = None
                    if "modify" in facts:
                        try:
                            mtime = datetime.strptime(facts["modify"], "%Y%m%d%H%M%S").timestamp()
                        except:
                            pass
                    if mtime is None:
                        mtime = self.mdtm(abs_path)
                    size = int(facts.get("size", 0)) if "size" in facts else None
                    results[rel_path] = {"type":"file", "size": size, "mtime": mtime}
        _walk(root, "")
        return results

    def __enter__(self):
        return self
    def __exit__(self, exc_type, exc, tb):
        self.close()

# ---------------------------
# Remote directory selector
# ---------------------------

def choose_remote_dir(ftp: FTPClient, start: str = "/") -> str:
    print("\nBuilding remote directory tree (this may take a moment)...")
    tree_dirs = []

    def collect_dirs(base: str):
        try:
            for name, facts in ftp.list_dir(base):
                if name in (".", ".."):
                    continue
                is_dir = facts.get("type") == "dir" or ftp.is_dir(base, name)
                if is_dir:
                    rel = (base.rstrip("/") + "/" + name) if base != "/" else f"/{name}"
                    tree_dirs.append(rel)
                    collect_dirs(rel)
        except Exception:
            pass

    collect_dirs(start)

    if not tree_dirs:
        print(f"No subdirectories found under {start}. Using it as the selection.")
        return start

    print("\nRemote directories:")
    for idx, d in enumerate([start] + tree_dirs):
        print(f"[{idx}] {d}")

    choice = input("\nEnter index or full remote path: ").strip()
    if choice.isdigit():
        i = int(choice)
        all_dirs = [start] + tree_dirs
        if 0 <= i < len(all_dirs):
            return all_dirs[i]
        print("Invalid index, using start path.")
        return start
    else:
        return choice if choice else start

# ---------------------------
# Local gather
# ---------------------------

def gather_local(local_root: Path) -> Dict[str, Dict]:
    result = {}
    for root, dirs, files in os.walk(local_root):
        rroot = Path(root)
        for d in dirs:
            rel = str((rroot / d).relative_to(local_root)).replace("\\", "/")
            result[rel] = {"type":"dir"}
        for f in files:
            p = rroot / f
            st = p.stat()
            rel = str(p.relative_to(local_root)).replace("\\", "/")
            result[rel] = {"type":"file", "size": st.st_size, "mtime": st.st_mtime}
    return result

# ---------------------------
# Sync status & dashboard
# ---------------------------

class SyncStatus:
    def __init__(self):
        self.total_files = 0
        self.completed_files = 0
        self.current_file = ""
        self.direction = ""  # upload/download
        self.last_sync = None
        self.next_sync_in = 0
        self.lock = threading.Lock()

    def start_file(self, path, direction):
        with self.lock:
            self.current_file = path
            self.direction = direction

    def file_done(self):
        with self.lock:
            self.completed_files += 1
            self.current_file = ""
            self.direction = ""

    def set_total(self, total):
        with self.lock:
            self.total_files = total

    def set_next_sync(self, seconds):
        with self.lock:
            self.next_sync_in = seconds

    def set_last_sync(self):
        with self.lock:
            self.last_sync = time.time()

def draw_dashboard(status: SyncStatus):
    import os
    while True:
        # Clear screen (cross-platform)
        os.system('cls' if os.name == 'nt' else 'clear')

        cols, rows = shutil.get_terminal_size(fallback=(80, 24))
        left_width = max(40, cols // 2)
        with status.lock:
            last = time.strftime("%H:%M:%S", time.localtime(status.last_sync)) if status.last_sync else "---"
            next_sync = f"{status.next_sync_in}s" if status.next_sync_in else "---"
            current = status.current_file or "Idle"
            direction = status.direction or "---"
            percent = (status.completed_files / status.total_files * 100) if status.total_files else 0

        lines = [
            "┌" + "─"*(left_width-2) + "┐",
            f"│ Sync Dashboard{' '*(left_width-18)}│",
            "├" + "─"*(left_width-2) + "┤",
            f"│ Last Sync : {last}{' '*(left_width-20)}│",
            f"│ Next Sync : {next_sync}{' '*(left_width-20)}│",
            f"│ Files     : {status.completed_files}/{status.total_files}{' '*(left_width-20)}│",
            f"│ Current   : {current[:left_width-14]:<{left_width-14}}│",
            f"│ Direction : {direction:<{left_width-12}}│",
            f"│ Progress  : {percent:6.2f}%{' '*(left_width-18)}│",
            "└" + "─"*(left_width-2) + "┘"
        ]

        for l in lines:
            print(l)

        # Keep logs on the right or below dashboard
        # Just leave a separator
        print("\n" + "─"*cols + "\n")

        time.sleep(0.5)

# ---------------------------
# Two-way sync
# ---------------------------

def sync_two_way(ftp: FTPClient, remote_root: str, local_root: Path, status: SyncStatus) -> None:
    ensure_dir(local_root)
    remote_map = ftp.walk(remote_root)
    local_map = gather_local(local_root)
    all_paths = sorted(set(remote_map.keys()) | set(local_map.keys()))
    status.set_total(len(all_paths))
    for rel in all_paths:
        rmeta = remote_map.get(rel)
        lmeta = local_map.get(rel)
        remote_abs = f"{remote_root.rstrip('/')}/{rel}" if rel else remote_root
        local_abs = local_root / rel

        # Directories
        if (rmeta and rmeta.get("type")=="dir") or (lmeta and lmeta.get("type")=="dir"):
            ensure_dir(local_abs)
            continue

        # Remote only
        if rmeta and not lmeta:
            status.start_file(rel, "download")
            ftp.download_file(remote_abs, local_abs)
            status.file_done()
        # Local only
        elif lmeta and not rmeta:
            status.start_file(rel, "upload")
            ftp.upload_file(local_abs, remote_abs)
            status.file_done()
        # Both exist: compare
        elif rmeta and lmeta:
            rmt = rmeta.get("mtime")
            lmt = lmeta.get("mtime")
            if rmt and lmt:
                if abs(rmt - lmt) < 1.0:
                    status.file_done()
                    continue
                if rmt > lmt:
                    status.start_file(rel, "download")
                    ftp.download_file(remote_abs, local_abs)
                    status.file_done()
                else:
                    status.start_file(rel, "upload")
                    ftp.upload_file(local_abs, remote_abs)
                    status.file_done()
            else:
                status.start_file(rel, "download")
                ftp.download_file(remote_abs, local_abs)
                status.file_done()
    status.set_last_sync()

# ---------------------------
# Backups
# ---------------------------

def latest_snapshot_dir(root: Path) -> Optional[Path]:
    if not root.exists():
        return None
    snaps = [p for p in root.iterdir() if p.is_dir() and p.name.startswith("backup_")]
    if not snaps:
        return None
    return sorted(snaps)[-1]

def hardlink_or_copy(src: Path, dst: Path) -> None:
    try:
        os.link(src, dst)
    except Exception:
        copy2(src, dst)

def snapshot(local_root: Path, backups_root: Path = BACKUPS_ROOT) -> Path:
    ensure_dir(backups_root)
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    target = backups_root / f"backup_{ts}"
    ensure_dir(target)
    prev = latest_snapshot_dir(backups_root)

    for root, dirs, files in os.walk(local_root):
        rel_root = Path(root).relative_to(local_root)
        dst_root = target / rel_root
        ensure_dir(dst_root)
        for d in dirs:
            ensure_dir(dst_root / d)
        for f in files:
            src_file = Path(root) / f
            dst_file = dst_root / f
            if prev:
                prev_file = prev / rel_root / f
                if prev_file.exists() and src_file.stat().st_size == prev_file.stat().st_size and int(src_file.stat().st_mtime) == int(prev_file.stat().st_mtime):
                    hardlink_or_copy(prev_file, dst_file)
                    continue
            hardlink_or_copy(src_file, dst_file)
    print(f"Backup complete: {target}")
    return target

def list_backups(backups_root: Path = BACKUPS_ROOT) -> List[Path]:
    if not backups_root.exists():
        return []
    snaps = [p for p in backups_root.iterdir() if p.is_dir() and p.name.startswith("backup_")]
    return sorted(snaps)

def restore_backup(backup_dir: Path, local_root: Path) -> None:
    backup_files = set()
    for root, _, files in os.walk(backup_dir):
        rel_root = Path(root).relative_to(backup_dir)
        for f in files:
            backup_files.add(str(rel_root / f))
    for root, _, files in os.walk(local_root):
        rel_root = Path(root).relative_to(local_root)
        for f in files:
            rel = str(rel_root / f)
            if rel not in backup_files:
                try: os.remove(Path(root)/f)
                except: pass
    for root, dirs, files in os.walk(backup_dir):
        rel_root = Path(root).relative_to(backup_dir)
        dst_root = local_root / rel_root
        ensure_dir(dst_root)
        for d in dirs:
            ensure_dir(dst_root / d)
        for f in files:
            src = Path(root)/f
            dst = dst_root/f
            if not dst.exists() or sha1_of_file(src) != sha1_of_file(dst):
                if dst.exists(): 
                    try: os.remove(dst)
                    except: pass
                hardlink_or_copy(src, dst)
    print("Restore complete.")

# ---------------------------
# CLI Menu
# ---------------------------

def main_menu():
    print(
        "\n=== FTP Sync & Backup ===\n"
        "1) Select remote directory\n"
        "2) Set/change local mirror directory\n"
        "3) One-time sync now\n"
        "4) Watch mode (sync every N seconds)\n"
        "5) Make backup snapshot\n"
        "6) List backups\n"
        "7) Restore a backup\n"
        "8) Exit\n"
    )

def main():
    cfg = load_config()
    host, port, username, password = cfg["host"], int(cfg.get("port",21)), cfg["username"], cfg["password"]
    sync_interval = int(cfg.get("sync_interval", DEFAULT_SYNC_INTERVAL))
    local_root = Path(cfg.get("local_root", "ftp_mirror")).expanduser().resolve()
    ensure_dir(local_root)
    remote_root = cfg.get("last_remote_dir", "/")

    # Launch dashboard
    status = SyncStatus()
    t = threading.Thread(target=draw_dashboard, args=(status,), daemon=True)
    t.start()

    while True:
        try:
            main_menu()
            choice = input(f"Select an option (interval={sync_interval}s): ").strip()
            if choice=="1":
                with FTPClient(host,port,username,password) as ftp:
                    remote_root = choose_remote_dir(ftp, start=remote_root or "/")
                    cfg["last_remote_dir"]=remote_root
                    save_config(cfg)
                    print(f"Selected remote: {remote_root}")
            elif choice=="2":
                p = Path(input("Local mirror path: ").strip()).expanduser().resolve()
                ensure_dir(p)
                local_root = p
                cfg["local_root"]=str(local_root)
                save_config(cfg)
            elif choice=="3":
                with FTPClient(host,port,username,password) as ftp:
                    sync_two_way(ftp, remote_root, local_root, status)
            elif choice=="4":
                interval_in = input(f"Sync interval seconds (Enter for {sync_interval}): ").strip()
                if interval_in: 
                    sync_interval = max(1,int(interval_in))
                    cfg["sync_interval"]=sync_interval
                    save_config(cfg)
                print("Entering watch mode. Ctrl+C to stop.")
                while True:
                    status.set_next_sync(sync_interval)
                    with FTPClient(host,port,username,password) as ftp:
                        sync_two_way(ftp, remote_root, local_root, status)
                    time.sleep(sync_interval)
            elif choice=="5":
                snapshot(local_root, BACKUPS_ROOT)
            elif choice=="6":
                snaps=list_backups(BACKUPS_ROOT)
                if not snaps: print("No backups yet.")
                else:
                    for i,s in enumerate(snaps):
                        print(f"[{i}] {s.name}")
            elif choice=="7":
                snaps=list_backups(BACKUPS_ROOT)
                if not snaps: print("No backups to restore.")
                else:
                    for i,s in enumerate(snaps):
                        print(f"[{i}] {s.name}")
                    sel = input("Index: ").strip()
                    if sel.isdigit() and 0<=int(sel)<len(snaps):
                        restore_backup(snaps[int(sel)], local_root)
            elif choice=="8" or choice.lower() in ("q","quit","exit"):
                print("Bye!"); break
            else:
                print("Invalid option.")
        except KeyboardInterrupt:
            print("\nStopped by user.")
        except ftplib.error_perm as e:
            print(f"FTP permission error: {e}")
        except Exception as e:
            print(f"Error: {e}")

if __name__=="__main__":
    main()
