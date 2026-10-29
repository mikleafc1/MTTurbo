//go:build !windows

package main

import "syscall"

// hideWindow is a no-op on non-Windows platforms.
func hideWindow() *syscall.SysProcAttr { return nil }
