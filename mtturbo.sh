#!/usr/bin/env bash
# ============================================================================
#  MTTurbo - Ultra-Fast Anti-Censorship MTProto Proxy Installer
#  Inspired by MTPulse, rebuilt from scratch for reliability & speed.
#
#  Why MTTurbo?
#    - FakeTLS (ee) secrets only -> defeats DPI deep inspection
#    - Engine 1 "Turbo":   mtg v2 (Go)   -> max throughput, doppelganger
#    - Engine 2 "Sponsor": telemt (Rust) -> official @MTProxybot ad-tag support
#    - BBR congestion control + kernel network tuning out of the box
#    - systemd hardening, health check, IPv4/IPv6, UFW aware
#
#  Supported OS : Ubuntu / Debian (amd64, arm64)
#  Usage        : bash mtturbo.sh   (or: bash <(curl -Ls <url>/mtturbo.sh))
# ============================================================================

set -u

SCRIPT_VERSION="1.2.0"
MTTURBO_HOME="/etc/mtturbo"
MTTURBO_ENV="${MTTURBO_HOME}/mtturbo.env"
MTTURBO_VAR="/var/lib/mtturbo"
MTG_BIN="/usr/local/bin/mtg"
TELEMT_BIN="/usr/local/bin/telemt"
MTG_SERVICE="mtturbo-mtg"
TELEMT_SERVICE="mtturbo-telemt"
MTG_PINNED_VERSION="2.2.8"
TELEMT_PINNED_VERSION="3.5.13"

# ----------------------------- Colors --------------------------------------
if [ -t 1 ]; then
  R='\033[0;31m';  G='\033[0;32m';  Y='\033[0;33m';  B='\033[0;34m'
  M='\033[0;35m';  C='\033[0;36m';  W='\033[0;37m';  N='\033[0m'
  BG='\033[1;32m'; BM='\033[1;35m'; BB='\033[1;34m'; BC='\033[1;36m'
  BY='\033[1;33m'; BR='\033[1;31m'
else
  R=''; G=''; Y=''; B=''; M=''; C=''; W=''; N=''
  BG=''; BM=''; BB=''; BC=''; BY=''; BR=''
fi

# ----------------------------- Helpers -------------------------------------
ok()   { echo -e " ${G}[OK]${N}   $1"; }
err()  { echo -e " ${R}[ERR]${N}  $1"; }
warn() { echo -e " ${Y}[WARN]${N} $1"; }
info() { echo -e " ${C}[INFO]${N} $1"; }
step() { echo -e "\n${BC}>>> $1${N}"; }

line() {
  local char="${1:-=}" len="${2:-58}" col="${3:-${C}}"
  printf "%b" "${col}"
  printf "%${len}s" '' | tr ' ' "$char"
  printf "%b\n" "${N}"
}

banner() {
  clear
  echo -e "${BG}"
  echo "  __  __ _______  ______ _____  _    _ _______ __  __ "
  echo " |  \/  |_   __ \|  ____|  __ \| |  | |__   __|  \/  |"
  echo " | \  / | | |__) | |__  | |__) | |__| |  | |  | \  / |"
  echo " | |\/| | |  ___/|  __| |  _  /|  __  |  | |  | |\/| |"
  echo " | |  | |_| |    | |____| | \ \| |  | |  | |  | |  | |"
  echo " |_|  |_||_|     |______|_|  \_\_|  |_|  |_|  |_|  |_|"
  echo -e "${N}"
  echo -e "  ${BY}Ultra-Fast Anti-Censorship MTProto Proxy${N}   ${W}v${SCRIPT_VERSION}${N}"
  echo -e "  ${W}FakeTLS  •  BBR Turbo  •  Sponsor Channel${N}"
  line "=" 58
}

pause_enter() {
  echo ""
  read -r -p " Press Enter to return to the menu..." _
}

# Read a single answer even when the script is piped (curl ... | bash).
# Falls back to /dev/tty when stdin is not a terminal; empty answer = cancel.
confirm() {
  local ans=""
  if [ -t 0 ]; then
    read -r -p "$1" ans
  elif [ -r /dev/tty ]; then
    read -r -p "$1" ans </dev/tty 2>/dev/null || ans=""
  else
    ans=""
  fi
  printf '%s' "$ans"
}

require_root() {
  if [ "$(id -u)" -ne 0 ]; then
    err "This script must run as root. Try: sudo bash $0"
    exit 1
  fi
}

check_os() {
  if [ ! -f /etc/os-release ]; then
    err "Cannot detect OS (/etc/os-release missing)."
    exit 1
  fi
  . /etc/os-release
  case "${ID:-}" in
    ubuntu|debian) ;;
    *)
      err "Only Ubuntu / Debian are supported (detected: ${PRETTY_NAME:-unknown})."
      exit 1
      ;;
  esac
}

http_get() {
  # http_get <url> [outfile|-]
  local url="$1" out="${2:--}"
  if command -v curl >/dev/null 2>&1; then
    if [ "$out" = "-" ]; then curl -fsSL --max-time 25 "$url"; else curl -fsSL --max-time 60 -o "$out" "$url"; fi
  elif command -v wget >/dev/null 2>&1; then
    if [ "$out" = "-" ]; then wget -qO- --timeout=25 "$url"; else wget -qO "$out" --timeout=60 "$url"; fi
  else
    echo "Neither curl nor wget found." >&2; return 1
  fi
}

detect_arch() {
  case "$(uname -m)" in
    x86_64)  echo "amd64";  echo "x86_64";  ;;
    aarch64) echo "arm64";  echo "aarch64"; ;;
    *)       echo "";       echo "";       ;;
  esac
}

public_ip4() {
  local ip=""
  for src in "https://api.ipify.org" "https://ipv4.icanhazip.com" "https://ifconfig.me"; do
    ip="$(http_get "$src" - 2>/dev/null | tr -d '[:space:]')" && [ -n "$ip" ] && { echo "$ip"; return 0; }
  done
  echo ""
}

public_ip6() {
  local ip=""
  ip="$(http_get "https://ipv6.icanhazip.com" - 2>/dev/null | tr -d '[:space:]')"
  [ -n "$ip" ] && echo "$ip" || echo ""
}

port_in_use() {
  if command -v ss >/dev/null 2>&1; then
    ss -tlnH "sport = :$1" 2>/dev/null | grep -q .
  else
    netstat -tln 2>/dev/null | grep -q ":$1 "
  fi
}

# Wait for a service to be active AND its port to accept connections.
# telemt's probe phase (proxy-secret download, STUN via intermediate
# servers, ME handshake) takes ~6-10s BEFORE the listener binds, so the
# old fixed 'sleep 2' check always produced a false "failed to start".
wait_up() {
  local svc="$1" port="$2" timeout="${3:-30}" waited=0
  while [ "$waited" -lt "$timeout" ]; do
    if systemctl is-active --quiet "$svc" && port_in_use "$port"; then return 0; fi
    sleep 2
    waited=$((waited+2))
  done
  systemctl is-active --quiet "$svc" && port_in_use "$port"
}

valid_port() {
  [[ "$1" =~ ^[0-9]+$ ]] && [ "$1" -ge 1 ] && [ "$1" -le 65535 ]
}

gen_hex16() {
  if command -v openssl >/dev/null 2>&1; then
    openssl rand -hex 16
  else
    head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n'
  fi
}

# Extract the raw 16-byte key (32 hex) that @MTProxybot expects.
# Full link secret looks like: ee<32hex><domain-hex> — the bot wants ONLY <32hex>.
raw_bot_secret() {
  local s="${1:-${SECRET:-}}"
  case "$s" in
    ee*) echo "${s:2:32}" ;;
    *)   echo "${s:0:32}" ;;
  esac
}

str_to_hex() {
  printf '%s' "$1" | od -An -tx1 | tr -d ' \n'
}

# Build FakeTLS secret: ee + 16-byte key + domain hex
build_tls_secret() {
  local domain="$1"
  echo "ee$(gen_hex16)$(str_to_hex "$domain")"
}

hex_to_ascii() {
  local h="$1"
  echo "$h" | sed 's/../\\x&/g' | xargs -0 printf 2>/dev/null || echo ""
}

# ------------------------- FakeTLS domains ---------------------------------
FAKE_DOMAINS=(
  "www.speedtest.net"
  "www.wikipedia.org"
  "www.samsung.com"
  "www.opera.com"
  "www.oracle.com"
  "www.mozilla.org"
  "www.python.org"
  "www.debian.org"
  "www.cloudflare.com"
  "www.kernel.org"
)

pick_domain() {
  echo ""
  echo -e " ${BC}Choose a FakeTLS camouflage domain${N} ${W}(what DPI sees)${N}:"
  local i=1 d
  for d in "${FAKE_DOMAINS[@]}"; do
    echo -e "   ${M}${i})${N} ${W}$d${N}"
    i=$((i + 1))
  done
  echo -e "   ${M}c)${N} ${W}Custom domain${N}"
  while true; do
    read -r -p " 👉 Selection [1]: " choice
    choice=${choice:-1}
    case "$choice" in
      c|C)
        read -r -p " 👉 Enter domain (e.g. www.example.com): " custom
        if [ -n "$custom" ]; then echo "$custom"; return 0; fi
        ;;
      *)
        if [[ "$choice" =~ ^[0-9]+$ ]] && [ "$choice" -ge 1 ] && [ "$choice" -le 10 ]; then
          echo "${FAKE_DOMAINS[$((choice - 1))]}"
          return 0
        fi
        ;;
    esac
    warn "Invalid selection, try again."
  done
}

ask_port() {
  local default="${1:-443}" port
  while true; do
    read -r -p " 👉 Proxy port [${default}]: " port
    port=${port:-$default}
    if ! valid_port "$port"; then err "Invalid port number."; continue; fi
    if port_in_use "$port"; then
      warn "Port $port is already in use."
      read -r -p " 👉 Choose another port: " port
      continue
    fi
    echo "$port"
    return 0
  done
}

ask_channel() {
  local ch
  read -r -p " 👉 Sponsor Telegram channel (without @, empty to skip): " ch
  ch="${ch#@}"
  echo "$ch"
}

ask_tag() {
  local tag
  while true; do
    read -r -p " 👉 @MTProxybot ad-tag (32 hex chars, empty to skip): " tag
    tag="$(printf '%s' "$tag" | tr -d '[:space:]' | tr 'A-Z' 'a-z')"
    if [ -z "$tag" ]; then
      echo ""
      return 0
    fi
    if [[ "$tag" =~ ^[0-9a-f]{32}$ ]]; then
      echo "$tag"
      return 0
    fi
    err "Invalid ad-tag. It must be exactly 32 hexadecimal characters (0-9, a-f)."
  done
}

valid_ad_tag() {
  [[ "${1:-}" =~ ^[0-9a-fA-F]{32}$ ]]
}

# ------------------------- Engine downloads --------------------------------
latest_mtg_version() {
  local loc
  loc="$(curl -sI --max-time 12 "https://github.com/9seconds/mtg/releases/latest" 2>/dev/null | tr -d '\r' | awk 'tolower($1)=="location:"{print $2}')"
  echo "${loc##*/v}"
}

latest_telemt_version() {
  local loc
  loc="$(curl -sI --max-time 12 "https://github.com/telemt/telemt/releases/latest" 2>/dev/null | tr -d '\r' | awk 'tolower($1)=="location:"{print $2}')"
  echo "${loc##*/}"
}

install_mtg() {
  local arch gharch tmpdir tarball ver
  arch="$(detect_arch | head -1)"
  [ -n "$arch" ] || { err "Unsupported architecture: $(uname -m)"; return 1; }
  gharch="$arch"

  step "Installing mtg engine (Turbo mode)"
  ver="$(latest_mtg_version)"
  [ -n "$ver" ] || ver="$MTG_PINNED_VERSION"
  info "mtg version: v${ver}"

  tmpdir="$(mktemp -d)"
  tarball="${tmpdir}/mtg.tar.gz"
  info "Downloading mtg-${ver}-linux-${gharch}..."
  if ! http_get "https://github.com/9seconds/mtg/releases/download/v${ver}/mtg-${ver}-linux-${gharch}.tar.gz" "$tarball"; then
    err "Download failed. Check network access to github.com"
    rm -rf "$tmpdir"; return 1
  fi
  tar -xzf "$tarball" -C "$tmpdir"
  local bin
  bin="$(find "$tmpdir" -type f -name mtg | head -1)"
  [ -n "$bin" ] || { err "Binary not found inside archive."; rm -rf "$tmpdir"; return 1; }
  install -m 0755 "$bin" "$MTG_BIN"
  rm -rf "$tmpdir"
  ok "mtg installed -> ${MTG_BIN}"
}

install_telemt() {
  local arch tmpdir tarball ver
  arch="$(detect_arch | tail -1)"
  [ -n "$arch" ] || { err "Unsupported architecture: $(uname -m)"; return 1; }

  step "Installing telemt engine (Sponsor mode)"
  ver="$(latest_telemt_version)"
  [ -n "$ver" ] || ver="$TELEMT_PINNED_VERSION"
  info "telemt version: ${ver}"

  tmpdir="$(mktemp -d)"
  tarball="${tmpdir}/telemt.tar.gz"
  info "Downloading telemt-${ver} linux-${arch} (musl static)..."
  if ! http_get "https://github.com/telemt/telemt/releases/download/${ver}/telemt-${arch}-linux-musl.tar.gz" "$tarball"; then
    err "Download failed. Check network access to github.com"
    rm -rf "$tmpdir"; return 1
  fi
  tar -xzf "$tarball" -C "$tmpdir"
  local bin
  bin="$(find "$tmpdir" -type f -name telemt | head -1)"
  [ -n "$bin" ] || { err "Binary not found inside archive."; rm -rf "$tmpdir"; return 1; }
  install -m 0755 "$bin" "$TELEMT_BIN"
  rm -rf "$tmpdir"
  ok "telemt installed -> ${TELEMT_BIN}"
}

# ------------------------- Config writers ----------------------------------
write_mtg_config() {
  # $1 port  $2 secret
  cat > "${MTTURBO_HOME}/config.toml" <<EOF
# MTTurbo - mtg engine config (generated)
secret = "$2"
bind-to = "0.0.0.0:$1"
concurrency = 8192
prefer-ip = "prefer-ipv4"
debug = false
EOF
}

write_telemt_config() {
  # $1 port  $2 hex16secret  $3 domain  $4 tag(optional)  $5 public IPv4(optional)
  local tag_line="" host_line="" nat_line=""
  if [ -n "$4" ]; then
    valid_ad_tag "$4" || { err "Refusing to write invalid Telemt ad-tag."; return 1; }
    tag_line="ad_tag = \"$4\""
  fi
  if [ -n "$5" ]; then
    host_line="public_host = \"$5\""
    nat_line="middle_proxy_nat_ip = \"$5\""
  fi

  # Telemt stores TLS-front data relative to its working directory.
  mkdir -p "${MTTURBO_VAR}/tlsfront"

  cat > "${MTTURBO_HOME}/telemt.toml" <<EOF
# MTTurbo - telemt engine config (generated)
# Sponsor/Middle-Proxy configuration with explicit public NAT IPv4.
[general]
use_middle_proxy = true
log_level = "normal"
${tag_line}
${nat_line}

[general.modes]
classic = false
secure = false
tls = true

[general.links]
show = "*"
${host_line}
public_port = $1

[network]
ipv4 = true
ipv6 = false
prefer = 4

[server]
port = $1

[[server.listeners]]
ip = "0.0.0.0"

[censorship]
tls_domain = "$3"
mask = true
tls_emulation = true
tls_front_dir = "tlsfront"

[access.users]
"user1" = "$2"
EOF
}

shell_quote() {
  printf '%q' "${1:-}"
}

write_env() {
  # write_env ENGINE PORT SECRET DOMAIN CHANNEL TAG IP4 IP6
  local q_engine q_port q_secret q_domain q_channel q_tag q_ip4 q_ip6
  q_engine="$(shell_quote "$1")"
  q_port="$(shell_quote "$2")"
  q_secret="$(shell_quote "$3")"
  q_domain="$(shell_quote "$4")"
  q_channel="$(shell_quote "$5")"
  q_tag="$(shell_quote "$6")"
  q_ip4="$(shell_quote "$7")"
  q_ip6="$(shell_quote "$8")"
  umask 077
  {
    printf '%s\n' '# MTTurbo state (generated) - do not edit manually'
    printf 'ENGINE=%s\n' "$q_engine"
    printf 'PORT=%s\n' "$q_port"
    printf 'SECRET=%s\n' "$q_secret"
    printf 'DOMAIN=%s\n' "$q_domain"
    printf 'CHANNEL=%s\n' "$q_channel"
    printf 'TAG=%s\n' "$q_tag"
    printf 'IP4=%s\n' "$q_ip4"
    printf 'IP6=%s\n' "$q_ip6"
  } > "$MTTURBO_ENV"
  chmod 600 "$MTTURBO_ENV"
}

read_env() {
  [ -f "$MTTURBO_ENV" ] && . "$MTTURBO_ENV"
}

build_links() {
  # build_links <ip> <port> <secret>
  # MTProxy native links use server/port/secret. Sponsored-channel promotion
  # is configured through @MTProxybot and Telemt ad_tag, not a &channel= query.
  local ip="$1" port="$2" secret="$3"
  echo "tg://proxy?server=${ip}&port=${port}&secret=${secret}"
  echo "https://t.me/proxy?server=${ip}&port=${port}&secret=${secret}"
}

print_proxy_info() {
  read_env
  local engine="${ENGINE:-}" port="${PORT:-}" secret="${SECRET:-}" \
        domain="${DOMAIN:-}" channel="${CHANNEL:-}" tag="${TAG:-}" ip4="${IP4:-}" ip6="${IP6:-}"
  if [ -z "$engine" ]; then
    warn "No MTTurbo installation found. Install first (menu 1 or 2)."
    return 1
  fi
  line "=" 58 "$G"
  echo -e " ${BG}  🚀 MTTurbo Proxy Details  ${N}"
  line "=" 58 "$G"
  echo -e " ${W}Engine :${N} $engine"
  echo -e " ${W}Mode   :${N} FakeTLS (${domain})"
  echo -e " ${W}Port   :${N} $port"
  echo -e " ${W}IPv4   :${N} $ip4"
  [ -n "$ip6" ] && echo -e " ${W}IPv6   :${N} $ip6"
  [ -n "$channel" ] && echo -e " ${W}Requested channel:${N} @${channel} ${Y}(promotion is controlled by @MTProxybot)${N}"
  if [ "$engine" = "telemt" ]; then
    if [ -n "$tag" ]; then
      echo -e " ${W}Ad-Tag :${N} $tag ${G}(configured)${N}"
    else
      echo -e " ${W}Ad-Tag :${N} ${Y}not set — register via @MTProxybot (menu 3)${N}"
    fi
  fi
  echo ""
  echo -e " ${BY}Secret:${N} $secret"
  echo -e " ${BY}Bot Key :${N} $(raw_bot_secret "$secret") ${W}<- send THIS to @MTProxybot (32 hex)${N}"
  echo ""
  echo -e " ${BM}▪ Telegram Desktop / Apps (tg link):${N}"
  while IFS= read -r l; do echo -e "   ${C}${l}${N}"; done < <(build_links "$ip4" "$port" "$secret")
  if [ -n "$ip6" ]; then
    echo -e " ${BM}▪ IPv6 links:${N}"
    while IFS= read -r l; do echo -e "   ${C}${l}${N}"; done < <(build_links "[${ip6}]" "$port" "$secret")
  fi
  line "=" 58 "$G"
  return 0
}

# ------------------------- Performance tuning -------------------------------
apply_sysctl() {
  step "Applying kernel network tuning (BBR + buffers)"
  if ! grep -qs "tcp_bbr" /proc/modules && ! sysctl net.ipv4.tcp_congestion_control 2>/dev/null | grep -q bbr; then
    modprobe tcp_bbr 2>/dev/null || true
  fi
  cat > /etc/sysctl.d/99-mtturbo.conf <<EOF
# MTTurbo network performance tuning
net.core.default_qdisc = fq
net.ipv4.tcp_congestion_control = bbr
net.core.rmem_max = 67108864
net.core.wmem_max = 67108864
net.ipv4.tcp_rmem = 4096 87380 33554432
net.ipv4.tcp_wmem = 4096 16384 33554432
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 65535
net.ipv4.tcp_fastopen = 3
net.ipv4.tcp_slow_start_after_idle = 0
net.ipv4.tcp_mtu_probing = 1
net.ipv4.tcp_fin_timeout = 15
net.ipv4.tcp_tw_reuse = 1
net.ipv4.ip_local_port_range = 1024 65535
fs.file-max = 2097152
EOF
  sysctl -p /etc/sysctl.d/99-mtturbo.conf >/dev/null 2>&1 || true
  if sysctl net.ipv4.tcp_congestion_control 2>/dev/null | grep -q bbr; then
    ok "BBR congestion control is ACTIVE (huge speed boost)"
  else
    warn "BBR not available on this kernel — tuning applied without it."
  fi
}

open_firewall() {
  local port="$1"
  if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
    ufw allow "${port}/tcp" >/dev/null 2>&1 && ok "UFW rule added for ${port}/tcp"
  fi
}

# ------------------------- Service management ------------------------------
write_service() {
  # write_service <engine: mtg|telemt>
  local svc bin exec env_line=""
  if [ "$1" = "mtg" ]; then
    svc="$MTG_SERVICE"
    bin="$MTG_BIN"
    exec="${bin} run ${MTTURBO_HOME}/config.toml"
  else
    svc="$TELEMT_SERVICE"
    bin="$TELEMT_BIN"
    # NOTE: systemd does NOT run ExecStart through a shell — 'cd X && bin' makes
    # systemd try to execute the binary 'cd' (status=203/EXEC). WorkingDirectory
    # below already puts the process into MTTURBO_VAR, so call the binary directly.
    exec="${bin} ${MTTURBO_HOME}/telemt.toml"
    # Silence the Direct-DC fallback module (DC:8888 is unreachable from most
    # datacenter IPs -> endless connect-timeout spam). Routing is untouched:
    # telemt default keeps the listener always-up (direct-first, ME joins when
    # ready). RUST_LOG overrides config log_level. ME logs remain visible.
    env_line="Environment=RUST_LOG=info,telemt::transport::upstream=off"
  fi
  cat > "/etc/systemd/system/${svc}.service" <<EOF
# MTTurbo service (${1} engine) - generated
[Unit]
Description=MTTurbo MTProto Proxy (${1})
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=${exec}
WorkingDirectory=${MTTURBO_VAR}
${env_line}
Restart=always
RestartSec=3
LimitNOFILE=1048576
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
ProtectSystem=full

[Install]
WantedBy=multi-user.target
EOF
  systemctl daemon-reload
  systemctl enable "${svc}" >/dev/null 2>&1
  systemctl restart "${svc}"
}

install_self_command() {
  local self
  self="$(readlink -f "${BASH_SOURCE[0]}")"
  if [ -f "$self" ] && [ "$self" != "/usr/local/bin/mtturbo" ]; then
    cp -f "$self" /usr/local/bin/mtturbo && chmod +x /usr/local/bin/mtturbo && \
      ok "You can now run 'mtturbo' from anywhere."
  fi
}

# ------------------------- Install flows -----------------------------------
install_engine() {
  # install_engine <mtg|telemt> <port> <domain> <channel> <tag>
  local engine="$1" port="$2" domain="$3" channel="$4" tag="$5"
  local secret16 tls_secret ip4 ip6

  mkdir -p "$MTTURBO_HOME" "$MTTURBO_VAR"

  if [ "$engine" = "mtg" ]; then
    install_mtg || return 1
  else
    install_telemt || return 1
  fi

  step "Generating FakeTLS secret"
  secret16="$(gen_hex16)"
  tls_secret="ee${secret16}$(str_to_hex "$domain")"
  ok "FakeTLS secret generated for '${domain}'"

  step "Detecting public IP"
  ip4="$(public_ip4)"
  ip6="$(public_ip6)"
  [ -n "$ip4" ] && ok "IPv4: $ip4" || warn "Could not detect IPv4"
  [ -n "$ip6" ] && ok "IPv6: $ip6"

  step "Writing configuration"
  if [ "$engine" = "mtg" ]; then
    write_mtg_config "$port" "$tls_secret"
  else
    write_telemt_config "$port" "$secret16" "$domain" "$tag" "$ip4" || return 1
  fi
  write_env "$engine" "$port" "$tls_secret" "$domain" "$channel" "$tag" "$ip4" "$ip6"

  step "Creating systemd service"
  write_service "$engine"
  local svc_name; [ "$engine" = "mtg" ] && svc_name="$MTG_SERVICE" || svc_name="$TELEMT_SERVICE"
  if wait_up "$svc_name" "$port" 30; then
    ok "Service is up and running (port $port listening)"
  else
    err "Service failed to start — last log lines:"
    journalctl -u "$svc_name" -n 8 --no-pager 2>/dev/null | sed 's/^/        /'
  fi

  apply_sysctl
  open_firewall "$port"
  install_self_command

  echo ""
  print_proxy_info
}

quick_install_action() {
  banner
  echo -e " ${BG}  ⚡ QUICK INSTALL — Turbo mode (recommended)  ${N}"
  line "=" 58
  echo ""
  echo -e " ${W}Everything is automatic. Just 2 questions.${N}"
  echo ""
  local port channel
  port="$(ask_port 443)" || return 1
  channel="$(ask_channel)"
  echo ""
  local domain="${FAKE_DOMAINS[0]}"
  install_engine "mtg" "$port" "$domain" "$channel" "" || return 1
  pause_enter
}

advanced_install_action() {
  banner
  echo -e " ${BG}  ⚙️  ADVANCED INSTALL — choose everything  ${N}"
  line "=" 58
  echo ""
  echo -e " ${BC}Engine:${N}"
  echo -e "   ${M}1)${N} ${W}Turbo   (mtg v2 — max speed, no official ad-tag)${N}"
  echo -e "   ${M}2)${N} ${W}Sponsor (telemt — @MTProxybot ad-tag supported)${N}"
  local eng_choice engine port domain channel tag=""
  read -r -p " 👉 Engine [1]: " eng_choice
  case "$eng_choice" in 2) engine="telemt" ;; *) engine="mtg" ;; esac
  echo ""
  port="$(ask_port 443)" || return 1
  domain="$(pick_domain)"
  echo ""
  channel="$(ask_channel)"
  if [ "$engine" = "telemt" ]; then
    echo ""
    echo -e " ${Y}Optional: official sponsored channel requires an ad-tag.${N}"
    echo -e " ${Y}Get it by registering your proxy with @MTProxybot on Telegram.${N}"
    tag="$(ask_tag)"
  fi
  echo ""
  install_engine "$engine" "$port" "$domain" "$channel" "$tag" || return 1
  pause_enter
}

# ------------------------- Other actions -----------------------------------
service_menu_action() {
  local active_svc
  read_env
  if [ "${ENGINE:-mtg}" = "telemt" ]; then active_svc="$TELEMT_SERVICE"; else active_svc="$MTG_SERVICE"; fi
  while true; do
    banner
    echo -e " ${BG}  🛠  SERVICE MANAGEMENT  ${N}"
    line "-" 58
    echo -e "   ${M}1)${N} Status          ${W}(${active_svc})${N}"
    echo -e "   ${M}2)${N} Start"
    echo -e "   ${M}3)${N} Stop"
    echo -e "   ${M}4)${N} Restart"
    echo -e "   ${M}5)${N} Live logs (last 50)"
    echo -e "   ${M}0)${N} Back"
    echo ""
    read -r -p " 👉 Select: " c
    case "$c" in
      1) systemctl status "$active_svc" --no-pager -l | head -20 ;;
      2) systemctl start "$active_svc" && ok "Started" ;;
      3) systemctl stop "$active_svc" && ok "Stopped" ;;
      4) systemctl restart "$active_svc" && ok "Restarted" ;;
      5) journalctl -u "$active_svc" -n 50 --no-pager | tail -30 ;;
      0) return 0 ;;
      *) warn "Invalid option" ;;
    esac
    [ "$c" != "0" ] && { echo ""; read -r -p " Press Enter to continue..." _; }
  done
}

change_port_action() {
  banner
  echo -e " ${BG}  🔁 CHANGE PORT  ${N}"
  line "-" 58
  read_env
  if [ -z "${ENGINE:-}" ]; then err "Nothing installed yet."; pause_enter; return 1; fi
  local port
  port="$(ask_port "${PORT}")" || return 1
  local secret="${SECRET}"
  local engine="${ENGINE}"
  if [ "$engine" = "mtg" ]; then
    write_mtg_config "$port" "$secret"
  else
    write_telemt_config "$port" "${SECRET:2:32}" "$DOMAIN" "$TAG" "$IP4" || return 1
  fi
  write_env "$engine" "$port" "$secret" "$DOMAIN" "$CHANNEL" "$TAG" "$IP4" "$IP6"
  write_service "$engine"
  open_firewall "$port"
  ok "Port changed to $port — service restarted."
  pause_enter
}

rotate_secret_action() {
  banner
  echo -e " ${BG}  🎲 ROTATE SECRET  ${N}"
  line "-" 58
  read_env
  if [ -z "${ENGINE:-}" ]; then err "Nothing installed yet."; pause_enter; return 1; fi
  local secret16 tls_secret
  secret16="$(gen_hex16)"
  tls_secret="ee${secret16}$(str_to_hex "$DOMAIN")"
  if [ "$ENGINE" = "mtg" ]; then
    write_mtg_config "$PORT" "$tls_secret"
  else
    write_telemt_config "$PORT" "$secret16" "$DOMAIN" "$TAG" "$IP4"
  fi
  write_env "$ENGINE" "$PORT" "$tls_secret" "$DOMAIN" "$CHANNEL" "$TAG" "$IP4" "$IP6"
  write_service "$ENGINE"
  ok "New secret generated — old links are now invalid."
  print_proxy_info
  pause_enter
}

sponsor_action() {
  banner
  echo -e " ${BG}  🏷  SPONSORED CHANNEL SETUP  ${N}"
  line "-" 58
  read_env
  if [ -z "${ENGINE:-}" ]; then err "Nothing installed yet."; pause_enter; return 1; fi

  echo ""
  echo -e " ${W}Official Telegram sponsored-channel setup:${N}"
  echo -e " ${M}1)${N} Register this proxy in @MTProxybot with ${BC}/newproxy${N}."
  echo -e " ${M}2)${N} Send ${BC}${IP4:-SERVER_IP}:${PORT}${N} to the bot."
  echo -e " ${M}3)${N} When asked for the secret, send ONLY this 32-hex user secret:"
  echo ""
  echo -e "       ${BM}$(raw_bot_secret "${SECRET:-}")${N}"
  echo ""
  echo -e " ${M}4)${N} Copy the 32-hex ${BC}ad-tag${N} returned by @MTProxybot."
  echo -e " ${M}5)${N} Paste it below. The script validates it, writes ${BC}ad_tag${N},"
  echo -e "    keeps ${BC}use_middle_proxy = true${N}, and restarts Telemt."
  echo -e " ${M}6)${N} In @MTProxybot use ${BC}/myproxies${N} → select the proxy → ${BC}Set promotion${N}."
  echo -e " ${M}7)${N} Send a ${BC}public${N} channel link. Private channels cannot be promoted."
  echo -e " ${M}8)${N} Telegram may take about ${BC}1 hour${N} to propagate the promotion."
  echo ""

  if [ "${ENGINE}" != "telemt" ]; then
    warn "Current engine is mtg (Turbo). Official ad-tag sponsorship requires Telemt."
    read -r -p " 👉 Switch this installation to telemt now? [y/N]: " yn
    if [[ "$yn" =~ ^[Yy]$ ]]; then
      local tag secret16
      tag="$(ask_tag)"
      if [ -z "$tag" ]; then
        warn "No ad-tag supplied; switch cancelled."
        pause_enter
        return 0
      fi
      secret16="$(raw_bot_secret "${SECRET:-}")"
      install_telemt || { pause_enter; return 1; }
      write_telemt_config "$PORT" "$secret16" "$DOMAIN" "$tag" "$IP4" || { pause_enter; return 1; }
      write_env "telemt" "$PORT" "$SECRET" "$DOMAIN" "$CHANNEL" "$tag" "$IP4" "$IP6"
      systemctl stop "$MTG_SERVICE" 2>/dev/null
      systemctl disable "$MTG_SERVICE" 2>/dev/null
      rm -f "/etc/systemd/system/${MTG_SERVICE}.service"
      write_service "telemt"
      if wait_up "$TELEMT_SERVICE" "$PORT" 30; then
        ok "Switched to telemt and configured the validated ad-tag."
        info "Finish the Telegram-side promotion with /myproxies → Set promotion."
      else
        err "telemt failed to start — last log lines:"
        journalctl -u "$TELEMT_SERVICE" -n 12 --no-pager 2>/dev/null | sed 's/^/          /'
      fi
    fi
    pause_enter
    return 0
  fi

  local current_tag="${TAG:-}" newtag=""
  if valid_ad_tag "$current_tag"; then
    echo -e " ${W}Saved ad-tag:${N} ${G}${current_tag}${N}"
    if grep -Fqx "ad_tag = \"${current_tag}\"" "${MTTURBO_HOME}/telemt.toml" 2>/dev/null; then
      echo -e " ${W}Config:${N} ${G}ad_tag matches saved state${N}"
    else
      warn "Saved ad-tag and telemt.toml do not match. Re-enter the tag to repair it."
    fi
  else
    echo -e " ${W}Saved ad-tag:${N} ${Y}NONE${N}"
  fi

  read -r -p " 👉 Enter ad-tag from @MTProxybot (empty = keep current): " newtag
  newtag="$(printf '%s' "$newtag" | tr -d '[:space:]' | tr 'A-Z' 'a-z')"
  if [ -n "$newtag" ]; then
    if ! valid_ad_tag "$newtag"; then
      err "Invalid ad-tag. Nothing was changed."
      pause_enter
      return 1
    fi
    if ! write_telemt_config "$PORT" "$(raw_bot_secret "${SECRET:-}")" "$DOMAIN" "$newtag" "$IP4"; then
      err "Could not write Telemt configuration."
      pause_enter
      return 1
    fi
    write_env "telemt" "$PORT" "$SECRET" "$DOMAIN" "$CHANNEL" "$newtag" "$IP4" "$IP6"
    write_service "telemt"
    if wait_up "$TELEMT_SERVICE" "$PORT" 30; then
      ok "Validated ad-tag saved and Telemt restarted successfully."
      info "Now use @MTProxybot → /myproxies → Set promotion if you have not done so already."
      info "Telegram-side promotion propagation can take about 1 hour."
    else
      err "telemt failed to start — configuration was written but service is unhealthy."
      journalctl -u "$TELEMT_SERVICE" -n 12 --no-pager 2>/dev/null | sed 's/^/          /'
    fi
  elif [ -n "$current_tag" ] && ! valid_ad_tag "$current_tag"; then
    err "The saved ad-tag is invalid. Enter a valid 32-hex tag to repair it."
  fi
  pause_enter
}

health_check_action() {
  banner
  echo -e " ${BG}  🩺 HEALTH CHECK  ${N}"
  line "-" 58
  read_env
  if [ -z "${ENGINE:-}" ]; then err "Nothing installed yet."; pause_enter; return 1; fi
  local svc="$MTG_SERVICE"; [ "$ENGINE" = "telemt" ] && svc="$TELEMT_SERVICE"
  local fails=0

  if systemctl is-active --quiet "$svc"; then ok "systemd service '$svc' is running"; else err "service '$svc' is NOT running"; fails=$((fails+1)); fi
  if port_in_use "$PORT"; then ok "port $PORT is listening"; else err "port $PORT is NOT listening"; fails=$((fails+1)); fi
  if (timeout 3 bash -c "echo > /dev/tcp/127.0.0.1/$PORT") 2>/dev/null; then ok "local TCP connect to $PORT OK"; else err "local TCP connect FAILED"; fails=$((fails+1)); fi
  if sysctl -n net.ipv4.tcp_congestion_control 2>/dev/null | grep -q bbr; then ok "BBR congestion control active"; else warn "BBR not active (run menu 7)"; fi
  if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
    if ufw status | grep -q "$PORT/tcp"; then ok "UFW allows $PORT/tcp"; else warn "UFW active but $PORT/tcp not allowed (run: ufw allow $PORT/tcp)"; fi
  fi
  if [ "$ENGINE" = "telemt" ]; then
    # Sponsored channel (ad-tag) only works via Telegram MIDDLE-PROXY mode.
    # Success = an actual ME handshake/pool line. NOTE: pool_init FAILURE
    # lines also carry the 'middle_proxy' module path, so module presence
    # alone is NOT proof of a healthy sponsor path.
    local me_fail
    if journalctl -u "$svc" -n 300 --no-pager 2>/dev/null | grep -qE "RPC handshake OK|ME writer restored|ME writer created|Middle Proxy Mode"; then
      ok "telemt: Middle Proxy transport has been observed healthy"
      me_fail="$(journalctl -u "$svc" -n 100 --no-pager 2>/dev/null | grep -ciE 'All ME servers|ME (pool|servers?|writer).{0,40}(failed|lost|exhaust)|middle_proxy.*(failed|lost|exhaust)' || true)"
      if [ "${me_fail:-0}" -ge 5 ]; then
        if journalctl -u "$svc" -n 100 --no-pager 2>/dev/null | grep -qE 'RPC handshake OK|ME writer restored'; then
          info "telemt: ${me_fail} transient ME drops in last 100 lines — auto-restored (self-heals, normal)"
        else
          warn "telemt: ${me_fail} ME failures in last 100 lines, none recovered yet — sponsored channel may not inject"
        fi
        journalctl -u "$svc" -n 100 --no-pager 2>/dev/null | grep -iE 'All ME servers|ME (pool|servers?|writer).{0,40}(failed|lost|exhaust)|middle_proxy.*(failed|lost|exhaust)' | tail -n 3 | cut -c1-150 | sed 's/^/          /'
      fi
      local up_fail
      up_fail="$(journalctl -u "$svc" -n 100 --no-pager 2>/dev/null | grep -c 'Upstream failed after retries' || true)"
      if [ "${up_fail:-0}" -ge 3 ]; then
        info "direct-DC fallback is unreachable from this server (${up_fail} timeouts/100 lines) — harmless, traffic rides the ME path"
        if [ ! -f "/etc/systemd/system/${svc}.service.d/logfilter.conf" ]; then
          info "        silence the log spam:  mkdir -p /etc/systemd/system/${svc}.service.d && { echo '[Service]'; echo 'Environment=RUST_LOG=info,telemt::transport::upstream=off'; } > /etc/systemd/system/${svc}.service.d/logfilter.conf && systemctl daemon-reload && systemctl restart ${svc}"
        fi
      fi
    elif journalctl -u "$svc" -n 100 --no-pager 2>/dev/null | grep -q 'All ME servers'; then
      err "telemt: ME pool is DOWN (all ME servers failing) — listener may stay closed, proxy offline"
      fails=$((fails+1))
      warn "        telemt retries automatically. If 443 stays closed >2 min: systemctl restart ${svc}"
    else
      warn "telemt: Middle-Proxy traffic not seen yet — service may still be probing (~10s) or tag inactive"
    fi
    if valid_ad_tag "${TAG:-}" && grep -Fqx "ad_tag = \"${TAG}\"" "${MTTURBO_HOME}/telemt.toml" 2>/dev/null; then
      ok "ad-tag is valid and matches telemt.toml: ${TAG}"
      info "This confirms local configuration only; Telegram promotion must be set via @MTProxybot."
    else
      warn "No valid configured ad-tag found — sponsored channel setup is incomplete (menu 3)."
    fi
  fi
  echo ""
  local conns
  conns="$(ss -tnH "dport = :$PORT" 2>/dev/null | wc -l)"
  info "Established connections on $PORT: $conns"
  echo ""
  if [ "$fails" -eq 0 ]; then
    echo -e " ${BG}  ✅ ALL CHECKS PASSED — if clients still fail, the issue is${N}"
    echo -e " ${BG}     your server IP reputation or the client network.     ${N}"
  else
    echo -e " ${BR}  ❌ $fails check(s) failed — see messages above.${N}"
  fi
  echo ""
  echo -e " ${W}Last 5 log lines:${N}"
  journalctl -u "$svc" -n 30 --no-pager 2>/dev/null | grep -v 'early eof' | tail -n 5 | sed 's/^/   /'
  pause_enter
}

tuning_action() {
  banner
  echo -e " ${BG}  🔧 PERFORMANCE TUNING (BBR + kernel)  ${N}"
  line "-" 58
  apply_sysctl
  pause_enter
}

uninstall_action() {
  banner
  echo -e " ${BR}  ⚠️  UNINSTALL MTTURBO  ${N}"
  line "-" 58
  read_env
  local port="${PORT:-}" yn
  echo -e " ${W}This will remove ALL of the following:${N}"
  echo -e "   • systemd services ($MTG_SERVICE / $TELEMT_SERVICE)"
  echo -e "   • engine binaries (mtg / telemt)"
  echo -e "   • config & state ($MTTURBO_HOME, $MTTURBO_VAR)"
  echo -e "   • the ${B}mtturbo${W} command itself (/usr/local/bin/mtturbo)"
  echo -e "   • UFW rule + kernel tuning file (/etc/sysctl.d/99-mtturbo.conf)"
  echo ""
  yn="$(confirm " 👉 Remove everything? [y/N]: ")"
  if [[ "$yn" =~ ^[Yy]$ ]]; then
    step "Stopping & disabling services"
    # NOTE: stop/disable one unit at a time — a single call with both units
    # aborts the whole transaction when one of them does not exist, leaving
    # the running proxy alive after "uninstall".
    systemctl stop "$MTG_SERVICE" 2>/dev/null
    systemctl stop "$TELEMT_SERVICE" 2>/dev/null
    systemctl disable "$MTG_SERVICE" 2>/dev/null
    systemctl disable "$TELEMT_SERVICE" 2>/dev/null
    rm -f "/etc/systemd/system/${MTG_SERVICE}.service" "/etc/systemd/system/${TELEMT_SERVICE}.service"
    systemctl daemon-reload 2>/dev/null
    systemctl reset-failed 2>/dev/null
    ok "Services stopped and removed"

    step "Removing binaries, configs and the mtturbo command"
    rm -f "$MTG_BIN" "$TELEMT_BIN"
    rm -rf "$MTTURBO_HOME" "$MTTURBO_VAR"
    rm -f /usr/local/bin/mtturbo
    ok "Files removed (incl. /usr/local/bin/mtturbo)"

    step "Restoring firewall & kernel defaults"
    if [ -n "$port" ] && command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
      if ufw delete allow "${port}/tcp" >/dev/null 2>&1; then ok "UFW rule deleted for ${port}/tcp"; fi
    fi
    rm -f /etc/sysctl.d/99-mtturbo.conf
    sysctl --system >/dev/null 2>&1
    ok "Tuning file removed (a reboot restores stock kernel values)"

    echo ""
    line "=" 58 "$G"
    echo -e " ${BG}  ✅  MTTurbo fully uninstalled  ${N}"
    line "=" 58 "$G"
    echo -e " ${W}Verify: ${C}systemctl list-units | grep mtturbo${W}  →  nothing found${N}"
    echo -e " ${W}Verify: ${C}ss -tlnp | grep ${port:-PORT}${W}      →  not listening${N}"
    echo ""
    info "Bye! 🚀"
    exit 0
  else
    info "Cancelled."
    pause_enter
  fi
}


# ------------------------- Existing-install update ---------------------------
apply_telemt_fix_action() {
  banner
  echo -e " ${BG}  🔧 APPLY TELEMT MIDDLE-PROXY FIX  ${N}"
  line "-" 58
  read_env

  if [ "${ENGINE:-}" != "telemt" ]; then
    err "Current MTTurbo engine is not telemt."
    info "This action only updates an existing Telemt installation."
    pause_enter
    return 1
  fi

  if [ -z "${PORT:-}" ] || [ -z "${SECRET:-}" ]; then
    err "MTTurbo state is incomplete: PORT/SECRET missing."
    pause_enter
    return 1
  fi

  local ip4="${IP4:-}"
  if [ -z "$ip4" ]; then
    ip4="$(public_ip4)"
  fi
  if [ -z "$ip4" ]; then
    err "Could not determine the server's public IPv4."
    return 1
  fi

  local secret16="${SECRET:2:32}"
  if [ "${SECRET:0:2}" != "ee" ] || [ "${#secret16}" -ne 32 ]; then
    err "Current FakeTLS secret is not in the expected ee + 32-hex format."
    return 1
  fi

  if [ -f "${MTTURBO_HOME}/telemt.toml" ]; then
    cp -a "${MTTURBO_HOME}/telemt.toml" "${MTTURBO_HOME}/telemt.toml.backup-$(date +%Y%m%d-%H%M%S)"
    ok "Existing telemt.toml backed up."
  fi

  write_telemt_config "${PORT}" "${secret16}" "${DOMAIN}" "${TAG:-}" "$ip4" || return 1
  write_env "telemt" "$PORT" "$SECRET" "$DOMAIN" "${CHANNEL:-}" "${TAG:-}" "$ip4" "${IP6:-}"

  step "Refreshing Telemt systemd service"
  write_service "telemt"

  if wait_up "$TELEMT_SERVICE" "$PORT" 30; then
    ok "Telemt restarted and port $PORT is listening."
  else
    err "Telemt did not become ready within 30 seconds."
    journalctl -u "$TELEMT_SERVICE" -n 20 --no-pager 2>/dev/null | sed 's/^/        /'
    pause_enter
    return 1
  fi

  echo ""
  echo -e " ${G}Applied:${N}"
  echo -e "   Public NAT IPv4 : ${BC}${ip4}${N}"
  echo -e "   Outbound family  : ${BC}IPv4${N}"
  echo -e "   Middle Proxy     : ${BC}enabled${N}"
  echo -e "   Ad-tag           : ${BC}${TAG:-not set}${N}"
  echo ""
  echo -e " ${Y}Important:${N} this changes the Telemt configuration only."
  echo -e " Your existing FakeTLS secret and ad-tag are preserved."
  echo ""
  pause_enter
}

# ------------------------- Main menu ---------------------------------------
main_menu() {
  while true; do
    banner
    echo ""
    echo -e "   ${M}1)${N} ${BG} ⚡ Quick Install (Turbo / recommended) ${N}"
    echo -e "   ${M}2)${N} ${W}⚙  Advanced Install (engine, domain, tag...)${N}"
    echo -e "   ${M}3)${N} ${W}🏷  Sponsored channel & ad-tag setup${N}"
    echo -e "   ${M}4)${N} ${W}🔁  Change port${N}"
    echo -e "   ${M}5)${N} ${W}🎲  Rotate secret${N}"
    echo -e "   ${M}6)${N} ${W}🚀  Show proxy info & links${N}"
    echo -e "   ${M}7)${N} ${W}🩺  Health check${N}"
    echo -e "   ${M}8)${N} ${W}🔧  Performance tuning (BBR)${N}"
    echo -e "   ${M}9)${N} ${W}🛠  Service management${N}"
    echo -e "   ${M}10)${N} ${R}🗑  Uninstall${N}"
    echo -e "   ${M}11)${N} ${W}🔧  Apply Telemt Middle-Proxy fix${N}"
    echo -e "   ${M}0)${N} ${W}Exit${N}"
    echo ""
    read -r -p " 👉 Select an option: " choice
    case "$choice" in
      1)  quick_install_action ;;
      2)  advanced_install_action ;;
      3)  sponsor_action ;;
      4)  change_port_action ;;
      5)  rotate_secret_action ;;
      6)  banner; print_proxy_info; pause_enter ;;
      7)  health_check_action ;;
      8)  tuning_action ;;
      9)  service_menu_action ;;
      10) uninstall_action ;;
      11) apply_telemt_fix_action ;;
      0)  echo -e "${G}Bye! 🚀${N}"; exit 0 ;;
      *)  warn "Invalid option" ;;
    esac
  done
}

# ------------------------- Entry point -------------------------------------
require_root
check_os
command -v systemctl >/dev/null 2>&1 || { err "systemd is required."; exit 1; }

case "${1:-}" in
  --quick)       quick_install_action ;;
  --info)        print_proxy_info ;;
  --apply-fix)   apply_telemt_fix_action ;;
  --uninstall)   uninstall_action ;;
  *)             main_menu ;;
esac
