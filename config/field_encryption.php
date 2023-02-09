<?php
return [
    'aes_key' => env('FIELD_ENCRYPTION_AES_KEY', 'field_encryption'),
    'aes_pre' => env('FIELD_ENCRYPTION_AES_PRE', 'field_'),
    'aes_tail' => env('FIELD_ENCRYPTION_AES_TAIL', '_encryption'),
    'table_tmp' => env('FIELD_TABLE_TMP', '_tmp_field_encryption_4869'),
    'table_tmp_switch' => (bool)env('FIELD_TABLE_TMP_SWITCH', true),

    'field' => [
//        [
//            'table' => 'test_table',
//            'column' => 'test_number',
//            'rule' => 'default',
//        ],
    ],

    'rules' => [
        //只是配置pre_len 和 encryption_field_len;pre_len 前置保留字段  encryption_field_len 中间加密字段
        'default' => [
            'pre_len' => 0,
            'tail_len' => 0, //字段其实不管用
            'encryption_field_len' => 20000,
        ],
    ],
];
