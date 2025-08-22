import threading
import shutil
import time
import sys
import os
from abc import ABC, abstractmethod
from typing import List, Dict, Any, Optional
from dataclasses import dataclass, field
from datetime import datetime


@dataclass
class TransferData:
    """Data structure for transfer information"""
    file: str
    direction: str  # 'upload' or 'download'
    percent: float  # Individual file progress (0-100)
    eta: str  # Time remaining for this specific file
    status: str = "active"  # 'active', 'completed', 'failed'
    timestamp: datetime = field(default_factory=datetime.now)


class SyncStatus:
    """Thread-safe status container"""
    def __init__(self):
        self.lock = threading.Lock()
        self.transfers: List[TransferData] = []
        self.completed_files: int = 0
        self.total_files: int = 0
        self.current_file: Optional[str] = None
        self.current_file_progress: float = 0.0
        self.queue_progress: float = 0.0  # Overall queue progress (0-100)
        self.queue_eta: str = "Unknown"  # ETA for entire queue
        self.next_sync_in: float = 0
        self.next_backup_in: float = 0
        self.last_sync_time: str = "Never"
        self.last_backup_time: str = "Never"
        self.right_panel_content: List[str] = ["Loading..."]
        self.input_prompt: str = "Option: "
        self._should_stop: bool = False

    def update_current_transfer(self, filename: str, progress: float, eta: str):
        """Update the currently active transfer"""
        with self.lock:
            self.current_file = filename
            self.current_file_progress = progress
            
            # Update or add the current transfer in the list
            for transfer in reversed(self.transfers):
                if transfer.file == filename and transfer.status == "active":
                    transfer.percent = progress
                    transfer.eta = eta
                    return
            
            # If not found, add as new active transfer
            self.transfers.append(TransferData(
                file=filename,
                direction="unknown",  # Will be set by caller
                percent=progress,
                eta=eta,
                status="active"
            ))
    
    def complete_transfer(self, filename: str, direction: str):
        """Mark a transfer as completed"""
        with self.lock:
            for transfer in reversed(self.transfers):
                if transfer.file == filename:
                    transfer.status = "completed"
                    transfer.percent = 100.0
                    transfer.eta = "Done"
                    transfer.direction = direction
                    return
    
    def update_queue_progress(self, completed: int, total: int, overall_eta: str = "Unknown"):
        """Update overall queue progress"""
        with self.lock:
            self.completed_files = completed
            self.total_files = total
            self.queue_progress = (completed / total * 100) if total > 0 else 0
            self.queue_eta = overall_eta

    def add_transfer(self, transfer: TransferData):
        """Thread-safe method to add transfer data"""
        with self.lock:
            self.transfers.append(transfer)
            # Keep only last 100 transfers to prevent memory issues
            if len(self.transfers) > 100:
                self.transfers = self.transfers[-100:]

    def update_right_panel(self, content_lines: List[str], prompt: str = "Option: "):
        """Update the right panel content"""
        with self.lock:
            self.right_panel_content = content_lines.copy()
            self.input_prompt = prompt

    def stop_dashboard(self):
        """Signal dashboard to stop"""
        with self.lock:
            self._should_stop = True

    def should_stop(self) -> bool:
        """Check if dashboard should stop"""
        with self.lock:
            return self._should_stop


class Panel(ABC):
    """Abstract base class for dashboard panels"""
    
    @abstractmethod
    def render(self, width: int, height: int, status: SyncStatus) -> List[str]:
        """Render the panel content"""
        pass


class TransferHistoryPanel(Panel):
    """Left panel showing transfer history and status"""
    
    def render(self, width: int, height: int, status: SyncStatus) -> List[str]:
        lines = [
            "┌" + "─" * (width - 2) + "┐",
            "│" + "TRANSFER HISTORY".center(width - 2) + "│",
            "├" + "─" * (width - 2) + "┤"
        ]
        
        # Calculate available space for transfers
        reserved_lines = 7  # Header (3) + Status section (4)
        transfer_rows = max(0, height - reserved_lines)
        
        # Get transfers to display
        transfers_to_show = status.transfers[-transfer_rows:] if transfer_rows > 0 else []
        
        if transfers_to_show:
            for transfer in transfers_to_show:
                filename = self._truncate_filename(transfer.file, 20)
                direction = "↑" if transfer.direction.lower().startswith('u') else "↓"
                
                # Format based on status
                if transfer.status == "completed":
                    line = f"{filename:<24} {direction} 100.0% Done "
                elif transfer.status == "failed":
                    line = f"{filename:<24} {direction}  FAIL   ERR "
                else:  # active
                    line = f"{filename:<24} {direction} {transfer.percent:6.1f}% {transfer.eta:<5}"
                
                lines.append("│ " + line.ljust(width - 4) + " │")
        else:
            lines.append("│ " + "No transfer history".center(width - 4) + " │")
        
        # Fill remaining space
        while len(lines) < height - 4:
            lines.append("│" + " " * (width - 2) + "│")
        
        # Add status section
        lines.extend(self._render_status_section(width, status))
        
        return lines
    
    def _truncate_filename(self, filename: str, max_length: int) -> str:
        """Truncate filename if too long"""
        return filename[:max_length] + "..." if len(filename) > max_length else filename
    
    def _render_status_section(self, width: int, status: SyncStatus) -> List[str]:
        """Render the status section at the bottom"""
        lines = ["├" + "─" * (width - 2) + "┤"]
        
        # Current file progress (if any)
        if status.current_file:
            current_line = f"Current: {self._truncate_filename(status.current_file, 25)} ({status.current_file_progress:.1f}%)"
            lines.append("│ " + current_line.ljust(width - 4) + " │")
        
        # Overall queue progress
        progress_line = f"Queue: {status.completed_files}/{status.total_files} files ({status.queue_progress:.1f}%) ETA: {status.queue_eta}"
        lines.append("│ " + progress_line.ljust(width - 4) + " │")
        
        # Sync status
        status_line = f"Last Sync: {status.last_sync_time} | Next: {status.next_sync_in}s"
        lines.append("│ " + status_line.ljust(width - 4) + " │")
        
        # Backup status
        backup_line = f"Last Backup: {status.last_backup_time} | Next: {status.next_backup_in}s"
        lines.append("│ " + backup_line.ljust(width - 4) + " │")
        
        lines.append("└" + "─" * (width - 2) + "┘")
        return lines


class ContentPanel(Panel):
    """Right panel showing dynamic content"""
    
    def render(self, width: int, height: int, status: SyncStatus) -> List[str]:
        content = status.right_panel_content.copy()
        
        # Ensure we don't exceed available height
        if len(content) > height:
            content = content[:height]
        
        # Pad content to fill available space
        while len(content) < height:
            content.append("")
        
        # Truncate lines that are too long
        return [line[:width] if len(line) > width else line for line in content]


class TerminalBuffer:
    """Manages terminal output to reduce flickering"""
    
    def __init__(self):
        self.last_content = []
        self.cursor_hidden = False
    
    def update_display(self, new_content: List[str], prompt: str = ""):
        """Update display only if content has changed"""
        if new_content != self.last_content:
            self._hide_cursor()
            self._clear_and_draw(new_content)
            self.last_content = new_content.copy()
        
        # Position cursor for input (always do this to handle input)
        if prompt:
            self._position_cursor_for_prompt(len(new_content), prompt)
    
    def _hide_cursor(self):
        """Hide cursor to reduce flicker"""
        if not self.cursor_hidden:
            print("\033[?25l", end="", flush=True)  # Hide cursor
            self.cursor_hidden = True
    
    def _show_cursor(self):
        """Show cursor"""
        if self.cursor_hidden:
            print("\033[?25h", end="", flush=True)  # Show cursor
            self.cursor_hidden = False
    
    def _clear_and_draw(self, content: List[str]):
        """Clear screen and draw new content"""
        print("\033[H\033[J", end="")  # Move to top and clear screen
        for line in content:
            print(line)
    
    def _position_cursor_for_prompt(self, content_lines: int, prompt: str):
        """Position cursor for input prompt"""
        print(f"\033[{content_lines + 1};1H\033[K{prompt}", end="", flush=True)
    
    def cleanup(self):
        """Cleanup terminal state"""
        self._show_cursor()
        print("\033[H\033[J", end="", flush=True)  # Clear screen


class Dashboard:
    """Main dashboard controller"""
    
    def __init__(self, status: SyncStatus, refresh_rate: float = 0.5):
        self.status = status
        self.refresh_rate = refresh_rate
        self.left_panel = TransferHistoryPanel()
        self.right_panel = ContentPanel()
        self.buffer = TerminalBuffer()
        self.thread: Optional[threading.Thread] = None
    
    def start(self):
        """Start the dashboard in a separate thread"""
        if self.thread is None or not self.thread.is_alive():
            self.thread = threading.Thread(target=self._run, daemon=True)
            self.thread.start()
    
    def stop(self):
        """Stop the dashboard"""
        self.status.stop_dashboard()
        if self.thread and self.thread.is_alive():
            self.thread.join(timeout=1.0)
        self.buffer.cleanup()
    
    def _run(self):
        """Main dashboard loop"""
        try:
            while not self.status.should_stop():
                self._update_display()
                time.sleep(self.refresh_rate)
        except KeyboardInterrupt:
            pass
        finally:
            self.buffer.cleanup()
    
    def _update_display(self):
        """Update the dashboard display"""
        cols, rows = shutil.get_terminal_size(fallback=(100, 30))
        left_width = max(50, cols // 2)
        right_width = cols - left_width
        
        with self.status.lock:
            # Render panels
            left_content = self.left_panel.render(left_width, rows - 1, self.status)
            right_content = self.right_panel.render(right_width, rows - 1, self.status)
            current_prompt = self.status.input_prompt
        
        # Combine panels side by side
        combined_content = self._combine_panels(left_content, right_content, left_width)
        
        # Update display
        self.buffer.update_display(combined_content, current_prompt)
    
    def _combine_panels(self, left: List[str], right: List[str], left_width: int) -> List[str]:
        """Combine left and right panels side by side"""
        max_lines = max(len(left), len(right))
        combined = []
        
        for i in range(max_lines):
            left_line = left[i].ljust(left_width) if i < len(left) else " " * left_width
            right_line = right[i] if i < len(right) else ""
            combined.append(left_line + right_line)
        
        return combined


# Convenience functions for backward compatibility
def update_dashboard_right_panel(status: SyncStatus, content_lines: List[str], prompt: str = "Option: "):
    """Update the right panel content of the dashboard"""
    status.update_right_panel(content_lines, prompt)


def start_dashboard(status: SyncStatus, refresh_rate: float = 0.5) -> Dashboard:
    """Start the dashboard and return the dashboard instance"""
    dashboard = Dashboard(status, refresh_rate)
    dashboard.start()
    return dashboard


# Example usage
if __name__ == "__main__":
    # Create status object
    status = SyncStatus()
    
    # Start dashboard
    dashboard = start_dashboard(status)
    
    try:
        # Simulate queue setup
        status.update_queue_progress(0, 10, "45s")
        
        # Simulate some transfers
        for i in range(10):
            filename = f"document_{i}.pdf"
            direction = "upload" if i % 2 == 0 else "download"
            
            # Simulate file transfer progress
            for progress in [0, 25, 50, 75, 100]:
                eta = f"{int((100-progress)/25)}s" if progress < 100 else "Done"
                
                # Update current transfer
                status.update_current_transfer(filename, progress, eta)
                
                # Update right panel content
                update_dashboard_right_panel(status, [
                    "Transfer Queue Status:",
                    f"Processing: {filename}",
                    f"File Progress: {progress}%",
                    f"Queue: {i}/{10} files completed",
                    "",
                    "Available commands:",
                    "1. View detailed logs",
                    "2. Pause current transfer", 
                    "3. Cancel queue",
                    "4. Exit"
                ])
                
                time.sleep(0.5)  # Simulate transfer time
            
            # Complete the transfer
            status.complete_transfer(filename, direction)
            status.update_queue_progress(i + 1, 10, f"{(10-i-1)*3}s")
        
        # Final state
        status.current_file = None
        update_dashboard_right_panel(status, [
            "All transfers completed!",
            "",
            "Final status:",
            f"✓ {status.completed_files} files transferred",
            f"✓ Queue completed in {time.strftime('%H:%M:%S')}",
            "",
            "Press Enter to exit..."
        ])
        
        # Keep dashboard running
        input()
        
    except KeyboardInterrupt:
        print("\nShutting down...")
    finally:
        dashboard.stop()