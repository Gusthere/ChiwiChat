<?php
return [
    'keys_directory' => __DIR__ . '/../keys',
    'master_key' => getenv('ENCRYPTION_MASTER_KEY') ?: 'master_key_no_asignada',
];