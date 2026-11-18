USE structiqx;

INSERT INTO organizations (name,slug,address,currency) VALUES
('Himalayan Structures','himalayan-structures','Kathmandu, Nepal','NPR');

INSERT INTO users (name,email,password_hash,phone) VALUES
('Demo Administrator','admin@structiqx.test','$2y$10$7pBfQx8d1R2nVbYhJwMHeeYgHq0b5r8nJZfT0xQq1Xo6y9Rk0l2qG','9800000000');

INSERT INTO organization_members (organization_id,user_id,role) VALUES (1,1,'organization_admin');

INSERT INTO clients (organization_id,name,company,email,phone) VALUES
(1,'Ramesh Shrestha','Green Valley Developers','ramesh@example.com','9811111111');

INSERT INTO projects (organization_id,client_id,title,description,category,location,start_date,end_date,budget,progress,status,project_manager_id,created_by)
VALUES (1,1,'Baneshwor Commercial Complex','Six-storey commercial and office development.','Building','Baneshwor, Kathmandu','2026-01-15','2026-12-20',25000000,38,'active',1,1);

INSERT INTO tasks (organization_id,project_id,title,description,assigned_to,priority,status,start_date,due_date,progress,created_by)
VALUES (1,1,'Foundation excavation','Complete excavation and verify formation level.',1,'high','in_progress','2026-10-01','2026-11-25',65,1),
(1,1,'Rebar inspection','Inspect and document reinforcement for grade beams.',1,'high','todo','2026-11-20','2026-11-28',0,1);

INSERT INTO expenses (organization_id,project_id,category,description,amount,expense_date,status,created_by)
VALUES (1,1,'Labour','Foundation labour payroll',485000,'2026-11-10','approved',1),
(1,1,'Material','Cement and aggregate purchase',760000,'2026-11-12','pending',1);

INSERT INTO suppliers (organization_id,name,contact_person,phone,email) VALUES
(1,'Kathmandu Building Supplies','Sita Gurung','9822222222','supply@example.com');
