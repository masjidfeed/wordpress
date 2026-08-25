<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Contract;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\FirebaseException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\InvalidArgumentException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\MessagingException;
use Masjid_App\Dependencies\Kreait\Firebase\Messaging\AppInstance;
use Masjid_App\Dependencies\Kreait\Firebase\Messaging\Message;
use Masjid_App\Dependencies\Kreait\Firebase\Messaging\Messages;
use Masjid_App\Dependencies\Kreait\Firebase\Messaging\MulticastSendReport;
use Masjid_App\Dependencies\Kreait\Firebase\Messaging\RegistrationToken;
use Masjid_App\Dependencies\Kreait\Firebase\Messaging\RegistrationTokens;
use Masjid_App\Dependencies\Kreait\Firebase\Messaging\Topic;
/**
 * @phpstan-import-type MessageInputShape from Message
 */
interface Messaging
{
    /**
     * @deprecated 7.5.0
     */
    public const BATCH_MESSAGE_LIMIT = 500;
    /**
     * @param Message|MessageInputShape $message
     *
     * @throws MessagingException
     * @throws FirebaseException
     * @throws InvalidArgumentException
     *
     * @return array<array-key, mixed>
     */
    public function send(Message|array $message, bool $validateOnly = \false): array;
    /**
     * @param Message|MessageInputShape $message
     * @param RegistrationTokens|RegistrationToken|list<RegistrationToken|string>|non-empty-string $registrationTokens
     *
     * @throws InvalidArgumentException if the message is invalid or the list of registration tokens is empty
     * @throws MessagingException if the API request failed
     * @throws FirebaseException if something very unexpected happened (never :))
     */
    public function sendMulticast(Message|array $message, RegistrationTokens|RegistrationToken|array|string $registrationTokens, bool $validateOnly = \false): MulticastSendReport;
    /**
     * @param list<Message|MessageInputShape>|Messages $messages
     *
     * @throws InvalidArgumentException if the message is invalid
     * @throws MessagingException if the API request failed
     * @throws FirebaseException if something very unexpected happened (never :))
     */
    public function sendAll(array|Messages $messages, bool $validateOnly = \false): MulticastSendReport;
    /**
     * @param Message|MessageInputShape $message
     *
     * @throws InvalidMessage
     * @throws MessagingException
     * @throws FirebaseException
     * @throws InvalidArgumentException
     *
     * @return array<non-empty-string, mixed>
     */
    public function validate(Message|array $message): array;
    /**
     * @param RegistrationTokens|RegistrationToken|list<RegistrationToken|non-empty-string>|non-empty-string $registrationTokenOrTokens
     *
     * @throws MessagingException
     * @throws FirebaseException
     *
     * @return array{
     *     valid: list<non-empty-string>,
     *     unknown: list<non-empty-string>,
     *     invalid: list<non-empty-string>
     * }
     */
    public function validateRegistrationTokens(RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array;
    /**
     * @param Topic|non-empty-string $topic
     * @param RegistrationTokens|RegistrationToken|list<RegistrationToken|non-empty-string>|non-empty-string $registrationTokenOrTokens
     *
     * @return array<string, array<string, string>>
     */
    public function subscribeToTopic(string|Topic $topic, RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array;
    /**
     * @param iterable<non-empty-string|Topic> $topics
     * @param RegistrationTokens|RegistrationToken|list<RegistrationToken|non-empty-string>|non-empty-string $registrationTokenOrTokens
     *
     * @return array<string, array<string, string>>
     */
    public function subscribeToTopics(iterable $topics, RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array;
    /**
     * @param Topic|non-empty-string $topic
     * @param RegistrationTokens|RegistrationToken|list<RegistrationToken|non-empty-string>|non-empty-string $registrationTokenOrTokens
     *
     * @return array<string, array<string, string>>
     */
    public function unsubscribeFromTopic(string|Topic $topic, RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array;
    /**
     * @param array<non-empty-string|Topic> $topics
     * @param RegistrationTokens|RegistrationToken|list<RegistrationToken|non-empty-string>|non-empty-string $registrationTokenOrTokens
     *
     * @return array<string, array<string, string>>
     */
    public function unsubscribeFromTopics(array $topics, RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array;
    /**
     * @param RegistrationTokens|RegistrationToken|list<RegistrationToken|non-empty-string>|non-empty-string $registrationTokenOrTokens
     *
     * @return array<string, array<string, string>>
     */
    public function unsubscribeFromAllTopics(RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array;
    /**
     * @see https://developers.google.com/instance-id/reference/server#results
     *
     * @param RegistrationToken|non-empty-string $registrationToken
     *
     * @throws InvalidArgument if the registration token is invalid
     * @throws MessagingException
     */
    public function getAppInstance(RegistrationToken|string $registrationToken): AppInstance;
}
