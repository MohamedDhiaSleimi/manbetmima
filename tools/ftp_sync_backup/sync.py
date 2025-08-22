import os, time
from pathlib import Path
from dashboard import SyncStatus
from utils import log_message

def gather_local(local_root: Path):
    result={}
    for root, dirs, files in os.walk(local_root):
        rroot = Path(root)
        for d in dirs:
            rel=str((rroot/d).relative_to(local_root)).replace("\\","/")
            result[rel]={"type":"dir"}
        for f in files:
            p=rroot/f
            st=p.stat()
            rel=str(p.relative_to(local_root)).replace("\\","/")
            result[rel]={"type":"file","size":st.st_size,"mtime":st.st_mtime}
    return result

def sync_two_way(ftp, remote_root, local_root: Path, status: SyncStatus):
    remote_map=ftp.walk(remote_root)
    local_map=gather_local(local_root)
    all_paths=set(remote_map.keys())|set(local_map.keys())
    with status.lock:
        status.total_files=len(all_paths)
        status.completed_files=0
    for rel in sorted(all_paths):
        direction="remote→local" if rel in remote_map else "local→remote"
        local_file=local_root/rel
        remote_file=f"{remote_root}/{rel}"
        transfer={"file":rel,"direction":direction,"percent":0.0,"eta":0}
        with status.lock:
            status.transfers.append(transfer)
        # Simulate transfer
        start=time.time()
        time.sleep(0.05)
        end=time.time()
        with status.lock:
            transfer["percent"]=100.0
            transfer["eta"]=0
            status.completed_files+=1
        log_message(f"{direction} {rel} done")
