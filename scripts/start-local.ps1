$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

function Invoke-DockerAti {
    & docker @args
    if ($LASTEXITCODE -ne 0) { throw "Falha no Docker: $($args[0])" }
}

& docker info --format '{{.ServerVersion}}' 2>$null
if ($LASTEXITCODE -ne 0) { throw 'O motor do Docker está indisponível. Inicie o Docker Desktop antes de continuar.' }
if (-not (Test-Path .env)) { throw 'Configuração local .env ausente.' }

$composeAti = @('compose', '-p', 'united-ati', '-f', 'docker-compose.yml', '-f', 'compose.local.yaml')
$sourceVolumeAti = 'plataforma360-postgres-data'
$targetVolumeAti = 'united-ati-local-postgres-data'
$volumesAti = & docker volume ls --format '{{.Name}}'
if ($LASTEXITCODE -ne 0) { throw 'Não foi possível consultar os volumes.' }
if ($targetVolumeAti -notin $volumesAti) {
    # pg_dump creates a consistent snapshot while KFlow remains online.
    $sourceContainersAti = @(& docker ps --filter "volume=$sourceVolumeAti" --format '{{.Names}}')
    if ($LASTEXITCODE -ne 0 -or $sourceContainersAti.Count -ne 1) {
        throw 'Mantenha o PostgreSQL do KFlow iniciado para importar seus cadastros sem interrompê-lo.'
    }
    New-Item -ItemType Directory -Path .local-runtime -Force | Out-Null
    $dumpPathAti = Join-Path $PWD '.local-runtime/platform-seed.dump'
    Invoke-DockerAti exec $sourceContainersAti[0] sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --file=/tmp/united-ati-seed.dump'
    Invoke-DockerAti cp ($sourceContainersAti[0] + ':/tmp/united-ati-seed.dump') $dumpPathAti
    Invoke-DockerAti @composeAti up -d --wait postgres
    Invoke-DockerAti cp $dumpPathAti 'united-postgres:/tmp/united-ati-seed.dump'
    Invoke-DockerAti exec united-postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner --no-privileges --exit-on-error /tmp/united-ati-seed.dump'
    Invoke-DockerAti exec united-postgres touch /var/lib/postgresql/data/.united-import-complete
} else {
    Invoke-DockerAti @composeAti up -d --wait postgres
    Invoke-DockerAti exec united-postgres test -f /var/lib/postgresql/data/.united-import-complete
}
Invoke-DockerAti @composeAti up -d --build php nginx adminer
Invoke-DockerAti @composeAti exec -T php php bin/console doctrine:migrations:migrate --no-interaction
Invoke-DockerAti @composeAti exec -T php php bin/console cache:clear
Write-Output 'United Ati: http://localhost:4300/login'
Write-Output 'KFlow permanece na sua porta e com seu banco original.'
