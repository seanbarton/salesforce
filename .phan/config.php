<?php

return [
  "allow_method_param_type_widening" => true,
  "prefer_narrowed_phpdoc_param_type" => false,
  "prefer_narrowed_phpdoc_return_type" => false,
  "dead_code_detection" => true,
  "directory_list" => ["src", "tests", "vendor"],
  "exclude_analysis_directory_list" => ["vendor"],
  "suppress_issue_types" => [
    // false positives
    "PhanReadOnlyPublicProperty",
    "PhanUnreferencedClass",
    "PhanUnreferencedPublicClassConstant",
    "PhanUnreferencedPublicMethod",
    // this is a stupid warning (be careful! this might do exactly what it's supposed to do)
    "PhanSuspiciousBinaryAddLists"
  ]
];
