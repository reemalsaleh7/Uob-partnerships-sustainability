The naming conventions used for

- tables is snake_case and plural names (e.g. workflow_instances)
- for primary keys xxx_id (e.g. agreement_id)

---

# Database Architecture

## Purpose

The University Partnerships and Initiatives Management System stores and manages:

- University organizational structure
- Users and role-based permissions
- Partner organizations
- Agreements
- Agreement versions
- Agreement workflows
- Initiatives
- Initiative workflows
- Notifications
- Audit logs

The database follows Third Normal Form (3NF) to minimize redundancy while maintaining data integrity.

## University Structure

This defines **where** someone belongs.

<pre class="overflow-visible! px-0!" data-start="425" data-end="719"><div class="relative w-full mt-4 mb-1"><div class=""><div class="contents"><div class="relative"><div class="h-full min-h-0 min-w-0"><div class="h-full min-h-0 min-w-0"><div class="border border-token-border-light border-radius-3xl corner-superellipse/1.1 rounded-3xl"><div class="h-full w-full border-radius-3xl bg-(--code-block-surface) corner-superellipse/1.1 overflow-clip rounded-3xl [--code-block-surface:var(--bg-elevated-secondary)] dark:[--code-block-surface:var(--composer-surface-primary)] lxnfua_clipPathFallback"><div class="pointer-events-none absolute end-1.5 top-1 z-2 md:end-2 md:top-1"></div><div class="relative"><div class="pe-11 pt-3"><div class="relative z-0 flex max-w-full"><div id="code-block-viewer" dir="ltr" class="q9tKkq_viewer cm-editor z-10 light:cm-light dark:cm-light flex h-full w-full flex-col items-stretch ͼs ͼ16"><div class="cm-scroller"><pre class="cm-content q9tKkq_readonly m-0"><code><span>University
│
├── President Office
├── Vice President Office
├── Legal Office
├── Financial Office
│
├── College of Information Technology
│      │
│      ├── Computer Science
│      └── Information Systems
│
├── College of Engineering
│      │
│      ├── Civil
│      └── Mechanical</span></code></pre></div></div></div></div></div></div></div></div></div><div class=""><div class=""></div></div></div></div></div></div></pre>

One department belongs to **exactly one** college.

## Users

A user belongs to one organizational unit.

Examples

| user    | unit                         |
| ------- | ---------------------------- |
| Ahmed   | President Office             |
| Ali     | Vice President Office        |
| Sara    | Legal Office                 |
| Fatima  | Computer Science Department  |

## Positions

A position defines the person's place in the hierarchy.

Examples

Position							Unique?
Doctor							No
Department Head					Yes (per department)
Dean							Yes (per college)
President Office Member			No
Vice President Office Member		No
Legal Officer						No
Financial Officer					No

Notice: we are not saying there is only one "Department Head" in the whole university.

Instead:

Department Head + Department = must be unique.

Meaning

Computer Science

↓

Department Head

↓

Khadija

There cannot be another active Department Head for Computer Science.

But Engineering has its own Department Head.

## Roles (Permissions)

Roles determine what someone can do, not who they are.

Examples

Role							Permissions
Agreement Creator				Create Agreements
Agreement Approver			Approve Agreements
Initiative Creator				Create Initiatives
Initiative Approver				Approve Initiatives
User Administrator				Manage Users
System Administrator			Everything

## Workflow Engine

Everything requiring approvals uses the same engine.

```Markdown
Agreement
↓
Workflow Instance
↓
Workflow Steps
↓
History
```

Exactly the same for

```Markdown
Initiative
↓
Workflow Instance
↓
Workflow Steps
↓
History
```

## Versioning

Nothing is overwritten.

```Markdown
Agreement

Version 1

↓

Rejected

↓

Version 2

↓

Approved

↓

Renewal

↓

Version 3
```

Exactly the same for initiatives.

## The Database Will Eventually Look Like This

```Markdown
users
│
├────────────┐
│            │
▼            ▼
positions   roles
│            │
▼            ▼
organizational_units
        │
        ▼
workflow engine
        │
        ▼
agreements
initiatives
```



# Organizational Structure

## Purpose

The organizational_units table stores the hierarchical structure of the university.

## Design Decisions

- Uses a recursive self-reference.
- Supports unlimited hierarchy depth.
- Avoids creating separate tables for colleges, departments, and offices.
- Soft deletion is implemented using is_active.

## Business Rules

- Every department belongs to exactly one college.
- Every college belongs to the university.
- Offices belong directly to the university.
- Organizational units are never physically deleted.


## Organizational Unit Types

UNIVERSITY
Root node of the organizational hierarchy.

OFFICE
Administrative offices reporting directly to the University.

COLLEGE
Academic colleges.

DEPARTMENT
Academic departments belonging to a college.
