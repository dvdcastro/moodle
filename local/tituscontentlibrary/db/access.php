<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/tituscontentlibrary:manageintegration' => [
        'riskbitmask' => RISK_CONFIG,
        'captype'     => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => ['manager' => CAP_ALLOW],
    ],
    'local/tituscontentlibrary:addcontent' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype'     => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => ['manager' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
    ],
];
