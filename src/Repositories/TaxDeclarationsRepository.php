<?php
declare(strict_types=1);

namespace Moni\Repositories;

/** Uses existing per-user settings, so this feature does not require a database migration. */
final class TaxDeclarationsRepository
{
    public static function draft(string $model, int $year, int $quarter): array
    {
        $value = json_decode((string)SettingsRepository::get("tax_draft_{$model}_{$year}_{$quarter}"), true);
        return is_array($value) ? $value : [];
    }

    public static function saveDraft(string $model, int $year, int $quarter, array $draft): void
    {
        SettingsRepository::set("tax_draft_{$model}_{$year}_{$quarter}", json_encode($draft, JSON_THROW_ON_ERROR));
    }

    public static function history(): array
    {
        $history = [];
        foreach (SettingsRepository::all() as $key=>$value) {
            if (!str_starts_with($key, 'tax_filed_')) { continue; }
            $record = json_decode((string)$value, true);
            if (is_array($record) && ($record['status'] ?? '') === 'presented') { $history[] = $record; }
        }
        usort($history, static fn(array $a,array $b):int => \Moni\Services\TaxDeclarationService::revisionOrder($b, $a));
        return $history;
    }

    public static function addSubmission(array $record): void
    {
        $record['status'] = 'presented';
        $record['saved_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $key = "tax_filed_{$record['model']}_{$record['year']}_{$record['quarter']}_" . bin2hex(random_bytes(8));
        SettingsRepository::set($key, json_encode($record, JSON_THROW_ON_ERROR));
    }
}
