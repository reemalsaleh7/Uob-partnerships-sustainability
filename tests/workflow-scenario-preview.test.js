'use strict';

const assert = require('node:assert/strict');
const preview = require(
    '../uob-agreements/workspace/assets/js/admin-workflow-scenarios.js'
);

const stages = [
    {
        step_key: 'CREATOR',
        step_label: 'Creator',
        execution_mode: 'SEQUENTIAL',
        is_optional: false
    },
    {
        step_key: 'VP_INITIAL',
        step_label: 'VP Initial',
        execution_mode: 'SEQUENTIAL',
        is_optional: false
    },
    {
        step_key: 'LEGAL_REVIEW',
        step_label: 'Legal Review',
        execution_mode: 'SEQUENTIAL',
        is_optional: false
    },
    {
        step_key: 'FINANCE_REVIEW',
        step_label: 'Finance Review',
        execution_mode: 'PARALLEL',
        is_optional: true
    },
    {
        step_key: 'VP_FINAL',
        step_label: 'VP Final',
        execution_mode: 'SEQUENTIAL',
        is_optional: false
    }
];

assert.equal(preview.optionalStageCount(stages), 1);
assert.equal(preview.totalScenarioCount(stages), '2');

const complete = preview.scenarioAt(stages, 0);
assert.equal(complete.skipped.length, 0);
assert.equal(complete.phases.length, 4);
assert.equal(complete.phases[2].stages.length, 2);
assert.deepEqual(
    complete.phases[2].stages.map((stage) => stage.step_key),
    ['LEGAL_REVIEW', 'FINANCE_REVIEW']
);

const withoutFinance = preview.scenarioAt(stages, 1);
assert.deepEqual(
    withoutFinance.skipped.map((stage) => stage.step_key),
    ['FINANCE_REVIEW']
);
assert.equal(withoutFinance.phases[2].stages.length, 1);
assert.equal(withoutFinance.includedStageCount, 4);

const twoOptional = [
    ...stages,
    {
        step_key: 'MEDIA_REVIEW',
        step_label: 'Media Review',
        execution_mode: 'SEQUENTIAL',
        is_optional: true
    }
];
assert.equal(preview.totalScenarioCount(twoOptional), '4');
assert.equal(preview.scenarioAt(twoOptional, 0).skipped.length, 0);
assert.equal(preview.scenarioAt(twoOptional, 3).skipped.length, 2);

assert.throws(
    () => preview.scenarioAt(stages, 2),
    /outside the available range/
);

console.log('Workflow scenario visual preview algorithm tests passed.');
