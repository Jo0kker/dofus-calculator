<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordWebhookService
{
    private ?string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.discord.webhook_url');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->webhookUrl);
    }

    public function sendImportResult(array $result, float $duration, ?string $triggeredBy = null): void
    {
        if (! $this->isConfigured()) {
            Log::warning('Discord webhook URL not configured, skipping notification');

            return;
        }

        $imported = $result['imported'] ?? 0;
        $updated = $result['updated'] ?? 0;
        $deleted = $result['deleted'] ?? 0;
        $deletedItems = $result['deleted_items'] ?? 0;
        $unknownJobIds = $result['unknown_job_ids'] ?? [];
        $errors = $result['errors'] ?? [];
        $hasErrors = ! empty($errors);

        $color = $hasErrors ? 15158332 : 3066993; // Red or Green
        $status = $hasErrors ? 'Terminé avec erreurs' : 'Terminé avec succès';

        $fields = [
            [
                'name' => 'Recettes importées',
                'value' => (string) $imported,
                'inline' => true,
            ],
            [
                'name' => 'Recettes mises à jour',
                'value' => (string) $updated,
                'inline' => true,
            ],
            [
                'name' => 'Recettes supprimées',
                'value' => (string) $deleted,
                'inline' => true,
            ],
            [
                'name' => 'Items supprimés',
                'value' => (string) $deletedItems,
                'inline' => true,
            ],
            [
                'name' => 'Durée',
                'value' => $this->formatDuration($duration),
                'inline' => true,
            ],
        ];

        if (! empty($unknownJobIds)) {
            $fields[] = [
                'name' => 'IDs de métiers inconnus',
                'value' => implode(', ', $unknownJobIds),
                'inline' => false,
            ];
        }

        if ($triggeredBy) {
            $fields[] = [
                'name' => 'Lancé par',
                'value' => $triggeredBy,
                'inline' => true,
            ];
        }

        if ($hasErrors) {
            $errorCount = count($errors);
            $sanitizedErrors = array_map(fn (string $err) => str_replace(['`', '@', '<', '>'], '', $err), array_slice($errors, 0, 5));
            $errorSample = implode("\n", $sanitizedErrors);
            if ($errorCount > 5) {
                $errorSample .= "\n... et ".($errorCount - 5).' autres erreurs';
            }
            $fields[] = [
                'name' => "Erreurs ($errorCount)",
                'value' => "```\n".mb_substr($errorSample, 0, 1000)."\n```",
                'inline' => false,
            ];
        }

        $payload = [
            'embeds' => [
                [
                    'title' => 'Import Recettes - '.$status,
                    'color' => $color,
                    'fields' => $fields,
                    'timestamp' => now()->toIso8601String(),
                    'footer' => [
                        'text' => 'Dofus Calculator',
                    ],
                ],
            ],
        ];

        try {
            $response = Http::post($this->webhookUrl, $payload);

            if (! $response->successful()) {
                Log::error('Failed to send Discord webhook', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Discord webhook exception', ['error' => $e->getMessage()]);
        }
    }

    private function formatDuration(float $seconds): string
    {
        $roundedSeconds = max(0, (int) round($seconds));

        if ($roundedSeconds < 60) {
            return round($seconds, 1).'s';
        }

        $minutes = intdiv($roundedSeconds, 60);
        $remainingSeconds = $roundedSeconds % 60;

        return "{$minutes}m {$remainingSeconds}s";
    }
}
