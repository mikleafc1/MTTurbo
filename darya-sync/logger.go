package main

import (
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"
)

// Logger is a combined ring buffer (in-memory) + file logger.
type Logger struct {
	mu      sync.Mutex
	ring    []string
	max     int
	file    *os.File
	fileMu  sync.Mutex
	console bool
}

// NewLogger creates a logger writing to dir/darya-sync.log and keeping max ring entries.
func NewLogger(dir string, max int, console bool) (*Logger, error) {
	if max <= 0 {
		max = 500
	}
	l := &Logger{ring: make([]string, 0, max), max: max, console: console}
	p := filepath.Join(dir, "darya-sync.log")
	f, err := os.OpenFile(p, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o600)
	if err == nil {
		l.file = f
	}
	return l, nil // file logging is best-effort; ring always works
}

func (l *Logger) stamp() string {
	return time.Now().Format("2006-01-02 15:04:05")
}

// Logf appends a formatted, timestamped line to ring + file (+stderr when console).
func (l *Logger) Logf(format string, args ...any) {
	msg := fmt.Sprintf(format, args...)
	line := fmt.Sprintf("[%s] %s", l.stamp(), msg)
	l.mu.Lock()
	if len(l.ring) >= l.max {
		copy(l.ring, l.ring[len(l.ring)-l.max+1:])
		l.ring = l.ring[:l.max-1]
	}
	l.ring = append(l.ring, line)
	l.mu.Unlock()

	if l.console {
		fmt.Fprintln(os.Stderr, line)
	}
	l.fileMu.Lock()
	if l.file != nil {
		fmt.Fprintln(l.file, line)
	}
	l.fileMu.Unlock()
}

// Tail returns the last n lines (oldest first).
func (l *Logger) Tail(n int) []string {
	if n <= 0 {
		n = 100
	}
	l.mu.Lock()
	defer l.mu.Unlock()
	if n > len(l.ring) {
		n = len(l.ring)
	}
	out := make([]string, n)
	copy(out, l.ring[len(l.ring)-n:])
	return out
}

// All returns the whole ring (oldest first).
func (l *Logger) All() []string { return l.Tail(l.max) }

// Close flushes and closes the file.
func (l *Logger) Close() {
	l.fileMu.Lock()
	defer l.fileMu.Unlock()
	if l.file != nil {
		_ = l.file.Close()
		l.file = nil
	}
}

// TailLinesText is a convenience: Tail joined with newlines.
func (l *Logger) TailLinesText(n int) string {
	return strings.Join(l.Tail(n), "\n")
}

// ensure io import used even if future refactor drops it
var _ io.Writer = os.Stderr
