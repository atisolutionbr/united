#!/bin/bash
# Script para criar diretórios e arquivos de templates dentro do container Docker
# Use: docker exec $(docker ps -q -f "name=plataforma360-php") bash /scripts/create-admin-hubs.sh

set -e

BASE_DIR="/app/apps/core/templates/admin"

echo "Criando diretórios para admin hubs..."

# Criar diretórios
mkdir -p "$BASE_DIR/intelligence"
mkdir -p "$BASE_DIR/integrations"  
mkdir -p "$BASE_DIR/operations"
mkdir -p "$BASE_DIR/platform"

echo "✓ Diretórios criados com sucesso:"
ls -la "$BASE_DIR"

echo ""
echo "Próximas etapas:"
echo "1. Copie os arquivos .twig para cada diretório"
echo "2. Execute: make up"
echo "3. Acesse os hubs via interface administrativa"
