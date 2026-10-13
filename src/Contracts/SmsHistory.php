<?php

namespace MaxoTel\Sms\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use MaxoTel\Sms\Data\SmsHistoryQuery;
use MaxoTel\Sms\Data\SmsRecord;
use MaxoTel\Sms\Enums\SmsStatus;

interface SmsHistory
{
    public function find(string $uuid): ?SmsRecord;

    public function findBySmsId(int $smsId, ?string $connection = null): ?SmsRecord;

    /**
     * @return LengthAwarePaginator<int, SmsRecord>
     */
    public function search(SmsHistoryQuery $query): LengthAwarePaginator;

    /**
     * @return Collection<int, SmsRecord>
     */
    public function failures(SmsHistoryQuery $query): Collection;

    public function statusFor(string $uuid): ?SmsStatus;
}
