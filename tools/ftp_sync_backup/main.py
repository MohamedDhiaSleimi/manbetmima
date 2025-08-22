#!/usr/bin/env python3
# -*- coding: utf-8 -*-
from pathlib import Path
from config import load_config, save_config
from ftp_client import FTPClient
from sync import sync_two_way
from backup import snapshot, list_backups, restore_backup
from dashboard import SyncStatus, start_dashboard, update_dashboard_right_panel
import time


def show_menu(status):
    """Display the main menu on the dashboard"""
    menu = [
            "1) Select remote directory",
            "2) Set/change local mirror",
            "3) One-time sync",
            "4) Watch mode",
            "5) Backup snapshot",
            "6) List backups",
            "7) Restore backup",
            "8) Exit",
            ]
    update_dashboard_right_panel(status, menu)

def main():
    cfg = load_config()
    local_root = Path(cfg.get("local_root", "ftp_mirror")).expanduser()
    local_root.mkdir(parents=True, exist_ok=True)
    status = SyncStatus()
    start_dashboard(status)
    
    # Initial menu
    show_menu(status)

    while True:
        try:
            # Get user input
            choice = input().strip()
            
            if choice == "1":
                update_dashboard_right_panel(status, ["Selecting remote directory..."], "")
                
                with FTPClient(cfg["host"], cfg.get("port",21), cfg["username"], cfg["password"]) as ftp:
                    from ftp_client import choose_remote_dir
                    remote_root = choose_remote_dir(ftp,status, cfg.get("last_remote_dir","/"))
                    cfg["last_remote_dir"] = remote_root
                    save_config(cfg)
                    status.last_sync_time = time.strftime("%H:%M:%S")
                    
                    update_dashboard_right_panel(status, 
                        [f"Selected remote: {remote_root}", ""] + 
                        ["Press any key to continue..."], "")
                    input()  # Wait for user to press enter
                    show_menu(status)
            
            elif choice == "2":
                update_dashboard_right_panel(status, ["Enter local mirror path:"], "Local path: ")
                
                path_input = input().strip()
                p = Path(path_input).expanduser().resolve()
                p.mkdir(parents=True, exist_ok=True)
                local_root = p
                cfg["local_root"] = str(local_root)
                save_config(cfg)
                
                update_dashboard_right_panel(status, 
                    [f"Local mirror set to: {local_root}", ""] + 
                    ["Press any key to continue..."], "")
                input()  # Wait for user to press enter
                show_menu(status)
            
            elif choice == "3":
                update_dashboard_right_panel(status, ["Starting one-time sync..."], "")
                
                with FTPClient(cfg["host"], cfg.get("port",21), cfg["username"], cfg["password"]) as ftp:
                    sync_two_way(ftp, cfg.get("last_remote_dir","/"), local_root, status)
                    status.last_sync_time = time.strftime("%H:%M:%S")
                
                update_dashboard_right_panel(status, 
                    ["Sync completed!", ""] + 
                    ["Press any key to continue..."], "")
                input()  # Wait for user to press enter
                show_menu(status)
            
            elif choice == "4":
                update_dashboard_right_panel(status, ["Enter sync interval in seconds:"], "Interval: ")
                
                interval_input = input().strip()
                interval = int(interval_input or 5)
                
                update_dashboard_right_panel(status, 
                    [f"Starting watch mode with {interval}s interval...", "Press Ctrl+C to stop"], "")
                
                try:
                    while True:
                        with FTPClient(cfg["host"], cfg.get("port",21), cfg["username"], cfg["password"]) as ftp:
                            sync_two_way(ftp, cfg.get("last_remote_dir","/"), local_root, status)
                            status.last_sync_time = time.strftime("%H:%M:%S")
                        time.sleep(interval)
                except KeyboardInterrupt:
                    pass
                
                update_dashboard_right_panel(status, 
                    ["Watch mode stopped", ""] + 
                    ["Press any key to continue..."], "")
                input()  # Wait for user to press enter
                show_menu(status)
            
            elif choice == "5":
                update_dashboard_right_panel(status, ["Creating backup snapshot..."], "")
                
                snapshot(local_root)
                status.last_backup_time = time.strftime("%H:%M:%S")
                
                update_dashboard_right_panel(status, 
                    ["Backup snapshot created!", ""] + 
                    ["Press any key to continue..."], "")
                input()  # Wait for user to press enter
                show_menu(status)
            
            elif choice == "6":
                snaps = list_backups()
                if snaps:
                    backup_list = [f"[{i}] {s.name}" for i, s in enumerate(snaps)]
                    update_dashboard_right_panel(status, 
                        ["Available backups:"] + backup_list + [""] + 
                        ["Press any key to continue..."], "")
                    input()  # Wait for user to press enter
                    show_menu(status)
                else:
                    update_dashboard_right_panel(status, 
                        ["No backups available", ""] + 
                        ["Press any key to continue..."], "")
                    input()  # Wait for user to press enter
                    show_menu(status)
            
            elif choice == "7":
                snaps = list_backups()
                if snaps:
                    backup_list = [f"[{i}] {s.name}" for i, s in enumerate(snaps)]
                    update_dashboard_right_panel(status, 
                        ["Select backup to restore:"] + backup_list, "Index: ")
                    
                    sel = input().strip()
                    if sel.isdigit() and int(sel) < len(snaps):
                        restore_backup(snaps[int(sel)], local_root)
                        update_dashboard_right_panel(status, 
                            [f"Restored backup: {snaps[int(sel)].name}", ""] + 
                            ["Press any key to continue..."], "")
                        input()  # Wait for user to press enter
                        show_menu(status)
                    else:
                        update_dashboard_right_panel(status, 
                            ["Invalid selection", ""] + 
                            ["Press any key to continue..."], "")
                        input()  # Wait for user to press enter
                        show_menu(status)
                else:
                    update_dashboard_right_panel(status, 
                        ["No backups available", ""] + 
                        ["Press any key to continue..."], "")
                    input()  # Wait for user to press enter
                    show_menu(status)
            
            elif choice == "8":
                break
                
        except KeyboardInterrupt:
            update_dashboard_right_panel(status, 
                ["Operation cancelled", ""] + 
                ["Press any key to continue..."], "")
            input()  # Wait for user to press enter
            show_menu(status)
        except Exception as e:
            update_dashboard_right_panel(status, 
                [f"Error: {e}", ""] + 
                ["Press any key to continue..."], "")
            input()  # Wait for user to press enter
            show_menu(status)

if __name__=="__main__":
    main()