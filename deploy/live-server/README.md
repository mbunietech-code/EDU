# Live classroom — self-hosted video infrastructure

The live classroom (`/learn/rooms/{room}/live`) runs entirely on infrastructure you control.
There is **no Jitsi, no meet.jit.si, no JaaS/8x8, no LiveKit Cloud and no other hosted
video service**. The browser talks to:

```
Browser ──HTTPS──▶ Laravel app            auth, room rules, permissions, chat/Q&A, attendance,
   │                (your domain)          short-lived media tokens, moderation, recordings
   │
   ├──WSS (443)──▶ LiveKit SFU            signalling + WebRTC media (UDP 50000-60000, TCP 7881)
   │                live.<domain>          ◀── Laravel moderates via its server API + receives webhooks
   │
   └──STUN/TURN──▶ coturn                 relay for learners behind strict NAT / firewalls
                    turn.<domain>          (UDP/TCP 3478, TLS 5349)

                      ┌────────── LiveKit SFU (one server) ──────────┐
   Teacher cam/mic ──▶│  receives each stream once, forwards it to   │──▶ Student
   Student cam/mic ──▶│  every subscriber (simulcast + adaptive       │──▶ Student
                      │  quality); Egress records to MP4             │──▶ Student
                      └──────────────────────────────────────────────┘
```

## Why this architecture

| Option | Verdict |
| --- | --- |
| Peer-to-peer mesh | Rejected — every participant uploads to every other participant; breaks beyond ~4 people. |
| **LiveKit (open source, Apache-2.0)** | **Chosen.** One Go binary gives an SFU, WSS signalling, simulcast, adaptive stream, dynacast, automatic reconnection/ICE restart, server API for moderation and Egress for recording. Its browser SDK is bundled into our own JS (no external script). |
| mediasoup | Excellent SFU library but no signalling server — we would have to write and operate a Node signalling service ourselves. |
| Janus | Mature, but plugin configuration + custom signalling; more moving parts for the same result. |

Laravel stays the authority: it decides who may join and what they may publish, signs a token
that expires in 10 minutes carrying exactly those rights, and performs every host action
(mute, rights, remove, lock, record, end) through the SFU's server API. The browser never gets
room-admin rights. Chat and Q&A are stored and sanitised by Laravel (data messages on the SFU
are only "refresh now" nudges), so shared hosting without WebSockets still works for the app.

## 1. What you need

| Item | Minimum | Recommended |
| --- | --- | --- |
| Live server (VPS / dedicated, **public IPv4**) | 2 vCPU, 4 GB RAM, 1 Gbit/s, Ubuntu 22.04/24.04 | 4–8 vCPU, 8–16 GB RAM |
| Recording (Egress, optional) | +3 vCPU and +2 GB RAM **per simultaneous recording** | separate server for Egress |
| Docker Engine + Compose plugin | 24+ | latest |
| Two DNS names | `live.example.com`, `turn.example.com` → server IP | |

> **Shared hosting (e.g. Hostinger shared/Cloud plans) cannot run the SFU or TURN** — they need
> long-running processes and thousands of UDP ports. Keep Laravel where it is if you like, and
> run this folder on a VPS you control. Automatic recording import needs Laravel and Egress to
> share a folder (same server, or a mounted share); otherwise leave `LIVE_RECORDING_ENABLED=false`
> and upload recordings in the studio.

### Capacity guide (LiveKit SFU, VP8 simulcast, adaptive stream on)

| Class shape | Server (SFU only) | Server bandwidth (approx.) |
| --- | --- | --- |
| 1 teacher + 30 learners watching (720p teacher, learners mostly muted) | 2 vCPU / 4 GB | up ~2.5 Mbit/s in, ~30–60 Mbit/s out |
| 1 teacher + 100 learners | 4 vCPU / 8 GB | ~100–180 Mbit/s out |
| 10–15 people all on camera (grid) | 4 vCPU / 8 GB | ~50–120 Mbit/s |
| Several such rooms at once | add ~1 vCPU per ~50–80 subscribed video streams | sum of the rooms |

Per client (defaults set in `classroom.js`):

- Camera: captured at 720p, published as simulcast layers 180p / 360p / 720p (≈150 kbit/s – 1.7 Mbit/s).
  Each viewer only receives the layer its tile needs (small tiles → 180p/360p).
- Screen share: up to 1080p at 15 fps (≈1–2.5 Mbit/s), optimised for text.
- Audio: Opus with DTX, ≈20–40 kbit/s.
- Anyone relayed through TURN doubles their traffic on the TURN server.

## 2. DNS

| Record | Type | Value |
| --- | --- | --- |
| `live.example.com` | A | your server's public IPv4 |
| `turn.example.com` | A | the same IP (or a second one, see "TURN on 443") |

Use DNS only (no CDN proxy such as the orange Cloudflare cloud — WebRTC/TURN cannot be proxied).

## 3. Firewall

| Port | Protocol | Purpose |
| --- | --- | --- |
| 80 | TCP | Let's Encrypt HTTP challenge (Caddy) |
| 443 | TCP | WSS signalling + server API (Caddy → LiveKit) |
| 7881 | TCP | WebRTC over TCP fallback |
| 50000–60000 | UDP | WebRTC media (LiveKit) |
| 3478 | UDP + TCP | STUN / TURN |
| 5349 | TCP | TURN over TLS |
| 49152–49999 | UDP | TURN relay ports |
| 7880, 6379, 9090 | — | **keep closed** (local only) |

Ubuntu (ufw):

```bash
sudo ufw allow 22/tcp && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp
sudo ufw allow 7881/tcp && sudo ufw allow 50000:60000/udp
sudo ufw allow 3478 && sudo ufw allow 5349/tcp && sudo ufw allow 49152:49999/udp
sudo ufw enable
```

Also open the same ports in your provider's cloud firewall / security group, if it has one.

## 4. Production deployment

```bash
# on the live server
sudo apt update && sudo apt install -y docker.io docker-compose-v2 git
sudo mkdir -p /opt/live-server/recordings
# copy this folder to /opt/live-server (git clone, scp or rsync)
cd /opt/live-server
cp .env.example .env
nano .env                     # domains, PUBLIC_IP, secrets (see below)
nano livekit.yaml             # webhook.api_key = LIVE_API_KEY, webhook.urls = https://<your app>/api/webhooks/livekit
docker compose up -d          # Caddy + LiveKit + coturn
docker compose logs -f livekit
```

Generate secrets:

```bash
echo "API$(openssl rand -hex 8)"     # LIVE_API_KEY
openssl rand -base64 48               # LIVE_API_SECRET (≥ 32 characters)
openssl rand -hex 32                  # TURN_SECRET
```

Recording (optional):

```bash
docker compose --profile recording up -d   # adds Redis + Egress
```

If Laravel runs on the same server, let it read and delete the recordings:

```bash
sudo chgrp -R www-data /opt/live-server/recordings
sudo chmod -R 2775 /opt/live-server/recordings
sudo setfacl -d -m g:www-data:rwx /opt/live-server/recordings   # files Egress creates later
```

Updates: change the `*_VERSION` values in `.env`, then `docker compose pull && docker compose up -d`.

## 5. SSL / TLS

- Caddy obtains and renews Let's Encrypt certificates for `live.` and `turn.` automatically
  (ports 80/443 must reach the server; set `ACME_EMAIL`).
- Browsers connect with `wss://live.example.com` — never `ws://` in production (mixed-content
  and camera permissions require HTTPS end to end).
- coturn uses the `turn.` certificate from Caddy's store for `turns:` on 5349.
- The Laravel app itself must be served over **HTTPS** (`APP_URL=https://…`), with
  `SESSION_SECURE_COOKIE=true`. Camera, microphone and screen sharing only work on secure pages.
- The classroom page sends `Permissions-Policy: camera=(self), microphone=(self), display-capture=(self)`.
  If a proxy/CDN adds its own `Permissions-Policy`, it must allow the same.

### TURN on port 443 (strict corporate / school networks)

Some networks allow only TCP 443. Give the server a second public IP, point `turn.example.com`
at it, and run coturn's TLS listener on `443` of that IP (`--tls-listening-port=443
--listening-ip=<second ip>`), with Caddy bound only to the first IP. Then use
`turns:turn.example.com:443?transport=tcp` in `TURN_SERVER_URL`.

## 6. Laravel configuration (`.env` of the app)

| Variable | Example | Notes |
| --- | --- | --- |
| `LIVE_SERVER_URL` | `wss://live.example.com` | browser signalling address |
| `LIVE_SERVER_API_URL` | `https://live.example.com` | optional; derived from the URL above |
| `LIVE_SERVER_API_KEY` | `APIxxxxxxxx` | = `LIVE_API_KEY` |
| `LIVE_SERVER_API_SECRET` | 48 random chars | = `LIVE_API_SECRET` (never commit) |
| `LIVE_TOKEN_TTL_MINUTES` | `10` | join tokens are short-lived |
| `STUN_SERVER_URLS` | `stun:turn.example.com:3478` | |
| `TURN_SERVER_URL` | `turn:turn.example.com:3478?transport=udp,turn:turn.example.com:3478?transport=tcp,turns:turn.example.com:5349?transport=tcp` | comma-separated |
| `TURN_SERVER_SECRET` | = `TURN_SECRET` | expiring per-user credentials (preferred) |
| `TURN_SERVER_USERNAME` / `TURN_SERVER_CREDENTIAL` | | only if you run coturn with a fixed user instead |
| `LIVE_ICE_TRANSPORT_POLICY` | `all` | `relay` forces TURN (testing) |
| `LIVE_RECORDING_ENABLED` | `true` | only with the Egress profile running |
| `LIVE_RECORDING_IMPORT_DIR` | `/opt/live-server/recordings` | the Egress output folder as seen by Laravel |

Then:

```bash
php artisan migrate                 # or Admin → Database → apply 0015_2026_09_25_self_hosted_live_rooms.sql
php artisan config:cache
npm run build                       # on your computer; upload public/build
# queue worker + scheduler must be running (recording import, reminders, stale-room cleanup)
```

Admin → Learning → **Live sessions** shows the configuration status; press
**Check video server** to test that Laravel can reach the SFU API.

## 7. Local development

`localhost` counts as a secure origin, so camera and microphone work on
`http://127.0.0.1:8000` without certificates.

```bash
# 1. SFU in dev mode (API key "devkey", secret "secret")
docker run --rm -p 7880:7880 -p 7881:7881 -p 7882:7882/udp livekit/livekit-server --dev --bind 0.0.0.0

# 2. Laravel .env
LIVE_SERVER_URL=ws://127.0.0.1:7880
LIVE_SERVER_API_KEY=devkey
LIVE_SERVER_API_SECRET=secret

# 3. App
php artisan serve        # http://127.0.0.1:8000
npm run dev              # or npm run build
```

(Without Docker: download the `livekit-server` binary from the LiveKit GitHub releases and run
`livekit-server --dev --bind 0.0.0.0`.) Two browser profiles (or one normal + one private window)
logged in as a host and a learner are enough to test a call.

### LAN testing (phones on the same Wi-Fi, e.g. `http://192.168.1.176:8000`)

Browsers **block the camera on `http://<LAN IP>`**. Use HTTPS on the LAN:

```bash
# once: create a local CA and a certificate for your LAN IP
mkcert -install
mkcert 192.168.1.176
# TLS proxy for the app (8443) and the SFU (7443)
caddy reverse-proxy --from https://192.168.1.176:8443 --to 127.0.0.1:8000 &
caddy reverse-proxy --from https://192.168.1.176:7443 --to 127.0.0.1:7880 &
# SFU must advertise the LAN IP
docker run --rm -p 7880:7880 -p 7881:7881 -p 7882:7882/udp livekit/livekit-server --dev --bind 0.0.0.0 --node-ip 192.168.1.176
```

Set `LIVE_SERVER_URL=wss://192.168.1.176:7443` and `APP_URL=https://192.168.1.176:8443`, and
install mkcert's root CA on the phone. For a quick desktop-only test, Chrome can instead be started with
`--unsafely-treat-insecure-origin-as-secure=http://192.168.1.176:8000` (never use this for real users).

## 8. Testing checklist

| # | Test | How | Expected |
| --- | --- | --- | --- |
| 1 | Teacher creates room | Studio → Live rooms → Create | Room saved, scheduled |
| 2 | Teacher starts session | Classroom → Start class | Status Live, learners notified |
| 3 | Student joins | Learner opens the class → Join | Tile appears for both |
| 4 | Teacher camera | Teacher camera on | Learner sees teacher video |
| 5 | Student camera | Learner camera on (if allowed) | Teacher sees learner |
| 6 | Microphone | Both speak | Audio both ways; speaking ring shows |
| 7 | Multiple participants | 3–5 devices | All tiles; grid layout works |
| 8 | Chat | Send chat / question | Appears instantly for others, timestamps, unread badge |
| 9 | Screen sharing | Teacher → Share (tab/window/screen) | Shared screen becomes the main tile |
| 10 | Mute | People → ⋯ → Mute mic / Mute everyone | Learner's mic turns off |
| 11 | Participant removal | People → ⋯ → Remove | Learner disconnected, cannot rejoin |
| 12 | Reconnection | Turn Wi-Fi off 10 s, then on | "Reconnecting…", then back without reload |
| 13 | Authorization | Learner opens a private room they are not invited to | 403 |
| 14 | Unauthorized user | Guest / tampered token | Login page / SFU refuses (token signature) |
| 15 | TURN fallback | `LIVE_ICE_TRANSPORT_POLICY=relay`, join | Call still works (chrome://webrtc-internals shows `relay` candidates) |
| 16 | Mobile browser | Android Chrome, iOS Safari | Join, camera/mic, chat work (screen share hidden on phones) |
| 17 | Desktop browser | Chrome, Edge, Firefox, Safari | All features |
| 18 | HTTPS | Visit over http | Redirect to https; camera prompt appears on https only |
| 19 | Secure WebSocket | DevTools → Network → WS | `wss://live.example.com/rtc…` |
| 20 | Session ends | Host → End class for everyone | Everyone sees "This class has ended"; attendance saved |

Automated tests (no real server needed — the SFU API is faked):

```bash
php artisan test tests/Feature/Learning
```

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| "Live video is unavailable" | `LIVE_SERVER_*` not set / wrong | Admin → Live sessions shows the missing variable |
| "Could not connect to the live classroom" | `wss://live.` not reachable, TLS not issued | `curl -I https://live.example.com` must return 404/200 from LiveKit; check `docker compose logs caddy` |
| Connected but no video/audio | UDP 50000–60000 blocked or wrong public IP | Open the ports; set `rtc.node_ip` in livekit.yaml |
| Works on Wi-Fi, fails on mobile data / office | No TURN / TURN blocked | Configure coturn, `TURN_SERVER_URL`, try `turns:` 5349 or TURN on 443 |
| TURN never used | Wrong `TURN_SERVER_SECRET`, clock skew | Secrets must match; keep server clocks in sync (NTP) |
| Camera prompt never appears | Page not on HTTPS | Serve the app over HTTPS (or use localhost in development) |
| "Your camera is being used by another app" | Zoom/Teams/other tab holds the device | Close it and retry |
| Learner cannot unmute | Host has not allowed mic, or room switch off | People → ⋯ → Microphone on, or Host tools → Participants may use mic & camera |
| "The host has locked this class" | Room locked | Host tools → Unlock |
| Kicked with "joined from another tab" | Same account opened the class twice | Use one tab per account |
| Recording stays "processing" | Egress not running / webhook not reaching Laravel / folder permissions | `docker compose --profile recording ps`, check `webhook.urls`, `LIVE_RECORDING_IMPORT_DIR` readable by PHP, queue worker running |
| 401 on `/api/webhooks/livekit` | `webhook.api_key` ≠ `LIVE_SERVER_API_KEY`, or different secret | Use the same key pair everywhere |

Useful tools: `chrome://webrtc-internals`, `docker compose logs -f livekit coturn egress`,
`turnutils_uclient` (from coturn) to test TURN credentials.

## 10. Security summary

- Only authenticated users reach the classroom; Laravel policies decide `view` / `join` / `moderate`.
- Join tokens: HS256, signed with the API secret, **10-minute lifetime**, bound to one room and one
  identity, listing exactly the sources the user may publish; clients never get admin grants.
- A removed user is refused new tokens and disconnected on the SFU; a locked room refuses newcomers.
- Moderation, chat, materials and recordings are authorised and validated server-side; chat is
  stored as text and rendered with `x-text` (no HTML); rate limits on every endpoint.
- Webhooks are verified (signed token + SHA-256 of the body); recording files are only read by
  plain file name from the configured import folder.
- TURN credentials are per user and expire (TURN REST API); coturn refuses relaying into private networks.
- Secrets live only in `.env` files that are not committed.
