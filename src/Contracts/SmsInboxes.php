<?php

namespace MaxoTel\Sms\Contracts;

use Illuminate\Support\Collection;
use MaxoTel\Sms\Data\InboxMessage;
use MaxoTel\Sms\Data\InboxMessageQuery;
use MaxoTel\Sms\Data\SmsInbox;

interface SmsInboxes
{
    /**
     * @return Collection<int, SmsInbox>
     */
    public function all(?string $connection = null): Collection;

    /**
     * @return Collection<int, InboxMessage>
     */
    public function messages(int $smsInbox, InboxMessageQuery $query, ?string $connection = null): Collection;

    public function messagesCsv(int $smsInbox, InboxMessageQuery $query, bool $headings = true, ?string $connection = null): string;
}
