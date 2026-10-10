<?php

namespace App\Services\Publishing;

use App\Enums\ContentType;
use App\Models\MediaFile;
use App\Models\SocialAccount;

/**
 * Platforma özgü içerik kurallarını doğrular (başlık/açıklama uzunluğu, medya türü, hashtag sayısı).
 */
class TargetValidator
{
    /**
     * @param  array<string, mixed>  $target
     * @return array<string, string> alan => hata
     */
    public function validate(array $target, SocialAccount $account, ?MediaFile $media, ?MediaFile $thumbnail): array
    {
        $errors = [];
        $type = ContentType::tryFrom((string) ($target['content_type'] ?? ''));

        if (! $type) {
            return ['content_type' => 'Geçersiz içerik türü.'];
        }
        if ($type->platform() !== $account->platform) {
            return ['content_type' => "{$type->label()} bu hesabın platformuna ({$account->platform->label()}) uygun değil."];
        }

        $req = $type->requirements();
        $title = trim((string) ($target['title'] ?? ''));
        $hashtags = array_values(array_filter((array) ($target['hashtags'] ?? [])));
        $caption = trim((string) ($target['caption'] ?? ''));
        $full = trim($caption."\n\n".implode(' ', array_map(fn ($t) => '#'.ltrim((string) $t, '#'), $hashtags)));

        if ($req['needs_title'] && $title === '') {
            $errors['title'] = "{$type->label()} için başlık zorunlu.";
        }
        if ($req['title_max'] && mb_strlen($title) > $req['title_max']) {
            $errors['title'] = "Başlık en fazla {$req['title_max']} karakter olabilir.";
        }
        if ($req['caption_max'] && mb_strlen($full) > $req['caption_max']) {
            $errors['caption'] = "Açıklama + hashtagler en fazla {$req['caption_max']} karakter olabilir (şu an ".mb_strlen($full).').';
        }
        if ($req['hashtags_max'] && count($hashtags) > $req['hashtags_max']) {
            $errors['hashtags'] = "En fazla {$req['hashtags_max']} hashtag kullanılabilir.";
        }
        if ($type === ContentType::XPost && $caption === '') {
            $errors['caption'] = 'X gönderisi için metin zorunlu.';
        }

        $kind = $type->mediaKind();
        if ($kind === 'none' && $media) {
            $errors['media_file_id'] = 'Bu içerik türü medya içermez; medyalı gönderi için "X medya gönderisi" seçin.';
        } elseif ($kind !== 'none' && ! $media) {
            $errors['media_file_id'] = "{$type->label()} için ".($kind === 'image' ? 'görsel' : ($kind === 'video' ? 'video' : 'medya')).' seçin.';
        } elseif ($media && $kind === 'video' && $media->type !== 'video') {
            $errors['media_file_id'] = "{$type->label()} için video dosyası gerekli.";
        } elseif ($media && $kind === 'image' && $media->type !== 'image') {
            $errors['media_file_id'] = "{$type->label()} için görsel gerekli (video için Reels seçin).";
        } elseif ($media && $req['max_duration'] && $media->duration_seconds && $media->duration_seconds > $req['max_duration']) {
            $errors['media_file_id'] = "{$type->label()} en fazla {$req['max_duration']} saniye olabilir (video: ".(int) $media->duration_seconds.' sn).';
        }

        if ($thumbnail && $thumbnail->type !== 'image') {
            $errors['thumbnail_media_id'] = 'Kapak bir görsel olmalı.';
        }

        return $errors;
    }
}
