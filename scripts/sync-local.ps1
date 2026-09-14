param([switch]$SkipAssets)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
function Invoke-DockerSyncAti {
    & docker @args
    if ($LASTEXITCODE -ne 0) { throw "Falha ao sincronizar o runtime United: $($args[0])" }
}
$inspectionJsonAti = & docker inspect united-php
if ($LASTEXITCODE -ne 0) { throw 'O Docker não respondeu à inspeção do United. Tente novamente após recuperar o motor Docker.' }
$inspectionAti = ($inspectionJsonAti | ConvertFrom-Json)[0]
$runtimeAti = $inspectionAti.Mounts | Where-Object { $_.Destination -eq '/var/www/html' }
if ($runtimeAti.Type -ne 'volume' -or $runtimeAti.Name -ne 'united-ati-local-runtime') { throw 'Sincronização permitida somente no volume Linux exclusivo united-ati-local-runtime. Execute start-local.ps1 primeiro.' }
New-Item -ItemType Directory -Force .local-runtime | Out-Null
$archiveAti = Join-Path $PWD '.local-runtime/united-source.tar'
& tar.exe -cf $archiveAti --exclude=./vendor --exclude=./var --exclude=./node_modules --exclude=./public/assets --exclude=./assets/vendor --exclude=./.env* --exclude=./.git -C apps/core .
if ($LASTEXITCODE -ne 0) { throw 'Falha ao empacotar o código local.' }
Invoke-DockerSyncAti cp $archiveAti united-php:/tmp/united-source.tar
Invoke-DockerSyncAti exec --user root united-php sh -c 'set -eu; stage=$(mktemp -d /tmp/united-sync.XXXXXX); tar -xf /tmp/united-source.tar -C "$stage"; rsync -a --delete --exclude=vendor --exclude=var --exclude=node_modules --exclude=public/assets --exclude=assets/vendor --exclude=".env*" --exclude=.git "$stage/" /var/www/html/; test -f /var/www/html/.env || touch /var/www/html/.env; mkdir -p /var/www/html/public/assets /var/www/html/assets/vendor; chown -R www-data:www-data /var/www/html/public/assets /var/www/html/assets/vendor; rm -rf "$stage"; rm -f /tmp/united-source.tar'
if (-not $SkipAssets) { Invoke-DockerSyncAti exec united-php php bin/console asset-map:compile --env=prod }
Invoke-DockerSyncAti exec united-php php bin/console cache:clear --env=prod
Invoke-DockerSyncAti kill --signal=USR2 united-php
Write-Output 'Código sincronizado no runtime Linux. United: http://localhost:4300/unitedati'
