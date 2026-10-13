<?php

namespace MaxoTel\Sms\Support;

final class ResponseSanitizer
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(private array $keys) {}

    /**
     * @param  array<mixed>  $response
     * @return array<mixed>
     */
    public function sanitize(array $response): array
    {
        $redact = array_map(strtolower(...), $this->keys);

        return $this->walk($response, $redact);
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $redact
     * @return array<mixed>
     */
    private function walk(array $data, array $redact): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $redact, true)) {
                $clean[$key] = '[REDACTED]';

                continue;
            }

            $clean[$key] = is_array($value) ? $this->walk($value, $redact) : $value;
        }

        return $clean;
    }
}
