-- Create university

INSERT INTO organizational_units
(
    name,
    code,
    unit_type
)

VALUES
(
    'University of Bahrain',
    'UOB',
    'UNIVERSITY'
);


-- Create College

INSERT INTO organizational_units
(
    name,
    code,
    unit_type,
    parent_unit_id
)

VALUES
(
    'College of Information Technology',
    'CIT',
    'COLLEGE',
    1
);


-- Create Department

INSERT INTO organizational_units
(
    name,
    code,
    unit_type,
    parent_unit_id
)

VALUES
(
    'Computer Science',
    'CS',
    'DEPARTMENT',
    2
);