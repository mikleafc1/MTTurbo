package main

import (
        "bytes"
        "encoding/json"
        "fmt"
        "io"
        "net/http"
        "net/url"
        "strings"
        "time"
)

const httpTimeout = 25 * time.Second

// Client wraps HTTP helpers for both sides (Darya API + WP plugin).
type Client struct {
        http    *http.Client
        log     *Logger
        DaryaURL string // normalized base (no trailing slash)
        DaryaKey string
        WPURL    string
        WPSecret string
}

// NewClient builds a client from config.
func NewClient(cfg *Config, log *Logger) *Client {
        return &Client{
                http:    &http.Client{Timeout: httpTimeout},
                log:     log,
                DaryaURL: cfg.DaryaURL,
                DaryaKey: cfg.DaryaKey,
                WPURL:    cfg.WPURL,
                WPSecret: cfg.WPSecret,
        }
}

// do performs an HTTP request and decodes the JSON body into map[string]any.
func (c *Client) do(method, fullURL string, headers map[string]string, body []byte) (map[string]any, error) {
        var rdr io.Reader
        if body != nil {
                rdr = bytes.NewReader(body)
        }
        req, err := http.NewRequest(method, fullURL, rdr)
        if err != nil {
                return nil, fmt.Errorf("ساخت درخواست: %w", err)
        }
        req.Header.Set("Accept", "application/json")
        if body != nil {
                req.Header.Set("Content-Type", "application/json")
        }
        for k, v := range headers {
                req.Header.Set(k, v)
        }
        resp, err := c.http.Do(req)
        if err != nil {
                return nil, fmt.Errorf("اتصال ناموفق: %w", err)
        }
        defer resp.Body.Close()
        raw, err := io.ReadAll(io.LimitReader(resp.Body, 16<<20)) // 16MB cap
        if err != nil {
                return nil, fmt.Errorf("خواندن پاسخ: %w", err)
        }
        var out map[string]any
        if err := json.Unmarshal(raw, &out); err != nil {
                return nil, fmt.Errorf("پاسخ JSON نیست (HTTP %d): %s", resp.StatusCode, preview(raw, 160))
        }
        // transport-level error surfaced to caller
        if resp.StatusCode < 200 || resp.StatusCode >= 300 {
                errMsg := jsonErrMessage(out)
                if errMsg == "" {
                        errMsg = strings.TrimSpace(string(raw))
                        if errMsg == "" {
                                errMsg = resp.Status
                        }
                }
                return out, fmt.Errorf("HTTP %d: %s", resp.StatusCode, errMsg)
        }
        return out, nil
}

// jsonErrMessage extracts {"ok":false,"error":{"message":...}} fields.
func jsonErrMessage(m map[string]any) string {
        if m == nil {
                return ""
        }
        if e, ok := m["error"].(map[string]any); ok {
                if s, ok := e["message"].(string); ok && s != "" {
                        return s
                }
                if s, ok := e["error"].(string); ok && s != "" {
                        return s
                }
        }
        if s, ok := m["message"].(string); ok && s != "" {
                return s
        }
        return ""
}

// preview truncates raw bytes for error messages.
func preview(b []byte, n int) string {
        s := strings.TrimSpace(string(b))
        if len(s) > n {
                return s[:n] + "…"
        }
        return s
}

// ---- Darya side ----

// DaryaURLFor builds {base}/api/index.php?path=v1/<endpoint> (+extra query).
func DaryaURLFor(base, path string, extra url.Values) string {
        q := url.Values{}
        q.Set("path", path)
        for k, vs := range extra {
                for _, v := range vs {
                        q.Add(k, v)
                }
        }
        return fmt.Sprintf("%s/api/index.php?%s", strings.TrimRight(base, "/"), q.Encode())
}

// DaryaGet GETs a Darya endpoint with X-Api-Key.
func (c *Client) DaryaGet(path string, extra url.Values) (map[string]any, error) {
        if c.DaryaURL == "" {
                return nil, fmt.Errorf("آدرس داریا تنظیم نشده است")
        }
        return c.do(http.MethodGet, DaryaURLFor(c.DaryaURL, path, extra), map[string]string{"X-Api-Key": c.DaryaKey}, nil)
}

// DaryaPost POSTs json to a Darya endpoint with X-Api-Key.
func (c *Client) DaryaPost(path string, payload any) (map[string]any, error) {
        if c.DaryaURL == "" {
                return nil, fmt.Errorf("آدرس داریا تنظیم نشده است")
        }
        b, err := json.Marshal(payload)
        if err != nil {
                return nil, err
        }
        return c.do(http.MethodPost, DaryaURLFor(c.DaryaURL, path, nil), map[string]string{"X-Api-Key": c.DaryaKey}, b)
}

// ---- WordPress plugin side ----

// WPEndpointBase is the plugin REST namespace.
const WPEndpointBase = "/wp-json/darya-bridge/v1"

// WPURLFor builds {wp}/wp-json/darya-bridge/v1/<ep>?<extra> (secret added by withSecret).
func WPURLFor(wpURL, ep string, extra url.Values) string {
        q := url.Values{}
        for k, vs := range extra {
                for _, v := range vs {
                        q.Add(k, v)
                }
        }
        u := fmt.Sprintf("%s%s/%s", strings.TrimRight(wpURL, "/"), WPEndpointBase, ep)
        if len(q) > 0 {
                u += "?" + q.Encode()
        }
        return u
}

// WPGet GETs a plugin endpoint with secret query param.
func (c *Client) WPGet(ep string, extra url.Values) (map[string]any, error) {
        if c.WPURL == "" {
                return nil, fmt.Errorf("آدرس وردپرس تنظیم نشده است")
        }
        full := WPURLFor(c.WPURL, ep, extra)
        // inject secret into query (kept out of logs by caller)
        full = c.withSecret(full)
        return c.do(http.MethodGet, full, nil, nil)
}

// WPPost POSTs json to a plugin endpoint with secret query param.
func (c *Client) WPPost(ep string, extra url.Values, payload any) (map[string]any, error) {
        if c.WPURL == "" {
                return nil, fmt.Errorf("آدرس وردپرس تنظیم نشده است")
        }
        full := c.withSecret(WPURLFor(c.WPURL, ep, extra))
        b, err := json.Marshal(payload)
        if err != nil {
                return nil, err
        }
        return c.do(http.MethodPost, full, map[string]string{"Content-Type": "application/json"}, b)
}

// withSecret appends secret=<cfg secret> to the query string.
func (c *Client) withSecret(u string) string {
        sep := "&"
        if !strings.Contains(u, "?") {
                sep = "?"
        }
        return u + sep + "secret=" + url.QueryEscape(c.WPSecret)
}

// ---- health checks ----

// PingDarya pings Darya: expect HTTP 200 and body containing "ok":true.
func (c *Client) PingDarya() (bool, string) {
        if c.DaryaURL == "" {
                return false, "آدرس داریا تنظیم نشده است"
        }
        m, err := c.DaryaGet("v1/ping", nil)
        if err != nil {
                return false, err.Error()
        }
        if ok, _ := m["ok"].(bool); ok {
                if data, ok := m["data"].(map[string]any); ok {
                        return true, fmt.Sprintf("pong (%s %s)", strOr(data, "app", "Darya"), strOr(data, "version", ""))
                }
                return true, "pong"
        }
        return false, "پاسخ نامعتبر: " + preview([]byte(fmt.Sprint(m)), 120)
}

// PingWP pings the plugin /status endpoint.
func (c *Client) PingWP() (bool, string) {
        if c.WPURL == "" {
                return false, "آدرس وردپرس تنظیم نشده است"
        }
        m, err := c.WPGet("status", nil)
        if err != nil {
                return false, err.Error()
        }
        if ok, _ := m["ok"].(bool); ok {
                desc := ""
                if v, ok := m["plugin_version"].(string); ok {
                        desc = "نسخه " + v
                } else if v, ok := m["version"].(string); ok {
                        desc = "نسخه " + v
                }
                return true, strings.TrimSpace("افزونه فعال " + desc)
        }
        return false, "پاسخ نامعتبر: " + preview([]byte(fmt.Sprint(m)), 120)
}

// strOr returns m[key] or fallback.
func strOr(m map[string]any, key, fb string) string {
        if v, ok := m[key].(string); ok && v != "" {
                return v
        }
        return fb
}
