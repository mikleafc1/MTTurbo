package main

import (
        "encoding/json"
        "errors"
        "fmt"
        "os"
        "path/filepath"
        "strings"
)

// JobConfig holds per-job settings.
type JobConfig struct {
        Enabled    bool `json:"enabled"`
        IntervalMin int `json:"interval_min"`
}

// JobsConfig is the map of job name -> settings.
type JobsConfig map[string]JobConfig

// Config is the persisted configuration (config.json next to the exe).
type Config struct {
        Listen     string     `json:"listen"`
        DaryaURL   string     `json:"darya_url"`
        DaryaKey   string     `json:"darya_api_key"`
        WPURL      string     `json:"wp_url"`
        WPSecret   string     `json:"wp_secret"`
        Jobs       JobsConfig `json:"jobs"`
        FirstRun   bool       `json:"first_run,omitempty"` // marker for the auto-created default config
}

// JobOrder is the canonical job name list used everywhere.
var JobOrder = []string{"trigger", "roles", "products", "orders", "carts"}

// JobDefaults maps job name -> default interval in minutes.
var JobDefaults = map[string]int{
        "trigger":  5,
        "roles":    60,
        "products": 120,
        "orders":   10,
        "carts":    15,
}

// DefaultConfig returns the default configuration.
func DefaultConfig() *Config {
        jobs := JobsConfig{}
        for _, name := range JobOrder {
                jobs[name] = JobConfig{Enabled: true, IntervalMin: JobDefaults[name]}
        }
        return &Config{
                Listen:   "127.0.0.1:8585",
                DaryaURL: "",
                DaryaKey: "",
                WPURL:    "",
                WPSecret: "",
                Jobs:     jobs,
                FirstRun: true,
        }
}

// Normalize fixes user-provided values: scheme defaults, trimming, listen fallback.
func (c *Config) Normalize() {
        c.Listen = strings.TrimSpace(c.Listen)
        if c.Listen == "" {
                c.Listen = "127.0.0.1:8585"
        }
        c.DaryaURL = NormalizeBaseURL(c.DaryaURL)
        c.WPURL = NormalizeBaseURL(c.WPURL)
        c.DaryaKey = strings.TrimSpace(c.DaryaKey)
        c.WPSecret = strings.TrimSpace(c.WPSecret)
        if c.Jobs == nil {
                c.Jobs = JobsConfig{}
        }
        for _, name := range JobOrder {
                jc, ok := c.Jobs[name]
                if !ok {
                        jc = JobConfig{Enabled: true, IntervalMin: JobDefaults[name]}
                }
                if jc.IntervalMin < 1 {
                        jc.IntervalMin = JobDefaults[name]
                }
                if jc.IntervalMin > 10080 {
                        jc.IntervalMin = 10080 // one week cap
                }
                c.Jobs[name] = jc
        }
}

// NormalizeBaseURL trims and adds a scheme when missing.
// Defaults to https:// ; uses http:// for localhost / 127.* / [::1] hosts.
func NormalizeBaseURL(raw string) string {
        raw = strings.TrimSpace(raw)
        if raw == "" {
                return ""
        }
        raw = strings.TrimRight(raw, "/")
        if !strings.Contains(raw, "://") {
                host := raw
                if i := strings.Index(host, "/"); i >= 0 {
                        host = host[:i]
                }
                if h, _, err := net_SplitHostPort(host); err == nil {
                        host = h
                }
                lower := strings.ToLower(host)
                if lower == "localhost" || strings.HasPrefix(lower, "127.") || lower == "::1" || lower == "[::1]" {
                        raw = "http://" + raw
                } else {
                        raw = "https://" + raw
                }
        }
        return raw
}

// net_SplitHostPort is a tiny local indirection to keep imports tidy.
func net_SplitHostPort(hostport string) (host string, port string, err error) {
        // ipv6 bracket form
        if strings.HasPrefix(hostport, "[") {
                if end := strings.Index(hostport, "]"); end > 0 {
                        host = hostport[1:end]
                        rest := hostport[end+1:]
                        if strings.HasPrefix(rest, ":") {
                                port = rest[1:]
                        }
                        return host, port, nil
                }
                return "", "", errors.New("bad bracketed host")
        }
        if i := strings.LastIndex(hostport, ":"); i >= 0 {
                return hostport[:i], hostport[i+1:], nil
        }
        return hostport, "", errors.New("no port")
}

// MaskKey shortens a secret for display: dk_abcd...wxyz
func MaskKey(s string) string {
        s = strings.TrimSpace(s)
        if s == "" {
                return ""
        }
        if len(s) <= 8 {
                return strings.Repeat("*", len(s))
        }
        return s[:4] + "..." + s[len(s)-4:]
}

// Validate checks the config and returns a list of problems (empty = ok).
func (c *Config) Validate() []string {
        var errs []string
        if strings.TrimSpace(c.DaryaURL) == "" {
                errs = append(errs, "darya_url نباید خالی باشد")
        } else if !strings.Contains(c.DaryaURL, "://") {
                errs = append(errs, "darya_url باید با http:// یا https:// شروع شود")
        }
        if strings.TrimSpace(c.DaryaKey) == "" {
                errs = append(errs, "darya_api_key نباید خالی باشد")
        }
        if strings.TrimSpace(c.WPURL) == "" {
                errs = append(errs, "wp_url نباید خالی باشد")
        } else if !strings.Contains(c.WPURL, "://") {
                errs = append(errs, "wp_url باید با http:// یا https:// شروع شود")
        }
        if strings.TrimSpace(c.WPSecret) == "" {
                errs = append(errs, "wp_secret نباید خالی باشد")
        }
        if strings.TrimSpace(c.Listen) == "" {
                errs = append(errs, "listen نباید خالی باشد")
        }
        for _, name := range JobOrder {
                if jc, ok := c.Jobs[name]; ok && jc.IntervalMin < 1 {
                        errs = append(errs, fmt.Sprintf("بازه jobs.%s باید حداقل ۱ دقیقه باشد", name))
                }
        }
        return errs
}

// ConfigPath returns config.json path inside dir.
func ConfigPath(dir string) string { return filepath.Join(dir, "config.json") }

// StatePath returns state.json path inside dir.
func StatePath(dir string) string { return filepath.Join(dir, "state.json") }

// LoadConfig reads config.json; missing file => defaults (auto-created by caller).
func LoadConfig(dir string) (*Config, bool, error) {
        p := ConfigPath(dir)
        b, err := os.ReadFile(p)
        if err != nil {
                if os.IsNotExist(err) {
                        return DefaultConfig(), true, nil
                }
                return nil, false, err
        }
        c := DefaultConfig()
        c.FirstRun = false
        if err := json.Unmarshal(b, c); err != nil {
                return nil, false, fmt.Errorf("config.json نامعتبر است: %w", err)
        }
        c.Normalize()
        return c, false, nil
}

// SaveConfig writes config.json (indented, readable).
func SaveConfig(dir string, c *Config) error {
        c.Normalize()
        b, err := json.MarshalIndent(c, "", "  ")
        if err != nil {
                return err
        }
        b = append(b, '\n')
        tmp := ConfigPath(dir) + ".tmp"
        if err := os.WriteFile(tmp, b, 0o600); err != nil {
                return err
        }
        return os.Rename(tmp, ConfigPath(dir))
}

// Masked returns a copy of the config safe for display (secrets masked).
func (c *Config) Masked() *Config {
        m := *c
        m.DaryaKey = MaskKey(c.DaryaKey)
        m.WPSecret = MaskKey(c.WPSecret)
        m.Jobs = JobsConfig{}
        for k, v := range c.Jobs {
                m.Jobs[k] = v
        }
        return &m
}
