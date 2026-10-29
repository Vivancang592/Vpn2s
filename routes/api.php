<?php

return [
    // API cấp liên kết Đăng ký cho App Client (V2Ray, Clash, Sing-Box...)
    'GET /sub'                         => ['Api\ClientController', 'subscribe'],

    // API Gateway giao tiếp Máy chủ Node VPS (Tích hợp unified endpoint cho Go/Bash)
    'POST /api/server/checkin'      => ['Api\ServerController', 'checkin'],
    'POST /api/server/sync'         => ['Api\ServerController', 'checkin'],
    'GET /api/server/users'         => ['Api\ServerController', 'users'],

    // API Webhook xử lý thanh toán tự động
    'POST /api/payment/webhook'     => ['Api\PaymentController', 'webhook'],

    // API chat Website công khai, được bảo vệ bằng phiên và CSRF
    'GET /api/ai-customer-service/session' => ['Api\AiCustomerServiceWebsiteChatController', 'bootstrap'],
    'GET /api/ai-customer-service/messages' => ['Api\AiCustomerServiceWebsiteChatController', 'messages'],
    'GET /api/ai-customer-service/typing' => ['Api\AiCustomerServiceWebsiteChatController', 'typingStatus'],
    'POST /api/ai-customer-service/typing' => ['Api\AiCustomerServiceWebsiteChatController', 'updateTypingStatus'],
    'GET /api/ai-customer-service/image' => ['Api\AiCustomerServiceWebsiteChatController', 'image'],
    'POST /api/ai-customer-service/message' => ['Api\AiCustomerServiceWebsiteChatController', 'sendMessage'],

    // Callback Meta công khai, xác minh bằng Verify Token và X-Hub-Signature-256
    'GET /webhook/facebook' => ['Api\AiCustomerServiceMetaWebhookController', 'verify'],
    'POST /webhook/facebook' => ['Api\AiCustomerServiceMetaWebhookController', 'receive'],
];