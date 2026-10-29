//go:build windows

package main

import "syscall"

// hideWindow prevents the cmd.exe console flash when launching the browser
// from the windowsgui build.
func hideWindow() *syscall.SysProcAttr {
	return &syscall.SysProcAttr{HideWindow: true, CreationFlags: 0x08000000} // CREATE_NO_WINDOW
}
