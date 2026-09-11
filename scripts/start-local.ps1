$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

function Invoke-DockerAti {
    & docker @args
    if ($LASTEXITCODE -ne 0) { throw "Falha no Docker: $($args[0])" }
}

& docker info --format '{{.ServerVersion}}' 2>$null
if ($LASTEXITCODE -ne 0) { throw 'O motor do Docker está indisponível. Inicie ou repare o Docker Desktop antes de continuar.' }
if (-not (Test-Path .env)) { throw 'Configuração local .env ausente.' }

$sourceVolumeAti = 'plataforma360-postgres-data'
$targetVolumeAti = 'united-ati-local-postgres-data'
$volumesAti = & docker volume ls --format '{{.Name}}'
if ($LASTEXITCODE -ne 0) { throw 'Não foi possível consultar os volumes.' }
if ($targetVolumeAti -notin $volumesAti) {
    if ($sourceVolumeAti -notin $volumesAti) { throw 'O banco original não foi encontrado. Não será criado um banco vazio.' }
    $activeAti = & docker ps --filter "volume=$sourceVolumeAti" --format '{{.Names}}'
    if ($LASTEXITCODE -ne 0) { throw 'Não foi possível verificar o uso do banco original.' }
    if ($activeAti) { throw 'O banco original está em execução. Pare sua instância antes de fazer a cópia local consistente.' }
    Invoke-DockerAti volume create $targetVolumeAti
    Invoke-DockerAti run --rm --user root --entrypoint sh --mount "type=volume,source=$sourceVolumeAti,target=/source,readonly" --mount "type=volume,source=$targetVolumeAti,target=/target" postgis/postgis:16-3.4-alpine -c 'test -f /source/PG_VERSION && cp -a /source/. /target/ && touch /target/.united-copy-complete'
}
Invoke-DockerAti run --rm --entrypoint sh --mount "type=volume,source=$targetVolumeAti,target=/target,readonly" postgis/postgis:16-3.4-alpine -c 'test -f /target/.united-copy-complete'
Invoke-DockerAti compose -f docker-compose.yml -f compose.local.yaml up -d --build postgres php nginx adminer
Invoke-DockerAti compose -f docker-compose.yml -f compose.local.yaml exec -T php php bin/console doctrine:migrations:migrate --no-interaction
Invoke-DockerAti compose -f docker-compose.yml -f compose.local.yaml exec -T php php bin/console cache:clear
Write-Output 'United Ati: http://localhost:3000/login'
