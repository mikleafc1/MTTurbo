package main

import (
        "context"
        "flag"
        "fmt"
        "net/http"
        "os"
        "os/exec"
        "os/signal"
        "path/filepath"
        "runtime"
        "strings"
        "syscall"
        "time"
)

const banner = `
╔══════════════════════════════════════════╗
║   داریا-سینک  |  DaryaSync v%s        ║
║   همگام‌ساز ووکامرس ↔ نرم‌افزار داریا      ║
╚══════════════════════════════════════════╝
`

func main() {
        open := flag.Bool("open", false, "باز کردن پنل در مرورگر پس از اجرا")
        cfgDir := flag.String("config", "", "مسیر پوشهٔ تنظیمات (پیش‌فرض: پوشهٔ فایل اجرایی)")
        flag.Parse()

        fmt.Printf(banner, appVersion)

        dir := *cfgDir
        if dir == "" {
                exe, err := os.Executable()
                if err != nil {
                        fmt.Fprintln(os.Stderr, "خطا در یافتن مسیر برنامه:", err)
                        dir = "."
                } else {
                        dir = filepath.Dir(exe)
                }
        }
        if err := os.MkdirAll(dir, 0o755); err != nil {
                fmt.Fprintln(os.Stderr, "ساخت پوشهٔ تنظیمات ناموفق:", err)
                os.Exit(1)
        }
        fmt.Printf("پوشهٔ کاری: %s\n", dir)

        // ---- config ----
        cfg, firstRun, err := LoadConfig(dir)
        if err != nil {
                fmt.Fprintln(os.Stderr, "⚠ خواندن config.json ناموفق:", err, "— از تنظیمات پیش‌فرض استفاده می‌شود")
                cfg = DefaultConfig()
                cfg.Normalize()
        }
        if firstRun {
                cfg.FirstRun = true
                if err := SaveConfig(dir, cfg); err != nil {
                        fmt.Fprintln(os.Stderr, "⚠ ذخیره config.json پیش‌فرض ناموفق:", err)
                }
                fmt.Println("⚠ فایل config.json ساخته شد — لطفاً تنظیمات را در پنل وارد کنید.")
        }

        // ---- logger + state ----
        logger, err := NewLogger(dir, 500, true)
        if err != nil {
                fmt.Fprintln(os.Stderr, "⚠ لاگر فایل فعال نشد:", err)
                logger = &Logger{ring: make([]string, 0, 500), max: 500, console: true}
        }
        logger.Logf("DaryaSync v%s شروع شد (dir=%s)", appVersion, dir)

        state, err := LoadState(dir)
        if err != nil {
                logger.Logf("⚠ خواندن state.json ناموفق: %v — وضعیت تازه ساخته شد", err)
                state = DefaultState()
        }

        // ---- scheduler + server ----
        sched := NewScheduler(dir, cfg, state, logger)
        srv := NewServer(sched, logger, dir)
        srv.Start()
        go sched.Loop()

        // initial connection test (background)
        go func() {
                time.Sleep(500 * time.Millisecond)
                sched.TestConnections()
        }()

        url := panelURL(cfg.Listen)
        fmt.Printf("پنل مدیریت: %s\n", url)
        if *open {
                openBrowser(url)
        }

        // ---- wait for SIGINT/SIGTERM ----
        sigCh := make(chan os.Signal, 1)
        signal.Notify(sigCh, os.Interrupt, syscall.SIGTERM)
        sig := <-sigCh
        fmt.Println("\nدر حال خروج …")
        logger.Logf("سیگنال %s دریافت شد — ذخیره وضعیت و خروج", sig)

        ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
        defer cancel()
        sched.Stop()
        if sv := srv.httpServerRef(); sv != nil {
                _ = sv.Shutdown(ctx)
        }
        sched.mu.Lock()
        if err := SaveState(dir, state); err != nil {
                logger.Logf("ذخیره state.json هنگام خروج ناموفق: %v", err)
        } else {
                logger.Logf("وضعیت ذخیره شد؛ خداحافظ!")
        }
        sched.mu.Unlock()
        logger.Close()
}

// panelURL builds the browser URL from the listen address.
func panelURL(listen string) string {
        if !strings.Contains(listen, ":") {
                return "http://" + listen + ":8585"
        }
        return "http://" + listen
}

// openBrowser opens the panel in the default browser (Windows: cmd /c start).
func openBrowser(url string) {
        var cmd *exec.Cmd
        switch runtime.GOOS {
        case "windows":
                cmd = exec.Command("cmd", "/c", "start", "", url)
        case "darwin":
                cmd = exec.Command("open", url)
        default:
                cmd = exec.Command("xdg-open", url)
        }
        if err := cmd.Start(); err != nil {
                fmt.Fprintln(os.Stderr, "باز کردن مرورگر ناموفق:", err)
        }
}

// httpServerRef exposes the running server for graceful shutdown.
func (sv *Server) httpServerRef() *http.Server {
        sv.mu.Lock()
        defer sv.mu.Unlock()
        return sv.httpSrv
}
