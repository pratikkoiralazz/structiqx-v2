CREATE DATABASE IF NOT EXISTS structiqx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE structiqx;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS email_verification_tokens, password_reset_tokens, contact_messages,
  approval_steps, approvals, comments, notifications, activity_logs, document_versions, documents,
  material_deliveries, purchase_order_items, purchase_orders, purchase_requests, suppliers,
  site_report_photos, site_reports, engineering_calculations, rate_analyses, boq_items, boq,
  expenses, budget_revisions, budgets, milestones, task_dependencies, tasks, project_members,
  projects, clients, organization_invitations, organization_members, organizations, users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(40), avatar VARCHAR(255), is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE, address TEXT, phone VARCHAR(40), email VARCHAR(190),
  currency CHAR(3) NOT NULL DEFAULT 'NPR', timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Kathmandu',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE organization_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,   role ENUM('super_admin','organization_admin','project_manager','civil_engineer','site_engineer','site_supervisor','accountant','procurement_officer','client') NOT NULL,
  status ENUM('active','invited','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_org_user (organization_id,user_id),
  CONSTRAINT fk_member_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE organization_invitations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL, role VARCHAR(40) NOT NULL, token CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL, accepted_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE clients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL, company VARCHAR(160), email VARCHAR(190), phone VARCHAR(40),
  address TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_client_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  INDEX idx_clients_org (organization_id)
) ENGINE=InnoDB;

CREATE TABLE projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NULL, title VARCHAR(180) NOT NULL, description TEXT,
  category VARCHAR(80), location VARCHAR(180), start_date DATE NULL, end_date DATE NULL,
  budget DECIMAL(15,2) NOT NULL DEFAULT 0, progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('planning','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'planning',
  project_manager_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_project_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  CONSTRAINT fk_project_pm FOREIGN KEY (project_manager_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_project_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_projects_org_status (organization_id,status), INDEX idx_projects_dates (start_date,end_date)
) ENGINE=InnoDB;

CREATE TABLE project_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, project_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL, role VARCHAR(40) NOT NULL DEFAULT 'civil_engineer',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_project_member (project_id,user_id),
  CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL, title VARCHAR(180) NOT NULL, description TEXT,
  assigned_to BIGINT UNSIGNED NULL, priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('todo','in_progress','in_review','completed','blocked','cancelled') NOT NULL DEFAULT 'todo',
  start_date DATE NULL, due_date DATE NULL, progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_task_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_task_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_task_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_tasks_project_status (project_id,status)
) ENGINE=InnoDB;

CREATE TABLE task_dependencies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, task_id BIGINT UNSIGNED NOT NULL,
  depends_on_task_id BIGINT UNSIGNED NOT NULL,
  CONSTRAINT fk_dep_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_dep_depends FOREIGN KEY (depends_on_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  UNIQUE KEY uq_task_dependency (task_id,depends_on_task_id)
) ENGINE=InnoDB;

CREATE TABLE milestones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL, title VARCHAR(180) NOT NULL, due_date DATE NOT NULL,
  status ENUM('planned','achieved','overdue') NOT NULL DEFAULT 'planned', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_milestone_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_milestone_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE budgets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL, amount DECIMAL(15,2) NOT NULL,
  revision_no INT UNSIGNED NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_budget_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_budget_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE budget_revisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, budget_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(15,2) NOT NULL, reason TEXT, changed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_revision_budget FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE expenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL, category VARCHAR(80) NOT NULL, description TEXT,
  amount DECIMAL(15,2) NOT NULL, expense_date DATE NOT NULL, status ENUM('draft','pending','approved','rejected','paid') DEFAULT 'pending',
  created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expense_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_expense_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  INDEX idx_expenses_org_project (organization_id,project_id)
) ENGINE=InnoDB;

CREATE TABLE boq (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL, title VARCHAR(180) NOT NULL,
  status ENUM('draft','pending','approved','revision') DEFAULT 'draft', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_boq_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_boq_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE boq_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, boq_id BIGINT UNSIGNED NOT NULL,
  item_code VARCHAR(40), description VARCHAR(255) NOT NULL, unit VARCHAR(20) NOT NULL,
  quantity DECIMAL(12,3) NOT NULL DEFAULT 0, unit_rate DECIMAL(15,2) NOT NULL DEFAULT 0,
  total DECIMAL(15,2) GENERATED ALWAYS AS (quantity*unit_rate) STORED,
  CONSTRAINT fk_boq_item FOREIGN KEY (boq_id) REFERENCES boq(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rate_analyses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  boq_item_id BIGINT UNSIGNED NOT NULL, component_type ENUM('material','labour','equipment') NOT NULL,
  name VARCHAR(160) NOT NULL, unit VARCHAR(20), quantity DECIMAL(12,3), rate DECIMAL(15,2),
  CONSTRAINT fk_rate_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_rate_item FOREIGN KEY (boq_item_id) REFERENCES boq_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE engineering_calculations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL, calculation_type VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL, inputs_json JSON NOT NULL, formula TEXT,
  steps_json JSON, results_json JSON, unit VARCHAR(40), standard_reference VARCHAR(255),
  status ENUM('draft','in_review','verified','approved','rejected') DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL, reviewed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_calc_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_calc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE site_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL, report_date DATE NOT NULL DEFAULT (CURDATE()),
  weather VARCHAR(80), work_completed TEXT, labour_count INT UNSIGNED DEFAULT 0,
  materials_used TEXT, equipment_used TEXT, issues TEXT, safety_observations TEXT,
  supervisor_remarks TEXT, created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_site_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_site_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE site_report_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, site_report_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL, caption VARCHAR(255),
  CONSTRAINT fk_photo_report FOREIGN KEY (site_report_id) REFERENCES site_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL, title VARCHAR(180) NOT NULL, category VARCHAR(80),
  file_path VARCHAR(255) NOT NULL, mime_type VARCHAR(100), file_size BIGINT UNSIGNED,
  access_level ENUM('private','team','client','public') DEFAULT 'team', version INT UNSIGNED DEFAULT 1,
  status ENUM('draft','pending','approved','rejected') DEFAULT 'draft',
  uploaded_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_document_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE document_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, document_id BIGINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL, file_path VARCHAR(255) NOT NULL,
  created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_doc_version FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL, contact_person VARCHAR(120), email VARCHAR(190), phone VARCHAR(40),
  address TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_supplier_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE purchase_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL, title VARCHAR(180) NOT NULL, description TEXT,
  status ENUM('draft','pending','approved','rejected','cancelled') DEFAULT 'draft',
  requested_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  purchase_request_id BIGINT UNSIGNED NULL, supplier_id BIGINT UNSIGNED NULL,
  order_number VARCHAR(60) NOT NULL UNIQUE, order_date DATE NOT NULL,
  status ENUM('draft','issued','partial','received','cancelled') DEFAULT 'draft',
  total DECIMAL(15,2) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_po_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE purchase_order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, purchase_order_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL, unit VARCHAR(20), quantity DECIMAL(12,3),
  unit_price DECIMAL(15,2), CONSTRAINT fk_poi_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE material_deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, purchase_order_id BIGINT UNSIGNED NOT NULL,
  delivery_date DATE NOT NULL, received_by BIGINT UNSIGNED NULL, notes TEXT,
  CONSTRAINT fk_delivery_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE approvals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  entity_type VARCHAR(40) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL, status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  requested_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_approval_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  INDEX idx_approval_entity (entity_type,entity_id)
) ENGINE=InnoDB;

CREATE TABLE approval_steps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, approval_id BIGINT UNSIGNED NOT NULL,
  step_no INT UNSIGNED NOT NULL, approver_id BIGINT UNSIGNED NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending', remarks TEXT, acted_at DATETIME NULL,
  CONSTRAINT fk_step_approval FOREIGN KEY (approval_id) REFERENCES approvals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  entity_type VARCHAR(40) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comment_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL, title VARCHAR(180) NOT NULL, body TEXT,
  link VARCHAR(255), is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, organization_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(40),
  entity_id BIGINT UNSIGNED NULL, description TEXT, ip_address VARCHAR(45), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_org (organization_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_user_public FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_reset_token_expiry (token,expires_at)
) ENGINE=InnoDB;

CREATE TABLE email_verification_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_verify_user_public FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_email_verify_token (token,expires_at)
) ENGINE=InnoDB;

CREATE TABLE contact_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40),
  organization VARCHAR(160),
  subject VARCHAR(180),
  message TEXT NOT NULL,
  status ENUM('new','read','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contact_status_created (status,created_at)
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_reset_expiry (token,expires_at)
) ENGINE=InnoDB;

CREATE INDEX idx_projects_org_dates ON projects (organization_id,end_date);
CREATE INDEX idx_tasks_org_dates ON tasks (organization_id,due_date);
CREATE INDEX idx_expenses_org_date ON expenses (organization_id,expense_date);
CREATE INDEX idx_documents_org_status ON documents (organization_id,status);
CREATE INDEX idx_site_reports_org_date ON site_reports (organization_id,report_date);
CREATE INDEX idx_calculations_org_status ON engineering_calculations (organization_id,status);
