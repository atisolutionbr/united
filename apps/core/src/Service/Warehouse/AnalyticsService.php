<?php

namespace App\Service\Warehouse;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AnalyticsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function getExecutiveIndicators(): array
    {
        $conn = $this->entityManager->getConnection();

        try {
            $agencias = $this->getAgenciasStats($conn);
        } catch (\Throwable) {
            $agencias = ['total' => 0, 'estados' => 0, 'municipios' => 0, 'crescimento' => null];
        }

        return [
            [
                'title' => 'Total de Agências',
                'value' => number_format($agencias['total'], 0, ',', '.'),
                'rawValue' => $agencias['total'],
                'variation' => $agencias['crescimento'] ? sprintf('%+.1f%%', $agencias['crescimento']) : 'N/A',
                'trend' => $agencias['crescimento'] > 0 ? 'up' : ($agencias['crescimento'] < 0 ? 'down' : 'steady'),
                'icon' => 'shop',
                'description' => 'Agências de turismo consolidadas no warehouse a partir do Cadastur.',
                'tone' => 'teal',
            ],
            [
                'title' => 'Estados Atendidos',
                'value' => (string) $agencias['estados'],
                'rawValue' => $agencias['estados'],
                'variation' => 'Cobertura nacional',
                'trend' => 'steady',
                'icon' => 'map',
                'description' => 'Estados brasileiros representados nos dados de turismo consolidados.',
                'tone' => 'blue',
            ],
            [
                'title' => 'Municípios com Dados',
                'value' => number_format($agencias['municipios'], 0, ',', '.'),
                'rawValue' => $agencias['municipios'],
                'variation' => 'Base territorial',
                'trend' => 'steady',
                'icon' => 'geo-alt',
                'description' => 'Municípios representados nos datasets do warehouse territorial.',
                'tone' => 'amber',
            ],
        ];
    }

    public function getRankingByEstado(): array
    {
        $conn = $this->entityManager->getConnection();
        try {
            return $conn->fetchAllAssociative("
                SELECT estado, COUNT(*) as total
                FROM warehouse.dw_turismo_agencias
                WHERE estado IS NOT NULL
                GROUP BY estado
                ORDER BY total DESC
                LIMIT 10
            ");
        } catch (\Throwable) {
            return [];
        }
    }

    public function getSeriesTemporalMensal(): array
    {
        $conn = $this->entityManager->getConnection();
        try {
            return $conn->fetchAllAssociative("
                SELECT
                    DATE_TRUNC('month', ingested_at) AS mes,
                    COUNT(*) as total
                FROM warehouse.dw_turismo_agencias
                GROUP BY DATE_TRUNC('month', ingested_at)
                ORDER BY mes DESC
                LIMIT 12
            ");
        } catch (\Throwable) {
            return [];
        }
    }

    public function getDataLineage(): array
    {
        $conn = $this->entityManager->getConnection();
        $steps = [];

        // CKAN providers
        try {
            $providers = (int) $conn->fetchOne("SELECT COUNT(*) FROM data_providers WHERE is_active = true");
            $steps['ckan'] = ['label' => 'CKAN', 'count' => $providers, 'unit' => 'provedores', 'active' => $providers > 0];
        } catch (\Throwable) {
            $steps['ckan'] = ['label' => 'CKAN', 'count' => 0, 'unit' => 'provedores', 'active' => false];
        }

        // RAW files
        try {
            $raw = (int) $conn->fetchOne("SELECT COUNT(*) FROM raw_files WHERE download_status = 'downloaded'");
            $steps['raw'] = ['label' => 'RAW', 'count' => $raw, 'unit' => 'arquivos', 'active' => $raw > 0];
        } catch (\Throwable) {
            $steps['raw'] = ['label' => 'RAW', 'count' => 0, 'unit' => 'arquivos', 'active' => false];
        }

        // STAGING
        try {
            $staging = (int) $conn->fetchOne("SELECT COUNT(*) FROM raw_files WHERE transformation_status = 'done'");
            $steps['staging'] = ['label' => 'STAGING', 'count' => $staging, 'unit' => 'datasets', 'active' => $staging > 0];
        } catch (\Throwable) {
            $steps['staging'] = ['label' => 'STAGING', 'count' => 0, 'unit' => 'datasets', 'active' => false];
        }

        // WAREHOUSE
        try {
            $whTables = $conn->fetchAllAssociative("
                SELECT table_name FROM information_schema.tables WHERE table_schema = 'warehouse'
            ");
            $whCount = count($whTables);
            $steps['warehouse'] = ['label' => 'WAREHOUSE', 'count' => $whCount, 'unit' => 'tabelas', 'active' => $whCount > 0];
        } catch (\Throwable) {
            $steps['warehouse'] = ['label' => 'WAREHOUSE', 'count' => 0, 'unit' => 'tabelas', 'active' => false];
        }

        // INDICADORES
        try {
            $indicators = (int) $conn->fetchOne("SELECT COUNT(*) FROM indicator");
            $steps['indicators'] = ['label' => 'INDICADORES', 'count' => $indicators, 'unit' => 'indicadores', 'active' => $indicators > 0];
        } catch (\Throwable) {
            $steps['indicators'] = ['label' => 'INDICADORES', 'count' => 0, 'unit' => 'indicadores', 'active' => false];
        }

        // DASHBOARDS
        try {
            $dashboards = (int) $conn->fetchOne("SELECT COUNT(*) FROM metabase_dashboards WHERE is_active = true");
            $steps['dashboards'] = ['label' => 'DASHBOARDS', 'count' => $dashboards, 'unit' => 'dashboards', 'active' => $dashboards > 0];
        } catch (\Throwable) {
            $steps['dashboards'] = ['label' => 'DASHBOARDS', 'count' => 0, 'unit' => 'dashboards', 'active' => false];
        }

        return $steps;
    }

    private function getAgenciasStats(\Doctrine\DBAL\Connection $conn): array
    {
        $total = (int) $conn->fetchOne("SELECT COUNT(*) FROM warehouse.dw_turismo_agencias");
        $estados = (int) $conn->fetchOne("SELECT COUNT(DISTINCT estado) FROM warehouse.dw_turismo_agencias WHERE estado IS NOT NULL");
        $municipios = (int) $conn->fetchOne("SELECT COUNT(DISTINCT municipio) FROM warehouse.dw_turismo_agencias WHERE municipio IS NOT NULL");

        return ['total' => $total, 'estados' => $estados, 'municipios' => $municipios, 'crescimento' => null];
    }
}
