(function () {
    'use strict';

    document.body.classList.add('admin-users-page');

    const isArabic = document.documentElement.lang === 'ar';
    const t = (english, arabic) => isArabic ? arabic : english;

    const elements = {
        alert: document.querySelector('[data-admin-users-alert]'),
        success: document.querySelector('[data-admin-users-success]'),
        filters: document.querySelector('[data-admin-users-filters]'),
        search: document.querySelector('[data-admin-user-search]'),
        status: document.querySelector('[data-admin-user-status]'),
        unitFilter: document.querySelector('[data-admin-user-unit-filter]'),
        refresh: document.querySelector('[data-admin-users-refresh]'),
        loading: document.querySelector('[data-admin-users-loading]'),
        empty: document.querySelector('[data-admin-users-empty]'),
        tableWrap: document.querySelector('[data-admin-users-table-wrap]'),
        body: document.querySelector('[data-admin-users-body]'),
        count: document.querySelector('[data-admin-users-count]'),
        pagination: document.querySelector('[data-admin-users-pagination]'),
        page: document.querySelector('[data-admin-users-page]'),
        prev: document.querySelector('[data-admin-users-prev]'),
        next: document.querySelector('[data-admin-users-next]'),
        placeholder: document.querySelector('[data-admin-user-placeholder]'),
        detailLoading: document.querySelector('[data-admin-user-detail-loading]'),
        form: document.querySelector('[data-admin-user-form]'),
        avatar: document.querySelector('[data-admin-user-avatar]'),
        name: document.querySelector('[data-admin-user-name]'),
        context: document.querySelector('[data-admin-user-context]'),
        active: document.querySelector('[data-admin-user-active]'),
        summary: document.querySelector('[data-admin-access-summary]'),
        roleGroups: document.querySelector('[data-admin-role-groups]'),
        unit: document.querySelector('[data-admin-user-unit]'),
        position: document.querySelector('[data-admin-user-position]'),
        effectiveDate: document.querySelector('[data-admin-effective-date]'),
        history: document.querySelector('[data-admin-position-history]'),
        reason: document.querySelector('[data-admin-change-reason]'),
        save: document.querySelector('[data-admin-user-save]'),
        saveLabel: document.querySelector('[data-admin-save-label]'),
        saveSpinner: document.querySelector('[data-admin-save-spinner]'),
        workflowSelects: Array.from(document.querySelectorAll('[data-admin-workflow-select]'))
    };

    const state = {
        options: null,
        users: [],
        pagination: { page: 1, pages: 1, total: 0, limit: 25 },
        selectedId: null,
        selectedUser: null,
        busy: false,
        searchTimer: null
    };


    const workflowRoutes = {
        initiative: [
            {
                order: 1,
                label: t('Creator', 'المنشئ'),
                roleNames: ['Initiative Creator']
            },
            {
                order: 2,
                label: t('Department Head', 'رئيس القسم'),
                roleNames: ['Initiative Approver'],
                positionNames: ['Department Head', 'Head of Department']
            },
            {
                order: 3,
                label: t('Dean', 'العميد'),
                roleNames: ['Initiative Approver'],
                positionNames: ['Dean']
            },
            {
                order: 4,
                label: t('Vice President / Office', 'نائب الرئيس / المكتب'),
                roleNames: ['Initiative Approver'],
                positionNames: [
                    'Vice President',
                    'Vice President Office Delegate',
                    'Vice President Office Staff'
                ]
            },
            {
                order: 5,
                label: t('President / Office', 'رئيس الجامعة / المكتب'),
                roleNames: ['Initiative Approver'],
                positionNames: [
                    'President',
                    'President Office Delegate',
                    'President Office Staff'
                ]
            }
        ],
        agreement: [
            {
                order: 1,
                label: t('Creator', 'المنشئ'),
                roleNames: ['Agreement Creator']
            },
            {
                order: 2,
                label: t('VP initial review', 'المراجعة الأولية لنائب الرئيس'),
                roleNames: ['Agreement Approver'],
                positionNames: [
                    'Vice President',
                    'Vice President Office Delegate',
                    'Vice President Office Staff'
                ]
            },
            {
                order: 3,
                label: t('Legal review', 'المراجعة القانونية'),
                roleNames: ['Agreement Approver'],
                positionNames: ['Legal Reviewer']
            },
            {
                order: 4,
                label: t('Finance review', 'المراجعة المالية'),
                roleNames: ['Agreement Approver'],
                positionNames: ['Finance Reviewer']
            },
            {
                order: 5,
                label: t('VP final review', 'المراجعة النهائية لنائب الرئيس'),
                roleNames: ['Agreement Approver'],
                positionNames: [
                    'Vice President',
                    'Vice President Office Delegate',
                    'Vice President Office Staff'
                ]
            },
            {
                order: 6,
                label: t('President approval', 'اعتماد رئيس الجامعة'),
                roleNames: ['Agreement Approver'],
                positionNames: [
                    'President',
                    'President Office Delegate',
                    'President Office Staff'
                ]
            }
        ]
    };

    const workflowAssignments = {
        INIT_CREATOR: {
            workflow: 'initiative',
            roleName: 'Initiative Creator'
        },
        INIT_DEPARTMENT_HEAD: {
            workflow: 'initiative',
            roleName: 'Initiative Approver',
            positionName: 'Department Head',
            unitTypes: ['DEPARTMENT']
        },
        INIT_DEAN: {
            workflow: 'initiative',
            roleName: 'Initiative Approver',
            positionName: 'Dean',
            unitTypes: ['COLLEGE']
        },
        INIT_VP: {
            workflow: 'initiative',
            roleName: 'Initiative Approver',
            positionName: 'Vice President',
            unitCode: 'VP'
        },
        INIT_VP_DELEGATE: {
            workflow: 'initiative',
            roleName: 'Initiative Approver',
            positionName: 'Vice President Office Delegate',
            unitCode: 'VP'
        },
        INIT_VP_STAFF: {
            workflow: 'initiative',
            roleName: 'Initiative Approver',
            positionName: 'Vice President Office Staff',
            unitCode: 'VP'
        },
        INIT_PRESIDENT: {
            workflow: 'initiative',
            roleName: 'Initiative Approver',
            positionName: 'President',
            unitCode: 'PRES'
        },
        INIT_PRESIDENT_DELEGATE: {
            workflow: 'initiative',
            roleName: 'Initiative Approver',
            positionName: 'President Office Delegate',
            unitCode: 'PRES'
        },
        INIT_PRESIDENT_STAFF: {
            workflow: 'initiative',
            roleName: 'Initiative Approver',
            positionName: 'President Office Staff',
            unitCode: 'PRES'
        },
        AGREEMENT_CREATOR: {
            workflow: 'agreement',
            roleName: 'Agreement Creator'
        },
        AGREEMENT_VP: {
            workflow: 'agreement',
            roleName: 'Agreement Approver',
            positionName: 'Vice President',
            unitCode: 'VP'
        },
        AGREEMENT_VP_DELEGATE: {
            workflow: 'agreement',
            roleName: 'Agreement Approver',
            positionName: 'Vice President Office Delegate',
            unitCode: 'VP'
        },
        AGREEMENT_VP_STAFF: {
            workflow: 'agreement',
            roleName: 'Agreement Approver',
            positionName: 'Vice President Office Staff',
            unitCode: 'VP'
        },
        AGREEMENT_LEGAL: {
            workflow: 'agreement',
            roleName: 'Agreement Approver',
            positionName: 'Legal Reviewer',
            unitCode: 'LEGAL'
        },
        AGREEMENT_FINANCE: {
            workflow: 'agreement',
            roleName: 'Agreement Approver',
            positionName: 'Finance Reviewer',
            unitCode: 'FIN'
        },
        AGREEMENT_PRESIDENT: {
            workflow: 'agreement',
            roleName: 'Agreement Approver',
            positionName: 'President',
            unitCode: 'PRES'
        },
        AGREEMENT_PRESIDENT_DELEGATE: {
            workflow: 'agreement',
            roleName: 'Agreement Approver',
            positionName: 'President Office Delegate',
            unitCode: 'PRES'
        },
        AGREEMENT_PRESIDENT_STAFF: {
            workflow: 'agreement',
            roleName: 'Agreement Approver',
            positionName: 'President Office Staff',
            unitCode: 'PRES'
        }
    };


    const workflowStageOptions = {
        initiative: {
            1: [
                ['INIT_CREATOR', t('Place as Initiative creator', 'تعيين كمنشئ المبادرة')]
            ],
            2: [
                ['INIT_DEPARTMENT_HEAD', t('Place as Department Head', 'تعيين كرئيس القسم')]
            ],
            3: [
                ['INIT_DEAN', t('Place as Dean', 'تعيين كالعميد')]
            ],
            4: [
                ['INIT_VP', t('Vice President', 'نائب الرئيس')],
                ['INIT_VP_DELEGATE', t('VP Office delegate', 'مفوض مكتب نائب الرئيس')],
                ['INIT_VP_STAFF', t('VP Office staff', 'موظف مكتب نائب الرئيس')]
            ],
            5: [
                ['INIT_PRESIDENT', t('President', 'رئيس الجامعة')],
                ['INIT_PRESIDENT_DELEGATE', t('President Office delegate', 'مفوض مكتب الرئيس')],
                ['INIT_PRESIDENT_STAFF', t('President Office staff', 'موظف مكتب الرئيس')]
            ]
        },
        agreement: {
            1: [
                ['AGREEMENT_CREATOR', t('Place as Agreement creator', 'تعيين كمنشئ الاتفاقية')]
            ],
            2: [
                ['AGREEMENT_VP', t('Vice President', 'نائب الرئيس')],
                ['AGREEMENT_VP_DELEGATE', t('VP Office delegate', 'مفوض مكتب نائب الرئيس')],
                ['AGREEMENT_VP_STAFF', t('VP Office staff', 'موظف مكتب نائب الرئيس')]
            ],
            3: [
                ['AGREEMENT_LEGAL', t('Place in Legal review', 'تعيين في المراجعة القانونية')]
            ],
            4: [
                ['AGREEMENT_FINANCE', t('Place in Finance review', 'تعيين في المراجعة المالية')]
            ],
            5: [
                ['AGREEMENT_VP', t('Vice President', 'نائب الرئيس')],
                ['AGREEMENT_VP_DELEGATE', t('VP Office delegate', 'مفوض مكتب نائب الرئيس')],
                ['AGREEMENT_VP_STAFF', t('VP Office staff', 'موظف مكتب نائب الرئيس')]
            ],
            6: [
                ['AGREEMENT_PRESIDENT', t('President', 'رئيس الجامعة')],
                ['AGREEMENT_PRESIDENT_DELEGATE', t('President Office delegate', 'مفوض مكتب الرئيس')],
                ['AGREEMENT_PRESIDENT_STAFF', t('President Office staff', 'موظف مكتب الرئيس')]
            ]
        }
    };

    function selectedRoleNamesFromForm() {
        const selected = new Set(selectedRoleIds());
        return new Set((state.options?.roles || [])
            .filter((role) => selected.has(Number(role.role_id)))
            .map((role) => role.role_name));
    }

    function selectedPositionFromForm() {
        const positionId = Number(elements.position.value || 0);
        return (state.options?.positions || []).find(
            (position) => Number(position.position_id) === positionId
        ) || null;
    }

    function selectedUnitFromForm() {
        const unitId = Number(elements.unit.value || 0);
        return (state.options?.units || []).find(
            (unit) => Number(unit.unit_id) === unitId
        ) || null;
    }

    function workflowFormState() {
        return {
            roleNames: selectedRoleNamesFromForm(),
            position: selectedPositionFromForm(),
            unit: selectedUnitFromForm()
        };
    }

    function workflowStageMatches(stage, formState) {
        const roleMatches = (stage.roleNames || []).every(
            (roleName) => formState.roleNames.has(roleName)
        );
        if (!roleMatches) return false;

        if (!stage.positionNames || stage.positionNames.length === 0) {
            return true;
        }

        return stage.positionNames.includes(formState.position?.name || '');
    }

    function workflowUnitContext(formState) {
        if (!formState.unit) return '';
        return formState.unit.path || formState.unit.name || '';
    }

    function compactUserName(user) {
        return fullName(user);
    }

    function listUserRoleNames(user) {
        return new Set((user?.roles || []).map((role) => role.role_name));
    }

    function listUserPositionName(user) {
        return String(user?.position_name || currentPosition(user)?.position_name || '');
    }

    function userMatchesWorkflowStage(stage, user) {
        const roles = listUserRoleNames(user);
        const roleMatches = (stage.roleNames || []).every(
            (roleName) => roles.has(roleName)
        );
        if (!roleMatches) return false;
        if (!stage.positionNames || stage.positionNames.length === 0) return true;
        return stage.positionNames.includes(listUserPositionName(user));
    }

    function usersAtWorkflowStage(stage) {
        return state.users
            .filter((user) => user.is_active === true)
            .filter((user) => userMatchesWorkflowStage(stage, user))
            .sort((left, right) => compactUserName(left).localeCompare(
                compactUserName(right),
                isArabic ? 'ar' : 'en'
            ));
    }

    function stageRepresentative(stage) {
        const people = usersAtWorkflowStage(stage)
            .filter((user) => Number(user.user_id) !== Number(state.selectedId));
        return people.length > 0 ? compactUserName(people[0]) : stage.label;
    }

    function placementDescription(stages, index) {
        const before = index > 0 ? stageRepresentative(stages[index - 1]) : null;
        const after = index < stages.length - 1
            ? stageRepresentative(stages[index + 1])
            : null;

        if (before && after) {
            return t(
                `Exact place: after ${before}, before ${after}`,
                `الموقع الدقيق: بعد ${before} وقبل ${after}`
            );
        }
        if (after) {
            return t(`Exact place: before ${after}`, `الموقع الدقيق: قبل ${after}`);
        }
        if (before) {
            return t(`Exact place: after ${before}`, `الموقع الدقيق: بعد ${before}`);
        }
        return t('Only stage in the route', 'المرحلة الوحيدة في المسار');
    }

    function renderWorkflowRoute(workflow) {
        const route = document.querySelector(
            `[data-admin-workflow-route="${workflow}"]`
        );
        const current = document.querySelector(
            `[data-admin-workflow-current="${workflow}"]`
        );
        const select = document.querySelector(
            `[data-admin-workflow-select="${workflow}"]`
        );
        if (!route || !current || !select) return;

        const stages = workflowRoutes[workflow] || [];
        const formState = workflowFormState();
        const active = [];
        const selectedAssignment = select.value;
        route.replaceChildren();

        stages.forEach((stage, index) => {
            const occupied = workflowStageMatches(stage, formState);
            if (occupied) active.push(stage);

            const node = document.createElement('section');
            node.className = `admin-workflow-node${occupied ? ' is-current' : ''}`;

            const rail = document.createElement('div');
            rail.className = 'admin-workflow-rail';
            const number = document.createElement('span');
            number.className = 'admin-workflow-number';
            number.textContent = String(stage.order);
            rail.appendChild(number);

            const body = document.createElement('div');
            body.className = 'admin-workflow-stage-body';

            const heading = document.createElement('div');
            heading.className = 'admin-workflow-stage-heading';
            const label = document.createElement('strong');
            label.textContent = stage.label;
            const placement = document.createElement('small');
            placement.className = 'admin-workflow-placement';
            placement.textContent = placementDescription(stages, index);
            heading.append(label, placement);

            const people = usersAtWorkflowStage(stage);
            const peopleWrap = document.createElement('div');
            peopleWrap.className = 'admin-workflow-actors';
            if (people.length === 0) {
                const empty = document.createElement('span');
                empty.className = 'is-empty';
                empty.textContent = t('No current holder', 'لا يوجد شخص حاليًا');
                peopleWrap.appendChild(empty);
            } else {
                people.slice(0, 4).forEach((user) => {
                    const chip = document.createElement('span');
                    chip.className = Number(user.user_id) === Number(state.selectedId)
                        ? 'is-selected-user'
                        : '';
                    chip.textContent = compactUserName(user);
                    peopleWrap.appendChild(chip);
                });
                if (people.length > 4) {
                    const more = document.createElement('span');
                    more.textContent = `+${people.length - 4}`;
                    peopleWrap.appendChild(more);
                }
            }

            const actions = document.createElement('div');
            actions.className = 'admin-workflow-actions';
            const options = workflowStageOptions[workflow]?.[stage.order] || [];
            const unique = new Map(options);
            unique.forEach((optionLabel, assignmentKey) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'admin-workflow-action';
                if (selectedAssignment === assignmentKey) {
                    button.classList.add('is-selected');
                }
                button.textContent = optionLabel;
                button.addEventListener('click', () => {
                    select.value = assignmentKey;
                    applyWorkflowAssignment(workflow);
                });
                actions.appendChild(button);
            });

            body.append(heading, peopleWrap, actions);
            node.append(rail, body);
            route.appendChild(node);
        });

        if (active.length === 0) {
            current.textContent = t(
                'This user does not currently occupy a stage in this workflow.',
                'هذا المستخدم لا يشغل حاليًا مرحلة في هذا المسار.'
            );
            current.className = 'admin-workflow-current is-empty mb-0';
            return;
        }

        current.textContent = t(
            `Current position: ${active.map((stage) => `${stage.order} — ${stage.label}`).join(', ')}`,
            `الموقع الحالي: ${active.map((stage) => `${stage.order} — ${stage.label}`).join('، ')}`
        );
        current.className = 'admin-workflow-current is-active mb-0';
    }

    function renderWorkflowPreview() {
        renderWorkflowRoute('initiative');
        renderWorkflowRoute('agreement');
    }

    function setWorkflowMessage(workflow, message, level = 'info') {
        const element = document.querySelector(
            `[data-admin-workflow-message="${workflow}"]`
        );
        if (!element) return;
        element.textContent = message;
        element.className = `admin-workflow-message is-${level} mb-0`;
    }

    function selectRoleByName(roleName) {
        const role = (state.options?.roles || []).find(
            (item) => item.role_name === roleName
        );
        if (!role) return false;

        const checkbox = elements.roleGroups.querySelector(
            `[data-admin-role-id="${role.role_id}"]`
        );
        if (!checkbox) return false;
        checkbox.checked = true;
        return true;
    }

    function assignmentUnit(assignment) {
        if (assignment.unitCode) {
            return (state.options?.units || []).find(
                (unit) => unit.is_active
                    && String(unit.code || '').toUpperCase()
                        === assignment.unitCode
            ) || null;
        }

        const current = selectedUnitFromForm();
        if (
            current
            && (!assignment.unitTypes
                || assignment.unitTypes.includes(
                    String(current.unit_type || '').toUpperCase()
                ))
        ) {
            return current;
        }

        return null;
    }

    function applyWorkflowAssignment(workflow) {
        const select = document.querySelector(
            `[data-admin-workflow-select="${workflow}"]`
        );
        const assignment = workflowAssignments[select?.value || ''];
        if (!assignment || assignment.workflow !== workflow) {
            setWorkflowMessage(
                workflow,
                t('Choose a workflow stage first.', 'اختاري مرحلة من المسار أولًا.'),
                'warning'
            );
            return;
        }

        if (!selectRoleByName(assignment.roleName)) {
            setWorkflowMessage(
                workflow,
                t('The required role is not available.', 'الدور المطلوب غير متوفر في النظام.'),
                'danger'
            );
            return;
        }

        let needsUnit = false;
        if (assignment.positionName) {
            const position = (state.options?.positions || []).find(
                (item) => item.name === assignment.positionName
            );
            if (!position) {
                setWorkflowMessage(
                    workflow,
                    t('The required position is not available.', 'المنصب المطلوب غير متوفر في النظام.'),
                    'danger'
                );
                return;
            }

            const unit = assignmentUnit(assignment);
            elements.unit.value = unit ? String(unit.unit_id) : '';
            renderPositionOptions(position.position_id);
            elements.position.value = String(position.position_id);
            needsUnit = !unit;
        }

        renderSummary(userFromForm());
        renderWorkflowPreview();

        if (needsUnit) {
            setWorkflowMessage(
                workflow,
                t(
                    'The role and position were selected. Choose a compatible college or department in the Organization tab before saving.',
                    'تم اختيار الدور والمنصب. اختاري كلية أو قسمًا متوافقًا من تبويب الجهة التنظيمية قبل الحفظ.'
                ),
                'warning'
            );
            return;
        }

        setWorkflowMessage(
            workflow,
            t(
                'The required role and position were prepared. Save changes to activate this workflow placement.',
                'تم تجهيز الدور والمنصب المطلوبين. اضغطي حفظ التغييرات لتفعيل موقع المستخدم في المسار.'
            ),
            'success'
        );
    }

    function today() {
        const now = new Date();
        const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
        return local.toISOString().slice(0, 10);
    }

    function showAlert(message) {
        elements.success.classList.add('d-none');
        elements.alert.textContent = message;
        elements.alert.classList.remove('d-none');
        elements.alert.focus({ preventScroll: true });
    }

    function showSuccess(message) {
        elements.alert.classList.add('d-none');
        elements.success.textContent = message;
        elements.success.classList.remove('d-none');
        elements.success.focus({ preventScroll: true });
    }

    function clearMessages() {
        elements.alert.classList.add('d-none');
        elements.success.classList.add('d-none');
    }

    function fullName(user) {
        return [user?.first_name, user?.last_name].filter(Boolean).join(' ').trim()
            || user?.email
            || t('Unnamed user', 'مستخدم بدون اسم');
    }

    function initials(user) {
        return fullName(user)
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0])
            .join('')
            .toUpperCase() || 'U';
    }

    function roleNames(user) {
        return Array.isArray(user?.roles)
            ? user.roles.map((role) => role.role_name).filter(Boolean)
            : [];
    }

    function currentPosition(user) {
        if (Array.isArray(user?.positions)) {
            return user.positions.find((position) => position.is_active === true) || null;
        }

        if (user?.position_id && user?.unit_id) {
            return {
                position_id: user.position_id,
                position_name: user.position_name,
                unit_id: user.unit_id,
                unit_name: user.unit_name,
                unit_code: user.unit_code,
                unit_type: user.unit_type,
                is_active: true
            };
        }

        return null;
    }

    function derivedAccess(user) {
        const names = new Set(roleNames(user));
        const permissions = new Set(Array.isArray(user?.permissions) ? user.permissions : []);
        const admin = names.has('System Administrator');

        return {
            agreement: admin
                || names.has('Agreement Creator')
                || permissions.has('CREATE_AGREEMENT'),
            initiative: admin
                || names.has('Initiative Creator')
                || permissions.has('CREATE_INITIATIVE'),
            agreementApproval: admin
                || names.has('Agreement Approver')
                || permissions.has('APPROVE_AGREEMENT'),
            initiativeApproval: admin
                || names.has('Initiative Approver')
                || permissions.has('APPROVE_INITIATIVE'),
            administrator: admin
                || permissions.has('MANAGE_USERS')
        };
    }

    function createBadge(label, enabled, className = '') {
        const badge = document.createElement('span');
        badge.className = `admin-access-badge ${enabled ? 'is-enabled' : 'is-disabled'} ${className}`.trim();
        badge.textContent = `${enabled ? '✓' : '—'} ${label}`;
        return badge;
    }

    function renderSummary(user) {
        const access = derivedAccess(user);
        elements.summary.replaceChildren(
            createBadge(
                t('Create Agreements', 'إنشاء الاتفاقيات'),
                access.agreement
            ),
            createBadge(
                t('Create Initiatives', 'إنشاء المبادرات'),
                access.initiative
            ),
            createBadge(
                t('Agreement approver', 'معتمد اتفاقيات'),
                access.agreementApproval
            ),
            createBadge(
                t('Initiative approver', 'معتمد مبادرات'),
                access.initiativeApproval
            ),
            createBadge(
                t('Administrator', 'مسؤول نظام'),
                access.administrator,
                'is-admin'
            )
        );
    }

    function appendOption(select, value, label, disabled = false) {
        const option = document.createElement('option');
        option.value = String(value ?? '');
        option.textContent = label;
        option.disabled = disabled;
        select.appendChild(option);
    }

    function unitLabel(unit) {
        const indentation = '— '.repeat(Math.max(0, Number(unit.depth) || 0));
        const typeNames = {
            UNIVERSITY: t('University', 'جامعة'),
            OFFICE: t('Office', 'مكتب'),
            COLLEGE: t('College', 'كلية'),
            DEPARTMENT: t('Department', 'قسم')
        };
        const code = unit.code ? ` · ${unit.code}` : '';
        const inactive = unit.is_active ? '' : ` · ${t('inactive', 'غير نشطة')}`;
        return `${indentation}${unit.name}${code} · ${typeNames[unit.unit_type] || unit.unit_type}${inactive}`;
    }

    function selectedUnit() {
        const unitId = Number(elements.unit.value || 0);
        return (state.options?.units || []).find(
            (unit) => Number(unit.unit_id) === unitId
        ) || null;
    }

    function positionCompatibleWithUnit(position, unit) {
        if (!unit) return true;

        const positionName = String(position?.name || '').trim().toLowerCase();
        const unitCode = String(unit?.code || '').trim().toUpperCase();
        const unitType = String(unit?.unit_type || '').trim().toUpperCase();
        const requiredCodes = {
            'president': 'PRES',
            'president office staff': 'PRES',
            'president office delegate': 'PRES',
            'vice president': 'VP',
            'vice president office staff': 'VP',
            'vice president office delegate': 'VP',
            'legal reviewer': 'LEGAL',
            'finance reviewer': 'FIN'
        };
        const requiredCode = requiredCodes[positionName] || null;
        if (requiredCode && unitCode !== requiredCode) return false;

        const allowedTypes = {
            'dean': ['COLLEGE'],
            'department head': ['DEPARTMENT'],
            'head of department': ['DEPARTMENT'],
            'faculty member': ['COLLEGE', 'DEPARTMENT'],
            'president': ['OFFICE'],
            'vice president': ['OFFICE'],
            'president office staff': ['OFFICE'],
            'president office delegate': ['OFFICE'],
            'vice president office staff': ['OFFICE'],
            'vice president office delegate': ['OFFICE'],
            'legal reviewer': ['OFFICE'],
            'finance reviewer': ['OFFICE']
        }[positionName] || [];

        return allowedTypes.length === 0 || allowedTypes.includes(unitType);
    }

    function renderPositionOptions(selectedPositionId = null) {
        const currentValue = selectedPositionId === null
            ? Number(elements.position.value || 0)
            : Number(selectedPositionId || 0);
        const prompt = elements.position.options[0]?.cloneNode(true)
            || new Option(t('No active position', 'بدون منصب نشط'), '');
        elements.position.replaceChildren(prompt);

        const unit = selectedUnit();
        let previousType = null;
        let currentGroup = null;

        (state.options?.positions || [])
            .filter((position) => positionCompatibleWithUnit(position, unit))
            .forEach((position) => {
                if (position.position_type !== previousType) {
                    currentGroup = document.createElement('optgroup');
                    currentGroup.label = position.position_type;
                    elements.position.appendChild(currentGroup);
                    previousType = position.position_type;
                }

                const option = document.createElement('option');
                option.value = String(position.position_id);
                option.textContent = `${position.name}${position.is_unique ? ` · ${t('unique', 'منصب فريد')}` : ''}`;
                currentGroup.appendChild(option);
            });

        if (
            currentValue > 0
            && elements.position.querySelector(`option[value="${currentValue}"]`)
        ) {
            elements.position.value = String(currentValue);
        } else {
            elements.position.value = '';
        }
    }

    function populateOptions() {
        const unitPrompt = elements.unit.options[0].cloneNode(true);
        const filterPrompt = elements.unitFilter.options[0].cloneNode(true);
        elements.unit.replaceChildren(unitPrompt);
        elements.unitFilter.replaceChildren(filterPrompt);

        (state.options?.units || []).forEach((unit) => {
            appendOption(
                elements.unit,
                unit.unit_id,
                unitLabel(unit),
                !unit.is_active
            );
            if (unit.is_active) {
                appendOption(elements.unitFilter, unit.unit_id, unitLabel(unit));
            }
        });

        renderPositionOptions();
    }

    function roleGroup(role) {
        const name = role.role_name;
        if (name.startsWith('Agreement ')) {
            return 'agreement';
        }
        if (name.startsWith('Initiative ')) {
            return 'initiative';
        }
        if (name === 'System Administrator') {
            return 'administration';
        }
        return 'other';
    }

    function roleGroupTitle(group) {
        return {
            agreement: t('Agreement workflow', 'سير عمل الاتفاقيات'),
            initiative: t('Initiative workflow', 'سير عمل المبادرات'),
            administration: t('System administration', 'إدارة النظام'),
            other: t('Additional roles', 'أدوار إضافية')
        }[group];
    }

    function roleExplanation(roleName) {
        return {
            'Agreement Creator': t(
                'May create, edit, and submit Agreements.',
                'يمكنه إنشاء الاتفاقيات وتعديلها وإرسالها.'
            ),
            'Agreement Approver': t(
                'May review, approve, or reject assigned Agreement steps.',
                'يمكنه مراجعة واعتماد أو رفض خطوات الاتفاقيات المسندة إليه.'
            ),
            'Initiative Creator': t(
                'May create and submit Initiative requests.',
                'يمكنه إنشاء طلبات المبادرات وإرسالها.'
            ),
            'Initiative Approver': t(
                'May review Initiative requests assigned through the hierarchy.',
                'يمكنه مراجعة طلبات المبادرات المسندة إليه حسب الهيكل التنظيمي.'
            ),
            'System Administrator': t(
                'Full access, including user management. Grant carefully.',
                'صلاحيات كاملة تشمل إدارة المستخدمين. تُمنح بحذر.'
            )
        }[roleName] || t('Additional system role.', 'دور إضافي في النظام.');
    }

    function renderRoleControls(selectedRoleIds) {
        const selected = new Set(selectedRoleIds.map(Number));
        elements.roleGroups.replaceChildren();

        ['agreement', 'initiative', 'administration', 'other'].forEach((groupName) => {
            const roles = (state.options?.roles || []).filter(
                (role) => roleGroup(role) === groupName
            );
            if (roles.length === 0) return;

            const section = document.createElement('section');
            section.className = 'admin-role-group';

            const title = document.createElement('h3');
            title.className = 'h6 mb-3';
            title.textContent = roleGroupTitle(groupName);
            section.appendChild(title);

            roles.forEach((role) => {
                const label = document.createElement('label');
                label.className = 'admin-role-option';

                const checkbox = document.createElement('input');
                checkbox.className = 'form-check-input';
                checkbox.type = 'checkbox';
                checkbox.value = String(role.role_id);
                checkbox.checked = selected.has(Number(role.role_id));
                checkbox.dataset.adminRoleId = String(role.role_id);

                const copy = document.createElement('span');
                const strong = document.createElement('strong');
                strong.textContent = role.role_name;
                const description = document.createElement('small');
                description.textContent = roleExplanation(role.role_name);
                copy.append(strong, description);

                label.append(checkbox, copy);
                section.appendChild(label);
            });

            elements.roleGroups.appendChild(section);
        });

        elements.roleGroups.querySelectorAll('[data-admin-role-id]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                renderSummary(userFromForm());
                renderWorkflowPreview();
            });
        });
    }

    function renderUsers() {
        elements.loading.classList.add('d-none');
        elements.body.replaceChildren();
        elements.empty.classList.toggle('d-none', state.users.length !== 0);
        elements.tableWrap.classList.toggle('d-none', state.users.length === 0);

        const total = state.pagination.total || 0;
        elements.count.textContent = t(
            `${total} user${total === 1 ? '' : 's'}`,
            `${total} مستخدم`
        );

        state.users.forEach((user) => {
            const row = document.createElement('tr');
            row.tabIndex = 0;
            row.dataset.userId = String(user.user_id);
            row.classList.toggle('is-selected', user.user_id === state.selectedId);

            const identityCell = document.createElement('td');
            const identity = document.createElement('div');
            identity.className = 'admin-user-identity';
            const avatar = document.createElement('span');
            avatar.className = 'admin-user-list-avatar';
            avatar.textContent = initials(user);
            const identityText = document.createElement('span');
            const name = document.createElement('strong');
            name.textContent = fullName(user);
            const meta = document.createElement('small');
            meta.textContent = `${user.university_id || '—'} · ${user.email || '—'}`;
            identityText.append(name, meta);
            identity.append(avatar, identityText);
            identityCell.appendChild(identity);

            const accessCell = document.createElement('td');
            const access = derivedAccess(user);
            const chips = document.createElement('div');
            chips.className = 'admin-table-chips';
            if (access.agreement) {
                chips.appendChild(createBadge(t('Agreement', 'اتفاقية'), true));
            }
            if (access.initiative) {
                chips.appendChild(createBadge(t('Initiative', 'مبادرة'), true));
            }
            if (access.administrator) {
                chips.appendChild(createBadge(t('Admin', 'مسؤول'), true, 'is-admin'));
            }
            if (!chips.hasChildNodes()) {
                chips.textContent = '—';
            }
            accessCell.appendChild(chips);

            const organizationCell = document.createElement('td');
            const position = currentPosition(user);
            const organization = document.createElement('div');
            organization.className = 'admin-organization-cell';
            const positionName = document.createElement('strong');
            positionName.textContent = position?.position_name || t('No position', 'بدون منصب');
            const unitName = document.createElement('small');
            unitName.textContent = position?.unit_name || t('No active unit', 'بدون وحدة نشطة');
            organization.append(positionName, unitName);
            organizationCell.appendChild(organization);

            const statusCell = document.createElement('td');
            const status = document.createElement('span');
            status.className = `status-badge ${user.is_active ? 'status-active' : 'status-rejected'}`;
            status.textContent = user.is_active
                ? t('Active', 'نشط')
                : t('Inactive', 'غير نشط');
            statusCell.appendChild(status);

            row.append(identityCell, accessCell, organizationCell, statusCell);
            row.addEventListener('click', () => selectUser(user.user_id));
            row.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectUser(user.user_id);
                }
            });
            elements.body.appendChild(row);
        });

        elements.pagination.classList.toggle(
            'd-none',
            state.pagination.pages <= 1
        );
        elements.page.textContent = t(
            `Page ${state.pagination.page} of ${state.pagination.pages}`,
            `الصفحة ${state.pagination.page} من ${state.pagination.pages}`
        );
        elements.prev.disabled = state.pagination.page <= 1;
        elements.next.disabled = state.pagination.page >= state.pagination.pages;
    }

    function renderHistory(positions) {
        elements.history.replaceChildren();
        const rows = Array.isArray(positions) ? positions : [];

        if (rows.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'text-center text-secondary py-4';
            cell.textContent = t(
                'No organizational assignment history.',
                'لا يوجد سجل للتكليفات التنظيمية.'
            );
            row.appendChild(cell);
            elements.history.appendChild(row);
            return;
        }

        rows.forEach((position) => {
            const row = document.createElement('tr');
            const positionCell = document.createElement('td');
            positionCell.textContent = position.position_name || '—';
            const unitCell = document.createElement('td');
            unitCell.textContent = position.unit_name || '—';
            const periodCell = document.createElement('td');
            periodCell.textContent = `${position.start_date || '—'} — ${position.end_date || t('Present', 'حتى الآن')}`;
            const statusCell = document.createElement('td');
            const status = document.createElement('span');
            status.className = `status-badge ${position.is_active ? 'status-active' : 'status-default'}`;
            status.textContent = position.is_active
                ? t('Current', 'حالي')
                : t('Previous', 'سابق');
            statusCell.appendChild(status);
            row.append(positionCell, unitCell, periodCell, statusCell);
            elements.history.appendChild(row);
        });
    }

    function field(name) {
        return elements.form.querySelector(`[data-admin-field="${name}"]`);
    }

    function populateUser(user) {
        state.selectedUser = user;
        elements.placeholder.classList.add('d-none');
        elements.detailLoading.classList.add('d-none');
        elements.form.classList.remove('d-none');

        elements.avatar.textContent = initials(user);
        elements.name.textContent = fullName(user);
        const position = currentPosition(user);
        elements.context.textContent = position
            ? `${position.position_name} · ${position.unit_name}`
            : t('No active organizational assignment', 'لا يوجد تكليف تنظيمي نشط');
        elements.active.checked = user.is_active === true;

        ['university_id', 'first_name', 'last_name', 'email', 'phone'].forEach((name) => {
            field(name).value = user[name] ?? '';
        });
        elements.form.querySelector('[data-admin-readonly="last_login"]').textContent =
            user.last_login ? AgreementApi.formatDate(user.last_login) : '—';

        renderRoleControls((user.roles || []).map((role) => role.role_id));
        renderSummary(user);

        elements.unit.value = position?.unit_id ? String(position.unit_id) : '';
        renderPositionOptions(position?.position_id || null);
        elements.effectiveDate.value = today();
        elements.effectiveDate.max = today();
        elements.reason.value = '';
        renderHistory(user.positions || []);
        elements.workflowSelects.forEach((select) => {
            select.value = '';
        });
        document.querySelectorAll('[data-admin-workflow-message]').forEach((message) => {
            message.textContent = '';
            message.className = 'admin-workflow-message mb-0';
        });
        renderWorkflowPreview();
        activateTab('identity');
    }

    async function selectUser(userId) {
        if (state.busy || userId === state.selectedId && state.selectedUser) {
            return;
        }

        clearMessages();
        state.selectedId = Number(userId);
        state.selectedUser = null;
        renderUsers();
        elements.placeholder.classList.add('d-none');
        elements.form.classList.add('d-none');
        elements.detailLoading.classList.remove('d-none');

        try {
            const user = await AgreementApi.request(
                `/admin/users/${encodeURIComponent(userId)}`
            );
            if (state.selectedId !== Number(userId)) return;
            populateUser(user);
        } catch (error) {
            elements.detailLoading.classList.add('d-none');
            elements.placeholder.classList.remove('d-none');
            showAlert(error.message || t(
                'The user could not be loaded.',
                'تعذر تحميل بيانات المستخدم.'
            ));
        }
    }

    function queryString() {
        const params = new URLSearchParams();
        const search = elements.search.value.trim();
        if (search) params.set('search', search);
        if (elements.status.value) params.set('active', elements.status.value);
        if (elements.unitFilter.value) {
            params.set('unit_id', elements.unitFilter.value);
        }
        params.set('page', String(state.pagination.page));
        params.set('limit', '25');
        return params.toString();
    }

    async function loadUsers({ preserveSelection = true } = {}) {
        if (!preserveSelection) {
            state.selectedId = null;
            state.selectedUser = null;
            elements.form.classList.add('d-none');
            elements.placeholder.classList.remove('d-none');
        }

        elements.loading.classList.remove('d-none');
        elements.empty.classList.add('d-none');
        elements.tableWrap.classList.add('d-none');

        try {
            const response = await AgreementApi.request(
                `/admin/users?${queryString()}`
            );
            state.users = response.items || [];
            state.pagination = response.pagination || state.pagination;
            renderUsers();

            if (
                !preserveSelection
                && state.selectedId === null
                && state.users.length > 0
            ) {
                await selectUser(state.users[0].user_id);
            }
        } catch (error) {
            elements.loading.classList.add('d-none');
            showAlert(error.message || t(
                'Users could not be loaded.',
                'تعذر تحميل المستخدمين.'
            ));
        }
    }

    function selectedRoleIds() {
        return Array.from(
            elements.roleGroups.querySelectorAll('[data-admin-role-id]:checked')
        ).map((checkbox) => Number(checkbox.value));
    }

    function userFromForm() {
        const selectedRoles = new Set(selectedRoleIds());
        const roles = (state.options?.roles || []).filter(
            (role) => selectedRoles.has(Number(role.role_id))
        );
        const permissions = Array.from(new Set(
            roles.flatMap((role) => role.permissions || [])
        ));
        return { roles, permissions };
    }

    function payload() {
        return {
            university_id: field('university_id').value.trim(),
            first_name: field('first_name').value.trim(),
            last_name: field('last_name').value.trim(),
            email: field('email').value.trim(),
            phone: field('phone').value.trim(),
            is_active: elements.active.checked,
            role_ids: selectedRoleIds(),
            unit_id: elements.unit.value ? Number(elements.unit.value) : null,
            position_id: elements.position.value
                ? Number(elements.position.value)
                : null,
            effective_date: elements.effectiveDate.value || today(),
            reason: elements.reason.value.trim(),
            expected_updated_at: state.selectedUser?.updated_at || ''
        };
    }

    function setBusy(busy) {
        state.busy = busy;
        elements.save.disabled = busy;
        elements.saveSpinner.classList.toggle('d-none', !busy);
        elements.saveLabel.textContent = busy
            ? t('Saving…', 'جارٍ الحفظ…')
            : t('Save changes', 'حفظ التغييرات');
        elements.form.querySelectorAll('input, select, textarea, button').forEach((control) => {
            if (control.matches('[data-admin-tab]')) return;
            control.disabled = busy;
        });
    }

    function needsSensitiveConfirmation(nextPayload) {
        const oldRoles = new Set((state.selectedUser?.roles || []).map(
            (role) => role.role_name
        ));
        const selected = new Set(selectedRoleIds());
        const selectedNames = new Set((state.options?.roles || [])
            .filter((role) => selected.has(Number(role.role_id)))
            .map((role) => role.role_name));

        if (
            !oldRoles.has('System Administrator')
            && selectedNames.has('System Administrator')
        ) {
            return t(
                'This grants full system administration, including user management. Continue?',
                'سيمنح هذا المستخدم صلاحيات إدارة النظام كاملة بما فيها إدارة المستخدمين. هل تريد المتابعة؟'
            );
        }

        if (state.selectedUser?.is_active && !nextPayload.is_active) {
            return t(
                'This will deactivate the account and prevent sign-in. Continue?',
                'سيؤدي هذا إلى تعطيل الحساب ومنع تسجيل الدخول. هل تريد المتابعة؟'
            );
        }

        return null;
    }

    async function saveUser(event) {
        event.preventDefault();
        if (state.busy || !state.selectedId) return;
        clearMessages();

        if (!elements.form.reportValidity()) return;
        const nextPayload = payload();
        if ((nextPayload.unit_id === null) !== (nextPayload.position_id === null)) {
            activateTab('organization');
            showAlert(t(
                'Select both an organizational unit and a position, or clear both.',
                'اختر الوحدة التنظيمية والمنصب معًا، أو اترك الاثنين بدون اختيار.'
            ));
            return;
        }

        const confirmation = needsSensitiveConfirmation(nextPayload);
        if (confirmation && !window.confirm(confirmation)) return;

        setBusy(true);
        try {
            const updated = await AgreementApi.request(
                `/admin/users/${encodeURIComponent(state.selectedId)}`,
                {
                    method: 'PATCH',
                    body: AgreementApi.jsonBody(nextPayload)
                }
            );
            populateUser(updated);
            await loadUsers({ preserveSelection: true });
            showSuccess(t(
                'User changes were saved and recorded in the audit log.',
                'تم حفظ تغييرات المستخدم وتسجيلها في سجل التدقيق.'
            ));
        } catch (error) {
            showAlert(error.message || t(
                'The user could not be saved.',
                'تعذر حفظ تغييرات المستخدم.'
            ));
        } finally {
            setBusy(false);
        }
    }

    function activateTab(name) {
        elements.form.querySelectorAll('[data-admin-tab]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.adminTab === name);
        });
        elements.form.querySelectorAll('[data-admin-panel]').forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.adminPanel !== name);
        });
    }

    function bindEvents() {
        elements.filters.addEventListener('submit', (event) => event.preventDefault());
        elements.search.addEventListener('input', () => {
            window.clearTimeout(state.searchTimer);
            state.searchTimer = window.setTimeout(() => {
                state.pagination.page = 1;
                loadUsers({ preserveSelection: false });
            }, 300);
        });

        [elements.status, elements.unitFilter].forEach((control) => {
            control.addEventListener('change', () => {
                state.pagination.page = 1;
                loadUsers({ preserveSelection: false });
            });
        });

        elements.refresh.addEventListener('click', async () => {
            const selectedId = state.selectedId;
            await loadUsers({ preserveSelection: true });
            if (selectedId) {
                state.selectedUser = null;
                await selectUser(selectedId);
            }
        });
        elements.prev.addEventListener('click', () => {
            if (state.pagination.page <= 1) return;
            state.pagination.page -= 1;
            loadUsers({ preserveSelection: false });
        });
        elements.next.addEventListener('click', () => {
            if (state.pagination.page >= state.pagination.pages) return;
            state.pagination.page += 1;
            loadUsers({ preserveSelection: false });
        });
        elements.unit.addEventListener('change', () => {
            renderPositionOptions();
            renderWorkflowPreview();
        });
        elements.position.addEventListener('change', renderWorkflowPreview);
        elements.workflowSelects.forEach((select) => {
            select.addEventListener('change', () => {
                setWorkflowMessage(select.dataset.adminWorkflowSelect, '');
            });
        });
        elements.form.addEventListener('submit', saveUser);
        elements.form.querySelectorAll('[data-admin-tab]').forEach((button) => {
            button.addEventListener('click', () => activateTab(button.dataset.adminTab));
        });
    }

    async function initialize() {
        try {
            await AgreementApi.requireSession('MANAGE_USERS');
            state.options = await AgreementApi.request('/admin/users/options');
            populateOptions();
            bindEvents();
            await loadUsers({ preserveSelection: false });
        } catch (error) {
            elements.loading.classList.add('d-none');
            showAlert(error.message || t(
                'User management could not be opened.',
                'تعذر فتح إدارة المستخدمين.'
            ));
        }
    }

    initialize();
})();
