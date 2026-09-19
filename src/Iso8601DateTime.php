<?php

declare(strict_types=1);

namespace HushHush;

/**
 * A \DateTime that formats itself as ATOM/ISO-8601 when cast to string.
 *
 * AuditLogApi::queryAuditLogRequest() declares from/to's openApiType as the
 * literal string 'string', not '\DateTime', so
 * ObjectSerializer::toQueryValue() never takes its DateTime-formatting
 * branch and instead bare-casts whatever it's handed — plain \DateTime has
 * no __toString(), so that throws. Still passing a real \DateTime (rather
 * than a pre-formatted string) keeps the value's type compatible with the
 * generated method's own `DateTime|null` docblock.
 */
final class Iso8601DateTime extends \DateTime implements \Stringable
{
    public function __toString(): string
    {
        return $this->format(\DateTime::ATOM);
    }
}
