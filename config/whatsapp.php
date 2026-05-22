<?php
return [
    'phone_number_id' => getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '',
    'access_token' => getenv('WHATSAPP_ACCESS_TOKEN') ?: '',
    'template_name' => getenv('WHATSAPP_TEMPLATE_NAME') ?: 'admission_approved',
    'language_code' => getenv('WHATSAPP_TEMPLATE_LANGUAGE') ?: 'en_US',
    'graph_version' => getenv('WHATSAPP_GRAPH_VERSION') ?: 'v25.0',
];
