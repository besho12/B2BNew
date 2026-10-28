<?php

namespace App\Services;

class B2BDemoCatalog
{
    private const PRODUCTS = [
        'DEMO-001' => ['sku' => 'DEMO-001', 'name' => 'Nano Bike Wash', 'category' => 'Cleaning', 'price_minor' => 1250, 'image' => '/images/demo-products/product-01.svg', 'case_quantity' => 1, 'stock_quantity' => 100, 'allow_backorder' => false],
        'DEMO-002' => ['sku' => 'DEMO-002', 'name' => 'Wet Chain Lube', 'category' => 'Lubrication', 'price_minor' => 875, 'image' => '/images/demo-products/product-02.svg', 'case_quantity' => 2, 'stock_quantity' => 100, 'allow_backorder' => false],
        'DEMO-003' => ['sku' => 'DEMO-003', 'name' => 'Dry Chain Lube', 'category' => 'Lubrication', 'price_minor' => 925, 'image' => '/images/demo-products/product-03.svg', 'case_quantity' => 1, 'stock_quantity' => 12, 'allow_backorder' => false],
        'DEMO-004' => ['sku' => 'DEMO-004', 'name' => 'Bio Degreaser', 'category' => 'Cleaning', 'price_minor' => 1100, 'image' => '/images/demo-products/product-04.svg', 'case_quantity' => 1, 'stock_quantity' => 100, 'allow_backorder' => false],
        'DEMO-005' => ['sku' => 'DEMO-005', 'name' => 'Foam Bike Cleaner', 'category' => 'Cleaning', 'price_minor' => 1450, 'image' => '/images/demo-products/product-05.svg', 'case_quantity' => 1, 'stock_quantity' => 100, 'allow_backorder' => false],
        'DEMO-006' => ['sku' => 'DEMO-006', 'name' => 'Tubeless Tyre Sealant', 'category' => 'Workshop', 'price_minor' => 1600, 'image' => '/images/demo-products/product-06.svg', 'case_quantity' => 3, 'stock_quantity' => 100, 'allow_backorder' => false],
        'DEMO-007' => ['sku' => 'DEMO-007', 'name' => 'Disc Brake Cleaner', 'category' => 'Cleaning', 'price_minor' => 750, 'image' => '/images/demo-products/product-07.svg', 'case_quantity' => 1, 'stock_quantity' => 100, 'allow_backorder' => false],
        'DEMO-008' => ['sku' => 'DEMO-008', 'name' => 'Chain Cleaning Brush', 'category' => 'Tools', 'price_minor' => 625, 'image' => '/images/demo-products/product-08.svg', 'case_quantity' => 1, 'stock_quantity' => 12, 'allow_backorder' => false],
        'DEMO-009' => ['sku' => 'DEMO-009', 'name' => 'Microfiber Cloth', 'category' => 'Accessories', 'price_minor' => 400, 'image' => '/images/demo-products/product-09.svg', 'case_quantity' => 5, 'stock_quantity' => 100, 'allow_backorder' => false],
        'DEMO-010' => ['sku' => 'DEMO-010', 'name' => 'Workshop Cleaning Kit', 'category' => 'Workshop', 'price_minor' => 2400, 'image' => '/images/demo-products/product-10.svg', 'case_quantity' => 1, 'stock_quantity' => 100, 'allow_backorder' => false],
    ];

    public function find(string $sku): ?array
    {
        $product = self::PRODUCTS[$sku] ?? null;
        if ($product) $product['stock_status'] = $product['stock_quantity'] <= 12 ? 'Low stock' : 'In stock';
        return $product;
    }
}