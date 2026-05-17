<?php
// ═══════════════════════════════════════════════════
//  AAC — Chat Endpoint
//  POST /api/chat.php?action=send        Send a message
//  GET  /api/chat.php?action=history     Chat session list
//  GET  /api/chat.php?action=session&id= Messages of a session
//  POST /api/chat.php?action=new_session Create new session
//  DELETE /api/chat.php?action=session&id= Delete session
// ═══════════════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helper.php';

setCorsHeaders();

// ─── Gemini API helper (server-side proxy to external generative model) ─
function callGeminiAPI(string $prompt): ?string {
    // Use config constants GEMINI_API_URL and GEMINI_API_KEY
    if (!defined('GEMINI_API_URL') || !GEMINI_API_URL) return null;
    if (!defined('GEMINI_API_KEY') || !GEMINI_API_KEY) return null;

    $url = GEMINI_API_URL;

    // Support both Google Generative Language / Gemini request shapes.
    if (str_contains($url, 'generateContent')) {
        $payloadData = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
        ];
    } else {
        $payloadData = [
            'prompt' => [
                'text' => $prompt,
            ],
            'temperature' => 0.3,
            'maxOutputTokens' => 512,
        ];
    }

    $payload = json_encode($payloadData);

    $useApiKey = str_contains($url, 'generativelanguage.googleapis.com');
    if ($useApiKey) {
        $sep = str_contains($url, '?') ? '&' : '?';
        $url = $url . $sep . 'key=' . urlencode(GEMINI_API_KEY);
    }

    $headers = ['Content-Type: application/json'];
    if (!$useApiKey) {
        $headers[] = 'Authorization: Bearer ' . GEMINI_API_KEY;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $http < 200 || $http >= 300) {
        error_log('[AAC] Gemini API error: ' . ($err ?: 'HTTP ' . $http) . ' - Response: ' . $resp);
        return null;
    }

    $data = json_decode($resp, true);
    if (is_array($data)) {
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return (string)$data['candidates'][0]['content']['parts'][0]['text'];
        }
        if (isset($data['candidates'][0]['content'])) {
            return is_string($data['candidates'][0]['content']) ? (string)$data['candidates'][0]['content'] : json_encode($data['candidates'][0]['content']);
        }
        if (isset($data['predictions'][0]['candidates'][0]['content'])) {
            return (string)$data['predictions'][0]['candidates'][0]['content'];
        }
        if (isset($data['output'][0]['content'])) {
            return (string)$data['output'][0]['content'];
        }
        if (isset($data['output'])) {
            return is_string($data['output']) ? (string)$data['output'] : json_encode($data['output']);
        }
        if (isset($data['text'])) {
            return (string)$data['text'];
        }
    }

    return $resp;
}

function geminiStatus(): array {
    $enabled = defined('GEMINI_API_URL') && GEMINI_API_URL && defined('GEMINI_API_KEY') && GEMINI_API_KEY;
    return [
        'enabled' => $enabled,
        'gemini_url' => GEMINI_API_URL,
        'gemini_key_set' => defined('GEMINI_API_KEY') && GEMINI_API_KEY ? true : false,
        'debug' => defined('DEBUG') && DEBUG,
    ];
}

$action  = $_GET['action'] ?? '';
$method  = $_SERVER['REQUEST_METHOD'];

if ($action === 'test_gemini' && $method === 'GET') {
    $status = geminiStatus();
    $result = ['status' => $status, 'auth_required' => false];

    if (isset($_GET['run']) && $_GET['run'] === '1') {
        if (!$status['enabled']) {
            error('Gemini is not configured. Set GEMINI_API_URL and GEMINI_API_KEY in .env and restart Apache.');
        }

        $sample = callGeminiAPI('Hello from AAC. This is a connectivity test.');
        $result['sample_response'] = $sample !== null ? trim($sample) : null;
        $result['sample_success'] = $sample !== null;
        if ($sample === null) {
            error('Gemini test call failed. Check your URL/key and review the PHP error log.');
        }
    }

    success($result);
}

$payload = requireAuth();
$userId  = (int) $payload['user_id'];

// ─── AI Knowledge Base ────────────────────────────
function getAIResponse(string $question, int $userId, PDO $db): string {
    $q = strtolower($question);

    // 1. Check custom knowledge base first
    $stmt = $db->prepare('SELECT title, content FROM knowledge_base ORDER BY CHAR_LENGTH(title) DESC');
    $stmt->execute();
    $entries = $stmt->fetchAll();

    foreach ($entries as $entry) {
        $keywords = array_filter(explode(' ', strtolower($entry['title'])));
        foreach ($keywords as $kw) {
            if (strlen($kw) > 3 && str_contains($q, $kw)) {
                return "**{$entry['title']}**\n\n{$entry['content']}";
            }
        }
    }

    // 2. Built-in NLP knowledge map
    $knowledge = [
        'machine learning'   => 'Machine Learning (ML) is a subset of AI that enables systems to learn automatically from data without being explicitly programmed. It includes three main types: **Supervised Learning** (labeled data), **Unsupervised Learning** (unlabeled data), and **Reinforcement Learning** (reward-based). Key algorithms include Decision Trees, Random Forests, SVMs, and Neural Networks.',
        'gradient descent'   => 'Gradient Descent is an optimization algorithm used to minimize a loss/cost function. It works by iteratively moving parameters in the direction of the negative gradient. Variants: Batch GD, Stochastic GD, Mini-batch GD. Learning rate α controls step size.',
        'neural network'     => 'Neural Networks are computing systems inspired by biological neural networks. They consist of: Input Layer, Hidden Layers (learn features), and Output Layer. Each neuron applies a weight, bias, and activation function (ReLU, Sigmoid, Tanh). Training uses backpropagation + gradient descent.',
        'osi model'          => 'The OSI model has 7 layers: 1. Physical, 2. Data Link, 3. Network, 4. Transport, 5. Session, 6. Presentation, 7. Application. Mnemonic: "Please Do Not Throw Sausage Pizza Away".',
        'tcp'                => 'TCP provides reliable, ordered, connection-oriented communication. 3-way handshake: (1) SYN, (2) SYN-ACK, (3) ACK. Ensures data integrity via sequence numbers and retransmission.',
        'big o'              => 'Big O notation describes algorithm time/space complexity. Common: O(1) constant, O(log n) binary search, O(n) linear, O(n log n) merge sort, O(n²) bubble sort.',
        'binary search'      => 'Binary Search is O(log n) — finds an element in a sorted array by repeatedly halving the search space. Compare middle element, then search left or right half.',
        'data structure'     => 'Key data structures: Array O(1) access, Linked List O(1) insert, Stack LIFO, Queue FIFO, Hash Table O(1) avg, Binary Tree hierarchical, Graph nodes+edges.',
        'recursion'          => 'Recursion is when a function calls itself to solve smaller subproblems. Requires a Base Case (termination) and Recursive Case. Examples: factorial, Fibonacci, tree traversal.',
        'database'           => 'DBMS organizes structured data. Key concepts: Tables, Primary Key, Foreign Key, SQL (SELECT/INSERT/UPDATE/DELETE), Normalization (1NF→2NF→3NF), ACID properties.',
        'sorting'            => 'Sorting algorithms: Bubble Sort O(n²), Merge Sort O(n log n) stable, Quick Sort O(n log n) avg. For most practical uses, Quick Sort or Merge Sort is preferred.',
        'operating system'   => 'An OS manages hardware and provides services. Key: Process Management (FCFS, SJF, Round Robin), Memory Management (paging, virtual memory), File System (FAT32, NTFS, ext4).',
        'polymorphism'       => 'Polymorphism in OOP means "many forms." Compile-time: Method Overloading. Runtime: Method Overriding. Core to OOP alongside Encapsulation, Inheritance, Abstraction.',
        'hash table'         => 'A Hash Table stores key-value pairs using a hash function to compute an index. Average O(1) for insert, delete, search. Collisions handled via chaining or open addressing.',
        'linked list'        => 'A Linked List is a linear data structure where each node contains data and a pointer to the next node. Singly: one direction. Doubly: two directions. O(1) insert/delete, O(n) search.',
        'dynamic programming'=> 'Dynamic Programming solves complex problems by breaking them into overlapping subproblems and storing results (memoization/tabulation). Examples: Fibonacci, Knapsack, Longest Common Subsequence.',
        'inheritance'        => 'Inheritance allows a child class to acquire properties and methods of a parent class. Promotes code reuse. Types: Single, Multiple (via interfaces), Multilevel, Hierarchical, Hybrid.',
    ];

    foreach ($knowledge as $keyword => $response) {
        if (str_contains($q, $keyword)) {
            return $response;
        }
    }

    // 3. Generic intelligent fallback
    $generics = [
        "That's a great academic question about **{$question}**! This is a fundamental topic in your CS/AI curriculum. The key is to understand both the theoretical foundations and practical implementations. Break it down into smaller concepts, understand each component, then see how they integrate. Would you like me to focus on any specific aspect?",
        "**{$question}** is an important concept you'll encounter throughout your degree. Focus on understanding the 'why' behind concepts, not just the 'how' — this will help you in both assignments and exams. I recommend reviewing your lecture slides and consulting your course textbook for detailed formulas and examples.",
        "Excellent question! Regarding **{$question}**: the fundamental idea revolves around structured problem-solving and systematic analysis. Make sure to practice with examples and past papers. Would you like me to elaborate on any particular part?",
    ];

    // Try external generative model if configured
    $gen = getGenerativeResponse($question);
    if ($gen) return $gen;

    return $generics[array_rand($generics)];
}

// If a generative API is configured, try it as the final fallback
function getGenerativeResponse(string $question): ?string {
    $resp = callGeminiAPI($question);
    if ($resp && trim($resp) !== '') return $resp;
    return null;
}

// ─── SEND MESSAGE ─────────────────────────────────
if ($action === 'send' && $method === 'POST') {
    $body      = getBody();
    $message   = trim($body['message']    ?? '');
    $sessionId = (int) ($body['session_id'] ?? 0);

    if (!$message) error('Message cannot be empty.');

    $db = getDB();

    // Auto-create session if not provided
    if (!$sessionId) {
        $title = mb_substr($message, 0, 60);
        $stmt  = $db->prepare('INSERT INTO chat_sessions (user_id, title) VALUES (?, ?)');
        $stmt->execute([$userId, $title]);
        $sessionId = (int) $db->lastInsertId();
    } else {
        // Verify session belongs to user
        $stmt = $db->prepare('SELECT id FROM chat_sessions WHERE id = ? AND user_id = ?');
        $stmt->execute([$sessionId, $userId]);
        if (!$stmt->fetch()) error('Session not found.', 404);
    }

    // Save user message
    $db->prepare('INSERT INTO chat_messages (session_id, user_id, role, content) VALUES (?, ?, "user", ?)')
       ->execute([$sessionId, $userId, $message]);

    // Generate AI response
    $aiResponse = getAIResponse($message, $userId, $db);

    // Save bot message
    $db->prepare('INSERT INTO chat_messages (session_id, user_id, role, content) VALUES (?, ?, "bot", ?)')
       ->execute([$sessionId, $userId, $aiResponse]);

    success([
        'session_id' => $sessionId,
        'response'   => $aiResponse,
    ], 'Message sent.');
}

// ─── TEST GEMINI CONNECTION ─────────────────────
elseif ($action === 'test_gemini' && $method === 'GET') {
    $status = geminiStatus();
    $result = ['status' => $status];

    if (isset($_GET['run']) && $_GET['run'] === '1') {
        if (!$status['enabled']) {
            error('Gemini is not configured. Set GEMINI_API_URL and GEMINI_API_KEY in .env and restart Apache.');
        }

        $sample = callGeminiAPI('Hello from AAC. This is a connectivity test.');
        $result['sample_response'] = $sample !== null ? trim($sample) : null;
        $result['sample_success'] = $sample !== null;
        if ($sample === null) {
            error('Gemini test call failed. Check your URL/key and review the PHP error log.');
        }
    }

    success($result);
}

// ─── GET CHAT HISTORY (list of sessions) ──────────
elseif ($action === 'history' && $method === 'GET') {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT cs.id, cs.title, cs.created_at,
                COUNT(cm.id) AS message_count,
                MAX(cm.created_at) AS last_message
         FROM chat_sessions cs
         LEFT JOIN chat_messages cm ON cm.session_id = cs.id
         WHERE cs.user_id = ?
         GROUP BY cs.id
         ORDER BY last_message DESC'
    );
    $stmt->execute([$userId]);
    $sessions = $stmt->fetchAll();

    success(['sessions' => $sessions]);
}

// ─── GET SESSION MESSAGES ─────────────────────────
elseif ($action === 'session' && $method === 'GET') {
    $sessionId = (int) ($_GET['id'] ?? 0);
    if (!$sessionId) error('Session ID required.');

    $db   = getDB();

    // Verify ownership
    $stmt = $db->prepare('SELECT id, title FROM chat_sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sessionId, $userId]);
    $session = $stmt->fetch();
    if (!$session) error('Session not found.', 404);

    $stmt = $db->prepare(
        'SELECT id, role, content, created_at
         FROM chat_messages
         WHERE session_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([$sessionId]);
    $messages = $stmt->fetchAll();

    success(['session' => $session, 'messages' => $messages]);
}

// ─── CREATE NEW SESSION ───────────────────────────
elseif ($action === 'new_session' && $method === 'POST') {
    $db   = getDB();
    $stmt = $db->prepare('INSERT INTO chat_sessions (user_id, title) VALUES (?, "New Chat")');
    $stmt->execute([$userId]);
    $id   = (int) $db->lastInsertId();

    success(['session_id' => $id, 'title' => 'New Chat'], 'Session created.');
}

// ─── DELETE SESSION ───────────────────────────────
elseif ($action === 'session' && $method === 'DELETE') {
    $sessionId = (int) ($_GET['id'] ?? 0);
    if (!$sessionId) error('Session ID required.');

    $db   = getDB();
    $stmt = $db->prepare('DELETE FROM chat_sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sessionId, $userId]);

    if ($stmt->rowCount() === 0) error('Session not found.', 404);

    success([], 'Session deleted.');
}

else {
    error('Invalid action or method.', 404);
}
