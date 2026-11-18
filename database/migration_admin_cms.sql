USE structiqx;

ALTER TABLE organizations
  ADD COLUMN IF NOT EXISTS approval_status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'approved',
  ADD COLUMN IF NOT EXISTS approval_reason TEXT NULL,
  ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS approved_by BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS registration_source VARCHAR(40) NOT NULL DEFAULT 'legacy';

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','content_manager','support_manager') NOT NULL DEFAULT 'support_manager',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identity_hash CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL,
  successful TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_admin_attempt_identity_date (identity_hash,attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_role_permissions (
  role ENUM('super_admin','content_manager','support_manager') NOT NULL,
  permission VARCHAR(80) NOT NULL,
  PRIMARY KEY (role,permission)
) ENGINE=InnoDB;

INSERT IGNORE INTO admin_role_permissions (role,permission) VALUES
('super_admin','*'),
('content_manager','dashboard.read'),
('content_manager','cms.manage'),
('content_manager','settings.manage'),
('support_manager','dashboard.read'),
('support_manager','messages.manage'),
('support_manager','organizations.read'),
('support_manager','users.read');

CREATE TABLE IF NOT EXISTS cms_pages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  page_title VARCHAR(180) NOT NULL,
  meta_title VARCHAR(180),
  meta_description VARCHAR(320),
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cms_public (slug,status,is_visible)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cms_page_sections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id BIGINT UNSIGNED NOT NULL,
  section_key VARCHAR(80) NOT NULL,
  heading VARCHAR(255),
  description TEXT,
  cta_text VARCHAR(100),
  cta_url VARCHAR(255),
  image_path VARCHAR(255),
  content_json JSON NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cms_page_section (page_id,section_key),
  CONSTRAINT fk_cms_section_page FOREIGN KEY (page_id) REFERENCES cms_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cms_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL UNIQUE,
  mime_type VARCHAR(80) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contact_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40),
  organization VARCHAR(160),
  subject VARCHAR(180),
  message TEXT NOT NULL,
  status ENUM('unread','new','pending','resolved','archived','read','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contact_status_created (status,created_at)
) ENGINE=InnoDB;

ALTER TABLE contact_messages
  MODIFY COLUMN status ENUM('unread','new','pending','resolved','archived','read','closed') NOT NULL DEFAULT 'new';

CREATE TABLE IF NOT EXISTS contact_message_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_message_note_message FOREIGN KEY (message_id) REFERENCES contact_messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_note_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_approval_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  previous_status VARCHAR(24) NOT NULL,
  new_status VARCHAR(24) NOT NULL,
  reason TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_org_approval_log_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_org_approval_log_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id),
  INDEX idx_org_approval_history (organization_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subscription_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  annual_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'NPR',
  organization_limit INT UNSIGNED NULL,
  user_limit INT UNSIGNED NULL,
  feature_limits JSON NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organization_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('trialing','active','past_due','cancelled','expired') NOT NULL DEFAULT 'trialing',
  billing_cycle ENUM('monthly','annual') NOT NULL DEFAULT 'monthly',
  payment_verified TINYINT(1) NOT NULL DEFAULT 0,
  payment_reference VARCHAR(120) NULL,
  starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME NULL,
  changed_by BIGINT UNSIGNED NULL,
  change_reason TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_subscription_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id),
  INDEX idx_subscriptions_status (status,organization_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_setting_user FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT UNSIGNED NULL,
  notification_type VARCHAR(60) NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT,
  target_type VARCHAR(60),
  target_id BIGINT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_notification_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  INDEX idx_admin_notifications_unread (admin_user_id,is_read,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  target_type VARCHAR(60),
  target_id BIGINT UNSIGNED NULL,
  previous_value JSON NULL,
  new_value JSON NULL,
  metadata JSON NULL,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_activity_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_admin_activity_created (created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO subscription_plans (name,slug,description,monthly_price,annual_price,currency,organization_limit,user_limit,feature_limits,is_enabled) VALUES
('Essential','essential','Starter plan',0,0,'NPR',1,5,JSON_OBJECT('projects',5,'storage_mb',500),1),
('Professional','professional','For growing project teams',0,0,'NPR',1,50,JSON_OBJECT('projects',50,'storage_mb',10000),1),
('Enterprise','enterprise','For larger organizations',0,0,'NPR',NULL,NULL,JSON_OBJECT('projects',-1,'storage_mb',-1),1);

INSERT IGNORE INTO cms_pages (slug,page_title,meta_title,meta_description,status,is_visible) VALUES
('home','Home','STRUCTIQX | Engineering Projects Simplified','Manage construction projects, engineering calculations, budgets, teams and site operations.','published',1),
('features','Features','Features | STRUCTIQX','Explore project, financial, engineering and site management capabilities.','published',1),
('solutions','Solutions','Solutions | STRUCTIQX','STRUCTIQX for engineering consultancies and construction companies.','published',1),
('pricing','Pricing','Pricing | STRUCTIQX','Flexible plans for engineering organizations.','published',1),
('about','About Us','About STRUCTIQX','Learn about the STRUCTIQX project operating system.','published',1),
('contact','Contact','Contact | STRUCTIQX','Contact the STRUCTIQX team.','published',1),
('faq','FAQ','FAQ | STRUCTIQX','Frequently asked questions about STRUCTIQX.','draft',0),
('privacy','Privacy Policy','Privacy Policy | STRUCTIQX','Privacy practices for STRUCTIQX.','draft',0),
('terms','Terms and Conditions','Terms | STRUCTIQX','Terms and conditions for STRUCTIQX.','draft',0);

UPDATE cms_pages SET status='draft',is_visible=0
WHERE slug='faq'
  AND NOT EXISTS (SELECT 1 FROM cms_page_sections WHERE cms_page_sections.page_id=cms_pages.id);
