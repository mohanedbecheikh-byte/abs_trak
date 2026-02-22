# AbsTrack Deployment

## What Is Already Prepared In This Repo
- Environment loading from a local `.env` file (`includes/env.php`).
- Production-safe `.env.example` template.
- Apache hardening via `.htaccess` and deny rules in `includes/` and `sql/`.
- Docker production stack in `docker-compose.prod.yml`.
- One-command deployment scripts in `scripts/deploy.sh` and `scripts/deploy.ps1`.

## 1. First-Time Server Setup
1. Install Docker + Docker Compose plugin.
2. Clone the repository.
3. Copy environment file:
   - `cp .env.example .env`
4. Edit `.env` with real values (minimum):
   - `DB_HOST=mysql`
   - `DB_NAME=abstrack`
   - `DB_USER=abstrack_user`
   - `DB_PASS=<strong password>`
   - `MYSQL_ROOT_PASSWORD=<strong password>`
   - `APP_SHOW_DEMO_CREDENTIALS=0`
   - `TRUST_PROXY_HEADERS=1` (only if behind reverse proxy/load balancer)
   - `INVITATION_CODE=<optional signup code>`

## 2. Deploy (Docker)
- Linux:
  - `bash scripts/deploy.sh main docker-compose.prod.yml`
- Windows PowerShell:
  - `powershell -ExecutionPolicy Bypass -File .\scripts\deploy.ps1 -Branch main -ComposeFile docker-compose.prod.yml`

## 3. Post-Deploy
1. Seed optional records:
   - `docker compose -f docker-compose.prod.yml exec app php seed.php`
2. Confirm services:
   - `docker compose -f docker-compose.prod.yml ps`
3. Check app logs:
   - `docker compose -f docker-compose.prod.yml logs -f app`
4. Configure your domain DNS to point to this server.
5. Put Nginx/Caddy in front of this stack for HTTPS (recommended), or enable TLS directly at your edge/load balancer.

## 4. Update Deployment (Later)
- Repeat:
  - `bash scripts/deploy.sh main docker-compose.prod.yml`

## Notes
- Do not commit `.env`.
- Do not expose MySQL publicly in production.
- If you use Apache/Nginx without Docker, keep the `.htaccess` deny rules and set your web root to the project directory only after confirming sensitive files are blocked.
