# =============================================
# SSH Tunnel Keep-Alive Script - Tiqan System
# Run: powershell -ExecutionPolicy Bypass -File keep-tunnel.ps1
# =============================================

$VPS_IP = "92.113.31.147"
$VPS_USER = "root"
$LOCAL_PORT = 80
$REMOTE_PORT = 8080

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  SSH Tunnel - Tiqan WA Bot" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "VPS: $VPS_USER@$VPS_IP" -ForegroundColor Green
Write-Host "Tunnel: VPS:$REMOTE_PORT -> localhost:$LOCAL_PORT" -ForegroundColor Green
Write-Host ""
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

while ($true) {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] Opening SSH tunnel..." -ForegroundColor Yellow

    ssh -o "ServerAliveInterval=30" -o "ServerAliveCountMax=3" -o "ExitOnForwardFailure=yes" -N -R "${REMOTE_PORT}:localhost:${LOCAL_PORT}" "${VPS_USER}@${VPS_IP}"

    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] Tunnel disconnected! Reconnecting in 5 seconds..." -ForegroundColor Red
    Start-Sleep -Seconds 5
}
