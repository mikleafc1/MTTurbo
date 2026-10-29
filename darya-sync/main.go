package main

import (
	"context"
	"flag"
	"fmt"
	"net"
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
	// ---- subcommand: exit / stop ----
	if len(os.Args) > 1 {
		switch strings.ToLower(strings.TrimSpace(os.Args[1])) {
		case "exit", "stop":
			cliExit()
			return
		}
	}

	noOpen := flag.Bool("no-open", false, "باز نکردن خودکار پنل در مرورگر")
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

	url := panelURL(cfg.Listen)

	// ---- single instance: if the port is busy, another DaryaSync is already running ----
	if portBusy(cfg.Listen) {
		fmt.Println("⚠ نمونهٔ دیگری از DaryaSync در حال اجراست — همان پنل در مرورگر باز می‌شود.")
		openBrowser(url)
		return
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
	exitCh := make(chan struct{})
	sched := NewScheduler(dir, cfg, state, logger)
	srv := NewServer(sched, logger, dir, exitCh, looksLikeTempDir(dir))
	srv.Start()
	go sched.Loop()

	// initial connection test (background)
	go func() {
		time.Sleep(500 * time.Millisecond)
		sched.TestConnections()
	}()

	// auto-open the Persian dashboard in the browser —
	// the windowsgui build has no console window, the panel IS the interface.
	if !*noOpen {
		go func() {
			time.Sleep(800 * time.Millisecond)
			openBrowser(url)
		}()
	}

	// ---- wait for OS signal OR exit request from the web panel ----
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, os.Interrupt, syscall.SIGTERM)
	select {
	case sig := <-sigCh:
		fmt.Println("\nدر حال خروج …")
		logger.Logf("سیگنال %s دریافت شد — ذخیره وضعیت و خروج", sig)
	case <-exitCh:
		fmt.Println("\nدر حال خروج …")
		logger.Logf("خروج از طریق پنل وب درخواست شد — ذخیره وضعیت و خروج")
	}

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
	// give the HTTP exit response a moment to reach the browser, then quit
	time.Sleep(300 * time.Millisecond)
}

// panelURL builds the browser URL from the listen address.
func panelURL(listen string) string {
	if !strings.Contains(listen, ":") {
		return "http://" + listen + ":8585"
	}
	return "http://" + listen
}

// portBusy reports whether something is already listening on the address.
func portBusy(listen string) bool {
	conn, err := net.DialTimeout("tcp", listen, 600*time.Millisecond)
	if err != nil {
		return false
	}
	_ = conn.Close()
	return true
}

// looksLikeTempDir detects "run from inside the archive" (WinRAR/7-Zip temp paths)
// so the panel can warn the user that settings will not persist there.
func looksLikeTempDir(dir string) bool {
	d := strings.ToLower(filepath.ToSlash(dir))
	if strings.Contains(d, "rar$") || strings.Contains(d, "7-zip") || strings.Contains(d, "7zip") {
		return true
	}
	return strings.Contains(d, "/temp/") && !strings.Contains(d, "daryasync")
}

// cliExit asks a running DaryaSync instance to exit gracefully (DaryaSync.exe exit).
func cliExit() {
	dir := "."
	if exe, err := os.Executable(); err == nil {
		dir = filepath.Dir(exe)
	}
	listen := DefaultConfig().Listen
	if cfg, _, err := LoadConfig(dir); err == nil && cfg.Listen != "" {
		listen = cfg.Listen
	}
	client := &http.Client{Timeout: 4 * time.Second}
	resp, err := client.Post(panelURL(listen)+"/api/exit", "application/json", nil)
	if err != nil {
		fmt.Fprintln(os.Stderr, "DaryaSync در حال اجرا نیست یا در دسترس نیست:", err)
		os.Exit(1)
	}
	defer resp.Body.Close()
	fmt.Println("دستور خروج ارسال شد — DaryaSync متوقف می‌شود.")
}

// openBrowser opens the panel in the default browser.
func openBrowser(url string) {
	var cmd *exec.Cmd
	switch runtime.GOOS {
	case "windows":
		// `start "" url` — the empty title arg guards against quoted URLs
		cmd = exec.Command("cmd", "/c", "start", "", url)
		cmd.SysProcAttr = hideWindow()
	case "darwin":
		cmd = exec.Command("open", url)
	default:
		cmd = exec.Command("xdg-open", url)
	}
	if err := cmd.Start(); err != nil {
		fmt.Fprintln(os.Stderr, "باز کردن مرورگر ناموفق:", err)
	}
}
