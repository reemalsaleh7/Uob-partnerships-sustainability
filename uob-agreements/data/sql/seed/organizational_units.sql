INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
VALUES ('University', 'UOB', 'UNIVERSITY', NULL, 1);

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'President Office', 'PRES', 'OFFICE', unit_id, 2
FROM organizational_units
WHERE code = 'UOB';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Vice President Office', 'VP', 'OFFICE', unit_id, 3
FROM organizational_units
WHERE code = 'UOB';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Legal Office', 'LEGAL', 'OFFICE', unit_id, 4
FROM organizational_units
WHERE code = 'UOB';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Financial Office', 'FIN', 'OFFICE', unit_id, 5
FROM organizational_units
WHERE code = 'UOB';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'College of Information Technology', 'CIT', 'COLLEGE', unit_id, 6
FROM organizational_units
WHERE code = 'UOB';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Department of Computer Science', 'CS', 'DEPARTMENT', unit_id, 7
FROM organizational_units
WHERE code = 'CIT';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Department of Information Systems', 'IS', 'DEPARTMENT', unit_id, 8
FROM organizational_units
WHERE code = 'CIT';
