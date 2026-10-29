package main

import (
	"encoding/json"
	"fmt"
	"os"
	"time"
)

// RunResult records the outcome of the last run of a job.
type RunResult struct {
	LastRun  time.Time `json:"last_run"`            // zero => never
	Status   string    `json:"last_status"`         // "ok" | "error" | ""
	Error    string    `json:"last_error,omitempty"`
	NextRun  time.Time `json:"next_run"`            // zero => ASAP
}

// JobState holds per-job persisted state (cursors etc).
type JobState map[string]any

// State is the persisted runtime state (state.json next to the exe).
type State struct {
	Jobs     map[string]JobState  `json:"jobs"`
	LastRuns map[string]RunResult `json:"last_runs"`
	Started  time.Time            `json:"started"`
}

// DefaultState returns an empty state.
func DefaultState() *State {
	return &State{
		Jobs: map[string]JobState{
			"trigger":  {},
			"roles":    {},
			"products": {},
			"orders":   {"since": ""},
			"carts":    {"since": ""},
		},
		LastRuns: map[string]RunResult{},
		Started:  time.Now(),
	}
}

// Cursor returns the "since" cursor for a job ("" when unset).
func (s *State) Cursor(job string) string {
	js, ok := s.Jobs[job]
	if !ok {
		return ""
	}
	v, _ := js["since"].(string)
	return v
}

// SetCursor stores the "since" cursor for a job.
func (s *State) SetCursor(job, since string) {
	if s.Jobs == nil {
		s.Jobs = map[string]JobState{}
	}
	js, ok := s.Jobs[job]
	if !ok {
		js = JobState{}
		s.Jobs[job] = js
	}
	js["since"] = since
}

// Result returns the last run result for a job (zero value when never run).
func (s *State) Result(job string) RunResult {
	if s.LastRuns == nil {
		return RunResult{}
	}
	return s.LastRuns[job]
}

// SetResult stores the last run result for a job.
func (s *State) SetResult(job string, r RunResult) {
	if s.LastRuns == nil {
		s.LastRuns = map[string]RunResult{}
	}
	s.LastRuns[job] = r
}

// LoadState reads state.json; missing file => fresh state.
func LoadState(dir string) (*State, error) {
	b, err := os.ReadFile(StatePath(dir))
	if err != nil {
		if os.IsNotExist(err) {
			return DefaultState(), nil
		}
		return nil, err
	}
	st := DefaultState()
	if err := json.Unmarshal(b, st); err != nil {
		// corrupt state: start fresh rather than crash
		return DefaultState(), nil
	}
	if st.Jobs == nil {
		st.Jobs = DefaultState().Jobs
	}
	if st.LastRuns == nil {
		st.LastRuns = map[string]RunResult{}
	}
	return st, nil
}

// SaveState writes state.json atomically.
func SaveState(dir string, s *State) error {
	if s.Jobs == nil {
		s.Jobs = DefaultState().Jobs
	}
	if s.LastRuns == nil {
		s.LastRuns = map[string]RunResult{}
	}
	b, err := json.MarshalIndent(s, "", "  ")
	if err != nil {
		return err
	}
	b = append(b, '\n')
	tmp := StatePath(dir) + ".tmp"
	if err := os.WriteFile(tmp, b, 0o600); err != nil {
		return err
	}
	return os.Rename(tmp, StatePath(dir))
}

// CursorFromItems computes the max date among item date fields, ISO8601.
// Checks common field names: date_created, abandoned_at, last_activity, date, modified.
func CursorFromItems(items []any, old string) string {
	maxT := time.Time{}
	if old != "" {
		if t, err := ParseFlexibleTime(old); err == nil {
			maxT = t
		}
	}
	fields := []string{"date_created_gmt", "date_created", "abandoned_at", "last_activity", "date", "modified", "date_modified"}
	for _, it := range items {
		m, ok := it.(map[string]any)
		if !ok {
			continue
		}
		for _, f := range fields {
			raw, ok := m[f]
			if !ok {
				continue
			}
			var str string
			switch v := raw.(type) {
			case string:
				str = v
			case float64:
				// unix timestamp (seconds)
				str = time.Unix(int64(v), 0).UTC().Format(time.RFC3339)
			default:
				continue
			}
			if str == "" {
				continue
			}
			t, err := ParseFlexibleTime(str)
			if err != nil {
				continue
			}
			if t.After(maxT) {
				maxT = t
			}
		}
	}
	if maxT.IsZero() {
		return old
	}
	return maxT.UTC().Format(time.RFC3339)
}

// ParseFlexibleTime parses ISO8601 and common "Y-m-d H:i:s" forms.
func ParseFlexibleTime(s string) (time.Time, error) {
	s = trimSpace(s)
	layouts := []string{
		time.RFC3339Nano,
		time.RFC3339,
		"2006-01-02T15:04:05.000000",
		"2006-01-02T15:04:05",
		"2006-01-02 15:04:05",
		"2006-01-02",
	}
	for _, l := range layouts {
		if t, err := time.Parse(l, s); err == nil {
			return t, nil
		}
	}
	return time.Time{}, fmt.Errorf("cannot parse time %q", s)
}

func trimSpace(s string) string {
	start, end := 0, len(s)
	for start < end && (s[start] == ' ' || s[start] == '\t' || s[start] == '\n' || s[start] == '\r') {
		start++
	}
	for end > start && (s[end-1] == ' ' || s[end-1] == '\t' || s[end-1] == '\n' || s[end-1] == '\r') {
		end--
	}
	return s[start:end]
}
