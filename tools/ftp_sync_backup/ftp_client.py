import ftplib
from pathlib import Path
from datetime import datetime, timezone
from dashboard import SyncStatus, update_dashboard_right_panel

class FTPClient:
    def __init__(self, host, port, user, pwd):
        self.ftp = ftplib.FTP()
        self.ftp.connect(host, port, timeout=30)
        self.ftp.login(user=user, passwd=pwd)
        self.ftp.set_pasv(True)

    def close(self):
        try: self.ftp.quit()
        except: pass

    def list_dir(self, path):
        out=[]
        try:
            self.ftp.cwd(path)
            lines=[]
            self.ftp.retrlines("LIST", lines.append)
            for line in lines:
                parts=line.split(maxsplit=8)
                if len(parts)<9: continue
                mode, name = parts[0], parts[8]
                out.append((name, {"type":"dir" if mode.startswith("d") else "file"}))
        except: pass
        return out

    def is_dir(self,parent,name):
        cur=self.ftp.pwd()
        try:
            self.ftp.cwd(parent+"/"+name)
            self.ftp.cwd(cur)
            return True
        except: return False

    def download_file(self, remotepath: str, localpath: Path, callback=None):
        localpath.parent.mkdir(parents=True, exist_ok=True)
        with localpath.open("wb") as f:
            def cb(data):
                f.write(data)
                if callback: callback(len(data))
            self.ftp.retrbinary(f"RETR {remotepath}", cb)

    def upload_file(self, localpath: Path, remotepath: str, callback=None):
        parts=[p for p in remotepath.split("/") if p]
        accum=""
        for p in parts[:-1]:
            accum+="/"+p
            try: self.ftp.mkd(accum)
            except: pass
        with localpath.open("rb") as f:
            def cb(block):
                if callback: callback(len(block))
            self.ftp.storbinary(f"STOR {remotepath}", f, callback=cb)

    def walk(self, root: str):
        results={}
        def _walk(base, rel=""):
            for name,facts in self.list_dir(base):
                if name in (".",".."): continue
                rtype=facts.get("type","")
                rel_path=f"{rel}/{name}".lstrip("/")
                abs_path=f"{base.rstrip('/')}/{name}"
                if rtype=="dir":
                    results[rel_path]={"type":"dir"}
                    _walk(abs_path, rel_path)
                else:
                    results[rel_path]={"type":"file","size":0,"mtime":None}
        _walk(root)
        return results

    def __enter__(self): return self
    def __exit__(self, exc_type, exc, tb): self.close()

def choose_remote_dir(ftp: FTPClient,status:SyncStatus, start="/"):
    tree_dirs=[]
    def collect(base):
        for name,facts in ftp.list_dir(base):
            if name in (".",".."): continue
            is_dir = facts.get("type")=="dir" or ftp.is_dir(base,name)
            if is_dir:
                rel = base.rstrip("/")+ "/" + name if base!="/" else f"/{name}"
                tree_dirs.append(rel)
                collect(rel)
    collect(start)
    print("Remote dirs:")
    output = [f"[{i}] {d}" for i, d in enumerate([start] + tree_dirs)]
    update_dashboard_right_panel(status, output, "Select remote directory (index or path): ")
    choice=input("Index or path: ").strip()
    if choice.isdigit():
        idx=int(choice)
        all_dirs=[start]+tree_dirs
        return all_dirs[idx] if 0<=idx<len(all_dirs) else start
    return choice or start
