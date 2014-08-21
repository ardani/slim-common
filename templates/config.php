<?php
// *** Local configuration file - do not version.

// General settings:
define('USE_API', true);

// Timezone, defaults to UTC:
define('TIMEZONE', 'UTC');

// Local DB configuration:
define('DB1_HOST', '127.0.0.1');
define('DB1_PORT', '3306');
define('DB1_NAME', '');
define('DB1_USER', 'local');
define('DB1_PASS', 'password');

// Local Memcached configuration:
define('CACHE1_HOST', '127.0.0.1');
define('CACHE1_PORT', '11211');

// nonce configuration
define('NONCE_LIFESPAN', 86400);
define('NONCE_SPLIT', 24);
define('AUTH_SALT', ''); // http://randomkeygen.com/

// Local logging configuration:
define('LOG_LEVEL', 4); // 0 (Fatal) - 4 (Debug), see Slim/Log.php

// Local Google client configuration:
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');