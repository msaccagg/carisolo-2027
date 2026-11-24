<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Carisolo-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$familyKey = getenv('FAMILY_KEY');
if ($familyKey !== false && $familyKey !== '') {
    $providedKey = $_SERVER['HTTP_X_CARISOLO_KEY'] ?? '';
    if (!hash_equals($familyKey, $providedKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

try {
    $pdo = new PDO('sqlite:/var/www/html/data/carisolo.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS bookings (' .
        'id TEXT PRIMARY KEY, ' .
        'payload TEXT NOT NULL, ' .
        'updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
    );

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        $rows = $pdo->query('SELECT payload FROM bookings')->fetchAll(PDO::FETCH_ASSOC);
        $bookings = [];
        foreach ($rows as $row) {
            $bookings[] = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
        }
        echo json_encode($bookings, JSON_THROW_ON_ERROR);
        exit;
    }

    if ($method === 'POST') {
        $bookings = json_decode(file_get_contents('php://input'), false, 512, JSON_THROW_ON_ERROR);
        if (!is_array($bookings)) {
            http_response_code(400);
            echo json_encode(['error' => 'Request body must be an array']);
            exit;
        }

        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM bookings');
        $insert = $pdo->prepare('INSERT INTO bookings (id, payload) VALUES (:id, :payload)');
        $count = 0;
        foreach ($bookings as $booking) {
            if (!is_object($booking) || !isset($booking->id)) {
                continue;
            }
            $id = (string) $booking->id;
            if ($id === '') {
                continue;
            }

            $insert->execute([
                ':id' => $id,
                ':payload' => json_encode($booking, JSON_THROW_ON_ERROR),
            ]);
            $count++;
        }
        $pdo->commit();

        echo json_encode(['ok' => true, 'count' => $count], JSON_THROW_ON_ERROR);
        exit;
    }

    header('Allow: GET, POST, OPTIONS');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}