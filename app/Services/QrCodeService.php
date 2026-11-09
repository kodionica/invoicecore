<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    private const SIZE = 480;

    public function png(string $text): string
    {
        return (new Writer(new GDLibRenderer(self::SIZE)))->writeString($text, 'UTF-8', ErrorCorrectionLevel::M());
    }

    public function svg(string $text): string
    {
        $renderer = new ImageRenderer(new RendererStyle(self::SIZE), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($text, 'UTF-8', ErrorCorrectionLevel::M());
    }
}
