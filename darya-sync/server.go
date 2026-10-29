package main

import (
        "context"
        "embed"
        "encoding/json"
        "fmt"
        "html/template"
        "net/http"
        "strings"
        "sync"
        "time"
)

//go:embed web/index.html web/app.css
var webFS embed.FS

const appVersion = "1.0.0"

// Server is the local admin web UI + API.
type Server struct {
        sched *Scheduler
        log   *Logger
        dir   string

        mu      sync.Mutex
        httpSrv *http.Server
        started time.Time
}

// NewServer builds the server (not started yet).
func NewServer(s *Scheduler, log *Logger, dir string) *Server {
        return &Server{sched: s, log: log, dir: dir, started: time.Now()}
}

// indexData is passed to the HTML template.
type indexData struct {
        Listen   string
        Version  string
        FirstRun bool
}

// Handler builds the route mux.
func (sv *Server) Handler() http.Handler {
        mux := http.NewServeMux()
        tpl := template.Must(template.ParseFS(webFS, "web/index.html"))

        mux.HandleFunc("GET /{$}", func(w http.ResponseWriter, r *http.Request) {
                cfg := sv.sched.Cfg()
                data := indexData{Listen: cfg.Listen, Version: appVersion, FirstRun: cfg.FirstRun}
                w.Header().Set("Content-Type", "text/html; charset=utf-8")
                if err := tpl.Execute(w, data); err != nil {
                        sv.log.Logf("render index: %v", err)
                }
        })
        mux.HandleFunc("GET /app.css", func(w http.ResponseWriter, r *http.Request) {
                b, err := webFS.ReadFile("web/app.css")
                if err != nil {
                        http.Error(w, "css not found", http.StatusNotFound)
                        return
                }
                w.Header().Set("Content-Type", "text/css; charset=utf-8")
                w.Header().Set("Cache-Control", "no-store")
                _, _ = w.Write(b)
        })
        mux.HandleFunc("GET /api/status", sv.handleStatus)
        mux.HandleFunc("POST /api/run", sv.handleRun)
        mux.HandleFunc("POST /api/test", sv.handleTest)
        mux.HandleFunc("GET /api/log", sv.handleLog)
        mux.HandleFunc("POST /api/config", sv.handleConfig)
        mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
                http.NotFound(w, r)
        })
        return logRequests(sv.log, mux)
}

// logRequests adds a one-line request log for the API (skip dashboard assets).
func logRequests(log *Logger, next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
                if strings.HasPrefix(r.URL.Path, "/api/") {
                        start := time.Now()
                        next.ServeHTTP(w, r)
                        log.Logf("HTTP %s %s (%s)", r.Method, r.URL.String(), time.Since(start).Round(time.Millisecond))
                        return
                }
                next.ServeHTTP(w, r)
        })
}

// Start launches the HTTP server in a goroutine.
func (sv *Server) Start() {
        sv.mu.Lock()
        listen := sv.sched.Cfg().Listen
        sv.httpSrv = &http.Server{
                Addr:              listen,
                Handler:           sv.Handler(),
                ReadHeaderTimeout: 10 * time.Second,
        }
        sv.mu.Unlock()
        go func() {
                sv.log.Logf("وب‌سرور مدیریت روی http://%s اجرا شد", listen)
                if err := sv.httpSrv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
                        sv.log.Logf("خطای وب‌سرور: %v", err)
                }
        }()
}

// RestartIfListenChanged swaps the listener when the listen address changed.
func (sv *Server) RestartIfListenChanged() {
        sv.mu.Lock()
        old := sv.httpSrv
        sv.mu.Unlock()
        newListen := sv.sched.Cfg().Listen
        if old == nil || old.Addr == newListen {
                return
        }
        sv.log.Logf("تغییر آدرس وب به %s — بازآغاز وب‌سرور …", newListen)
        ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
        defer cancel()
        _ = old.Shutdown(ctx)
        sv.Start()
}

// ---- JSON helpers ----

func writeJSON(w http.ResponseWriter, code int, v any) {
        w.Header().Set("Content-Type", "application/json; charset=utf-8")
        w.WriteHeader(code)
        _ = json.NewEncoder(w).Encode(v)
}

func writeErr(w http.ResponseWriter, code int, msg string) {
        writeJSON(w, code, map[string]any{"ok": false, "error": msg})
}

// ---- handlers ----

func fmtTime(t time.Time) string {
        if t.IsZero() {
                return ""
        }
        return t.Local().Format("2006-01-02 15:04:05")
}

type jobStatusJSON struct {
        Name        string `json:"name"`
        Enabled     bool   `json:"enabled"`
        IntervalMin int    `json:"interval_min"`
        LastRun     string `json:"last_run"`
        LastStatus  string `json:"last_status"`
        LastError   string `json:"last_error"`
        NextRun     string `json:"next_run"`
        Running     bool   `json:"running"`
}

func (sv *Server) handleStatus(w http.ResponseWriter, r *http.Request) {
        cfg := sv.sched.Cfg().Masked()
        jobs := make([]jobStatusJSON, 0, len(JobOrder))
        for _, name := range JobOrder {
                res := sv.sched.State().Result(name)
                jobs = append(jobs, jobStatusJSON{
                        Name:        name,
                        Enabled:     sv.sched.EnabledOf(name),
                        IntervalMin: sv.sched.IntervalOf(name),
                        LastRun:     fmtTime(res.LastRun),
                        LastStatus:  res.Status,
                        LastError:   res.Error,
                        NextRun:     fmtTime(res.NextRun),
                        Running:     sv.sched.IsRunning(name),
                })
        }
        dOK, dMsg := sv.sched.DaryaStatus()
        wOK, wMsg := sv.sched.WPStatus()
        out := map[string]any{
                "version":        appVersion,
                "first_run":      sv.sched.Cfg().FirstRun,
                "config_masked":  cfg,
                "jobs":           jobs,
                "connections":    map[string]any{"darya_ok": dOK, "darya_msg": dMsg, "wp_ok": wOK, "wp_msg": wMsg},
                "queue":          map[string]any{"wp": sv.sched.WPQueue()},
                "uptime_minutes": int(time.Since(sv.started).Minutes()),
        }
        writeJSON(w, http.StatusOK, out)
}

func (sv *Server) handleRun(w http.ResponseWriter, r *http.Request) {
        job := strings.TrimSpace(r.URL.Query().Get("job"))
        if job == "" {
                writeErr(w, http.StatusBadRequest, "پارامتر job لازم است")
                return
        }
        if job != "all" {
                valid := false
                for _, n := range JobOrder {
                        if n == job {
                                valid = true
                                break
                        }
                }
                if !valid {
                        writeErr(w, http.StatusBadRequest, "وظیفه ناشناخته: "+job)
                        return
                }
                if !sv.sched.EnabledOf(job) {
                        writeErr(w, http.StatusBadRequest, "وظیفه «"+job+"» غیرفعال است؛ ابتدا در تنظیمات فعال کنید")
                        return
                }
        }
        started := sv.sched.Trigger(job)
        writeJSON(w, http.StatusOK, map[string]any{"ok": true, "started": started, "job": job})
}

func (sv *Server) handleTest(w http.ResponseWriter, r *http.Request) {
        dOK, dMsg, wOK, wMsg := sv.sched.TestConnections()
        writeJSON(w, http.StatusOK, map[string]any{
                "ok": dOK && wOK,
                "darya": map[string]any{"ok": dOK, "msg": dMsg},
                "wp":    map[string]any{"ok": wOK, "msg": wMsg},
        })
}

func (sv *Server) handleLog(w http.ResponseWriter, r *http.Request) {
        lines := 100
        if v := r.URL.Query().Get("lines"); v != "" {
                if n, err := atoi(v); err == nil && n > 0 && n <= 500 {
                        lines = n
                }
        }
        writeJSON(w, http.StatusOK, map[string]any{"ok": true, "lines": sv.log.Tail(lines)})
}

func atoi(s string) (int, error) {
        n := 0
        if s == "" {
                return 0, fmt.Errorf("empty")
        }
        for _, c := range s {
                if c < '0' || c > '9' {
                        return 0, fmt.Errorf("not a number")
                }
                n = n*10 + int(c-'0')
                if n > 1<<30 {
                        return 0, fmt.Errorf("too big")
                }
        }
        return n, nil
}

func (sv *Server) handleConfig(w http.ResponseWriter, r *http.Request) {
        var in Config
        dec := json.NewDecoder(r.Body)
        if err := dec.Decode(&in); err != nil {
                writeErr(w, http.StatusBadRequest, "JSON نامعتبر: "+err.Error())
                return
        }
        // keep real secrets when the masked value is posted back untouched
        old := sv.sched.Cfg()
        if in.DaryaKey == MaskKey(old.DaryaKey) && in.DaryaKey != "" {
                in.DaryaKey = old.DaryaKey
        }
        if in.WPSecret == MaskKey(old.WPSecret) && in.WPSecret != "" {
                in.WPSecret = old.WPSecret
        }
        in.FirstRun = false // saving config clears the first-run marker
        in.Normalize()
        if errs := in.Validate(); len(errs) > 0 {
                writeJSON(w, http.StatusBadRequest, map[string]any{"ok": false, "errors": errs})
                return
        }
        if err := sv.sched.ApplyConfig(&in); err != nil {
                writeErr(w, http.StatusInternalServerError, "ذخیره تنظیمات ناموفق: "+err.Error())
                return
        }
        go sv.RestartIfListenChanged()
        writeJSON(w, http.StatusOK, map[string]any{"ok": true, "message": "تنظیمات ذخیره و اعمال شد"})
}
