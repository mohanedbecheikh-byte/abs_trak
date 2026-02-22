param(
    [string]$Branch = "main",
    [string]$ComposeFile = "docker-compose.prod.yml"
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    throw "git is required."
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "docker is required."
}

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "Created .env from .env.example. Update secrets before exposing this server publicly."
}

git fetch origin
git checkout $Branch
git pull --ff-only origin $Branch

docker compose -f $ComposeFile up -d --build
docker compose -f $ComposeFile ps

Write-Host "Deployment completed for branch: $Branch"
