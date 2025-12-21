param(
    [string]$Host = "127.0.0.1",
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

function Stop-PortListeners {
    param([int]$Port)

    try {
        $connections = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
        foreach ($connection in $connections) {
            if ($connection.OwningProcess -and $connection.OwningProcess -ne $PID) {
                Stop-Process -Id $connection.OwningProcess -Force -ErrorAction SilentlyContinue
            }
        }
        return
    } catch {
        # Older Windows/PowerShell: fall back to netstat parsing.
    }

    $lines = netstat -ano | Select-String -Pattern (":$Port\s+.*LISTENING\s+(\d+)$")
    foreach ($line in $lines) {
        if ($line.Matches.Count -gt 0) {
            $pid = [int]$line.Matches[0].Groups[1].Value
            if ($pid -and $pid -ne $PID) {
                Stop-Process -Id $pid -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $repoRoot

Stop-PortListeners -Port $Port

Write-Host "Starting Laravel dev server at http://$Host`:$Port (single process, no-reload) ..."
php artisan serve --host=$Host --port=$Port --no-reload
