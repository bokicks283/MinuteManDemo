CREATE TABLE service_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(100) NOT NULL,
  customer_email VARCHAR(255) NOT NULL,
  service_type VARCHAR(50) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  request_description TEXT NULL,
  due_date DATE NULL,
  request_status VARCHAR(30) NOT NULL DEFAULT 'submitted',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE request_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NOT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_status_history_request
    FOREIGN KEY (request_id)
    REFERENCES service_requests(id)
    ON DELETE CASCADE
);

CREATE INDEX idx_service_requests_status
    ON service_requests(request_status);

CREATE INDEX idx_status_history_request_id
    ON request_status_history(request_id);
