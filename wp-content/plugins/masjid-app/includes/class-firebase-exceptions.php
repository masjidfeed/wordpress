<?php
/**
 * Firebase client exceptions mirroring the retry semantics of the FCM and IID APIs.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_App_Firebase_Exception extends RuntimeException {

    private $retry_after;

    public function __construct($message = '', $code = 0, ?Throwable $previous = null, ?int $retry_after = null) {
        parent::__construct($message, $code, $previous);
        $this->retry_after = $retry_after;
    }

    public function is_retryable(): bool {
        return false;
    }

    public function retry_after(): ?int {
        return $this->retry_after;
    }
}

class Masjid_App_Firebase_Api_Connection_Failed extends Masjid_App_Firebase_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_App_Firebase_Invalid_Message extends Masjid_App_Firebase_Exception {
}

class Masjid_App_Firebase_Authentication_Error extends Masjid_App_Firebase_Exception {
}

class Masjid_App_Firebase_Not_Found extends Masjid_App_Firebase_Exception {
}

class Masjid_App_Firebase_Quota_Exceeded extends Masjid_App_Firebase_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_App_Firebase_Server_Error extends Masjid_App_Firebase_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_App_Firebase_Server_Unavailable extends Masjid_App_Firebase_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_App_Firebase_Invalid_App_Check_Token extends Masjid_App_Firebase_Exception {
}

class Masjid_App_Firebase_Failed_Verify_App_Check_Token extends Masjid_App_Firebase_Exception {
}
