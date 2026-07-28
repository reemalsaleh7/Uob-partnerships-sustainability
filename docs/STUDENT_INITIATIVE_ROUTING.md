# Student Initiative routing rule

## Eligibility

A student may create and submit an Initiative only when:

1. The session user is active.
2. The user has the `Student Initiative Creator` role.
3. The user has `CREATE_INITIATIVE`, `EDIT_INITIATIVE`, and
   `SUBMIT_INITIATIVE`.
4. An active `student_profiles` row exists.
5. `student_profiles.department_id` points to an active
   `DEPARTMENT` organizational unit.
6. That Department has an active parent College.
7. The Department has one active Department Head with
   `APPROVE_INITIATIVE`.
8. The College has one active Dean with `APPROVE_INITIATIVE`.

## Route

```text
Student creator
-> Department Head of student_profiles.department_id
-> Dean of the Department's parent College
-> Vice President
-> President
```

## Service implementation

The Initiative hierarchy resolver should use this precedence:

```text
Student:
    student_profiles.department_id

Faculty / Department Head / Dean:
    active user_positions.unit_id
```

For the Student route:

```sql
SELECT
    sp.department_id,
    department.parent_unit_id AS college_id
FROM student_profiles sp
JOIN organizational_units department
  ON department.unit_id = sp.department_id
WHERE sp.user_id = :creator_user_id
  AND sp.is_active = TRUE
  AND department.is_active = TRUE
  AND department.unit_type = 'DEPARTMENT';
```

Then resolve the active `Department Head` in `department_id`,
the active `Dean` in `college_id`, followed by the VP and President
steps already defined in the Initiative workflow template.

The client must not provide the Department, College, next reviewer,
or actor ID.
