<?php

namespace MaxoTel\Sms\History;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use MaxoTel\Sms\Contracts\SmsHistory;
use MaxoTel\Sms\Data\SmsHistoryQuery;
use MaxoTel\Sms\Data\SmsRecord;
use MaxoTel\Sms\Enums\SmsStatus;
use MaxoTel\Sms\Exceptions\HistoryUnavailableException;
use MaxoTel\Sms\Models\SmsLog;

/**
 * @internal
 */
final class DatabaseSmsHistory implements SmsHistory
{
    public function find(string $uuid): ?SmsRecord
    {
        return $this->model()->newQuery()->where('uuid', $uuid)->first()?->toRecord();
    }

    public function findBySmsId(int $smsId, ?string $connection = null): ?SmsRecord
    {
        $query = $this->model()->newQuery()->where('sms_id', $smsId);

        if ($connection !== null) {
            $query->where('connection', $connection);
        }

        return $query->first()?->toRecord();
    }

    public function search(SmsHistoryQuery $query): LengthAwarePaginator
    {
        return $this->apply($query)
            ->orderByDesc('created_at')
            ->paginate()
            ->through(fn (SmsLog $log): SmsRecord => $log->toRecord());
    }

    public function failures(SmsHistoryQuery $query): Collection
    {
        return $this->apply($query)
            ->whereIn('status', [SmsStatus::Failed->value, SmsStatus::Rejected->value])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SmsLog $log): SmsRecord => $log->toRecord());
    }

    public function statusFor(string $uuid): ?SmsStatus
    {
        $status = $this->model()->newQuery()->where('uuid', $uuid)->value('status');

        if ($status instanceof SmsStatus) {
            return $status;
        }

        return is_string($status) ? SmsStatus::tryFrom($status) : null;
    }

    /**
     * @return Builder<SmsLog>
     */
    private function apply(SmsHistoryQuery $query): Builder
    {
        $builder = $this->model()->newQuery();

        if ($query->recipient !== null) {
            $builder->where('recipient', $query->recipient);
        }

        if ($query->connection !== null) {
            $builder->where('connection', $query->connection);
        }

        if ($query->smsInbox !== null) {
            $builder->where('sms_inbox', $query->smsInbox);
        }

        if ($query->status !== null) {
            $builder->where('status', $query->status);
        }

        if ($query->errorType !== null) {
            $builder->where('error_type', $query->errorType);
        }

        if ($query->reference !== null) {
            $builder->where('reference', $query->reference);
        }

        if ($query->from !== null) {
            $builder->where('created_at', '>=', $query->from);
        }

        if ($query->to !== null) {
            $builder->where('created_at', '<=', $query->to);
        }

        return $builder;
    }

    private function model(): SmsLog
    {
        if (! config('sms.logging.enabled')) {
            throw new HistoryUnavailableException;
        }

        /** @var class-string<SmsLog> $class */
        $class = config('sms.logging.model', SmsLog::class);

        return new $class;
    }
}
