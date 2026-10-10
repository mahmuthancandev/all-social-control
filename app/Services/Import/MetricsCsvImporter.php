<?php

namespace App\Services\Import;

use App\Models\AccountMetricSnapshot;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Platformların kendi analitik panellerinden (YouTube Studio, Instagram, TikTok, X Analytics)
 * dışa aktarılan veya elle hazırlanan günlük metrik CSV'lerini içe aktarır.
 * Yalnızca dosyada bulunan sütunlar yazılır; olmayan metrikler NULL kalır.
 */
class MetricsCsvImporter
{
    /** @var array<string, array<int, string>> */
    private const ALIASES = [
        'date' => ['date', 'tarih', 'day', 'gün', 'gun', 'tarih (gg.aa.yyyy)'],
        'followers' => ['followers', 'takipçi', 'takipci', 'subscribers', 'aboneler', 'abone', 'total followers', 'toplam takipçi'],
        'following' => ['following', 'takip edilen'],
        'posts_count' => ['posts', 'gönderi', 'gönderiler', 'video count', 'videos'],
        'views' => ['views', 'görüntüleme', 'görüntülemeler', 'izlenme', 'video views', 'plays'],
        'reach' => ['reach', 'erişim', 'accounts reached'],
        'impressions' => ['impressions', 'gösterim', 'gösterimler'],
        'likes' => ['likes', 'beğeni', 'beğeniler', 'begeni'],
        'comments' => ['comments', 'yorum', 'yorumlar'],
        'shares' => ['shares', 'paylaşım', 'paylaşımlar', 'reposts', 'retweets'],
    ];

    /**
     * @return array{rows: int, skipped: int, columns: array<int, string>}
     */
    public function import(SocialAccount $account, string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('Dosya okunamadı.');
        }

        $firstLine = (string) fgets($handle);
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : (substr_count($firstLine, "\t") > 0 ? "\t" : ',');
        $headers = array_map(fn ($h) => Str::lower(trim((string) $h, " \"'\t\r\n")), str_getcsv($firstLine, $delimiter, '"', ''));

        $map = [];
        foreach ($headers as $index => $header) {
            foreach (self::ALIASES as $field => $aliases) {
                if (in_array($header, $aliases, true) && ! in_array($field, $map, true)) {
                    $map[$index] = $field;
                }
            }
        }

        if (! in_array('date', $map, true)) {
            fclose($handle);
            throw new RuntimeException('CSV\'de tarih sütunu bulunamadı. Beklenen başlıklar: date/tarih, followers/takipçi, views/görüntüleme, likes/beğeni, comments/yorum, shares/paylaşım, reach/erişim, impressions/gösterim.');
        }
        if (count($map) < 2) {
            fclose($handle);
            throw new RuntimeException('CSV\'de tanınan metrik sütunu yok.');
        }

        $rows = 0;
        $skipped = 0;
        while (($line = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($line === [null] || count(array_filter($line, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }

            $values = [];
            foreach ($map as $index => $field) {
                $values[$field] = $line[$index] ?? null;
            }

            $date = $this->parseDate((string) $values['date']);
            if (! $date || $date->isFuture()) {
                $skipped++;

                continue;
            }

            $data = ['source' => 'import', 'is_demo' => $account->is_demo];
            foreach ($values as $field => $raw) {
                if ($field === 'date') {
                    continue;
                }
                $number = $this->parseNumber($raw);
                if ($number !== null) {
                    $data[$field] = $number;
                }
            }

            AccountMetricSnapshot::updateOrCreate(
                ['social_account_id' => $account->id, 'captured_on' => $date->toDateString()],
                $data,
            );
            $rows++;
        }
        fclose($handle);

        $latest = AccountMetricSnapshot::where('social_account_id', $account->id)->whereNotNull('followers')->latest('captured_on')->value('followers');
        if ($latest !== null) {
            $account->update(['followers_count' => $latest]);
        }

        SyncLog::create([
            'social_account_id' => $account->id,
            'type' => 'import',
            'status' => $skipped > 0 ? 'partial' : 'success',
            'items_count' => $rows,
            'message' => "CSV içe aktarma: {$rows} gün aktarıldı, {$skipped} satır atlandı.",
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        return ['rows' => $rows, 'skipped' => $skipped, 'columns' => array_values(array_unique($map))];
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'Y/m/d', 'd-m-Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date;
                }
            } catch (Throwable) {
            }
        }
        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function parseNumber(mixed $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $value = trim((string) $raw);
        if ($value === '' || $value === '-' || Str::lower($value) === 'null') {
            return null;
        }
        // "12.345" (TR binlik) veya "12,345" (EN binlik) → 12345; "1,5K"/"2.3M" kısaltmaları
        if (preg_match('/^([\d.,]+)\s*([kKmM])$/', $value, $m)) {
            $n = (float) str_replace(',', '.', $m[1]);

            return (int) round($n * (Str::lower($m[2]) === 'k' ? 1000 : 1000000));
        }
        $digits = preg_replace('/[^\d-]/', '', $value);

        return $digits === '' || $digits === '-' ? null : (int) $digits;
    }
}
