CREATE TABLE audit_logs (

    audit_log_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    action VARCHAR(100) NOT NULL,

    description TEXT,

    ip_address VARCHAR(45),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

);