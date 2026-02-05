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
        return !empty($this->webhookUrl);
    }

    public function sendImportResult(array $result, float $duration, ?string $triggeredBy = null): void
    {
        if (!$this->isConfigured()) {
            Log::warning('Discord webhook URL not configured, skipping notification');
            return;
        }

        $imported = $result['imported'] ?? 0;
        $updated = $result['updated'] ?? 0;
        $errors = $result['errors'] ?? [];
        $hasErrors = !empty($errors);

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
                'name' => 'Durée',
                'value' => $this->formatDuration($duration),
                'inline' => true,
            ],
        ];

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
                $errorSample .= "\n... et " . ($errorCount - 5) . " autres erreurs";
            }
            $fields[] = [
                'name' => "Erreurs ($errorCount)",
                'value' => "```\n" . mb_substr($errorSample, 0, 1000) . "\n```",
                'inline' => false,
            ];
        }

        $payload = [
            'embeds' => [
                [
                    'title' => 'Import Recettes - ' . $status,
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

            if (!$response->successful()) {
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
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds - ($minutes * 60));

        return "{$minutes}m {$remainingSeconds}s";
    }
}
