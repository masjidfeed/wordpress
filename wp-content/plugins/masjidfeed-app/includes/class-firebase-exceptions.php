<?php
/**
 * Firebase client exceptions mirroring the retry semantics of the FCM and IID APIs.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_Firebase_Exception extends RuntimeException {

    private $retry_after;
    private $http_status = 0;
    private $response_body = '';

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

    public function set_response_details(int $http_status, string $response_body): void {
        $this->http_status = $http_status;
        $this->response_body = substr($response_body, 0, 2000);
    }

    public function http_status(): int {
        return $this->http_status;
    }

    public function response_body(): string {
        return $this->response_body;
    }
}

class Masjid_Feed_Firebase_Api_Connection_Failed extends Masjid_Feed_Firebase_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_Feed_Firebase_Invalid_Message extends Masjid_Feed_Firebase_Exception {
}

class Masjid_Feed_Firebase_Authentication_Error extends Masjid_Feed_Firebase_Exception {
}

class Masjid_Feed_Firebase_Not_Found extends Masjid_Feed_Firebase_Exception {
}

class Masjid_Feed_Firebase_Quota_Exceeded extends Masjid_Feed_Firebase_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_Feed_Firebase_Server_Error extends Masjid_Feed_Firebase_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_Feed_Firebase_Server_Unavailable extends Masjid_Feed_Firebase_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_Feed_Firebase_Invalid_App_Check_Token extends Masjid_Feed_Firebase_Exception {
}

class Masjid_Feed_Firebase_Failed_Verify_App_Check_Token extends Masjid_Feed_Firebase_Exception {
}
