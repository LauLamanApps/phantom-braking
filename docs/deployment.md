# Deployment (Proxmox)

Production runs in its **own Debian LXC container** on Proxmox, without Docker: nginx, PHP 8.5-FPM, PostgreSQL 17
and supervisor (Messenger worker). Code is deployed with **Deployer** (`deploy.php`) from your own machine. TLS is
handled by a **reverse proxy** in front of it.

This page uses placeholders: `<LXC_IP>` for the container, `<PROXY_IP>` for the reverse proxy, `<CTID>` for the
container id. The real values are deliberately not in this public repository: keep them in `deploy.local.php` and
`docs/deployment.local.md`, which are git-ignored.

```
visitor ──HTTPS──▶ reverse proxy ──HTTP:80──▶ LXC: nginx ─▶ PHP-FPM ─▶ PostgreSQL
                   (TLS, all domains)              │                   └▶ S3 buckets (photos, videos)
                                                   └─ supervisor: messenger:consume async
```

| Domain | Behaviour |
|---|---|
| `phantombraking.eu` | The site |
| `www.phantombraking.eu` | 301 → `https://phantombraking.eu` (same path) |
| `spook-remmen.nl`, `www.spook-remmen.nl` | 301 → `https://phantombraking.eu/nl` |

Everything the server needs lives in [`.deploy/`](../.deploy): the provisioning script, the nginx vhost, PHP settings,
the supervisor worker, sudo rights for the deploy user, log rotation and the backup cron job.

## 1. Create the LXC (on the Proxmox host)

```bash
pveam update
pveam download local debian-12-standard_12.12-1_amd64.tar.zst   # `pveam available --section system` lists current names

pct create <CTID> local:vztmpl/debian-12-standard_12.12-1_amd64.tar.zst \
  --hostname phantombraking \
  --unprivileged 1 --features nesting=1 \
  --cores 2 --memory 4096 --swap 1024 \
  --rootfs <STORAGE>:20 \
  --net0 name=eth0,bridge=<BRIDGE>,ip=dhcp \
  --ssh-public-keys /root/.ssh/authorized_keys \
  --onboot 1
pct start <CTID>
pct exec <CTID> -- ip -4 -br addr show eth0    # the address it got: <LXC_IP>
```

- Put it on the network the reverse proxy can reach (add `tag=<VLAN>` to `--net0` for a VLAN), and **reserve its
  address in the router's DHCP** (or use a static `ip=`), because the deploy and the proxy point at it.
- **Debian 12 or 13**: older `pve-container` versions refuse current Debian 13 templates ("unsupported debian
  version"); Debian 12 always works. `provision.sh` supports both.
- `nesting=1` is the Proxmox default for unprivileged containers; systemd needs it.
- `--ssh-public-keys` gives root in the container the same SSH keys as root on the host; no password.
- Sizing: 20 GB holds the OS, 5 releases and the database for a long time (photos and videos go to S3). 2 cores and
  4 GB RAM are plenty; raise them in Proxmox when needed.

Then copy `deploy.local.php.dist` to `deploy.local.php` and set `hostname` to `<LXC_IP>`.

## 2. Provision (inside the LXC)

```bash
ssh root@<LXC_IP>        # or: pct enter <CTID> on the Proxmox host
apt-get update && apt-get install -y git
git clone https://github.com/LauLamanApps/phantom-braking.git /root/phantom-braking
bash /root/phantom-braking/.deploy/provision.sh
```

The script is safe to re-run. It:

- installs nginx, supervisor, Composer, PostgreSQL 17 (from apt.postgresql.org) and PHP 8.5 with the needed extensions (from packages.sury.org);
- installs the PHP settings (uploads up to 400 MB, memory for photo processing, OPcache for production);
- creates the `deployer` user (member of `www-data`, may only reload PHP-FPM and restart the worker via sudo);
- creates `/var/www/phantombraking.eu/shared/` and the database `phantom_braking` with a random password;
- writes `shared/.env.local` from [`.deploy/env.local.dist`](../.deploy/env.local.dist) with a generated `APP_SECRET`,
  `PLATE_FINGERPRINT_KEY` and the database URL (never overwritten afterwards);
- installs the nginx vhost (including both redirects), 14-day log rotation, the worker, nightly database dumps and
  automatic security updates.

Then:

1. Add your SSH public key to `/home/deployer/.ssh/authorized_keys`. The server clones the public repository over
   HTTPS, so it needs no GitHub key and your SSH agent is not forwarded.
2. Fill in `/var/www/phantombraking.eu/shared/.env.local`:
   - `TRUSTED_PROXIES`: the IP of the reverse proxy. Without it, every visitor appears to come from the proxy (one shared rate limit) and the site doesn't know it runs on HTTPS.
   - `MEDIA_S3_*`: see [Object storage](#object-storage).
   - `SENTRY_DSN` (optional), `RDW_APP_TOKEN` (optional).
   - `OPERATOR_NAME`, `OPERATOR_EMAIL`, `OPERATOR_ADDRESS`: shown in the privacy statement; the legal pages show a
     visible placeholder until they are set.

> **`PLATE_FINGERPRINT_KEY` must never change** once reports come in (repeat reports would no longer match) and must
> never be the dev value: with a known key, anyone can recover a plate from its fingerprint by trying them all.
> Keep a copy of `.env.local` in your password manager.

## 3. Reverse proxy

Set `TRUSTED_PROXIES=<PROXY_IP>` in `.env.local`, so the app sees the visitor's IP (rate limits) and knows it runs on
HTTPS. With **Nginx Proxy Manager**, add two Proxy Hosts:

| | Proxy Host 1 | Proxy Host 2 |
|---|---|---|
| Domain names | `phantombraking.eu`, `www.phantombraking.eu` | `spook-remmen.nl`, `www.spook-remmen.nl` |
| Scheme / forward host / port | `http` / `<LXC_IP>` / `80` | `http` / `<LXC_IP>` / `80` |
| Websockets, cache assets | off | off |
| SSL | Let's Encrypt certificate, Force SSL, HTTP/2, HSTS | same |

Both go to the container, whose nginx does the redirects (`www` → apex, `spook-remmen.nl` → `/nl`). NPM sends
`X-Forwarded-For`/`-Proto` and the original `Host` by default.

On **Proxy Host 1**, tab *Advanced*, add (a report can carry a 300 MB video plus photos; NPM's default limit is lower):

```nginx
client_max_body_size 400M;
proxy_read_timeout 300s;
proxy_send_timeout 300s;
```

Also check how long NPM keeps its access logs (`/data/logs` in the NPM container): the privacy statement promises
**at most 14 days**, for the proxy as well as the container.

## 4. DNS

`A`/`AAAA` records for all four host names to the public IP of the reverse proxy.

## 5. First deploy

From your machine, in this repository:

```bash
make deploy                 # current main branch
make deploy version=1.0.0   # or a tag v1.0.0
```

Deployer clones the code into a new release, runs `composer install --no-dev`, clears the cache, runs the
migrations, builds Tailwind and the AssetMapper files, switches the `current` symlink, reloads PHP-FPM (OPcache) and
restarts the worker. It keeps 5 releases; `make deploy-rollback` switches back to the previous one.

After the first deploy, inside the LXC:

```bash
cd /var/www/phantombraking.eu/current
sudo -u www-data php bin/console app:admin:create "Your Name"   # prints a one-time login link
```

Open the link and add a passkey. Passkeys are bound to `phantombraking.eu` (`DEFAULT_URI`): they don't work on another
domain, and a new domain means adding new passkeys via `app:admin:login-link`.

## Object storage

Photos and videos are stored in two private S3 buckets (`MEDIA_S3_*`); they never touch the LXC disk except while an
upload is processed. Options:

- **Sliplane Object Storage** (as planned): managed and off-site; you need its endpoint, keys and the two buckets.
- **Self-hosted on Proxmox** (e.g. RustFS, the S3 server of the dev stack, or Garage/MinIO in its own container):
  no monthly cost, but then backups of the media are your responsibility too. Either way the storage belongs in the
  privacy statement.

Create both buckets (private) before the first upload. With a self-hosted store,
`bin/console app:media:ensure-bucket` creates them.

## Backups

- **Database**: a nightly `pg_dump` to `/var/backups/phantom-braking/` (14 days kept), via `/etc/cron.d/phantom-braking-backup`.
- **Container**: schedule a Proxmox backup (`vzdump`, snapshot mode) of the LXC, e.g. nightly, to a different disk or a
  Proxmox Backup Server.
- **Off-site**: copy the dumps (or the vzdump backups) off the Proxmox host. A backup on the same machine doesn't survive
  a disk failure.
- **`.env.local`**: in your password manager. Without `PLATE_FINGERPRINT_KEY` a restored database can't link new
  reports to old ones.

Test a restore once: `pg_restore --clean --if-exists -d phantom_braking db-YYYY-MM-DD.dump`.

## Before going live

- [ ] `OPERATOR_*` set, and the legal pages reviewed (no placeholders visible).
- [ ] Processing agreements with the S3 provider and Sentry, if those are used.
- [ ] Logs on the LXC **and** the reverse proxy are kept for at most 14 days.
- [ ] HTTPS works on all four host names; `spook-remmen.nl` lands on `/nl`.
- [ ] An admin with at least two passkeys.
- [ ] A test report with a photo and a video goes through end to end (upload, moderation, publication).
- [ ] A backup restore was tested.
