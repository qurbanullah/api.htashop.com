# Production Server Guide

This document explains how to access the VPS production server, locate configuration files, and redeploy services.

---

## Server Details

| Item | Value |
|---|---|
| Provider | IONOS (ionos.co.uk) |
| Plan | VPS 8 / 16 / 480 |
| IP address | `87.106.56.165` |
| SSH user | `volvicon` |
| Stack | Docker Swarm |
| Panel | https://my.ionos.co.uk → Servers & Cloud |

---

## Before You Connect

**Turn off your VPN** before connecting via SSH. Some VPNs block outbound SSH (port 22) or cause the connection to time out silently.

---

## Step 1 — Find the SSH Password (if you don't have it saved)

1. Go to https://my.ionos.co.uk and log in with your IONOS account credentials.
   _(This is your IONOS website login — not the server password.)_
2. Click **Servers & Cloud** in the top navigation.
3. Select your VPS (VPS 8 16 480).
4. Look for the **Initial Password** field on that page — this is the SSH password for the server itself.

> **Note:** If someone has previously changed the SSH password with `passwd`, the Initial Password shown by IONOS will no longer work. Ask the person who changed it for the current password.

---

## Step 2 — Open PowerShell as Administrator

Press `Win`, type `PowerShell`, right-click **Windows PowerShell**, and choose **Run as administrator**.

---

## Step 3 — Connect via SSH

```powershell
ssh volvicon@87.106.56.165
```

When it shows `volvicon@87.106.56.165's password:`:

- **The cursor will not move and nothing will appear as you type** — this is normal, it is not frozen.
- **To paste:** right-click once in the PowerShell window (no Ctrl+V), then press **Enter**.
- You have roughly 5–10 attempts before the IP is temporarily blocked by `fail2ban`. If blocked, wait 15 minutes and try again.

> **Alternative if blocked or SSH fails:** Log in to the IONOS panel → your VPS → look for **Console** or **KVM/VNC** access. This gives you a browser-based terminal that bypasses SSH and IP bans entirely.

---

## Step 4 — Find Configuration Files on the Server

Once logged in, the repositories are cloned under the home directory. Check with:

```bash
ls ~/
ls ~/volvicon/
```

The `.env.docker` files (production environment config) are located in each deployed app directory, for example:

```bash
/home/volvicon/app/api/.env.docker
/home/volvicon/app/manage/.env.docker
```

To search for any `.env.docker` file across the whole server:

```bash
find ~ -name ".env.docker" 2>/dev/null
```

---

## Step 5 — Edit a Configuration File

Use `nano` to edit a file:

```bash
nano /home/volvicon/app/api/.env.docker
```

**Keys inside nano:**
- Arrow keys to move the cursor
- Type to edit
- `Ctrl+O` then `Enter` — save
- `Ctrl+X` — exit

### Common values to update after a password change

If you change the password for `team@volvicon.com` on the Mailcow server, update this line in `.env.docker`:

```dotenv
MAIL_PASSWORD=your_new_password_here
```

> The Mailcow SMTP server (`mail.volvicon.com`) only accepts connections on **port 465 with implicit SSL**.
> Laravel uses `MAIL_SCHEME=smtps` (not `MAIL_ENCRYPTION=tls`) for this.
> See `.env.docker.example` in this repository for the full reference.

The Python bulk email tool (`tools/emails-sender/`) reads its password from a separate file:

```
tools/emails-sender/.env   ←  update VOLVICON_SMTP_PASSWORD here too
```

---

## Step 6 — Rebuild and Redeploy After a Config Change

Because `.env.docker` is **baked into the Docker image at build time** (see `Dockerfile`), you must rebuild and redeploy whenever you change it. Do this from your **local Windows machine**, not the server:

```powershell
cd C:\dev\real3d\websites\api.volvicon.com
bash deploy.sh
```

`deploy.sh` will:
1. Build a new Docker image with the updated `.env.docker`
2. Save it to a `.tar` file
3. Copy it to the server via SCP
4. Load it on the server
5. Update the running Docker Swarm service (zero-downtime rolling update)

> Make sure you are **not on a VPN** during the deploy — it uses `scp` and `ssh` to the same server IP.

---

## Step 7 — Check Logs After Deploying

To confirm emails are working after a redeploy, SSH into the server and tail the Laravel log:

```bash
tail -f ~/volvicon/api.volvicon.com/storage/logs/laravel.log
```

Then trigger a test action (e.g. send a contact message from the website). You should see log output. If there is still an SMTP error it will appear here.

Queue worker log:

```bash
tail -f /home/volvicon/app/api/storage/logs/queue-worker.log
```

---

## Quick Reference — Common Tasks

| Task | Command |
|---|---|
| SSH into server | `ssh volvicon@87.106.56.165` |
| Edit API env config | `nano /home/volvicon/app/api/.env.docker` |
| Rebuild & deploy API | `bash deploy.sh` (run locally in `api.volvicon.com/`) |
| Check Laravel logs | `tail -f /home/volvicon/app/api/storage/logs/laravel.log` |
| Check queue worker logs | `tail -f /home/volvicon/app/api/storage/logs/queue-worker.log` |
| Check running containers | `docker service ls` (on the server) |
| Restart a service | `docker service update --force volvicon_api` (on the server) |
| Exit SSH session | `exit` |

