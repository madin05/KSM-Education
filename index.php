<?php
// Clean URL (see .htaccess / deploy/nginx/ksmedu.conf). The legacy target
// user/dashboard_user.php still resolves, so old bookmarks keep working.
header("Location: user/dashboard");

exit();
?>
