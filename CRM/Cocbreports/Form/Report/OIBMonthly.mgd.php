<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  [
    'name' => 'CRM_Cocbreports_Form_Report_OIBMonthly',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'OIBMonthly',
      'description' => 'OIBMonthly (com.wannapixel.cocbreports)',
      'class_name' => 'CRM_Cocbreports_Form_Report_OIBMonthly',
      'report_url' => 'com.wannapixel.cocbreports/oibmonthly',
      'component' => '',
    ],
  ],
];
