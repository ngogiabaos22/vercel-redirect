"""Hàng đợi công việc nền đơn giản (render, tạo giọng, tải mô hình…)."""
from __future__ import annotations

import threading
import time
import traceback
import uuid

_jobs: dict[str, "Job"] = {}
_heavy = threading.Semaphore(1)  # chỉ chạy 1 việc nặng một lúc để máy không bị treo


class Job:
    def __init__(self, kind: str, project: str = ""):
        self.id = uuid.uuid4().hex[:10]
        self.kind = kind
        self.project = project
        self.state = "queued"
        self.progress = 0.0
        self.message = "Đang chờ…"
        self.log: list[str] = []
        self.result = None
        self.error = ""
        self.created = time.time()
        self.cancelled = False

    def update(self, progress: float | None = None, message: str | None = None):
        if progress is not None:
            self.progress = max(0.0, min(1.0, progress))
        if message:
            self.message = message
            self.log.append(time.strftime("%H:%M:%S ") + message)
            self.log = self.log[-200:]

    def to_dict(self):
        return {"id": self.id, "kind": self.kind, "project": self.project, "state": self.state,
                "progress": round(self.progress, 4), "message": self.message, "log": self.log[-40:],
                "result": self.result, "error": self.error}


def start(kind: str, fn, project: str = "", heavy: bool = True) -> Job:
    job = Job(kind, project)
    _jobs[job.id] = job

    def runner():
        if heavy:
            _heavy.acquire()
        try:
            job.state = "running"
            job.update(0.0, "Bắt đầu")
            job.result = fn(job)
            job.state = "done"
            job.update(1.0, "Hoàn tất")
        except Exception as e:  # noqa: BLE001
            job.state = "error"
            job.error = str(e)
            job.update(None, f"Lỗi: {e}")
            traceback.print_exc()
        finally:
            if heavy:
                _heavy.release()

    threading.Thread(target=runner, daemon=True).start()
    return job


def get(jid: str) -> Job | None:
    return _jobs.get(jid)


def active(project: str = "") -> list[dict]:
    return [j.to_dict() for j in _jobs.values()
            if j.state in ("queued", "running") and (not project or j.project == project)]


def recent(limit: int = 40) -> list[dict]:
    """Các việc gần đây (đang chạy + đã xong), mới nhất trước – dùng cho màn hình hàng loạt."""
    js = sorted(_jobs.values(), key=lambda j: j.created, reverse=True)[:limit]
    return [dict(j.to_dict(), created=j.created, log=j.log[-3:]) for j in js]
