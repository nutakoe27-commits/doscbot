/* Paste into the StatsHub FRAME console, not Winline's top frame.
 * Observes existing requests only; never sends data to another service.
 * Target supplied by the user: Winline 17139995 -> statistics 75512542.
 */
(() => {
  "use strict";
  const TARGET = "75512542";
  const PROVIDERS = new Set(["statshub.yunai.tech", "statshub.sportradar.com"]);
  const DATA_HOSTS = new Set([...PROVIDERS, "sh.fn.yunai.tech"]);
  const match = location.pathname.match(/^\/winline\/(?:ru|en)\/match\/(\d+)(?:\/(?:report|statistics))?\/?$/);
  if (!PROVIDERS.has(location.hostname) || !match || match[1] !== TARGET) {
    throw new Error("Выбери в Console контекст iframe StatsHub для матча 75512542. В top этот код не запускается.");
  }
  if (window.doscbotProbe) {
    console.info("Диагностика уже установлена", window.doscbotProbe.status());
    return;
  }
  const PRIVATE = /password|passwd|token|secret|authorization|cookie|session|api.?key|signature|email|phone|address|^(key|csrf|sid|jwt|auth|ip|ipv4|ipv6|user_?id|device_?id|client_?id)$/i;
  const state = {
    format: "doscbot.browser-widget-probe.v1",
    capture_id: crypto.randomUUID(),
    event_id: "17139995", statistics_id: TARGET,
    event_url: "https://winline.ru/stavki/sport/futbol/mezhdunarodnye/liga_naczij_konkakaf/17139995",
    statistics_url: "https://winline.ru/stats/match/" + TARGET,
    binding: "user_provided_pair", started_at: new Date().toISOString(),
    initial_requests_observed: false, requests: [], snapshots: [], initial_data: {},
    limits: {response_bytes: 2 * 1024 * 1024, total_bytes: 12 * 1024 * 1024, requests: 100, snapshots: 30},
    skipped: {}, redacted: 0, truncated: false
  };
  let bytes = 0, active = true, clickTimer = null;
  const pending = new Set(), xhrMeta = new WeakMap();
  const rawFetch = window.fetch, rawOpen = XMLHttpRequest.prototype.open, rawSend = XMLHttpRequest.prototype.send;
  const size = value => new TextEncoder().encode(JSON.stringify(value)).length;
  const skip = name => { state.skipped[name] = (state.skipped[name] || 0) + 1; };
  const currentScope = () => PROVIDERS.has(location.hostname) &&
    new RegExp("^/winline/(?:ru|en)/match/" + TARGET + "(?:/(?:report|statistics))?/?$").test(location.pathname);
  const safeUrl = value => {
    const u = new URL(value, location.href);
    const path = u.pathname.split("/").map(part => PRIVATE.test(part) || part.length > 80 || /^[a-f0-9]{24,}$/i.test(part) ? "[redacted]" : part).join("/");
    return {origin: u.origin, path, query_keys: [...new Set([...u.searchParams.keys()].filter(k => !PRIVATE.test(k)))].sort()};
  };
  const clean = value => {
    let budget = 100000;
    const seen = new WeakSet();
    const visit = (item, depth = 0) => {
      if (--budget < 0 || depth > 32) { state.truncated = true; return "[capture limit]"; }
      if (item && typeof item === "object") {
        if (seen.has(item)) { state.truncated = true; return "[circular]"; }
        seen.add(item);
        if (Array.isArray(item)) {
          if (item.length > 10000) state.truncated = true;
          return item.slice(0, 10000).map(child => visit(child, depth + 1));
        }
        const output = {};
        const entries = Object.entries(item);
        if (entries.length > 5000) state.truncated = true;
        for (const [key, child] of entries.slice(0, 5000)) {
          if (PRIVATE.test(key)) { state.redacted++; continue; }
          output[key] = visit(child, depth + 1);
        }
        return output;
      }
      if (typeof item === "string") {
        if (/^https?:\/\//i.test(item)) { state.redacted++; return safeUrl(item); }
        if (item.includes("@") || /^(?:Bearer\s|eyJ[A-Za-z0-9_-]+\.)/.test(item)) { state.redacted++; return "[redacted]"; }
      }
      return item;
    };
    return visit(value);
  };
  const accepted = value => {
    try {
      const u = new URL(value, location.href);
      return currentScope() && u.protocol === "https:" && DATA_HOSTS.has(u.hostname) &&
        !u.username && !u.password && !/\/(?:login|account|wallet|auth)(?:\/|$)/i.test(u.pathname);
    } catch { return false; }
  };
  const add = (collection, item) => {
    const length = size(item);
    if (bytes + length > state.limits.total_bytes) { skip("total_size"); return; }
    bytes += length;
    collection.push(item);
  };
  const record = (url, method, status, value, requestPage) => {
    if (!active || !accepted(url)) return;
    if (state.requests.length >= state.limits.requests) { skip("request_limit"); return; }
    if (size(value) > state.limits.response_bytes) { skip("response_size"); return; }
    add(state.requests, {observed_at: new Date().toISOString(), method, status,
      endpoint: safeUrl(url), requested_view: requestPage, observed_view: location.pathname, data: clean(value)});
  };
  const track = promise => {
    if (pending.size >= 8) { skip("pending_limit"); return; }
    const task = promise.catch(() => skip("response_read_error")).finally(() => pending.delete(task));
    pending.add(task);
  };
  function fetchProbe(...args) {
    const requestPage = location.pathname;
    return Reflect.apply(rawFetch, this, args).then(response => {
      try { if (active && accepted(response.url) && /json/i.test(response.headers.get("content-type") || "")) {
        const length = Number(response.headers.get("content-length") || 0);
        if (length > state.limits.response_bytes) skip("response_size");
        else if (pending.size < 8) {
          const clone = response.clone();
          const method = args[1]?.method || args[0]?.method || "GET";
          track(clone.text().then(text => {
            if (new TextEncoder().encode(text).length > state.limits.response_bytes) skip("response_size");
            else record(response.url, method, response.status, JSON.parse(text), requestPage);
          }));
        } else skip("pending_limit");
      } } catch { skip("instrumentation_error"); }
      return response;
    });
  }
  function openProbe(method, url, ...rest) {
    xhrMeta.set(this, {method, url: String(url), view: location.pathname});
    return Reflect.apply(rawOpen, this, [method, url, ...rest]);
  }
  function sendProbe(...args) {
    const meta = xhrMeta.get(this);
    if (meta && active) this.addEventListener("loadend", () => {
      try {
        const url = this.responseURL || new URL(meta.url, location.href).href;
        if (!accepted(url) || !/json/i.test(this.getResponseHeader("content-type") || "")) return;
        if (this.responseType === "json") record(url, meta.method, this.status, this.response, meta.view);
        else if (!this.responseType || this.responseType === "text") {
          if (new TextEncoder().encode(this.responseText).length > state.limits.response_bytes) skip("response_size");
          else record(url, meta.method, this.status, JSON.parse(this.responseText), meta.view);
        } else skip("unsupported_response_type");
      } catch { skip("response_read_error"); }
    }, {once: true});
    return Reflect.apply(rawSend, this, args);
  }
  const visible = el => !!el.getClientRects().length && getComputedStyle(el).visibility !== "hidden";
  const publicString = value => value.replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi,
    () => { state.redacted++; return "[redacted email]"; }).replace(/\b(?:\d{1,3}\.){3}\d{1,3}\b/g,
    () => { state.redacted++; return "[redacted IP]"; });
  const publicText = el => {
    let text = el?.innerText || "";
    for (const form of el?.querySelectorAll("form") || []) if (form.innerText) text = text.replaceAll(form.innerText, "");
    return publicString(text.trim());
  };
  const html = el => {
    const clone = el.cloneNode(true);
    clone.querySelectorAll("script,style,form,input,textarea,iframe").forEach(n => n.remove());
    for (const node of [clone, ...clone.querySelectorAll("*")]) for (const attr of [...node.attributes]) {
      if (!/^(?:class|role|data-testid|aria-[a-z-]+)$/.test(attr.name)) node.removeAttribute(attr.name);
    }
    const walker = document.createTreeWalker(clone, NodeFilter.SHOW_TEXT);
    for (let node = walker.nextNode(); node; node = walker.nextNode()) node.textContent = publicString(node.textContent);
    if (clone.outerHTML.length > 200000) state.truncated = true;
    return clone.outerHTML.slice(0, 200000);
  };
  const mark = (label = "manual") => {
    if (!currentScope()) { skip("scope_changed"); return; }
    if (state.snapshots.length >= state.limits.snapshots) { skip("snapshot_limit"); return; }
    const controls = [...document.querySelectorAll("button,[role=tab],[aria-pressed],[aria-selected]")]
      .filter(n => visible(n) && !n.closest("form")).slice(0, 200).map(n => ({text: publicText(n).slice(0, 120),
        pressed: n.getAttribute("aria-pressed"), selected: n.getAttribute("aria-selected"), class: n.className}));
    const headings = [...document.querySelectorAll("h1,h2,h3,h4,[role=heading]")].filter(n => visible(n) &&
      /статист|таблиц|сезон|последн|очные|личные|сыгран|форма|следующ|season|standings|statistics|matches|head.to.head/i.test(publicText(n)));
    const sections = headings.slice(0, 30).map(heading => {
      let root = heading.parentElement;
      for (let parent = root?.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
        if (parent.querySelectorAll("h1,h2,h3,h4,[role=heading]").length !== 1) break;
        root = parent;
      }
      const text = publicText(root);
      if (text.length > 100000) state.truncated = true;
      return {title: publicText(heading), text: text.slice(0, 100000), html: html(root)};
    });
    const body = publicText(document.body);
    if (body.length > 200000 || headings.length > 30) state.truncated = true;
    add(state.snapshots, {observed_at: new Date().toISOString(), label, path: location.pathname,
      controls, sections, body_text: body.slice(0, 200000)});
  };
  const click = event => {
    const el = event.target.closest?.("button,a,[role=tab]");
    if (!el || el.closest("form")) return;
    clearTimeout(clickTimer);
    clickTimer = setTimeout(() => mark("click: " + publicText(el).slice(0, 100)), 1800);
  };
  window.fetch = fetchProbe;
  XMLHttpRequest.prototype.open = openProbe;
  XMLHttpRequest.prototype.send = sendProbe;
  document.addEventListener("click", click, true);
  for (const name of ["__NEXT_DATA__", "__NUXT__"]) if (window[name]) {
    const value = clean(window[name]);
    if (size(value) <= state.limits.response_bytes) {
      const length = size(value);
      if (bytes + length <= state.limits.total_bytes) { state.initial_data[name] = value; bytes += length; }
      else skip("initial_data_size");
    } else skip("initial_data_size");
  }
  const api = {
    mark,
    status: () => ({statistics_id: TARGET, requests: state.requests.length, snapshots: state.snapshots.length,
      bytes, skipped: {...state.skipped}, truncated: state.truncated, active}),
    async report() { await Promise.allSettled([...pending]); mark("export"); return JSON.parse(JSON.stringify({...state, finished_at: new Date().toISOString()})); },
    async download() {
      const report = await api.report();
      const url = URL.createObjectURL(new Blob([JSON.stringify(report, null, 2)], {type: "application/json"}));
      const a = document.createElement("a");
      a.href = url; a.download = "doscbot-widget-" + TARGET + "-" + Date.now() + ".json";
      a.click(); setTimeout(() => URL.revokeObjectURL(url), 10000);
      return api.status();
    },
    stop() {
      active = false;
      if (window.fetch === fetchProbe) window.fetch = rawFetch;
      if (XMLHttpRequest.prototype.open === openProbe) XMLHttpRequest.prototype.open = rawOpen;
      if (XMLHttpRequest.prototype.send === sendProbe) XMLHttpRequest.prototype.send = rawSend;
      document.removeEventListener("click", click, true); clearTimeout(clickTimer);
      return api.status();
    }
  };
  window.doscbotProbe = api;
  mark("installed");
  console.info("doscbotProbe установлен. Переключай фильтры с паузой 2–3 секунды. Затем: await doscbotProbe.download()", api.status());
})();
