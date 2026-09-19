<?php

namespace App\Support;

use Illuminate\Support\Carbon as BaseCarbon;

/**
 * BaseCarbon::toJSON() hardcodes UTC conversion (toISOString()), which
 * shifts date-only fields (stored at local midnight) into the previous
 * calendar day for any positive UTC offset — the app timezone is
 * Africa/Cairo (UTC+3). Eloquent's serializeDate() calls toJSON()
 * directly, so this is the override point that actually reaches model
 * serialization (Carbon::serializeUsing() does not: toJSON() doesn't
 * route through jsonSerialize()). Registered via Date::use() in
 * AppServiceProvider.
 */
class Carbon extends BaseCarbon
{
    public function toJSON(): ?string
    {
        return $this->toIso8601String();
    }
}
