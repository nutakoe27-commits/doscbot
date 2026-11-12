"""Preserve widget JSON beyond the compact match/recent-games projection."""

import asyncio
import json
import re
from uuid import uuid4
from urllib.parse import urlsplit, parse_qsl

from .store import now, digest

PRIVATE = re.compile(r"password|passwd|token|secret|authorization|cookie|(?:^|[_-])session|api.?key|signature|email|phone|address|^(key|csrf|sid|jwt|auth|ip|ipv4|ipv6|user_?id|device_?id|client_?id)$", re.I)
PROVIDERS = {"statshub.yunai.tech", "statshub.sportradar.com"}
DATA_HOSTS = PROVIDERS | {"sh.fn.yunai.tech"}


def endpoint(url):
    parsed = urlsplit(url)
    parts = []
    for part in parsed.path.split("/"):
        if not part:
            continue
        if PRIVATE.search(part) or len(part) > 80 or re.fullmatch(r"[a-f0-9]{24,}", part, re.I):
            part = "[redacted]"
        parts.append(part)
    return {"origin": f"{parsed.scheme}://{parsed.hostname or ''}",
            "path": "/" + "/".join(parts),
            "query_keys": sorted({k for k, _ in parse_qsl(parsed.query) if not PRIVATE.search(k)})}


def sanitize(value):
    counters = {"redacted": 0, "truncated": False}
    budget = [100_000]
    def clean(item, depth=0):
        budget[0] -= 1
        if budget[0] < 0 or depth > 32:
            counters["truncated"] = True
            return "[capture limit]"
        if isinstance(item, dict):
            output = {}
            for index, (key, content) in enumerate(item.items()):
                if index >= 5000:
                    counters["truncated"] = True
                    break
                if PRIVATE.search(str(key)):
                    counters["redacted"] += 1
                    continue
                output[key] = clean(content, depth + 1)
            return output
        if isinstance(item, list):
            if len(item) > 10_000:
                counters["truncated"] = True
            return [clean(child, depth + 1) for child in item[:10_000]]
        if isinstance(item, str):
            if re.match(r"https?://", item, re.I):
                counters["redacted"] += 1
                return endpoint(item)
            if "@" in item or re.match(r"Bearer\s|eyJ[A-Za-z0-9_-]+\.", item):
                counters["redacted"] += 1
                return "[redacted]"
        return item
    return clean(value), counters


class WidgetCapture:
    def __init__(self, page, statistics_id, *, max_response=2 * 1024 * 1024,
                 max_total=16 * 1024 * 1024, max_records=200):
        self.page, self.statistics_id = page, str(statistics_id)
        self.capture_id=uuid4().hex
        self.started_at=now()
        self.max_response, self.max_total, self.max_records = max_response, max_total, max_records
        self.records, self.pending = [], set()
        self.seen={}
        self.body_slots=asyncio.Semaphore(8)
        self.summary = {"responses": 0, "captured": 0, "bytes": 0, "redacted": 0,
                        "truncated": 0, "skipped": {}, "http_errors": [],'duplicates':0}
        self.handler = self.on_response

    def start(self):
        self.page.on("response", self.handler)

    def skip(self, reason):
        counts = self.summary["skipped"]
        counts[reason] = counts.get(reason, 0) + 1

    def on_response(self, response):
        try:
            request = response.request
            frame = urlsplit(request.frame.url)
            source = urlsplit(response.url)
            path = rf"/winline/(?:ru|en)/match/{re.escape(self.statistics_id)}(?:/(?:report|statistics))?/?"
            if (frame.scheme != "https" or frame.hostname not in PROVIDERS or not re.fullmatch(path, frame.path)
                    or source.scheme != "https" or source.hostname not in DATA_HOSTS
                    or source.username or source.password or request.resource_type not in ("xhr", "fetch")):
                return
            self.summary["responses"] += 1
            if response.status >= 400 and len(self.summary["http_errors"]) < 10:
                self.summary["http_errors"].append({**endpoint(response.url), "status": response.status})
            if len(self.records) + len(self.pending) >= self.max_records:
                self.skip("record_limit")
                return
            task = asyncio.create_task(self.queued_read(response, frame))
            self.pending.add(task)
            task.add_done_callback(self.pending.discard)
        except Exception:
            self.skip("unavailable_frame")

    async def queued_read(self,response,frame):
        acquired=False
        try:
            async with self.body_slots:
                acquired=True
                await self.read(response,frame)
        except asyncio.CancelledError:
            if not acquired:
                self.skip('queued_read_cancelled')
            raise

    async def read(self, response, frame):
        try:
            content_type = (await response.header_value("content-type") or "").lower()
            if "json" not in content_type:
                self.skip("not_json")
                return
            length = await response.header_value("content-length")
            if length and length.isdecimal() and int(length) > self.max_response:
                self.skip("response_size")
                return
            # Chunked replies may have no Content-Length. The old compact
            # observer drops those; preserve them after checking decoded size.
            body = await asyncio.wait_for(response.body(), timeout=10)
            if len(body) > self.max_response:
                self.skip("response_size")
                return
            data, changes = sanitize(json.loads(body))
            route=endpoint(response.url)
            key=digest({'endpoint':route,'status':response.status,'data':data})
            observed=now()
            frame_url=f'https://{frame.hostname}{frame.path}'
            if key in self.seen:
                previous=self.seen[key]
                previous['last_observed_at']=observed
                previous['provider_frames']=sorted(set(previous.get('provider_frames',[previous['provider_frame']]))|{frame_url})
                self.summary['duplicates']+=1
                return
            if self.summary["bytes"] + len(body) > self.max_total:
                self.skip("total_size")
                return
            self.records.append({"format": "doscbot.widget-json.v1", "statistics_id": self.statistics_id,
                                 "capture_id":self.capture_id,
                                 "binding": "observed_event_link", "coverage": "observed_responses_only",
                                 "observed_at": observed, "provider_frame":frame_url,
                                 "endpoint": route, "http_status": response.status,
                                 "data": data, **changes})
            self.seen[key]=self.records[-1]
            self.summary["bytes"] += len(body)
            self.summary["captured"] += 1
            self.summary["redacted"] += changes["redacted"]
            self.summary["truncated"] += int(changes["truncated"])
        except asyncio.CancelledError:
            self.skip("read_cancelled")
            raise
        except Exception:
            self.skip("read_error")

    async def finish(self):
        self.page.remove_listener("response", self.handler)
        tasks = list(self.pending)
        if tasks:
            done, pending = await asyncio.wait(tasks, timeout=12)
            for task in pending:
                task.cancel()
            await asyncio.gather(*tasks, return_exceptions=True)
        incomplete = self.summary["truncated"] or bool(self.summary["http_errors"]) or any(
            key != "not_json" for key in self.summary["skipped"])
        self.summary["state"] = "partial" if incomplete else "captured" if self.records else "no_json_observed"
        return self.records, self.summary
