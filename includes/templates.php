<?php
if (!defined('ABSPATH'))
    exit;

function cpp_get_template_versions()
{
    return [
        'v1' => [
            'label' => 'Version 1 (2025)',
            'components' => [
                'Pre-Tax Premiums' => '<h3>Pre-Tax Premiums</h3>'
                    // Main test paragraph (major rewrite test)
                    . '<p><span class="cpp-template" data-key="plan-purpose">This Plan exists to give eligible employees (“Employees”) a variety of benefit choices, such as medical, dental, and vision insurance, a Health Savings Account (“HSA”), a Health Flexible Spending Arrangement (“Health FSA”), and a Dependent Care Assistance Plan (“Dependent Care FSA”). Employees can pay for these benefits on a pre-tax basis, which lowers their taxable income.</span></p>'
                    // Test 1: Minor wording tweak (should be inline)
                    . '<p><span class="cpp-template" data-key="test1">This Plan allows eligible employees to pay for certain benefits on a pre-tax basis, lowering their taxable income.</span></p>'
                    // Test 3: Demographic variable (should be inline)
                    . '<p><span class="cpp-template" data-key="test3">This Cafeteria Plan is adopted by {{company_name}} and is effective as of {{effective_date}}.</span></p>'
                    // Test 4: Add/remove sentences (likely inline)
                    . '<p><span class="cpp-template" data-key="test4">The Dependent Care Assistance Plan allows employees to pay for eligible dependent care expenses on a pre-tax basis. Employees must follow all IRS rules regarding reimbursements.</span></p>'
                    // Test 5: Substantial content change (should be full replacement)
                    . '<p><span class="cpp-template" data-key="test5">Employees may elect to contribute to a Health Savings Account (HSA), which allows tax-free contributions, growth, and withdrawals for qualified medical expenses.</span></p>'
                    . '<p><span class="cpp-template" data-key="test_s1">Eligible employees may participate in this Plan after completing 30 days of service.</span></p>'
                    . '<p><span class="cpp-template" data-key="test_s2">Benefits under this Plan are subject to annual limits as set by the IRS.</span></p>'
                    . '<p><span class="cpp-template" data-key="test_s3">Contributions are made on a pre-tax basis. Participation is voluntary.</span></p>'
                    . '<p><span class="cpp-template" data-key="test_s4">This Plan is offered by {{company_name}} and provides a range of healthcare options, including vision and dental.</span></p>'
                    . '<p><span class="cpp-template" data-key="test_s5">Employees may enroll in this Plan after completing 30 days of service. Claims must be submitted by March 31 for reimbursement</span></p>',
                'Health Flexible Spending Account (Health FSA)' => '<h3>Health Flexible Spending Account (Health FSA)</h3><p>The Health Flexible Spending Arrangement (Health FSA) reimburses eligible medical expenses, including dental and vision care, using pre-tax dollars.</p>',
                'Health Savings Account (HSA)' => '<h3>Health Savings Account (HSA)</h3><p>Employees may elect to contribute to a Health Savings Account (HSA), which allows tax-free contributions, growth, and withdrawals for qualified medical expenses.</p>',
                'Dependent Care Account' => '<h3>Dependent Care Account</h3><p>The Dependent Care Assistance Plan (Dependent Care FSA) reimburses qualifying child and dependent care costs to enable employees to work or seek employment.</p>',
            ]
        ],
        'v2' => [
            'label' => 'Version 2 (2026)',
            'components' => [
                'Pre-Tax Premiums' => '<h3>Pre-Tax Premiums</h3>'
                    // Main test paragraph (major rewrite test)
                    . '<p><span class="cpp-template" data-key="plan-purpose">This Plan gives eligible employees (“Employees”) a range of benefit options, including medical, dental, and vision coverage, a Health Savings Account (“HSA”), Health Flexible Spending Arrangement (“Health FSA”), and Dependent Care Assistance Plan (“Dependent Care FSA”). Employees may pay for these benefits with pre-tax salary reductions, lowering their taxable income and enhancing their take-home pay.</span></p>'
                    // Test 1: Minor wording tweak (should be inline)
                    . '<p><span class="cpp-template" data-key="test1">This Plan allows eligible employees to pay for qualified benefits on a pre-tax basis, reducing their taxable income.</span></p>'
                    // Test 3: Demographic variable (should be inline)
                    . '<p><span class="cpp-template" data-key="test3">This Cafeteria Plan has been adopted by {{company_name}} and shall be effective as of {{effective_date}}.</span></p>'
                    // Test 4: Add/remove sentences (likely inline)
                    . '<p><span class="cpp-template" data-key="test4">The Dependent Care Assistance Plan reimburses employees for eligible dependent care expenses using pre-tax contributions. All reimbursements are subject to IRS rules and plan guidelines.</span></p>'
                    // Test 5: Substantial content change (should be full replacement)
                    . '<p><span class="cpp-template" data-key="test5">The Health Savings Account (HSA) program enables eligible employees to make contributions with pre-tax dollars. These funds may be used for qualified medical expenses, and the account balance can be carried forward from year to year.</span></p>'
                    . '<p><span class="cpp-template" data-key="test_s1">Eligible employees may participate in this Plan following completion of 30 days of service.</span></p>'
                    . '<p><span class="cpp-template" data-key="test_s2">Benefits under this Plan are subject to annual limits as set by the IRS. All changes to limits will be communicated to employees in advance.</span></p>'
                    . '<p><span class="cpp-template" data-key="test_s3">Contributions are made on a pre-tax basis.</span></p>'
                    . '<p><span class="cpp-template" data-key="test_s4">{{company_name}} sponsors this Plan, giving employees multiple benefit choices such as dental and vision coverage.</span></p>'
                    . '<p><span class="cpp-template" data-key="test_s5">Employees are eligible to participate following 30 days of employment. Claims must be submitted by March 31 to be reimbursed.</span></p>',
                'Health Flexible Spending Account (Health FSA)' => '<h3>Health Flexible Spending Account (Health FSA)</h3><p>Updated details about Health FSA...</p>',
                'Health Savings Account (HSA)' => '<h3>Health Savings Account (HSA)</h3><p>Updated details about HSA...</p>',
                'Dependent Care Account' => '<h3>Dependent Care Account</h3><p>Updated details about Dependent Care Account...</p>',
            ]
        ],
    ];
}

/**
 * 7) Load sample library from JSON or array
 */
function cpp_load_plan_library()
{
    return [
        [
            'id' => 'cobra_clause',
            'trigger' => 'include_cobra', // used if user selected "yes"
            'title' => 'COBRA Coverage Clause',
            'body' => 'Under this plan, employees who qualify may continue coverage per COBRA guidelines...',
        ],
        // You can add more standard paragraphs here (FSA, etc.) or just inline them in the PDF code.
    ];
}