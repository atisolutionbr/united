<?php

namespace App\Tests\Service;

use App\Service\{PurchasingCatalog, ProcessGateway, ProductQualityReview, IntegrationFields};
use PHPUnit\Framework\TestCase;

final class PurchasingCatalogTest extends TestCase
{
    public function testRequestedFieldsAndCardsExist(): void
    {
        self::assertCount(12, PurchasingCatalog::CARDS);
        self::assertArrayHasKey('cost_center', PurchasingCatalog::fields('requests'));
        self::assertArrayHasKey('reserve_stock', PurchasingCatalog::fields('requisitions'));
    }

    public function testNegativeQuantityIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PurchasingCatalog::validate(['quantity' => '-2'], ['quantity' => ['label' => 'Quantidade', 'type' => 'number', 'required' => true]]);
    }

    public function testInvalidCalendarDateIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PurchasingCatalog::validate(['date' => '2026-02-30'], ['date' => ['label' => 'Data', 'type' => 'date']]);
    }

    public function testNestedPayloadAndScalarCollision(): void
    {
        $data = [];
        ProcessGateway::putPath($data, 'document.product', 'P1');
        self::assertSame('P1', ProcessGateway::readPath($data, 'document.product'));
        $this->expectException(\InvalidArgumentException::class);
        ProcessGateway::putPath($data, 'document.product.name', 'Invalid');
    }

    public function testSqlIdentifierCannotCarryAnExpression(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProcessGateway::identifier('orders; DROP TABLE orders');
    }

    public function testZeroFiscalRatesAreNotTreatedAsMissing(): void
    {
        $row = ['ncm' => '12345678', 'cst_ibs_cbs' => '000', 'cclass_trib' => '000001', 'ibs_rate' => '0', 'cbs_rate' => 0, 'tax_selective' => '0'];
        self::assertSame([], ProductQualityReview::fiscalIssues($row, array_fill_keys(array_keys($row), 'column')));
        self::assertSame('AÇO INOX', ProductQualityReview::normalizeDescription("  aço   Inox\n"));
    }

    public function testUnknownFiscalClassificationRemainsAReviewIssue(): void
    {
        $issues = ProductQualityReview::fiscalIssues(['ncm' => '123', 'ibs_rate' => -1], ['ncm' => 'ncm', 'ibs_rate' => 'ibs']);
        self::assertContains('NCM ausente ou fora do formato de oito dígitos', $issues);
        self::assertContains('cClassTrib não vinculado', $issues);
    }

    public function testAdditionalFieldsStayScopedToTheirForm(): void
    {
        $settings = ['custom_fields' => ['products' => ['extra_color' => ['label' => 'Cor', 'type' => 'text']]]];
        self::assertArrayHasKey('extra_color', IntegrationFields::forForm($settings, 'products', []));
        self::assertSame([], IntegrationFields::forForm($settings, 'customers', []));
    }
}
