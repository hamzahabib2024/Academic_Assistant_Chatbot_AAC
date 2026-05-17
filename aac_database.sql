-- ═══════════════════════════════════════════════════
--  AAC — AI Academic Assistant Chatbot
--  MySQL Database Schema
--  Import via phpMyAdmin or run in XAMPP MySQL console
-- ═══════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS aac_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aac_db;

-- ─── USERS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,   -- bcrypt hash
    role        ENUM('student','admin') DEFAULT 'student',
    status      ENUM('active','inactive') DEFAULT 'active',
    created_at  DATETIME            DEFAULT CURRENT_TIMESTAMP,
    last_login  DATETIME            NULL
) ENGINE=InnoDB;

-- ─── CHAT SESSIONS ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS chat_sessions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    title       VARCHAR(200)    DEFAULT 'New Chat',
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── CHAT MESSAGES ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS chat_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    session_id  INT             NOT NULL,
    user_id     INT             NOT NULL,
    role        ENUM('user','bot') NOT NULL,
    content     TEXT            NOT NULL,
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── KNOWLEDGE BASE ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS knowledge_base (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200)    NOT NULL,
    category    VARCHAR(100)    NOT NULL,
    content     TEXT            NOT NULL,
    created_by  INT             NULL,
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── UPLOADED FILES ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS uploaded_files (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    session_id  INT             NULL,
    filename    VARCHAR(255)    NOT NULL,
    original_name VARCHAR(255)  NOT NULL,
    file_type   VARCHAR(50)     NOT NULL,
    file_size   INT             NOT NULL,
    summary     TEXT            NULL,
    created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)          ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── SEED DATA ───────────────────────────────────────────

-- Default admin account  (password: admin123)
INSERT INTO users (name, email, password, role, status) VALUES
('Administrator', 'admin@demo.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Default student account  (password: demo123)
INSERT INTO users (name, email, password, role, status) VALUES
('Ahmed Hassan', 'student@demo.com',
 '$2y$10$TKh8H1.PkouYezgR3vSK4OqFyPTMI2I.0CtmL3SqkSuGFpSWKS5m2', 'student', 'active');

-- Sample knowledge base entries
INSERT INTO knowledge_base (title, category, content, created_by) VALUES
('Gradient Descent Algorithm', 'Machine Learning',
 'Gradient Descent is an optimization algorithm used to minimize a loss/cost function. It works by iteratively moving parameters in the direction of the negative gradient (steepest descent). Variants include: Batch GD (uses all data), Stochastic GD (one sample), and Mini-batch GD (subset). Learning rate α controls the step size — too large causes oscillation, too small slows convergence.',
 1),
('Binary Search Trees', 'Data Structures',
 'A node-based tree structure where each node has at most two children, with left subtree values less than the root and right greater. Supports O(log n) search, insertion, and deletion on average. Degrades to O(n) if unbalanced; self-balancing variants (AVL, Red-Black) solve this.',
 1),
('OSI Model Layers', 'Networking',
 'The OSI model is a 7-layer framework: 1. Physical (bits/cables), 2. Data Link (frames/MAC), 3. Network (packets/IP), 4. Transport (segments/TCP-UDP), 5. Session (connection management), 6. Presentation (encryption/encoding), 7. Application (HTTP/FTP/DNS). Mnemonic: "Please Do Not Throw Sausage Pizza Away".',
 1),
('Neural Networks', 'Machine Learning',
 'Neural Networks are computing systems inspired by biological neural networks. They consist of: Input Layer (receives data), Hidden Layers (learn features), and Output Layer (prediction). Each neuron applies a weight, bias, and activation function (ReLU, Sigmoid, Tanh). Training uses backpropagation + gradient descent.',
 1),
('ACID Properties', 'Database',
 'ACID stands for Atomicity (all or nothing), Consistency (valid state), Isolation (concurrent transactions independent), Durability (committed data persists). These properties ensure reliable database transactions even in case of errors, power failures, or other anomalies.',
 1);
