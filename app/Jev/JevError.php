<?php

declare(strict_types=1);

namespace App\Jev;

/**
 * Every way a Jev call can fail, each with a message safe to show on screen.
 * Only the 422 body is documented, so failures are classified by status.
 */
enum JevError: string
{
    case MissingKey = 'missing_key';
    case Unauthorised = 'unauthorised';
    case ImageRejected = 'image_rejected';
    case TooLarge = 'too_large';
    case InvalidRequest = 'invalid_request';
    case Busy = 'busy';
    case Timeout = 'timeout';
    case Unreachable = 'unreachable';
    case UnexpectedResponse = 'unexpected_response';

    public static function fromStatus(int $status): self
    {
        return match ($status) {
            400 => self::ImageRejected,
            401, 403 => self::Unauthorised,
            413 => self::TooLarge,
            422 => self::InvalidRequest,
            429, 529 => self::Busy,
            default => self::UnexpectedResponse,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::MissingKey => 'No Jev API key is set. Add JEV_API_KEY to .env, or set JEV_FAKE=true to try the app without one.',
            self::Unauthorised => 'Jev did not accept the API key. Check JEV_API_KEY in .env.',
            self::ImageRejected => 'Jev could not read that image. Try a different photo.',
            self::TooLarge => 'That image is too large for Jev. Try a smaller photo.',
            self::InvalidRequest => 'Jev rejected the request. Check JEV_MODEL is jev-preview or jev-1.14.0.',
            self::Busy => 'Jev is busy right now. Wait a moment and try again.',
            self::Timeout => 'Jev took too long to answer. Try again.',
            self::Unreachable => 'Could not reach Jev. Check your connection and JEV_BASE_URL.',
            self::UnexpectedResponse => 'Jev sent an answer this app does not understand. Try again.',
        };
    }
}
