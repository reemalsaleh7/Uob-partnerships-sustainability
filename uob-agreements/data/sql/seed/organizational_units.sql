INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order) VALUES
('University', 'UOB', 'UNIVERSITY', NULL, 1),
('President Office', 'PRES', 'OFFICE', 1, 2),
('Vice President Office', 'VP', 'OFFICE', 1, 3),
('Legal Office', 'LEGAL', 'OFFICE', 1, 4),
('Financial Office', 'FIN', 'OFFICE', 1, 5),
('College of Information Technology', 'CIT', 'COLLEGE', 1, 6),
('Department of Computer Science', 'CS', 'DEPARTMENT', 6, 7),
('Department of Information Systems', 'IS', 'DEPARTMENT', 6, 8);
