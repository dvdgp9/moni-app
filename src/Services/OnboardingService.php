<?php
declare(strict_types=1);

namespace Moni\Services;

use Moni\Repositories\SettingsRepository;
use Moni\Repositories\UsersRepository;

final class OnboardingService
{
    private const KEY_STEP = 'onboarding_step';
    private const KEY_COMPLETED_AT = 'onboarding_completed_at';
    private const KEY_DISMISSED_AT = 'onboarding_dismissed_at';
    private const KEY_DEFAULT_VAT = 'default_vat_rate';
    private const KEY_DEFAULT_IRPF = 'default_irpf_rate';

    public static function getState(int $userId): array
    {
        $user = UsersRepository::find($userId) ?? [];
        $settings = SettingsRepository::all($userId);

        $defaultVat = self::parseNullableNumber($settings[self::KEY_DEFAULT_VAT] ?? null);
        $defaultIrpf = self::parseNullableNumber($settings[self::KEY_DEFAULT_IRPF] ?? null);
        $invoiceDueDays = self::parseNullableInt($settings['invoice_due_days'] ?? null);
        $storedModels = json_decode((string)($settings['tax_models'] ?? '[]'), true);
        $storedProfile = json_decode((string)($settings['tax_profile'] ?? '{}'), true);
        $storedModels = is_array($storedModels) ? array_values(array_filter(array_map('strval', $storedModels))) : [];
        $storedProfile = is_array($storedProfile) ? $storedProfile : [];

        $sections = [
            'identity' => [
                'label' => 'Identidad',
                'complete' => self::hasIdentity($user),
                'hint' => 'Nombre, NIF, dirección y email de facturación ayudan a emitir documentos completos.',
            ],
            'branding' => [
                'label' => 'Imagen',
                'complete' => !empty($user['logo_url']) && !empty($user['color_primary']) && !empty($user['color_accent']),
                'hint' => 'Sin logo o colores, tus documentos seguirán funcionando, pero se verán menos profesionales.',
            ],
            'invoicing' => [
                'label' => 'Facturación',
                'complete' => $invoiceDueDays !== null && $defaultVat !== null && $defaultIrpf !== null,
                'hint' => 'Sin valores por defecto tendrás que indicarlos manualmente en cada documento.',
            ],
            'fiscal' => [
                'label' => 'Fiscal',
                'complete' => !empty($storedModels) && !empty($storedProfile),
                'hint' => 'Sin esta configuración el centro fiscal no podrá adaptarse bien a tu caso.',
            ],
        ];

        $completedSections = count(array_filter($sections, static fn(array $section): bool => $section['complete']));
        $progress = (int)round(($completedSections / count($sections)) * 100);

        return [
            'user' => $user,
            'settings' => $settings,
            'step' => max(1, min(5, self::parseNullableInt($settings[self::KEY_STEP] ?? null) ?? 1)),
            'completed_at' => $settings[self::KEY_COMPLETED_AT] ?? null,
            'dismissed_at' => $settings[self::KEY_DISMISSED_AT] ?? null,
            'default_vat_rate' => $defaultVat,
            'default_irpf_rate' => $defaultIrpf,
            'invoice_due_days' => $invoiceDueDays,
            'tax_models' => $storedModels,
            'tax_profile' => $storedProfile,
            'sections' => $sections,
            'completed_sections' => $completedSections,
            'progress' => $progress,
        ];
    }

    public static function shouldResumeAfterLogin(int $userId): bool
    {
        $state = self::getState($userId);
        return empty($state['completed_at']) && empty($state['dismissed_at']);
    }

    public static function setStep(int $userId, int $step): void
    {
        SettingsRepository::set(self::KEY_STEP, (string)max(1, min(5, $step)), $userId);
    }

    public static function dismiss(int $userId, int $step): void
    {
        self::setStep($userId, $step);
        SettingsRepository::set(self::KEY_DISMISSED_AT, date('Y-m-d H:i:s'), $userId);
    }

    public static function resume(int $userId, ?int $step = null): void
    {
        SettingsRepository::set(self::KEY_DISMISSED_AT, null, $userId);
        if ($step !== null) {
            self::setStep($userId, $step);
        }
    }

    public static function complete(int $userId): void
    {
        SettingsRepository::set(self::KEY_COMPLETED_AT, date('Y-m-d H:i:s'), $userId);
        SettingsRepository::set(self::KEY_DISMISSED_AT, null, $userId);
        self::setStep($userId, 5);
    }

    private static function parseNullableNumber(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $normalized = str_replace(',', '.', trim($value));
        return is_numeric($normalized) ? (float)$normalized : null;
    }

    private static function parseNullableInt(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return ctype_digit(trim($value)) ? (int)$value : null;
    }

    private static function hasIdentity(array $user): bool
    {
        $nameReady = trim((string)($user['company_name'] ?? '')) !== '' || trim((string)($user['name'] ?? '')) !== '';
        $nifReady = trim((string)($user['nif'] ?? '')) !== '';
        $addressReady = trim((string)($user['address'] ?? '')) !== '';
        $emailReady = trim((string)($user['billing_email'] ?? ($user['email'] ?? ''))) !== '';
        return $nameReady && $nifReady && $addressReady && $emailReady;
    }
}
