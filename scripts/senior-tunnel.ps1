$ErrorActionPreference = 'Stop'
$rootAti = Split-Path $PSScriptRoot -Parent
$keyAti = Join-Path $rootAti '.local-runtime/united-senior-tunnel'
$hostsAti = Join-Path $rootAti '.local-runtime/vps-known-hosts'
if (!(Test-Path -LiteralPath $keyAti)) { throw 'Chave do túnel Senior ausente. Configure a chave dedicada antes de iniciar.' }
while ($true) {
    & "$env:WINDIR\System32\OpenSSH\ssh.exe" -N -T -i $keyAti -p 22022 -o BatchMode=yes -o "UserKnownHostsFile=$hostsAti" -o ExitOnForwardFailure=yes -o ServerAliveInterval=30 -o ServerAliveCountMax=3 -R '172.17.0.1:21433:127.0.0.1:1433' united-tunnel@143.95.167.97
    Start-Sleep -Seconds 15
}
