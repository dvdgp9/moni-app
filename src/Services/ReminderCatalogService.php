<?php
declare(strict_types=1);

namespace Moni\Services;

use Moni\Database;
use PDO;

final class ReminderCatalogService
{
    /**
     * Syncs system fiscal reminders for all users.
     * We keep this conservative and only manage reminders that belong to the catalog.
     */
    public static function syncForAllUsers(): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT id FROM users ORDER BY id ASC');
        $users = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($users as $row) {
            $userId = (int)($row['id'] ?? 0);
            if ($userId > 0) {
                self::syncForUser($userId);
            }
        }
    }

    public static function syncForUser(int $userId): void
    {
        $pdo = Database::pdo();

        foreach (self::systemReminders() as $reminder) {
            $select = $pdo->prepare('SELECT id, enabled FROM reminders WHERE user_id = :uid AND title = :title LIMIT 1');
            $select->execute([
                ':uid' => $userId,
                ':title' => $reminder['title'],
            ]);
            $existing = $select->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $update = $pdo->prepare(
                    'UPDATE reminders
                     SET event_date = :event_date,
                         end_date = :end_date,
                         recurring = :recurring,
                         links = :links,
                         mandatory = 1
                     WHERE id = :id AND user_id = :uid'
                );
                $update->execute([
                    ':event_date' => $reminder['event_date'],
                    ':end_date' => $reminder['end_date'],
                    ':recurring' => $reminder['recurring'],
                    ':links' => $reminder['links'],
                    ':id' => (int)$existing['id'],
                    ':uid' => $userId,
                ]);
                continue;
            }

            $insert = $pdo->prepare(
                'INSERT INTO reminders (title, event_date, end_date, recurring, links, mandatory, enabled, user_id)
                 VALUES (:title, :event_date, :end_date, :recurring, :links, 1, 1, :uid)'
            );
            $insert->execute([
                ':title' => $reminder['title'],
                ':event_date' => $reminder['event_date'],
                ':end_date' => $reminder['end_date'],
                ':recurring' => $reminder['recurring'],
                ':links' => $reminder['links'],
                ':uid' => $userId,
            ]);
        }
    }

    private static function systemReminders(): array
    {
        return [
            [
                'title' => 'Campana de la Renta: inicio de seguimiento',
                'event_date' => '2026-04-15',
                'end_date' => null,
                'recurring' => 'yearly',
                'links' => self::rentaLinks(),
            ],
            [
                'title' => 'Campana de la Renta: revision recomendada',
                'event_date' => '2026-06-15',
                'end_date' => null,
                'recurring' => 'yearly',
                'links' => self::rentaLinks(),
            ],
            [
                'title' => 'Campana de la Renta: tramo final',
                'event_date' => '2026-06-25',
                'end_date' => null,
                'recurring' => 'yearly',
                'links' => self::rentaLinks(),
            ],
        ];
    }

    private static function rentaLinks(): string
    {
        return json_encode([
            [
                'label' => 'Portal Renta',
                'url' => 'https://sede.agenciatributaria.gob.es/Sede/Renta.html',
            ],
            [
                'label' => 'Fechas oficiales de la campana 2026',
                'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/calendario-contribuyente/calendario-contribuyente-2026/recuerde/fechas-campana-renta-patrimonio.html',
            ],
            [
                'label' => 'Obtener referencia / Cl@ve',
                'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/consultas-informaticas/firma-digital-sistema-clave-pin-tecnica/obtener-referencia-casilla-renta.html',
            ],
            [
                'label' => 'Calendario del contribuyente',
                'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/calendario-contribuyente/calendario-contribuyente-2026.html',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
