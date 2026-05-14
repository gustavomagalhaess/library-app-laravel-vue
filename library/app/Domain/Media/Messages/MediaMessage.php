<?php

namespace App\Domain\Media\Messages;

class MediaMessage
{
    public const string FAILED_TO_PERSIST_MEDIA = 'Failed to %s %s. Please try again in a few minutes.';
    public const string FAILED_TO_PERSIST_MEDIA_LOG = 'Failed to %s media (%s): %s';
    public const string UNSUPORTED_MEDIA_TYPE = 'Unsupported media type.';
    public const string ERROR = 'An error occurred while processing your request. Please try again in a few minutes.';
}
