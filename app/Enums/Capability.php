<?php

namespace App\Enums;

enum Capability: string
{
    case ReadFollowers = 'canReadFollowers';
    case ReadAnalytics = 'canReadAnalytics';
    case ListPosts = 'canListPosts';
    case PublishVideo = 'canPublishVideo';
    case PublishImage = 'canPublishImage';
    case PublishText = 'canPublishText';
    case ReadComments = 'canReadComments';
    case ReplyComments = 'canReplyComments';

    public function label(): string
    {
        return match ($this) {
            self::ReadFollowers => 'Takipçi/abone okuma',
            self::ReadAnalytics => 'Analitik okuma',
            self::ListPosts => 'Gönderileri listeleme',
            self::PublishVideo => 'Video yayınlama',
            self::PublishImage => 'Görsel yayınlama',
            self::PublishText => 'Metin gönderisi yayınlama',
            self::ReadComments => 'Yorumları okuma',
            self::ReplyComments => 'Yorumlara cevap verme',
        };
    }
}
