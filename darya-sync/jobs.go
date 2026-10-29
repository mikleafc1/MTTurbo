package main

import (
        "fmt"
        "net/url"
        "runtime/debug"
        "strconv"
        "sync"
        "sync/atomic"
        "time"
)

// Scheduler owns config+state, runs the 15s loop and the five jobs.
type Scheduler struct {
        dir  string
        log  *Logger
        mu   sync.Mutex // guards cfg + state
        cfg  *Config
        state *State

        clientMu sync.Mutex
        client   *Client

        runningMu sync.Mutex
        running   map[string]bool

        wake   chan struct{}
        stop   chan struct{}
        done   chan struct{}

        // connection health cache
        daryaOK atomic.Bool
        wpOK    atomic.Bool
        daryaMsg atomic.Value // string
        wpMsg    atomic.Value // string

        wpQueue atomic.Int64 // last known WP queue count (from plugin status)
}

// NewScheduler builds the scheduler.
func NewScheduler(dir string, cfg *Config, state *State, log *Logger) *Scheduler {
        s := &Scheduler{
                dir:     dir,
                cfg:     cfg,
                state:   state,
                log:     log,
                client:  NewClient(cfg, log),
                running: map[string]bool{},
                wake:    make(chan struct{}, 1),
                stop:    make(chan struct{}),
                done:    make(chan struct{}),
        }
        s.daryaMsg.Store("هنوز بررسی نشده")
        s.wpMsg.Store("هنوز بررسی نشده")
        return s
}

// Cfg returns a snapshot pointer (caller must treat as read-only).
func (s *Scheduler) Cfg() *Config {
        s.mu.Lock()
        defer s.mu.Unlock()
        return s.cfg
}

// State returns the state pointer (guarded externally by scheduler lock).
func (s *Scheduler) State() *State { return s.state }

// IntervalOf returns effective interval (minutes) for a job.
func (s *Scheduler) IntervalOf(name string) int {
        c := s.Cfg()
        if jc, ok := c.Jobs[name]; ok && jc.IntervalMin > 0 {
                return jc.IntervalMin
        }
        return JobDefaults[name]
}

// EnabledOf returns enabled flag for a job (default true).
func (s *Scheduler) EnabledOf(name string) bool {
        c := s.Cfg()
        if jc, ok := c.Jobs[name]; ok {
                return jc.Enabled
        }
        return true
}

// Wake nudges the loop to re-check due jobs immediately.
func (s *Scheduler) Wake() {
        select {
        case s.wake <- struct{}{}:
        default:
        }
}

// Loop is the scheduler main loop (run in a goroutine).
func (s *Scheduler) Loop() {
        defer close(s.done)
        tick := time.NewTicker(15 * time.Second)
        defer tick.Stop()
        s.checkDue() // first pass right away
        for {
                select {
                case <-s.stop:
                        return
                case <-tick.C:
                        s.checkDue()
                case <-s.wake:
                        s.checkDue()
                }
        }
}

// Stop terminates the loop and waits for it.
func (s *Scheduler) Stop() {
        close(s.stop)
        select {
        case <-s.done:
        case <-time.After(3 * time.Second):
        }
}

func (s *Scheduler) checkDue() {
        now := time.Now()
        var due []string
        for _, name := range JobOrder {
                if !s.EnabledOf(name) {
                        continue
                }
                r := s.state.Result(name)
                if r.NextRun.IsZero() || !r.NextRun.After(now) {
                        due = append(due, name)
                }
        }
        for _, name := range due {
                s.Trigger(name)
        }
}

// Trigger launches a job in a goroutine (no-op if already running or disabled).
// Returns true when a run was started. name == "all" runs every enabled job.
func (s *Scheduler) Trigger(name string) bool {
        if name == "all" {
                started := false
                for _, jn := range JobOrder {
                        if s.Trigger(jn) {
                                started = true
                        }
                }
                return started
        }
        if !s.EnabledOf(name) {
                return false
        }
        s.runningMu.Lock()
        if s.running[name] {
                s.runningMu.Unlock()
                return false
        }
        s.running[name] = true
        s.runningMu.Unlock()
        go func() {
                defer func() {
                        if r := recover(); r != nil {
                                s.log.Logf("خطای غیرمنتظره در وظیفه %s: %v\n%s", name, r, debug.Stack())
                        }
                        s.runningMu.Lock()
                        delete(s.running, name)
                        s.runningMu.Unlock()
                }()
                s.runJob(name)
        }()
        return true
}

// IsRunning reports whether a job is currently executing.
func (s *Scheduler) IsRunning(name string) bool {
        s.runningMu.Lock()
        defer s.runningMu.Unlock()
        return s.running[name]
}

// RunningJobs returns list of currently running job names.
func (s *Scheduler) RunningJobs() []string {
        s.runningMu.Lock()
        defer s.runningMu.Unlock()
        out := make([]string, 0, len(s.running))
        for _, n := range JobOrder {
                if s.running[n] {
                        out = append(out, n)
                }
        }
        return out
}

// runJob executes one job end-to-end and persists its result.
func (s *Scheduler) runJob(name string) {
        interval := s.IntervalOf(name)
        s.log.Logf("▶ اجرای وظیفه «%s» …", name)
        started := time.Now()
        var err error
        switch name {
        case "trigger":
                err = s.jobTrigger()
        case "roles":
                err = s.jobRoles()
        case "products":
                err = s.jobProducts()
        case "orders":
                err = s.jobOrders()
        case "carts":
                err = s.jobCarts()
        default:
                err = fmt.Errorf("وظیفه ناشناخته: %s", name)
        }
        dur := time.Since(started).Round(time.Millisecond)
        res := RunResult{
                LastRun: started,
                NextRun: time.Now().Add(time.Duration(interval) * time.Minute),
        }
        if err != nil {
                res.Status = "error"
                res.Error = err.Error()
                s.log.Logf("✖ وظیفه «%s» ناموفق (%s): %v", name, dur, err)
        } else {
                res.Status = "ok"
                s.log.Logf("✔ وظیفه «%s» موفق (%s)", name, dur)
        }
        s.mu.Lock()
        s.state.SetResult(name, res)
        if serr := SaveState(s.dir, s.state); serr != nil {
                s.log.Logf("ذخیره state.json ناموفق: %v", serr)
        }
        s.mu.Unlock()
}

// ---- job: trigger ----

func (s *Scheduler) jobTrigger() error {
        // 1) ask WP plugin to push its queued events
        if _, err := s.client.WPPost("run-sync", nil, map[string]any{}); err != nil {
                s.log.Logf("trigger/WP run-sync خطا: %v", err)
                if s.wpQueue.Load() == 0 && s.Cfg().WPURL != "" {
                        // status fetch also gives queue info; ignore failure here
                        if m, e := s.client.WPGet("status", nil); e == nil {
                                s.updateWPInfo(m)
                        }
                }
        } else {
                s.log.Logf("trigger/WP run-sync انجام شد")
        }
        // 2) ask Darya to pull pending sync queue
        m, err := s.client.DaryaPost("v1/sync/run", map[string]any{})
        if err != nil {
                return fmt.Errorf("sync/run داریا: %w", err)
        }
        if ok, _ := m["ok"].(bool); !ok {
                return fmt.Errorf("sync/run داریا: %s", jsonErrMessage(m))
        }
        if data, ok := m["data"].(map[string]any); ok {
                s.log.Logf("trigger/داریا: processed=%v failed=%v", data["processed"], data["failed"])
        }
        return nil
}

// ---- pagination over plugin export endpoints ----

const maxPages = 500 // hard cap to avoid runaway loops

func (s *Scheduler) paginate(ep string, q url.Values, perPage int) ([]any, error) {
        var all []any
        page := 1
        totalPages := 1
        for page <= totalPages && page <= maxPages {
                qq := url.Values{}
                for k, vs := range q {
                        for _, v := range vs {
                                qq.Add(k, v)
                        }
                }
                qq.Set("page", strconv.Itoa(page))
                if perPage > 0 {
                        qq.Set("per_page", strconv.Itoa(perPage))
                }
                m, err := s.client.WPGet("export/"+ep, qq)
                if err != nil {
                        return all, fmt.Errorf("%s صفحه %d: %w", ep, page, err)
                }
                if ok, _ := m["ok"].(bool); !ok {
                        return all, fmt.Errorf("%s صفحه %d: %s", ep, page, jsonErrMessage(m))
                }
                items, _ := m["items"].([]any)
                all = append(all, items...)
                if v, ok := m["total_pages"].(float64); ok && v > 0 {
                        totalPages = int(v)
                } else if len(items) == 0 {
                        break // no items and no total_pages => done
                } else {
                        totalPages = page + 1 // probe next page
                }
                s.log.Logf("export/%s صفحه %d/%d — %d ردیف", ep, page, maxInt(totalPages, page), len(items))
                if len(items) == 0 {
                        break
                }
                page++
        }
        return all, nil
}

func maxInt(a, b int) int {
        if a > b {
                return a
        }
        return b
}

// ---- job: roles ----

func (s *Scheduler) jobRoles() error {
        items, err := s.paginate("roles", nil, 100)
        if err != nil {
                return err
        }
        if len(items) == 0 {
                s.log.Logf("roles: کاربری برای همگام‌سازی نبود")
                return nil
        }
        saved := 0
        for i := 0; i < len(items); i += 100 {
                end := minInt(i+100, len(items))
                batch := items[i:end]
                if err := s.daryaSyncUsers(batch); err != nil {
                        return fmt.Errorf("roles دسته %d–%d: %w", i+1, end, err)
                }
                saved += end - i
        }
        s.log.Logf("roles: %d کاربر ارسال شد", saved)
        return nil
}

func (s *Scheduler) daryaSyncUsers(users []any) error {
        m, err := s.client.DaryaPost("v1/users/sync", map[string]any{"users": users})
        if err != nil {
                return err
        }
        if ok, _ := m["ok"].(bool); !ok {
                return fmt.Errorf("%s", jsonErrMessage(m))
        }
        return nil
}

// ---- job: products ----

func (s *Scheduler) jobProducts() error {
        items, err := s.paginate("products", nil, 100)
        if err != nil {
                return err
        }
        if len(items) == 0 {
                s.log.Logf("products: محصولی برای همگام‌سازی نبود")
                return nil
        }
        saved := 0
        for i := 0; i < len(items); i += 50 {
                end := minInt(i+50, len(items))
                batch := items[i:end]
                m, err := s.client.DaryaPost("v1/products/upsert", map[string]any{"products": batch})
                if err != nil {
                        return fmt.Errorf("products دسته %d–%d: %w", i+1, end, err)
                }
                if ok, _ := m["ok"].(bool); !ok {
                        return fmt.Errorf("products دسته %d–%d: %s", i+1, end, jsonErrMessage(m))
                }
                saved += end - i
                s.log.Logf("products: %d/%d محصول ارسال شد", saved, len(items))
        }
        s.log.Logf("products: مجموعاً %d محصول ارسال شد", saved)
        return nil
}

// ---- job: orders ----

func (s *Scheduler) jobOrders() error {
        s.mu.Lock()
        since := s.state.Cursor("orders")
        s.mu.Unlock()
        q := url.Values{"per_page": {"20"}}
        if since != "" {
                q.Set("since", since)
        }
        items, err := s.paginate("orders", q, 20)
        if err != nil {
                return err
        }
        if len(items) == 0 {
                s.log.Logf("orders: سفارش جدیدی نبود (since=%s)", orDash(since))
                return nil
        }
        var failErr error
        failed := 0
        okCount := 0
        var okItems []any
        for idx, it := range items {
                m, err := s.client.DaryaPost("v1/orders", map[string]any{"order": it})
                if err != nil {
                        failed++
                        if failErr == nil {
                                failErr = err
                        }
                        s.log.Logf("orders: سفارش #%d خطا: %v", idx+1, err)
                        continue
                }
                if ok, _ := m["ok"].(bool); !ok {
                        failed++
                        msg := jsonErrMessage(m)
                        if failErr == nil {
                                failErr = fmt.Errorf("%s", msg)
                        }
                        s.log.Logf("orders: سفارش #%d رد شد: %s", idx+1, msg)
                        continue
                }
                okCount++
                okItems = append(okItems, it)
                if data, ok := m["data"].(map[string]any); ok {
                        s.log.Logf("orders: سفارش #%d → فاکتور %v (%v)", idx+1, data["invoice_no"], data["status"])
                }
        }
        // advance cursor to newest date among successfully-synced orders
        newSince := since
        if len(okItems) > 0 {
                newSince = CursorFromItems(okItems, since)
        }
        s.mu.Lock()
        if newSince != since {
                s.state.SetCursor("orders", newSince)
        }
        s.mu.Unlock()
        s.log.Logf("orders: %d موفق، %d ناموفق؛ نشانگر since=%s", okCount, failed, orDash(newSince))
        if failErr != nil {
                return fmt.Errorf("%d سفارش ناموفق: %w", failed, failErr)
        }
        return nil
}

// ---- job: carts ----

func (s *Scheduler) jobCarts() error {
        s.mu.Lock()
        since := s.state.Cursor("carts")
        s.mu.Unlock()
        q := url.Values{}
        if since != "" {
                q.Set("since", since)
        }
        items, err := s.paginate("carts", q, 100)
        if err != nil {
                return err
        }
        if len(items) == 0 {
                s.log.Logf("carts: سبد جدیدی نبود (since=%s)", orDash(since))
                return nil
        }
        var failErr error
        failed := 0
        okCount := 0
        var okItems []any
        for i := 0; i < len(items); i += 25 {
                end := minInt(i+25, len(items))
                batch := items[i:end]
                m, err := s.client.DaryaPost("v1/abandoned-carts", map[string]any{"carts": batch})
                if err != nil {
                        failed += end - i
                        if failErr == nil {
                                failErr = err
                        }
                        s.log.Logf("carts: دسته %d–%d خطا: %v", i+1, end, err)
                        continue
                }
                if ok, _ := m["ok"].(bool); !ok {
                        failed += end - i
                        msg := jsonErrMessage(m)
                        if failErr == nil {
                                failErr = fmt.Errorf("%s", msg)
                        }
                        s.log.Logf("carts: دسته %d–%d رد شد: %s", i+1, end, msg)
                        continue
                }
                okCount += end - i
                okItems = append(okItems, batch...)
        }
        newSince := since
        if len(okItems) > 0 {
                newSince = CursorFromItems(okItems, since)
        }
        s.mu.Lock()
        if newSince != since {
                s.state.SetCursor("carts", newSince)
        }
        s.mu.Unlock()
        s.log.Logf("carts: %d موفق، %d ناموفق؛ نشانگر since=%s", okCount, failed, orDash(newSince))
        if failErr != nil {
                return fmt.Errorf("%d سبد ناموفق: %w", failed, failErr)
        }
        return nil
}

// ---- helpers ----

func minInt(a, b int) int {
        if a < b {
                return a
        }
        return b
}

func orDash(s string) string {
        if s == "" {
                return "—"
        }
        return s
}

// ---- connection tests ----

// TestConnections pings both sides and caches results.
func (s *Scheduler) TestConnections() (bool, string, bool, string) {
        c := s.currentClient()
        dOK, dMsg := c.PingDarya()
        s.daryaOK.Store(dOK)
        s.daryaMsg.Store(dMsg)
        wOK, wMsg := c.PingWP()
        s.wpOK.Store(wOK)
        s.wpMsg.Store(wMsg)
        if wOK {
                if m, err := c.WPGet("status", nil); err == nil {
                        s.updateWPInfo(m)
                }
        }
        s.log.Logf("تست اتصال — داریا: %s | وردپرس: %s", boolFA(dOK), boolFA(wOK))
        return dOK, dMsg, wOK, wMsg
}

func boolFA(b bool) string {
        if b {
                return "متصل ✔"
        }
        return "قطع ✖"
}

// updateWPInfo extracts queue counters from plugin /status response.
func (s *Scheduler) updateWPInfo(m map[string]any) {
        if m == nil {
                return
        }
        if n := findQueueCount(m, 0); n >= 0 {
                s.wpQueue.Store(int64(n))
        }
}

// findQueueCount searches common queue keys, depth-limited.
func findQueueCount(m map[string]any, depth int) int {
        if m == nil || depth > 3 {
                return -1
        }
        for _, k := range []string{"queue", "queue_count", "pending", "queue_size", "pending_events"} {
                switch v := m[k].(type) {
                case float64:
                        return int(v)
                case map[string]any:
                        if n := findQueueCount(v, depth+1); n >= 0 {
                                return n
                        }
                }
        }
        if d, ok := m["data"].(map[string]any); ok {
                return findQueueCount(d, depth+1)
        }
        return -1
}

// DaryaStatus returns cached darya connection info.
func (s *Scheduler) DaryaStatus() (bool, string) {
        msg, _ := s.daryaMsg.Load().(string)
        return s.daryaOK.Load(), msg
}

// WPStatus returns cached wp connection info.
func (s *Scheduler) WPStatus() (bool, string) {
        msg, _ := s.wpMsg.Load().(string)
        return s.wpOK.Load(), msg
}

// WPQueue returns last known wp queue count.
func (s *Scheduler) WPQueue() int { return int(s.wpQueue.Load()) }

// currentClient returns the live client (rebuilt after config change).
func (s *Scheduler) currentClient() *Client {
        s.clientMu.Lock()
        defer s.clientMu.Unlock()
        return s.client
}

// ApplyConfig swaps config live, rebuilds the client, recomputes next runs.
func (s *Scheduler) ApplyConfig(cfg *Config) error {
        cfg.Normalize()
        s.mu.Lock()
        s.cfg = cfg
        // keep cursors/state as-is; recompute next_run = last_run + new interval
        for _, name := range JobOrder {
                interval := JobDefaults[name]
                if jc, ok := cfg.Jobs[name]; ok && jc.IntervalMin > 0 {
                        interval = jc.IntervalMin
                }
                r := s.state.Result(name)
                if !r.LastRun.IsZero() {
                        r.NextRun = r.LastRun.Add(time.Duration(interval) * time.Minute)
                        s.state.SetResult(name, r)
                }
        }
        if err := SaveConfig(s.dir, s.cfg); err != nil {
                s.mu.Unlock()
                return err
        }
        if serr := SaveState(s.dir, s.state); serr != nil {
                s.log.Logf("ذخیره state.json ناموفق: %v", serr)
        }
        s.mu.Unlock()
        s.clientMu.Lock()
        s.client = NewClient(cfg, s.log)
        s.clientMu.Unlock()
        s.log.Logf("تنظیمات جدید اعمال شد (listen=%s)", cfg.Listen)
        s.Wake()
        return nil
}
