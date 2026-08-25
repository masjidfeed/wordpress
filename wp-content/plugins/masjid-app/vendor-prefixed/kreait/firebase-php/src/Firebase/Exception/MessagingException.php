<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception;

interface MessagingException extends FirebaseException
{
    /**
     * @return array<mixed>
     */
    public function errors(): array;
}
