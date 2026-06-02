<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/tituscontentlibrary:addcourse' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype'     => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'  => ['manager' => CAP_ALLOW],
    ],
    'local/tituscontentlibrary:viewlibrary' => [
        'riskbitmask'  => 0,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
    'local/tituscontentlibrary:manageplugin' => [
        'riskbitmask'  => RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
];
