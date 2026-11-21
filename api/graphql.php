<?php
/**
 * Handicraft by Sonia - Simple GraphQL API Endpoint
 * Supports querying products, categories, and faqs from MySQL
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Default query if accessed directly in browser via GET
$query = $input['query'] ?? ($_GET['query'] ?? '{ products { id name price stock } categories { id name } }');

$responseData = [];

try {
    // 1. Resolve 'products' field if requested in GraphQL query
    if (preg_match('/\bproducts\b/i', $query)) {
        $stmt = $pdo->query("
            SELECT p.id, p.category_id, p.name, p.description, p.price, p.image, p.stock, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            ORDER BY p.id ASC
        ");
        $responseData['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Resolve 'categories' field if requested in GraphQL query
    if (preg_match('/\bcategories\b/i', $query)) {
        $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id ASC");
        $responseData['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Resolve 'faqs' field if requested in GraphQL query
    if (preg_match('/\bfaqs\b/i', $query)) {
        $stmt = $pdo->query("SELECT id, question, answer FROM faqs WHERE status = 'active' ORDER BY sort_order ASC");
        $responseData['faqs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'data' => $responseData,
        'extensions' => [
            'engine' => 'Handicraft by Sonia GraphQL Resolver',
            'query_executed' => $query
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'errors' => [
            ['message' => 'GraphQL resolution error: ' . $e->getMessage()]
        ]
    ]);
}