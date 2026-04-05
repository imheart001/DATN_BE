<#
.SYNOPSIS
    Bootstrap this Laravel project on Windows.
.DESCRIPTION
    Installs PHP/Node dependencies, copies .env, generates an app key,
    creates storage links, runs migrations/seeds, and optionally builds assets.
.NOTES
    Run from the project root in PowerShell: .\setup-windows.ps1
#>

[CmdletBinding()]
param(
    [switch]$SkipNpm,
    [switch]$Dev,
    [switch]$Build
)

function Throw-MissingExecutable {
    param([string]$Name)
    Write-Error "Missing required executable: $Name. Please install it and ensure it is on PATH."
    exit 1
}

function Ensure-Command {
    param([string]$Name)
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        Throw-MissingExecutable $Name
    }
}

Write-Host 'Starting native Windows setup for Laravel project...' -ForegroundColor Cyan

Ensure-Command php
Ensure-Command composer
Ensure-Command node
Ensure-Command npm

$projectRoot = Resolve-Path .
Set-Location $projectRoot

if (-not (Test-Path .env)) {
    if (Test-Path .env.example) {
        Copy-Item .env.example .env
        Write-Host 'Copied .env.example to .env' -ForegroundColor Green
    } else {
        Write-Error '.env.example not found; cannot create .env.'
        exit 1
    }
} else {
    Write-Host '.env already exists; leaving it unchanged.' -ForegroundColor Yellow
}

Write-Host 'Installing PHP dependencies with Composer...' -ForegroundColor Cyan
$composerArgs = @('install', '--prefer-dist', '--no-interaction')
$composerProcess = Start-Process composer -ArgumentList $composerArgs -NoNewWindow -Wait -PassThru
if ($composerProcess.ExitCode -ne 0) { exit $composerProcess.ExitCode }

if (-not $SkipNpm) {
    Write-Host 'Installing JavaScript dependencies with npm...' -ForegroundColor Cyan
    $npmProcess = Start-Process npm -ArgumentList 'install' -NoNewWindow -Wait -PassThru
    if ($npmProcess.ExitCode -ne 0) { exit $npmProcess.ExitCode }
}

Write-Host 'Generating application key...' -ForegroundColor Cyan
$process = Start-Process php -ArgumentList 'artisan', 'key:generate', '--ansi' -NoNewWindow -Wait -PassThru
if ($process.ExitCode -ne 0) { exit $process.ExitCode }

Write-Host 'Creating storage symlink...' -ForegroundColor Cyan
$process = Start-Process php -ArgumentList 'artisan', 'storage:link', '--ansi' -NoNewWindow -Wait -PassThru
if ($process.ExitCode -ne 0) { exit $process.ExitCode }

Write-Host 'Running migrations and seeders...' -ForegroundColor Cyan
$process = Start-Process php -ArgumentList 'artisan', 'migrate', '--seed', '--force', '--ansi' -NoNewWindow -Wait -PassThru
if ($process.ExitCode -ne 0) { exit $process.ExitCode }

if ($Build) {
    Write-Host 'Building assets with npm run build...' -ForegroundColor Cyan
    $process = Start-Process npm -ArgumentList 'run', 'build' -NoNewWindow -Wait -PassThru
    if ($process.ExitCode -ne 0) { exit $process.ExitCode }
} elseif ($Dev) {
    Write-Host 'Starting Vite dev server with npm run dev...' -ForegroundColor Cyan
    Start-Process npm -ArgumentList 'run', 'dev' -NoNewWindow
    Write-Host 'Vite dev server started in a new process.' -ForegroundColor Green
}

Write-Host 'Native Windows setup completed.' -ForegroundColor Green
Write-Host 'Next step: run `php artisan serve --host=127.0.0.1 --port=8000` and open http://127.0.0.1:8000' -ForegroundColor Yellow
