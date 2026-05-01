-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    tel VARCHAR(20),
    governorate VARCHAR(100),
    role VARCHAR(20) DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- School Books Table
CREATE TABLE IF NOT EXISTS school_books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level VARCHAR(50) NOT NULL,
    grade VARCHAR(50) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    book_name VARCHAR(255) NOT NULL,
    language VARCHAR(50) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Donations Table
CREATE TABLE IF NOT EXISTS donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    condition_state VARCHAR(50),
    donated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES school_books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Table
CREATE TABLE IF NOT EXISTS inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    condition_state VARCHAR(50),
    stock INT DEFAULT 0,
    FOREIGN KEY (book_id) REFERENCES school_books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Requests Table
CREATE TABLE IF NOT EXISTS requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    target_state VARCHAR(50),
    status VARCHAR(50) DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES school_books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Matches Table
CREATE TABLE IF NOT EXISTS matches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    donor_id INT NOT NULL,
    requester_id INT NOT NULL,
    book_id INT NOT NULL,
    condition_given VARCHAR(50),
    status VARCHAR(50) DEFAULT 'pending',
    matched_at DATE DEFAULT (CURRENT_DATE),
    time TIME DEFAULT CURRENT_TIME,
    donor_is_read BOOLEAN NOT NULL DEFAULT 0,
    requester_is_read BOOLEAN NOT NULL DEFAULT 0,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES school_books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better query performance
CREATE INDEX idx_user_governorate ON users(governorate);
CREATE INDEX idx_donations_user_book ON donations(user_id, book_id);
CREATE INDEX idx_inventory_book_condition ON inventory(book_id, condition_state);
CREATE INDEX idx_requests_user_status ON requests(user_id, status);
CREATE INDEX idx_matches_users ON matches(donor_id, requester_id);
