CREATE TABLE public_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference CHAR(24) NOT NULL UNIQUE,
 request_type VARCHAR(20) NOT NULL,
 name VARCHAR(180) NOT NULL,
 email VARCHAR(254) NOT NULL,
 message TEXT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 response_note TEXT NULL,
 consent_version VARCHAR(60) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 INDEX public_requests_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
