//go:build windows

// Darya.exe — لانچر نسخه دسکتاپ ویندوزی نرم‌افزار حسابداری داریا
// بدون پنجره کنسول: وب‌سرور محلی PHP را اجرا می‌کند و پنل فارسی را
// در پنجرهٔ برنامه (Edge/Chrome app-mode) باز می‌کند — شبیه یک نرم‌افزار دسکتاپ.
package main

import (
        "encoding/json"
        "fmt"
        "net"
        "net/http"
        "os"
        "os/exec"
        "path/filepath"
        "strings"
        "syscall"
        "time"
        "unsafe"
)

const (
        port    = 8765
        baseURL = "http://127.0.0.1:8765"
)

var (
        user32      = &syscall.LazyDLL{Name: "user32.dll"}
        messageBoxW = user32.NewProc("MessageBoxW")
)

func msgBox(title, text string, icon uintptr) {
        t, _ := syscall.UTF16PtrFromString(title)
        m, _ := syscall.UTF16PtrFromString(text)
        _, _, _ = messageBoxW.Call(0, uintptr(unsafe.Pointer(m)), uintptr(unsafe.Pointer(t)), icon)
}

func main() {
        if len(os.Args) > 1 && (os.Args[1] == "stop" || os.Args[1] == "exit") {
                stopDarya()
                return
        }
        run()
}

func baseDir() string {
        exe, err := os.Executable()
        if err != nil {
                d, _ := os.Getwd()
                return d
        }
        return filepath.Dir(exe)
}

// run: init → spawn php → wait ready → open app window → monitor
func run() {
        base := baseDir()
        phpExe := filepath.Join(base, "php", "php.exe")
        appDir := filepath.Join(base, "app")
        router := filepath.Join(appDir, "router.php")
        dataDir := filepath.Join(base, "data")
        runFile := filepath.Join(dataDir, "run.json")

        if _, err := os.Stat(phpExe); err != nil {
                msgBox("داریا", "فایل php\\php.exe یافت نشد! پکیج را کامل Extract کرده‌اید؟", 0x30)
                return
        }
        if _, err := os.Stat(router); err != nil {
                msgBox("داریا", "پوشه app یافت نشد! پکیج را کامل Extract کرده‌اید؟", 0x30)
                return
        }

        // نمونهٔ در حال اجرا؟ فقط پنجره را دوباره باز کن
        if portBusy() {
                openAppWindow()
                return
        }

        // آماده‌سازی اولیه (ساخت دیتابیس SQLite + config + حساب مدیر)
        _ = os.MkdirAll(dataDir, 0o755)
        if _, err := os.Stat(filepath.Join(appDir, "config", "installed.lock")); err != nil {
                out, err := runHidden(phpExe, "-d", "display_errors=1", filepath.Join(appDir, "bin", "desktop-init.php"), appDir)
                if err != nil || !strings.Contains(out, `"ok":true`) {
                        msgBox("داریا — خطای راه‌اندازی", "راه‌اندازی اولیه ناموفق بود:\n"+lastLines(out, 6), 0x30)
                        return
                }
        }

        // اجرای وب‌سرور داخلی PHP
        args := []string{
                "-d", "extension_dir=" + filepath.Join(base, "php", "ext"),
                "-d", "extension=pdo_sqlite",
                "-d", "extension=sqlite3",
                "-d", "extension=mbstring",
                "-d", "extension=openssl",
                "-d", "extension=curl",
                "-d", "extension=gd",
                "-d", "extension=fileinfo",
                "-d", "extension=zip",
                "-d", "memory_limit=256M",
                "-d", "upload_max_filesize=20M",
                "-d", "post_max_size=25M",
                "-d", "max_execution_time=120",
                "-d", "date.timezone=Asia/Tehran",
                "-S", fmt.Sprintf("127.0.0.1:%d", port),
                "-t", appDir,
                router,
        }
        cmd := exec.Command(phpExe, args...)
        cmd.Dir = base
        cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
        if err := cmd.Start(); err != nil {
                msgBox("داریا", "اجرای وب‌سرور محلی ناموفق بود:\n"+err.Error(), 0x30)
                return
        }
        _ = os.WriteFile(runFile, []byte(fmt.Sprintf(`{"pid":%d,"port":%d}`, cmd.Process.Pid, port)), 0o644)

        // انتظار برای آماده شدن سرور
        ready := false
        for i := 0; i < 100; i++ {
                time.Sleep(200 * time.Millisecond)
                if httpAlive() {
                        ready = true
                        break
                }
        }
        if ready {
                openAppWindow()
        } else {
                msgBox("داریا", "وب‌سرور محلی پاسخ نداد. لطفاً از دوباره اجرا کردن برنامه و آزاد بودن پورت 8765 مطمئن شوید.", 0x30)
        }

        // مانیتور — تا php زنده است، برنامه هم زنده است
        _ = cmd.Wait()
        _ = os.Remove(runFile)
}

// stopDarya: توقف تمیز نمونهٔ در حال اجرا (Darya.exe stop)
func stopDarya() {
        base := baseDir()
        runFile := filepath.Join(base, "data", "run.json")
        b, err := os.ReadFile(runFile)
        if err != nil {
                return
        }
        var r struct {
                PID int `json:"pid"`
        }
        if json.Unmarshal(b, &r) == nil && r.PID > 0 {
                killer := exec.Command("taskkill", "/F", "/T", "/PID", fmt.Sprint(r.PID))
                killer.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
                _ = killer.Run()
        }
        _ = os.Remove(runFile)
}

func portBusy() bool {
        c, err := net.DialTimeout("tcp", fmt.Sprintf("127.0.0.1:%d", port), 600*time.Millisecond)
        if err != nil {
                return false
        }
        _ = c.Close()
        return true
}

func httpAlive() bool {
        client := &http.Client{Timeout: 2 * time.Second}
        resp, err := client.Get(baseURL + "/login")
        if err != nil {
                return false
        }
        _ = resp.Body.Close()
        return true
}

// openAppWindow: باز کردن پنل در حالت برنامه (بدون نوار مرورگر)
func openAppWindow() {
        url := baseURL + "/"
        candidates := []string{
                filepath.Join(os.Getenv("ProgramFiles(x86)"), `Microsoft\Edge\Application\msedge.exe`),
                filepath.Join(os.Getenv("ProgramFiles"), `Microsoft\Edge\Application\msedge.exe`),
                filepath.Join(os.Getenv("LOCALAPPDATA"), `Google\Chrome\Application\chrome.exe`),
                filepath.Join(os.Getenv("ProgramFiles"), `Google\Chrome\Application\chrome.exe`),
                filepath.Join(os.Getenv("ProgramFiles(x86)"), `Google\Chrome\Application\chrome.exe`),
        }
        for _, exe := range candidates {
                if _, err := os.Stat(exe); err == nil {
                        cmd := exec.Command(exe, "--app="+url, "--window-size=1300,880")
                        if cmd.Start() == nil {
                                return
                        }
                }
        }
        // مرورگر پیش‌فرض
        cmd := exec.Command("cmd", "/c", "start", "", url)
        cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
        _ = cmd.Start()
}

func runHidden(exe string, args ...string) (string, error) {
        cmd := exec.Command(exe, args...)
        cmd.Dir = baseDir()
        cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
        out, err := cmd.CombinedOutput()
        return string(out), err
}

func lastLines(s string, n int) string {
        lines := strings.Split(strings.ReplaceAll(s, "\r\n", "\n"), "\n")
        if len(lines) > n {
                lines = lines[len(lines)-n:]
        }
        return strings.Join(lines, "\n")
}
