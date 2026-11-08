$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$timestamp = (Get-Date).ToUniversalTime().ToString('yyyyMMdd-HHmmss')
$backupRoot = Join-Path (Split-Path $projectRoot -Parent) ("RemindEDU-backups\$timestamp")

New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null
Copy-Item (Join-Path $projectRoot 'storage\data\*') $backupRoot -Recurse -Force
Write-Output "Backup created: $backupRoot"
