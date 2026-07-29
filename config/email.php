<?php
// config/email.php

// The system will send emails FROM this address
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', '202208354@stu.uob.edu.bh'); // Your email
define('SMTP_PASSWORD', 'YOUR_PASSWORD');           // Your password
define('FROM_EMAIL', '202208354@stu.uob.edu.bh');    // Sender = Your email
define('FROM_NAME', 'UOB Notification System');      // Sender name

// NOTE: The system uses YOUR email to send notifications.
// Recipients will see "From: UOB Notification System <202208354@stu.uob.edu.bh>"