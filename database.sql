

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL,
  role ENUM('customer','admin','finance') NOT NULL DEFAULT 'customer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE otp_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT NOT NULL DEFAULT 0,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE refund_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ref_no VARCHAR(30) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  -- Passenger information
  passenger_name VARCHAR(120) NOT NULL,
  passenger_email VARCHAR(150) NOT NULL,
  passenger_phone VARCHAR(20) NOT NULL,
  mailing_address VARCHAR(255) NOT NULL,
  city_state_country VARCHAR(150) NOT NULL,
  booking_ref VARCHAR(20) NOT NULL,
  ticket_number VARCHAR(30) NULL,
  -- Trip information
  flight_numbers VARCHAR(100) NOT NULL,
  travel_date DATE NOT NULL,
  departure_city VARCHAR(100) NOT NULL,
  destination_city VARCHAR(100) NOT NULL,
  itinerary VARCHAR(255) NULL,
  -- Refund request (USD)
  amount_paid DECIMAL(10,2) NOT NULL,
  amount_requested DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(40) NOT NULL,
  preferred_refund_method VARCHAR(40) NULL,
  -- Reason, details, documents, declaration
  reason_category VARCHAR(60) NOT NULL,
  reason_other VARCHAR(150) NULL,
  reason_details TEXT NULL,
  documents_provided VARCHAR(255) NULL,
  signature_name VARCHAR(120) NOT NULL,
  declared_at DATETIME NOT NULL,
  -- Processing
  status ENUM('pending','under_review','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  decision_note TEXT NULL,
  decided_by INT NULL,
  handler ENUM('admin','finance') NOT NULL DEFAULT 'admin',
  transferred_by INT NULL,
  transferred_at DATETIME NULL,
  transfer_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (booking_ref),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (transferred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE refund_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  refund_id INT NOT NULL,
  changed_by INT NULL,
  old_status VARCHAR(20) NULL,
  new_status VARCHAR(20) NOT NULL,
  note TEXT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (refund_id) REFERENCES refund_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  refund_id INT NULL,
  customer_id INT NULL,
  kind ENUM('customer_admin','customer_finance','admin_finance','direct') NOT NULL DEFAULT 'customer_admin',
  user_one INT NULL,
  user_two INT NULL,
  started_by INT NULL,
  pair_key VARCHAR(20) NULL,
  subject VARCHAR(150) NULL,
  last_message_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_refund_kind (refund_id, kind),
  UNIQUE KEY uq_pair_key (pair_key),
  FOREIGN KEY (refund_id) REFERENCES refund_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES users(id),
  FOREIGN KEY (user_one) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (user_two) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender_id INT NOT NULL,
  body TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  INDEX (conversation_id, id),
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE message_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  file_path VARCHAR(120) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  file_type ENUM('image','video','document') NOT NULL,
  file_size INT NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  refund_id INT NOT NULL UNIQUE,
  finance_user_id INT NOT NULL,
  method VARCHAR(30) NOT NULL,
  transaction_ref VARCHAR(80) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (refund_id) REFERENCES refund_requests(id),
  FOREIGN KEY (finance_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Staff accounts. Change these emails to real inboxes you can read, because login codes are sent there.
INSERT INTO users (full_name, email, phone, role) VALUES
('System Admin',   'admin@example.com',   '0700000001', 'admin'),
('Finance Officer','finance@example.com', '0700000002', 'finance');
