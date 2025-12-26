<?php

namespace App\Http\Resources;

class AnnouncementDetailResource extends AnnouncementResource
{
    protected function bodyValue(): ?string
    {
        return (string) parent::bodyValue();
    }
}
